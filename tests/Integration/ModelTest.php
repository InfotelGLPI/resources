<?php

/**
 * -------------------------------------------------------------------------
 * resources plugin for GLPI
 * Copyright (C) 2015-2026 by the resources Development Team.
 *
 * https://github.com/InfotelGLPI/resources
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of resources.
 *
 * resources is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * resources is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with resources. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Resources\Tests\Integration;

use CommonDBTM;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Tests\DbTestCase;
use GlpiPlugin\Resources\Checklist;
use GlpiPlugin\Resources\Choice;
use GlpiPlugin\Resources\ChoiceItem;
use GlpiPlugin\Resources\Client;
use GlpiPlugin\Resources\Employee;
use GlpiPlugin\Resources\Employer;
use GlpiPlugin\Resources\Employment;
use GlpiPlugin\Resources\Habilitation;
use GlpiPlugin\Resources\LDAP;
use GlpiPlugin\Resources\LeavingInformation;
use GlpiPlugin\Resources\LinkAd;
use GlpiPlugin\Resources\Profile;
use GlpiPlugin\Resources\ReportConfig;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\ResourceHabilitation;
use GlpiPlugin\Resources\ResourceHoliday;
use GlpiPlugin\Resources\ResourceImport;
use GlpiPlugin\Resources\ResourceResting;
use GlpiPlugin\Resources\Task;
use GlpiPlugin\Resources\TaskPlanning;
use MassiveAction;
use PHPUnit\Framework\Attributes\DataProvider;
use Session;

/**
 * The model of a resource and of the rows that hang off it decides what a write may change,
 * whatever controller, massive action or API call hands it its input.
 */
class ModelTest extends DbTestCase
{
    private const NO_PERMISSION = "You don't have permission to perform this action.";

    public function setUp(): void
    {
        parent::setUp();

        $this->login();
        $this->setEntity('_test_root_entity', true);
        // The plugin rights are only granted to the profile active when the plugin was
        // installed: give them to the session of the test.
        Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
        Profile::initProfile();
    }

    private function setSessionRights(array $rights): void
    {
        foreach ($rights as $name => $value) {
            $_SESSION['glpiactiveprofile'][$name] = $value;
        }
    }

    private function setConfig(array $values): void
    {
        global $DB;

        $DB->update('glpi_plugin_resources_configs', $values, ['id' => 1]);
    }

    private function entity(string $name): int
    {
        return getItemByTypeName('Entity', $name, true);
    }

    private function createResource(string $name, ?int $entities_id = null, array $input = []): Resource
    {
        $resource = new Resource();
        $id = $resource->add($input + [
            'name'               => $name,
            'firstname'          => 'Model',
            'entities_id'        => $entities_id ?? $this->getTestRootEntity(true),
            'users_id_recipient' => Session::getLoginUserID(),
            'withtemplate'       => 0,
        ]);
        $this->assertGreaterThan(0, $id, "Resource $name not created");

        return $resource;
    }

    private function createTemplate(string $name, int $entities_id, int $is_recursive = 0): Resource
    {
        return $this->createResource($name, $entities_id, [
            'is_template'   => 1,
            'template_name' => $name,
            'is_recursive'  => $is_recursive,
        ]);
    }

    private function countReportsOf(int $resources_id): int
    {
        return countElementsInTable(ReportConfig::getTable(), ['plugin_resources_resources_id' => $resources_id]);
    }

    private function getMaxId(string $table): int
    {
        global $DB;

        return (int) $DB->request([
            'SELECT' => ['MAX' => 'id AS max'],
            'FROM'   => $table,
        ])->current()['max'];
    }

    public function testCreationIgnoresThePostedId(): void
    {
        $forced = $this->getMaxId(Resource::getTable()) + 1000;

        $resource = $this->createResource('Posted id', null, ['id' => $forced]);

        $this->assertNotSame($forced, $resource->getID());
        $this->assertFalse((new Resource())->getFromDB($forced));
    }

    public function testOnlyAReadableTemplateIsCloned(): void
    {
        $child_1 = $this->entity('_test_child_1');
        $child_2 = $this->entity('_test_child_2');

        $plain     = $this->createResource('Clone plain resource');
        $foreign   = $this->createTemplate('Clone foreign template', $child_2);
        $allowed   = $this->createTemplate('Clone allowed template', $child_1);
        foreach ([$plain, $foreign, $allowed] as $source) {
            $this->assertGreaterThan(0, (new ReportConfig())->add([
                'plugin_resources_resources_id' => $source->getID(),
                'comment'                       => 'Report of ' . $source->fields['name'],
            ]));
        }

        // A resource that is not a template, a key the request may not set, a list of ids
        $forged = [
            ['id_template' => $plain->getID()],
            ['resources_oldID' => $plain->getID()],
            ['resources_oldID' => [$plain->getID(), $allowed->getID()]],
            ['_resources_template_id' => $plain->getID()],
            ['id_template' => [$allowed->getID(), $plain->getID()]],
            ['id_template' => $allowed->getID() . 'x'],
        ];
        foreach ($forged as $input) {
            $resource = $this->createResource('Clone forged', $child_1, $input);
            $this->assertSame(0, $this->countReportsOf($resource->getID()), json_encode($input));
        }

        // A template out of the entities of the session
        $this->setEntity('_test_child_1', false);
        $resource = $this->createResource('Clone from a foreign template', $child_1, ['id_template' => $foreign->getID()]);
        $this->assertSame(0, $this->countReportsOf($resource->getID()));

        // A template the session may read
        $resource = $this->createResource('Clone from an allowed template', $child_1, ['id_template' => $allowed->getID()]);
        $this->assertSame(1, $this->countReportsOf($resource->getID()));
    }

    public function testClonedTasksTakeTheEntityOfTheNewResource(): void
    {
        $root    = $this->getTestRootEntity(true);
        $child_1 = $this->entity('_test_child_1');

        $template = $this->createTemplate('Task template', $root, 1);
        $task = new Task();
        $this->assertGreaterThan(0, $task->add([
            'name'                          => 'Task of the template',
            'plugin_resources_resources_id' => $template->getID(),
            'entities_id'                   => $root,
            'is_recursive'                  => 1,
        ]));

        $resource = $this->createResource('Task clone', $child_1, ['id_template' => $template->getID()]);

        $tasks = (new Task())->find(['plugin_resources_resources_id' => $resource->getID()]);
        $this->assertCount(1, $tasks);
        $clone = reset($tasks);
        $this->assertSame($child_1, (int) $clone['entities_id']);
        $this->assertSame(0, (int) $clone['is_recursive']);
    }

    public function testValidationFlagIsDroppedAtCreation(): void
    {
        $resource = $this->createResource('Forged flag', null, ['valid_resource_information' => 1]);
        $this->assertSame(0, (int) $resource->fields['valid_resource_information']);

        // The server-side property of the validation workflow still sets it
        $validated = new Resource();
        $validated->allow_validation_flag = true;
        $id = $validated->add([
            'name'                       => 'Workflow flag',
            'firstname'                  => 'Model',
            'entities_id'                => $this->getTestRootEntity(true),
            'valid_resource_information' => 1,
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, (int) $validated->fields['valid_resource_information']);
    }

    public function testStructuralColumnsAreNotUpdated(): void
    {
        $child_1  = $this->entity('_test_child_1');
        $resource = $this->createResource('Structural');
        $id       = $resource->getID();
        $before   = $resource->fields;

        $this->assertTrue($resource->update([
            'id'               => $id,
            'name'             => 'Structural renamed',
            'entities_id'      => $child_1,
            'is_recursive'     => 1,
            'is_template'      => 1,
            'is_deleted'       => 1,
            'date_declaration' => '2001-01-01',
        ]));
        $this->assertTrue($resource->getFromDB($id));
        $this->assertSame('Structural renamed', $resource->fields['name']);
        foreach (['entities_id', 'is_recursive', 'is_template', 'is_deleted', 'date_declaration'] as $field) {
            $this->assertEquals($before[$field], $resource->fields[$field], $field);
        }

        // The writer: an ordinary update (the wizard posts the current user) leaves it, the
        // resource form sets it when the session may choose it (central interface, CREATE)
        $tech = getItemByTypeName('User', 'tech', true);
        $this->assertTrue($resource->update(['id' => $id, 'users_id_recipient' => $tech]));
        $this->assertTrue($resource->getFromDB($id));
        $this->assertSame((int) $before['users_id_recipient'], (int) $resource->fields['users_id_recipient']);

        $this->assertTrue(Resource::canSetRecipient());
        $resource->allow_structural_fields = ['users_id_recipient'];
        $this->assertTrue($resource->update(['id' => $id, 'users_id_recipient' => $tech]));
        $this->assertTrue($resource->getFromDB($id));
        $this->assertSame($tech, (int) $resource->fields['users_id_recipient']);

        $this->setSessionRights(['plugin_resources' => READ | UPDATE]);
        $this->assertFalse(Resource::canSetRecipient());
        $this->setSessionRights(['plugin_resources' => ALLSTANDARDRIGHT + READNOTE + UPDATENOTE]);

        // The transfer remains the way to change the entity
        $this->assertTrue($resource->transferResource($id, $child_1));
        $this->assertTrue($resource->getFromDB($id));
        $this->assertSame($child_1, (int) $resource->fields['entities_id']);
        $this->hasSessionMessages(ERROR, [
            'The notification is not sent because the resource is not linked with a user',
        ]);
        // The permission set for that write does not outlive it
        $this->assertSame([], $resource->allow_structural_fields);

        // Trash and restore do not go through update()
        $this->assertTrue($resource->delete(['id' => $id]));
        $this->assertTrue($resource->getFromDB($id));
        $this->assertSame(1, (int) $resource->fields['is_deleted']);
        $this->assertTrue($resource->restore(['id' => $id]));
        $this->assertTrue($resource->getFromDB($id));
        $this->assertSame(0, (int) $resource->fields['is_deleted']);
    }

    public function testValidatedResourceIsFrozen(): void
    {
        $resource = $this->createResource('Frozen');
        $id       = $resource->getID();

        $resource->allow_validation_flag = true;
        $this->assertTrue($resource->update(['id' => $id, 'valid_resource_information' => 1]));
        $resource->allow_validation_flag = false;
        $this->assertTrue($resource->getFromDB($id));

        // Freeze off: writes go on
        $this->setConfig(['use_module_validation' => 1, 'freeze_form_after_validation' => 0]);
        $resource->checkNotFrozen();

        $this->setConfig(['use_module_validation' => 1, 'freeze_form_after_validation' => 1]);
        $this->expectException(AccessDeniedHttpException::class);
        $resource->checkNotFrozen();
    }

    public function testDirectorySynchronizationFollowsTheValidationTab(): void
    {
        $profiles_id = (int) $_SESSION['glpiactiveprofile']['id'];
        $resource    = $this->createResource('Synchronization');
        $id          = $resource->getID();

        $this->setConfig([
            'use_module_validation'      => 1,
            'can_view_synchronisationAD' => json_encode([$profiles_id]),
        ]);
        // Not validated by the manager yet
        $this->assertFalse($resource->canSynchronizeAD());

        $resource->allow_validation_flag = true;
        $this->assertTrue($resource->update(['id' => $id, 'valid_resource_information' => 1]));
        $resource->allow_validation_flag = false;
        $this->assertTrue($resource->getFromDB($id));
        $this->assertTrue($resource->canSynchronizeAD());

        // Profile not allowed to synchronize
        $this->setConfig(['can_view_synchronisationAD' => json_encode([$profiles_id + 1000])]);
        $this->assertFalse($resource->canSynchronizeAD());

        // Validation module off
        $this->setConfig([
            'use_module_validation'      => 0,
            'can_view_synchronisationAD' => json_encode([$profiles_id]),
        ]);
        $this->assertFalse($resource->canSynchronizeAD());
    }

    /**
     * Rows created under the resource given, one per itemtype without entity.
     *
     * @return array<class-string<CommonDBTM>, int>
     */
    private function createChildRows(int $resources_id): array
    {
        // A habilitation has to exist in the scope of the entity of the resource: a recursive
        // one of the root entity serves every resource of the tests
        $habilitations_id = $this->createItem(Habilitation::class, [
            'name'         => 'Child row habilitation ' . $resources_id,
            'entities_id'  => $this->getTestRootEntity(true),
            'is_recursive' => 1,
        ])->getID();
        $choiceitems_id = $this->createItem(ChoiceItem::class, [
            'name'         => 'Child row need ' . $resources_id,
            'entities_id'  => $this->getTestRootEntity(true),
            'is_recursive' => 1,
        ])->getID();

        $inputs = [
            Choice::class               => ['plugin_resources_choiceitems_id' => $choiceitems_id, 'comment' => ''],
            ResourceHoliday::class      => ['date_begin' => '2026-01-01', 'date_end' => '2026-01-05'],
            ResourceResting::class      => ['date_begin' => '2026-02-01'],
            ResourceHabilitation::class => ['plugin_resources_habilitations_id' => $habilitations_id],
            LeavingInformation::class   => ['comment' => 'Leaving'],
            ReportConfig::class         => ['comment' => 'Report'],
            LinkAd::class               => ['mail' => 'model@example.com'],
        ];

        $rows = [];
        foreach ($inputs as $itemtype => $input) {
            $item = new $itemtype();
            $id = $item->add(['plugin_resources_resources_id' => $resources_id] + $input);
            $this->assertGreaterThan(0, $id, "$itemtype not created");
            $rows[$itemtype] = $id;
        }

        return $rows;
    }

    public function testChildRowsAreScopedByTheEntityOfTheirResource(): void
    {
        $child_1 = $this->entity('_test_child_1');
        $child_2 = $this->entity('_test_child_2');

        $own     = $this->createResource('Child rows own', $child_1);
        $foreign = $this->createResource('Child rows foreign', $child_2);
        $own_rows     = $this->createChildRows($own->getID());
        $foreign_rows = $this->createChildRows($foreign->getID());

        $this->setEntity('_test_child_1', false);
        foreach ($foreign_rows as $itemtype => $id) {
            foreach ([READ, UPDATE, DELETE, PURGE] as $right) {
                $this->assertFalse((new $itemtype())->can($id, $right), "$itemtype $right");
            }
            $input = ['plugin_resources_resources_id' => $foreign->getID()];
            $this->assertFalse((new $itemtype())->can(-1, CREATE, $input), "$itemtype create");
        }
        foreach ($own_rows as $itemtype => $id) {
            foreach ([READ, UPDATE, PURGE] as $right) {
                $this->assertTrue((new $itemtype())->can($id, $right), "$itemtype $right");
            }
            $input = ['plugin_resources_resources_id' => $own->getID()];
            $this->assertTrue((new $itemtype())->can(-1, CREATE, $input), "$itemtype create");
            // No resource, or not a plain id: nothing to answer for
            foreach ([0, $own->getID() . 'x', [$own->getID()]] as $forged) {
                $input = ['plugin_resources_resources_id' => $forged];
                $this->assertFalse((new $itemtype())->can(-1, CREATE, $input), "$itemtype create");
            }
        }

        // An update may not move a row to a resource out of reach
        $holiday = new ResourceHoliday();
        $this->assertFalse($holiday->update([
            'id'                            => $own_rows[ResourceHoliday::class],
            'plugin_resources_resources_id' => $foreign->getID(),
            'date_begin'                    => '2026-01-01',
            'date_end'                      => '2026-01-06',
        ]));
        $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);
        $this->assertTrue($holiday->getFromDB($own_rows[ResourceHoliday::class]));
        $this->assertSame($own->getID(), (int) $holiday->fields['plugin_resources_resources_id']);

        // Same for a need
        $choice = new Choice();
        $this->assertFalse($choice->update([
            'id'                            => $own_rows[Choice::class],
            'plugin_resources_resources_id' => $foreign->getID(),
        ]));
        $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);
        $this->assertTrue($choice->getFromDB($own_rows[Choice::class]));
        $this->assertSame($own->getID(), (int) $choice->fields['plugin_resources_resources_id']);

        // A forged purge massive action: the core runs it on can() alone
        $ma = $this->createStub(MassiveAction::class);
        $ma->method('getAction')->willReturn('purge');
        $foreign_choice = $foreign_rows[Choice::class];
        MassiveAction::processMassiveActionsForOneItemtype($ma, new Choice(), [$foreign_choice => $foreign_choice]);
        $this->assertTrue((new Choice())->getFromDB($foreign_choice));
    }

    public function testNeedIsOnlyAddedUnderAnExistingResourceAndItem(): void
    {
        $resource = $this->createResource('Need input');
        $item     = $this->createItem(ChoiceItem::class, [
            'name'        => 'Need input item',
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        $choice = new Choice();
        foreach ([0, $resource->getID() . 'x', $this->getMaxId(Resource::getTable()) + 1] as $resources_id) {
            $this->assertFalse($choice->add([
                'plugin_resources_resources_id'   => $resources_id,
                'plugin_resources_choiceitems_id' => $item->getID(),
            ]));
        }
        $this->assertFalse($choice->add([
            'plugin_resources_resources_id'   => $resource->getID(),
            'plugin_resources_choiceitems_id' => $this->getMaxId(ChoiceItem::getTable()) + 1,
        ]));
        $this->assertGreaterThan(0, $choice->addHelpdeskItem($resource->getID(), $item->getID()));
    }

    public function testChecklistRowCountsForAnUpdatableResourceOnly(): void
    {
        $child_1 = $this->entity('_test_child_1');
        $child_2 = $this->entity('_test_child_2');
        $own     = $this->createResource('Checklist own', $child_1);
        $foreign = $this->createResource('Checklist foreign', $child_2);

        $this->setEntity('_test_child_1', false);
        // A row of an entity of the session naming a resource of another one
        $input = [
            'name'                              => 'Checklist planted',
            'entities_id'                       => $child_1,
            'plugin_resources_resources_id'     => $foreign->getID(),
            'plugin_resources_contracttypes_id' => 0,
            'checklist_type'                    => Checklist::RESOURCES_CHECKLIST_IN,
            'is_checked'                        => 0,
        ];
        $this->assertFalse((new Checklist())->can(-1, CREATE, $input));
        $own_input = ['plugin_resources_resources_id' => $own->getID()] + $input;
        $this->assertTrue((new Checklist())->can(-1, CREATE, $own_input));

        // The row takes the entity of its resource, and keeps both
        $this->setEntity('_test_root_entity', true);
        $checklist = new Checklist();
        $id = $checklist->add(['entities_id' => $child_2] + $own_input);
        $this->assertGreaterThan(0, $id);
        $this->assertTrue($checklist->getFromDB($id));
        $this->assertSame($child_1, (int) $checklist->fields['entities_id']);

        $this->assertTrue($checklist->update([
            'id'                            => $id,
            'name'                          => 'Checklist renamed',
            'plugin_resources_resources_id' => $foreign->getID(),
            'entities_id'                   => $child_2,
        ]));
        $this->assertTrue($checklist->getFromDB($id));
        $this->assertSame('Checklist renamed', $checklist->fields['name']);
        $this->assertSame($own->getID(), (int) $checklist->fields['plugin_resources_resources_id']);
        $this->assertSame($child_1, (int) $checklist->fields['entities_id']);
        $this->assertSame(0, countElementsInTable(Checklist::getTable(), ['plugin_resources_resources_id' => $foreign->getID()]));
    }

    public function testPlanningRowsAnswerToTheirTask(): void
    {
        $resources = [
            'own'     => $this->createResource('Planning rights own', $this->entity('_test_child_1')),
            'foreign' => $this->createResource('Planning rights foreign', $this->entity('_test_child_2')),
        ];
        $tasks     = [];
        $plannings = [];
        foreach ($resources as $key => $resource) {
            $task = new Task();
            $tasks[$key] = $task->add([
                'name'                          => "Planning rights $key",
                'plugin_resources_resources_id' => $resource->getID(),
                'plan'                          => ['begin' => '2026-05-04 09:00:00', 'end' => '2026-05-04 10:00:00'],
            ]);
            $this->assertGreaterThan(0, $tasks[$key]);
            $planning = new TaskPlanning();
            $this->assertTrue($planning->getFromDBbyTask($tasks[$key]));
            $plannings[$key] = $planning->getID();
        }

        $this->setEntity('_test_child_1', false);
        foreach ([READ, UPDATE, DELETE, PURGE] as $right) {
            $this->assertFalse((new TaskPlanning())->can($plannings['foreign'], $right), "right $right");
            $this->assertTrue((new TaskPlanning())->can($plannings['own'], $right), "right $right");
        }
        $input = [
            'plugin_resources_tasks_id' => $tasks['foreign'],
            'begin'                     => '2026-05-05 09:00:00',
            'end'                       => '2026-05-05 10:00:00',
        ];
        $this->assertFalse((new TaskPlanning())->can(-1, CREATE, $input));

        // A forged purge massive action: the core runs it on can() alone
        $ma = $this->createStub(MassiveAction::class);
        $ma->method('getAction')->willReturn('purge');
        MassiveAction::processMassiveActionsForOneItemtype($ma, new TaskPlanning(), [
            $plannings['foreign'] => $plannings['foreign'],
            $plannings['own']     => $plannings['own'],
        ]);
        $this->assertTrue((new TaskPlanning())->getFromDB($plannings['foreign']));
        $this->assertFalse((new TaskPlanning())->getFromDB($plannings['own']));
    }

    public function testEmployeeTakesAnEmployerAndAClientOfItsEntity(): void
    {
        $root    = $this->getTestRootEntity(true);
        $child_2 = $this->entity('_test_child_2');
        $resource = $this->createResource('Employer scope', $this->entity('_test_child_1'));
        $template = $this->createResource('Employer scope template', $child_2);

        $visible = $this->createItem(Employer::class, [
            'name'         => 'Employer scope inherited',
            'entities_id'  => $root,
            'is_recursive' => 1,
        ])->getID();
        $hidden = $this->createItem(Employer::class, [
            'name'         => 'Employer scope other entity',
            'entities_id'  => $child_2,
            'is_recursive' => 0,
        ])->getID();
        $hidden_client = $this->createItem(Client::class, [
            'name'         => 'Client scope other entity',
            'entities_id'  => $child_2,
            'is_recursive' => 0,
        ])->getID();

        $employee = new Employee();
        $this->assertFalse($employee->add([
            'plugin_resources_resources_id' => $resource->getID(),
            'plugin_resources_employers_id' => $hidden,
        ]));
        $this->assertFalse($employee->add([
            'plugin_resources_resources_id' => $resource->getID(),
            'plugin_resources_clients_id'   => $hidden_client,
        ]));
        $id = $employee->add([
            'plugin_resources_resources_id' => $resource->getID(),
            'plugin_resources_employers_id' => $visible,
            'plugin_resources_clients_id'   => 0,
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertFalse($employee->update(['id' => $id, 'plugin_resources_employers_id' => $hidden]));
        $this->hasSessionMessages(ERROR, array_fill(0, 3, 'Item not found'));
        $this->assertTrue($employee->getFromDB($id));
        $this->assertSame($visible, (int) $employee->fields['plugin_resources_employers_id']);

        // The copy of a template keeps the row, without what the new resource cannot see
        $this->assertGreaterThan(0, (new Employee())->add([
            'plugin_resources_resources_id' => $template->getID(),
            'plugin_resources_employers_id' => $hidden,
            'plugin_resources_clients_id'   => $hidden_client,
        ]));
        $copy_of = $this->createResource('Employer scope copy', $this->entity('_test_child_1'));
        Employee::cloneItem($template->getID(), $copy_of->getID());
        $copy = new Employee();
        $this->assertTrue($copy->getFromDBByCrit(['plugin_resources_resources_id' => $copy_of->getID()]));
        $this->assertSame(0, (int) $copy->fields['plugin_resources_employers_id']);
        $this->assertSame(0, (int) $copy->fields['plugin_resources_clients_id']);
    }

    public function testFrozenResourceIsOnlyChangedByItsWorkflows(): void
    {
        $resource = $this->createResource('Frozen model', null, ['comment' => 'Validated']);
        $id       = $resource->getID();
        $resource->allow_validation_flag = true;
        $this->assertTrue($resource->update(['id' => $id, 'valid_resource_information' => 1]));
        $resource->allow_validation_flag = false;
        $this->setConfig(['use_module_validation' => 1, 'freeze_form_after_validation' => 1]);

        // "Amend comment" massive action of the core, then a plain update (legacy API,
        // "Update" massive action)
        $ma = $this->createStub(MassiveAction::class);
        $ma->method('getAction')->willReturn('amend_comment');
        $ma->method('getInput')->willReturn(['amendment' => 'Amended']);
        MassiveAction::processMassiveActionsForOneItemtype($ma, new Resource(), [$id => $id]);
        $this->assertFalse((new Resource())->update(['id' => $id, 'name' => 'Frozen renamed']));
        $this->hasSessionMessages(ERROR, [self::NO_PERMISSION, self::NO_PERMISSION]);
        $this->assertTrue($resource->getFromDB($id));
        $this->assertSame('Validated', $resource->fields['comment']);
        $this->assertSame('Frozen model', $resource->fields['name']);

        // The workflows meant to change a validated resource go on, for their own write only
        $this->assertTrue($resource->updateFromWorkflow(['id' => $id, 'name' => 'Frozen changed']));
        $this->assertFalse($resource->update(['id' => $id, 'name' => 'Frozen again']));
        $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);
        $this->assertTrue($resource->getFromDB($id));
        $this->assertSame('Frozen changed', $resource->fields['name']);

        // Freeze off
        $this->setConfig(['freeze_form_after_validation' => 0]);
        $this->assertTrue($resource->update(['id' => $id, 'name' => 'Unfrozen']));
    }

    public function testPeriodListsAreRestrictedToTheEntityOfTheResource(): void
    {
        global $DB;

        if (!function_exists('plugin_resources_addDefaultJoin')) {
            require_once dirname(__DIR__, 2) . '/hook.php';
        }

        $own     = $this->createResource('Period list own', $this->entity('_test_child_1'));
        $foreign = $this->createResource('Period list foreign', $this->entity('_test_child_2'));
        $own_rows     = $this->createChildRows($own->getID());
        $foreign_rows = $this->createChildRows($foreign->getID());

        $this->setEntity('_test_child_1', false);
        $list = static function (string $itemtype) use ($DB): array {
            $table = $itemtype::getTable();
            $already_link_tables = [$table];
            $join  = plugin_resources_addDefaultJoin($itemtype, $table, $already_link_tables);
            $where = plugin_resources_addDefaultWhere($itemtype);

            // The search option of the resource reuses the join instead of adding a second one
            self::assertContains('glpi_plugin_resources_resources', $already_link_tables);
            self::assertNotEmpty($where);

            $ids = array_column(iterator_to_array($DB->request([
                'SELECT' => "$table.id",
                'FROM'   => $table,
                'WHERE'  => $where,
            ] + $join)), 'id');

            return array_map('intval', $ids);
        };

        $itemtypes = [ResourceHoliday::class, ResourceResting::class, LeavingInformation::class];
        foreach ($itemtypes as $itemtype) {
            $ids = $list($itemtype);
            $this->assertContains($own_rows[$itemtype], $ids, $itemtype);
            $this->assertNotContains($foreign_rows[$itemtype], $ids, $itemtype);
        }

        // A row is read with its resource: the list follows the form
        $this->setSessionRights(['plugin_resources' => 0]);
        foreach ($itemtypes as $itemtype) {
            $this->assertSame([], $list($itemtype), $itemtype);
        }
    }

    public function testDirectoryLinkStateIsWrittenByTheServerOnly(): void
    {
        $resource = $this->createResource('Directory link');
        $other    = $this->createResource('Directory link other');

        $linkad = new LinkAd();
        $id = $linkad->add([
            'plugin_resources_resources_id' => $resource->getID(),
            'login'                         => 'administrator',
            'action_done'                   => 1,
            'auth_id'                       => 3,
            'mail'                          => 'link@example.com',
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertTrue($linkad->getFromDB($id));
        $this->assertNull($linkad->fields['login']);
        $this->assertSame(0, (int) $linkad->fields['action_done']);
        $this->assertSame(0, (int) $linkad->fields['auth_id']);
        $this->assertSame('link@example.com', $linkad->fields['mail']);

        $this->assertTrue($linkad->update([
            'id'                            => $id,
            'login'                         => 'administrator',
            'action_done'                   => 1,
            'auth_id'                       => 3,
            'plugin_resources_resources_id' => $other->getID(),
            'phone'                         => '0102030405',
        ]));
        $this->assertTrue($linkad->getFromDB($id));
        $this->assertNull($linkad->fields['login']);
        $this->assertSame(0, (int) $linkad->fields['action_done']);
        $this->assertSame(0, (int) $linkad->fields['auth_id']);
        $this->assertSame($resource->getID(), (int) $linkad->fields['plugin_resources_resources_id']);
        $this->assertSame('0102030405', $linkad->fields['phone']);

        // The code that ran the directory operation records its outcome, once
        $linkad->allow_directory_fields = ['login', 'action_done'];
        $this->assertTrue($linkad->update(['id' => $id, 'login' => 'jdoe', 'action_done' => 1]));
        $this->assertSame([], $linkad->allow_directory_fields);
        $linkad->update(['id' => $id, 'login' => 'administrator']);
        $this->assertTrue($linkad->getFromDB($id));
        $this->assertSame('jdoe', $linkad->fields['login']);
        $this->assertSame(1, (int) $linkad->fields['action_done']);

        LinkAd::setActionDoneForResource($resource->getID(), false);
        $this->assertTrue($linkad->getFromDB($id));
        $this->assertSame(0, (int) $linkad->fields['action_done']);

        // A link only exists for a resource
        $this->assertFalse((new LinkAd())->add(['mail' => 'orphan@example.com']));
    }

    public static function dnProvider(): iterable
    {
        $ou = 'OU=Users,OU=Company,DC=corp,DC=local';
        yield ['CN=Doe John,OU=Users,OU=Company,DC=corp,DC=local', $ou, true];
        yield ['CN=Doe John,OU=Interns,OU=Users,OU=Company,DC=corp,DC=local', $ou, true];
        // Case and spaces around the separators do not matter
        yield ['cn=Doe John, ou=users, ou=company, dc=corp, dc=local', $ou, true];
        // Out of the OU
        yield ['CN=Administrator,CN=Users,DC=corp,DC=local', $ou, false];
        yield ['CN=Doe John,OU=Admins,OU=Company,DC=corp,DC=local', $ou, false];
        yield ['CN=Doe John,OU=OtherUsers,OU=Company,DC=corp,DC=local', $ou, false];
        yield ['OU=Users,OU=Company,DC=corp,DC=local', $ou, false];
        // No OU configured
        yield ['CN=Doe John,OU=Users,OU=Company,DC=corp,DC=local', '', false];
    }

    #[DataProvider('dnProvider')]
    public function testDirectoryOperationsStayInTheManagedOu(string $dn, string $ou, bool $expected): void
    {
        $this->assertSame($expected, LDAP::isDnUnderOu($dn, $ou));
    }

    public function testReportSetupKeepsItsResourceAndColumns(): void
    {
        global $DB;

        $resource = $this->createResource('Report setup');
        $other    = $this->createResource('Report setup other');

        $report = new ReportConfig();
        $id = $report->add([
            'id'                            => $this->getMaxId(ReportConfig::getTable()) + 1000,
            'plugin_resources_resources_id' => $resource->getID(),
            'comment'                       => 'Comment',
            'send_report_notif'             => 0,
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertTrue($report->getFromDB($id));
        $this->assertSame(0, (int) $report->fields['send_report_notif']);

        // One setup per resource
        $this->assertFalse((new ReportConfig())->add([
            'plugin_resources_resources_id' => $resource->getID(),
            'comment'                       => 'Second',
        ]));
        $this->hasSessionMessages(ERROR, ['A notification setup already exists for this resource']);

        $this->assertTrue($report->update([
            'id'                            => $id,
            'plugin_resources_resources_id' => $other->getID(),
            'information'                   => 'Information',
        ]));
        $this->assertTrue($report->getFromDB($id));
        $this->assertSame($resource->getID(), (int) $report->fields['plugin_resources_resources_id']);
        $this->assertSame('Information', $report->fields['information']);

        // Rows paired before the refusal no longer block the report of the resource
        $DB->insert(ReportConfig::getTable(), [
            'plugin_resources_resources_id' => $resource->getID(),
            'comment'                       => 'Legacy duplicate',
        ]);
        $found = new ReportConfig();
        $this->assertTrue($found->getFromDBByResource($resource->getID()));
        $this->assertSame($id, $found->getID());
    }

    public function testLeavingInformationKeepsItsResource(): void
    {
        $resource = $this->createResource('Leaving information');
        $other    = $this->createResource('Leaving information other');

        $leaving = new LeavingInformation();
        $forced  = $this->getMaxId(LeavingInformation::getTable()) + 1000;
        $id = $leaving->add([
            'id'                            => $forced,
            'plugin_resources_resources_id' => $resource->getID(),
            'company_name'                  => 'Company',
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertNotSame($forced, $id);

        // One row per resource
        $this->assertFalse((new LeavingInformation())->add([
            'plugin_resources_resources_id' => $resource->getID(),
            'company_name'                  => 'Second',
        ]));

        $this->assertTrue($leaving->update([
            'id'                            => $id,
            'plugin_resources_resources_id' => $other->getID(),
            'company_name'                  => 'New company',
        ]));
        $this->assertTrue($leaving->getFromDB($id));
        $this->assertSame($resource->getID(), (int) $leaving->fields['plugin_resources_resources_id']);
        $this->assertSame('New company', $leaving->fields['company_name']);
    }

    public function testEmploymentIsOnlyAttachedToAnUpdatableResource(): void
    {
        $child_1 = $this->entity('_test_child_1');
        $allowed = $this->createResource('Employment allowed', $child_1);
        $denied  = $this->createResource('Employment denied', $this->entity('_test_child_2'));

        $this->setEntity('_test_child_1', false);

        $employment = new Employment();
        $this->assertFalse($employment->add([
            'name'                          => 'Employment denied',
            'entities_id'                   => $child_1,
            'plugin_resources_resources_id' => $denied->getID(),
        ]));
        $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);

        $id = $employment->add([
            'name'                          => 'Employment allowed',
            'entities_id'                   => $child_1,
            'plugin_resources_resources_id' => $allowed->getID(),
        ]);
        $this->assertGreaterThan(0, $id);

        $this->assertFalse($employment->update([
            'id'                            => $id,
            'plugin_resources_resources_id' => $denied->getID(),
        ]));
        $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);
        $this->assertTrue($employment->getFromDB($id));
        $this->assertSame($allowed->getID(), (int) $employment->fields['plugin_resources_resources_id']);

        // Same resource, or none at all
        $this->assertTrue($employment->update(['id' => $id, 'plugin_resources_resources_id' => $allowed->getID(), 'comment' => 'Kept']));
        $this->assertTrue($employment->update(['id' => $id, 'plugin_resources_resources_id' => 0]));
        $this->assertTrue($employment->getFromDB($id));
        $this->assertSame(0, (int) $employment->fields['plugin_resources_resources_id']);
    }

    public function testResourceImportIsAnOrdinaryItem(): void
    {
        $resource  = $this->createResource('Import');
        $other     = $this->createResource('Import other');
        $resources = countElementsInTable(Resource::getTable());

        // add() creates an imported value, not a resource
        $import = new ResourceImport();
        $id = $import->add([
            'plugin_resources_resources_id' => $resource->getID(),
            'name'                          => 'Matricule',
            'value'                         => 'A1',
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertSame($resources, countElementsInTable(Resource::getTable()));

        // update() changes the value only
        $this->assertTrue($import->update([
            'id'                            => $id,
            'name'                          => 'Renamed',
            'value'                         => 'B2',
            'plugin_resources_resources_id' => $other->getID(),
            'resourceID'                    => $other->getID(),
            'datas'                         => [],
        ]));
        $this->assertTrue($import->getFromDB($id));
        $this->assertSame('B2', $import->fields['value']);
        $this->assertSame('Matricule', $import->fields['name']);
        $this->assertSame($resource->getID(), (int) $import->fields['plugin_resources_resources_id']);
    }

    public function testImportIntoResourceChecksTheResource(): void
    {
        $denied = $this->createResource('Import denied', $this->entity('_test_child_2'));

        $this->setEntity('_test_child_1', false);
        $this->expectException(AccessDeniedHttpException::class);
        (new ResourceImport())->importIntoResource($denied->getID(), []);
    }

    public function testResourceCreationFromImportChecksTheCreateRight(): void
    {
        $this->setSessionRights(['plugin_resources' => READ | UPDATE]);

        $this->expectException(AccessDeniedHttpException::class);
        (new ResourceImport())->createResourceFromImport(0, []);
    }

    public function testChildRowsDoNotOutliveTheirResource(): void
    {
        global $DB;

        $purged = $this->createResource('Purged');
        $kept   = $this->createResource('Kept');
        $tables = [LinkAd::getTable(), LeavingInformation::getTable(), ResourceImport::getTable()];

        foreach ([$purged, $kept] as $resource) {
            $this->assertGreaterThan(0, (new LinkAd())->add(['plugin_resources_resources_id' => $resource->getID()]));
            $this->assertGreaterThan(0, (new LeavingInformation())->add(['plugin_resources_resources_id' => $resource->getID()]));
            $this->assertGreaterThan(0, (new ResourceImport())->add([
                'plugin_resources_resources_id' => $resource->getID(),
                'name'                          => 'Matricule',
                'value'                         => 'A1',
            ]));
        }

        // The purge takes them along
        $this->assertTrue($purged->delete(['id' => $purged->getID()], true));
        foreach ($tables as $table) {
            $this->assertSame(0, countElementsInTable($table, ['plugin_resources_resources_id' => $purged->getID()]), $table);
        }

        // Rows left behind by former purges are removed by the install/update of the plugin
        $gone = $this->getMaxId(Resource::getTable()) + 1000;
        $DB->insert(LinkAd::getTable(), ['plugin_resources_resources_id' => $gone]);
        $DB->insert(LeavingInformation::getTable(), ['plugin_resources_resources_id' => $gone]);
        $DB->insert(ResourceImport::getTable(), ['plugin_resources_resources_id' => $gone, 'name' => 'Orphan']);

        Resource::deleteOrphanChildRows();
        Resource::deleteOrphanChildRows();

        foreach ($tables as $table) {
            $this->assertSame(0, countElementsInTable($table, ['plugin_resources_resources_id' => $gone]), $table);
            $this->assertSame(1, countElementsInTable($table, ['plugin_resources_resources_id' => $kept->getID()]), $table);
        }
    }
}
