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

use CommonITILActor;
use Computer;
use Glpi\DBAL\QueryExpression;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Tests\DbTestCase;
use GlpiPlugin\Resources\Actionprofile;
use GlpiPlugin\Resources\Checklist;
use GlpiPlugin\Resources\Checklistconfig;
use GlpiPlugin\Resources\Employee;
use GlpiPlugin\Resources\Employer;
use GlpiPlugin\Resources\Employment;
use GlpiPlugin\Resources\Habilitation;
use GlpiPlugin\Resources\Profile;
use GlpiPlugin\Resources\ReportConfig;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Resource_Change;
use GlpiPlugin\Resources\Resource_Item;
use GlpiPlugin\Resources\ResourceHabilitation;
use GlpiPlugin\Resources\Task;
use GlpiPlugin\Resources\Task_Item;
use GlpiPlugin\Resources\TaskPlanning;
use Appliance;
use ComputerType;
use GlpiPlugin\Resources\TransferEntity;
use GlpiPlugin\Resources\Wizard;
use Location;
use MassiveAction;
use Plugin;
use ReflectionProperty;
use Search;
use Session;
use Ticket;
use Ticket_User;
use TicketTemplate;
use User;

/**
 * Every id a request names next to the row it writes (the resource of a task, the object of a
 * link, the habilitation, the planning row, the change action...) is checked by the model or
 * the helper that writes it, whatever controller or massive action hands it over.
 */
class ReferencesTest extends DbTestCase
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
        // Writing those rights marks the profile as changed: Session::checkRight() would then
        // reload it from the database, over the rights a test sets in the session. Reload it now.
        Session::checkValidSessionId();

        if (!function_exists('plugin_resources_addLeftJoin')) {
            require_once dirname(__DIR__, 2) . '/hook.php';
        }
    }

    private function setSessionRights(array $rights): void
    {
        foreach ($rights as $name => $value) {
            $_SESSION['glpiactiveprofile'][$name] = $value;
        }
    }

    private function entity(string $name): int
    {
        return getItemByTypeName('Entity', $name, true);
    }

    /**
     * Run $callback with the plugin registered as active.
     *
     * The test environment does not load the plugin: the core search engine would not call
     * its hooks (Plugin::doOneHook()), the joins and the cells of its columns included.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function withActivePlugin(callable $callback): mixed
    {
        $property  = new ReflectionProperty(Plugin::class, 'activated_plugins');
        $activated = $property->getValue();
        $property->setValue(null, array_merge($activated, ['resources']));
        try {
            return $callback();
        } finally {
            $property->setValue(null, $activated);
        }
    }

    private function createResource(string $name, ?int $entities_id = null, array $input = []): Resource
    {
        $resource = new Resource();
        $id = $resource->add($input + [
            'name'               => $name,
            'firstname'          => 'References',
            'entities_id'        => $entities_id ?? $this->getTestRootEntity(true),
            'users_id_recipient' => Session::getLoginUserID(),
            'withtemplate'       => 0,
        ]);
        $this->assertGreaterThan(0, $id, "Resource $name not created");

        return $resource;
    }

    /**
     * An employer every resource of the tests may be given: Employee checks the employer
     * against the entity of the resource.
     */
    private function createEmployer(string $name): int
    {
        return $this->createItem(Employer::class, [
            'name'         => $name,
            'entities_id'  => $this->getTestRootEntity(true),
            'is_recursive' => 1,
        ])->getID();
    }

    /**
     * A user with a single authorization, in the entity given.
     */
    private function createUser(string $name, int $entities_id, int $is_recursive = 0): User
    {
        return $this->createItem(User::class, [
            'name'          => $name,
            '_profiles_id'  => getItemByTypeName('Profile', 'Self-Service', true),
            '_entities_id'  => $entities_id,
            '_is_recursive' => $is_recursive,
        ]);
    }

    public function testTaskTakesItsResourceAndEntityFromAnUpdatableResource(): void
    {
        $root    = $this->getTestRootEntity(true);
        $child_1 = $this->entity('_test_child_1');
        $child_2 = $this->entity('_test_child_2');

        $own     = $this->createResource('Task own', $child_1);
        $foreign = $this->createResource('Task foreign', $child_2);

        $this->setEntity('_test_child_1', false);

        // A resource of an entity out of reach, whatever entity the input names
        $task = new Task();
        $this->assertFalse($task->add([
            'name'                          => 'Injected task',
            'plugin_resources_resources_id' => $foreign->getID(),
            'entities_id'                   => $child_1,
        ]));
        $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);
        $this->assertSame(0, countElementsInTable(Task::getTable(), ['name' => 'Injected task']));

        // The entity of the task is the one of its resource, not the one posted
        $id = $task->add([
            'name'                          => 'Own task',
            'plugin_resources_resources_id' => $own->getID(),
            'entities_id'                   => $root,
            'is_recursive'                  => 1,
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertTrue($task->getFromDB($id));
        $this->assertSame($child_1, (int) $task->fields['entities_id']);
        $this->assertSame(0, (int) $task->fields['is_recursive']);

        // An update moves it neither to another resource nor to another entity
        $this->assertTrue($task->update([
            'id'                            => $id,
            'name'                          => 'Own task renamed',
            'plugin_resources_resources_id' => $foreign->getID(),
            'entities_id'                   => $child_2,
            'is_recursive'                  => 1,
        ]));
        $this->assertTrue($task->getFromDB($id));
        $this->assertSame('Own task renamed', $task->fields['name']);
        $this->assertSame($own->getID(), (int) $task->fields['plugin_resources_resources_id']);
        $this->assertSame($child_1, (int) $task->fields['entities_id']);
        $this->assertSame(0, (int) $task->fields['is_recursive']);

        // The transfer of the resource still takes its tasks along
        $this->setEntity('_test_root_entity', true);
        $this->assertTrue($own->transferResource($own->getID(), $child_2));
        $this->hasSessionMessages(ERROR, [
            'The notification is not sent because the resource is not linked with a user',
        ]);
        $this->assertTrue($task->getFromDB($id));
        $this->assertSame($child_2, (int) $task->fields['entities_id']);
    }

    public function testTaskResourceIsOnlyShownToItsReaders(): void
    {
        global $DB;

        $child_1 = $this->entity('_test_child_1');
        $own     = $this->createResource('Column own', $child_1);
        $foreign = $this->createResource('Column foreign', $this->entity('_test_child_2'));

        $own_task = (new Task())->add([
            'name'                          => 'Column task own',
            'plugin_resources_resources_id' => $own->getID(),
        ]);
        $this->assertGreaterThan(0, $own_task);
        // A task of the entity of the viewer attached to a resource of another one, as an
        // older version let it be created
        $DB->insert(Task::getTable(), [
            'name'                          => 'Column task foreign',
            'plugin_resources_resources_id' => $foreign->getID(),
            'entities_id'                   => $child_1,
        ]);
        $foreign_task = (int) $DB->insertId();

        $this->setEntity('_test_child_1', false);

        // The task list as the core builds it, the "Resources" and resource ID columns
        // displayed: the same data feeds the exports and the search of the API.
        $search = fn(array $criteria = []): array => $this->withActivePlugin(static function () use ($criteria): array {
            $data = Search::getDatas(Task::class, [
                'reset'    => 'reset',
                'criteria' => array_merge([
                    ['field' => 1, 'searchtype' => 'contains', 'value' => 'Column task'],
                ], $criteria),
            ], [12, 13]);
            $rows = [];
            foreach ($data['data']['rows'] as $row) {
                $rows[(int) $row['id']] = $row;
            }

            return $rows;
        });

        $rows = $search();
        $this->assertEqualsCanonicalizing([$own_task, $foreign_task], array_keys($rows));
        $this->assertStringContainsString('Column own', $rows[$own_task][Task::class . '_12']['displayname']);
        $this->assertStringNotContainsString('Column foreign', (string) json_encode($rows[$foreign_task]));
        $this->assertStringNotContainsString((string) $foreign->getID(), (string) $rows[$foreign_task][Task::class . '_13']['displayname']);

        // A criterion on the column cannot be used to guess the name either
        $criterion = static fn(string $value): array => [
            ['link' => 'AND', 'field' => 12, 'searchtype' => 'contains', 'value' => $value],
        ];
        $this->assertSame([], array_keys($search($criterion('Column foreign'))));
        $this->assertSame([$own_task], array_keys($search($criterion('Column own'))));

        // Without the right to read resources, no resource is joined to the tasks
        $this->setSessionRights(['plugin_resources' => 0]);
        $rows = $search();
        $this->assertCount(2, $rows);
        $this->assertStringNotContainsString('Column own', (string) json_encode($rows[$own_task]));
    }

    public function testTaskPlanningRowIsTheOneOfTheTask(): void
    {
        $resource = $this->createResource('Planning');
        $tasks    = [];
        foreach (['A' => '2026-03-02', 'B' => '2026-03-03'] as $name => $day) {
            $task = new Task();
            $id = $task->add([
                'name'                          => "Planning $name",
                'plugin_resources_resources_id' => $resource->getID(),
                'plan'                          => ['begin' => "$day 09:00:00", 'end' => "$day 10:00:00"],
            ]);
            $this->assertGreaterThan(0, $id);
            $tasks[$name] = $id;
        }
        $plannings = [];
        foreach ($tasks as $name => $tasks_id) {
            $planning = new TaskPlanning();
            $this->assertTrue($planning->getFromDBbyTask($tasks_id), "Planning of $name");
            $plannings[$name] = $planning->getID();
        }

        // Task A saved with the planning row of task B
        $task = new Task();
        $this->assertTrue($task->update([
            'id'   => $tasks['A'],
            'plan' => [
                'id'    => $plannings['B'],
                'begin' => '2026-03-04 14:00:00',
                'end'   => '2026-03-04 15:00:00',
            ],
        ]));

        $planning = new TaskPlanning();
        $this->assertTrue($planning->getFromDB($plannings['B']));
        $this->assertSame($tasks['B'], (int) $planning->fields['plugin_resources_tasks_id']);
        $this->assertSame('2026-03-03 09:00:00', $planning->fields['begin']);

        $this->assertTrue($planning->getFromDB($plannings['A']));
        $this->assertSame($tasks['A'], (int) $planning->fields['plugin_resources_tasks_id']);
        $this->assertSame('2026-03-04 14:00:00', $planning->fields['begin']);
        $this->assertSame(1, countElementsInTable(TaskPlanning::getTable(), ['plugin_resources_tasks_id' => $tasks['A']]));
    }

    public function testTaskItemsRequireAReadableObject(): void
    {
        global $DB;

        $resource = $this->createResource('Task items', $this->entity('_test_child_1'));
        $task     = new Task();
        $tasks_id = $task->add([
            'name'                          => 'Task items',
            'plugin_resources_resources_id' => $resource->getID(),
        ]);
        $this->assertGreaterThan(0, $tasks_id);
        $readable   = getItemByTypeName(Computer::class, '_test_pc11', true);
        $unreadable = getItemByTypeName(Computer::class, '_test_pc21', true);

        $this->setEntity('_test_child_1', false);
        $task_item = new Task_Item();
        $this->assertFalse($task_item->add([
            'plugin_resources_tasks_id' => $tasks_id,
            'itemtype'                  => Computer::class,
            'items_id'                  => $unreadable,
        ]));
        $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);
        // A type the plugin does not link
        $this->assertFalse($task_item->add([
            'plugin_resources_tasks_id' => $tasks_id,
            'itemtype'                  => 'Entity',
            'items_id'                  => $this->entity('_test_child_1'),
        ]));
        $this->assertGreaterThan(0, $task_item->add([
            'plugin_resources_tasks_id' => $tasks_id,
            'itemtype'                  => Computer::class,
            'items_id'                  => $readable,
        ]));

        // A link left by an older version is not displayed
        $DB->insert(Task_Item::getTable(), [
            'plugin_resources_tasks_id' => $tasks_id,
            'itemtype'                  => Computer::class,
            'items_id'                  => $unreadable,
        ]);
        ob_start();
        (new Task_Item())->showItemFromPlugin($tasks_id);
        $output = (string) ob_get_clean();
        $this->assertStringContainsString('_test_pc11', $output);
        $this->assertStringNotContainsString('_test_pc21', $output);
    }

    public function testHabilitationBelongsToTheEntityOfTheResource(): void
    {
        $root    = $this->getTestRootEntity(true);
        $child_1 = $this->entity('_test_child_1');
        $child_2 = $this->entity('_test_child_2');

        $resource      = $this->createResource('Habilitation scope', $child_1);
        $habilitations = [];
        foreach (
            [
                'foreign'       => [$child_2, 0],
                'parent'        => [$root, 0],
                'own'           => [$child_1, 0],
                'parent shared' => [$root, 1],
            ] as $name => [$entities_id, $is_recursive]
        ) {
            $habilitations[$name] = $this->createItem(Habilitation::class, [
                'name'         => "References $name",
                'entities_id'  => $entities_id,
                'is_recursive' => $is_recursive,
            ])->getID();
        }

        $links = static fn(): array => array_map('intval', array_column(
            (new ResourceHabilitation())->find(['plugin_resources_resources_id' => $resource->getID()]),
            'plugin_resources_habilitations_id',
        ));

        $model = new ResourceHabilitation();
        foreach (['foreign', 'parent'] as $name) {
            $this->assertFalse($model->add([
                'plugin_resources_resources_id'     => $resource->getID(),
                'plugin_resources_habilitations_id' => $habilitations[$name],
            ]), $name);
            $this->hasSessionMessages(ERROR, ['Item not found']);
        }
        // The wizard goes through the same check
        $model->addResourceHabilitationInDb($habilitations['foreign'], $resource->getID());
        $this->hasSessionMessages(ERROR, ['Item not found']);
        $this->assertSame([], $links());

        foreach (['own', 'parent shared'] as $name) {
            $this->assertGreaterThan(0, $model->add([
                'plugin_resources_resources_id'     => $resource->getID(),
                'plugin_resources_habilitations_id' => $habilitations[$name],
            ]), $name);
        }
        $this->assertEqualsCanonicalizing(
            [$habilitations['own'], $habilitations['parent shared']],
            $links(),
        );
    }

    public function testChecklistSetupOnlyLinksReadableObjects(): void
    {
        global $DB;

        $child_1    = $this->entity('_test_child_1');
        $readable   = getItemByTypeName(Computer::class, '_test_pc11', true);
        $unreadable = getItemByTypeName(Computer::class, '_test_pc21', true);

        $this->setEntity('_test_child_1', false);
        $setup = new Checklistconfig();
        foreach (
            [
                ['itemtype' => Computer::class, 'items' => $unreadable],
                ['itemtype' => 'Entity', 'items' => $child_1],
            ] as $link
        ) {
            $this->assertFalse($setup->add(['name' => 'Refused setup', 'entities_id' => $child_1] + $link));
            $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);
        }
        $valid_id = $setup->add([
            'name'        => 'Valid setup',
            'entities_id' => $child_1,
            'itemtype'    => Computer::class,
            'items'       => $readable,
        ]);
        $this->assertGreaterThan(0, $valid_id);
        $this->assertGreaterThan(0, $setup->add(['name' => 'No object', 'entities_id' => $child_1, 'itemtype' => '', 'items' => 0]));
        // An update cannot point the setup to an object out of reach either
        $this->assertFalse($setup->update(['id' => $valid_id, 'items' => $unreadable]));
        $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);

        // A setup saved before the check: the object is checked against the entity of the
        // resource, whatever the session may read (the seeding may run from a cron)
        $this->setEntity('_test_root_entity', true);
        $DB->insert(Checklistconfig::getTable(), [
            'name'        => 'Legacy setup',
            'entities_id' => $this->getTestRootEntity(true),
            'itemtype'    => Computer::class,
            'items'       => $unreadable,
        ]);
        $legacy_id = $DB->insertId();

        $resource = $this->createResource('Checklist seeding', $child_1);
        $setup->addResourceChecklist($resource, $legacy_id, Checklist::RESOURCES_CHECKLIST_IN);
        $setup->addResourceChecklist($resource, $valid_id, Checklist::RESOURCES_CHECKLIST_IN);

        $this->assertSame(2, countElementsInTable(Checklist::getTable(), [
            'plugin_resources_resources_id' => $resource->getID(),
            'name'                          => ['Legacy setup', 'Valid setup'],
        ]));
        $links = (new Resource_Item())->find(['plugin_resources_resources_id' => $resource->getID()]);
        $this->assertSame([$readable], array_map('intval', array_column($links, 'items_id')));
    }

    public function testResourceLinksRequireAReadableObject(): void
    {
        $resource   = $this->createResource('Links', $this->entity('_test_child_1'));
        $readable   = getItemByTypeName(Computer::class, '_test_pc11', true);
        $unreadable = getItemByTypeName(Computer::class, '_test_pc21', true);

        $this->setEntity('_test_child_1', false);
        $link = new Resource_Item();
        foreach (
            [
                [Computer::class, $unreadable],
                ['Entity', $this->entity('_test_child_1')],
            ] as [$itemtype, $items_id]
        ) {
            $this->assertFalse($link->add([
                'plugin_resources_resources_id' => $resource->getID(),
                'itemtype'                      => $itemtype,
                'items_id'                      => $items_id,
            ]), $itemtype);
            $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);
        }

        $id = $link->add([
            'plugin_resources_resources_id' => $resource->getID(),
            'itemtype'                      => Computer::class,
            'items_id'                      => $readable,
        ]);
        $this->assertGreaterThan(0, $id);

        // Only the comment of a link changes afterwards
        $this->assertTrue($link->update([
            'id'       => $id,
            'items_id' => $unreadable,
            'itemtype' => User::class,
            'comment'  => 'Changed',
        ]));
        $this->assertTrue($link->getFromDB($id));
        $this->assertSame($readable, (int) $link->fields['items_id']);
        $this->assertSame(Computer::class, $link->fields['itemtype']);
        $this->assertSame('Changed', $link->fields['comment']);
    }

    public function testAssociateMassiveActionLinksTheTypeProcessed(): void
    {
        $resource = $this->createResource('Associate');
        $computer = new Computer();
        $pc       = getItemByTypeName(Computer::class, '_test_pc01', true);

        // The sub form posts itemtype=User: the id processed is the one of a computer
        $ma = $this->createStub(MassiveAction::class);
        $ma->method('getAction')->willReturn('plugin_resources_add_item');
        $ma->method('getItemtype')->willReturn(Computer::class);
        $ma->method('getInput')->willReturn([
            'itemtype'                      => User::class,
            'plugin_resources_resources_id' => $resource->getID(),
        ]);

        Resource::processMassiveActionsForOneItemtype($ma, $computer, [$pc => 1]);

        $links = (new Resource_Item())->find(['plugin_resources_resources_id' => $resource->getID()]);
        $this->assertCount(1, $links);
        $link = reset($links);
        $this->assertSame(Computer::class, $link['itemtype']);
        $this->assertSame($pc, (int) $link['items_id']);
    }

    public function testUserLocationOnlyFollowsForAnUpdatableUser(): void
    {
        $location = $this->createItem(Location::class, [
            'name'         => 'References location',
            'entities_id'  => $this->getTestRootEntity(true),
            'is_recursive' => 1,
        ])->getID();
        $user = $this->createUser('references-location', $this->getTestRootEntity(true), 1);

        $link = new Resource_Item();
        $user_location = static function () use ($user): int {
            $loaded = new User();
            $loaded->getFromDB($user->getID());

            return (int) $loaded->fields['locations_id'];
        };

        // Without the right to update users, the location of the user is left alone
        $this->setSessionRights(['user' => READ]);
        $link->updateLocation(['id' => $user->getID(), 'locations_id' => $location], 'User');
        $this->assertSame(0, $user_location());

        $this->setSessionRights(['user' => ALLSTANDARDRIGHT]);
        $link->updateLocation(['id' => $user->getID(), 'locations_id' => $location], 'User');
        $this->assertSame($location, $user_location());
    }

    public function testEmployerWriteRequiresTheEmployeeRight(): void
    {
        $resource = $this->createResource('Employer');
        $stored   = $this->createEmployer('Employer stored');
        $chosen   = $this->createEmployer('Employer chosen');
        $this->createItem(Employee::class, [
            'plugin_resources_resources_id' => $resource->getID(),
            'plugin_resources_employers_id' => $stored,
        ]);

        $this->setSessionRights(['plugin_resources_employee' => READ]);
        // Posted back unchanged by a profile that only sees the field: nothing to write
        $this->assertNull(Employee::getEmployerWrite($resource->getID(), ['plugin_resources_employers_id' => (string) $stored]));
        $this->assertNull(Employee::getEmployerWrite($resource->getID(), []));
        // A new resource without employer and without the right: nothing to write either
        $this->assertNull(Employee::getEmployerWrite(0, ['plugin_resources_employers_id' => 0]));

        foreach ([[$resource->getID(), $chosen], [0, $chosen]] as [$resources_id, $employers_id]) {
            try {
                Employee::getEmployerWrite($resources_id, ['plugin_resources_employers_id' => $employers_id]);
                $this->fail('An employer was written without the Employee right');
            } catch (AccessDeniedHttpException) {
                // expected
            }
        }

        $this->setSessionRights(['plugin_resources_employee' => ALLSTANDARDRIGHT]);
        $write = Employee::getEmployerWrite($resource->getID(), ['plugin_resources_employers_id' => $chosen]);
        $this->assertIsArray($write);
        Employee::saveEmployerWrite($resource->getID(), $write);
        $employee = new Employee();
        $this->assertTrue($employee->getFromDBByCrit(['plugin_resources_resources_id' => $resource->getID()]));
        $this->assertSame($chosen, (int) $employee->fields['plugin_resources_employers_id']);
    }

    public function testChangeActionsFollowTheProfile(): void
    {
        global $DB;

        $root = $this->getTestRootEntity(true);
        foreach ([Resource_Change::CHANGE_RESOURCEMANAGER, Resource_Change::CHANGE_NAME] as $action) {
            $DB->insert(Resource_Change::getTable(), [
                'actions_id'        => $action,
                'entities_id'       => $root,
                'itilcategories_id' => 0,
            ]);
        }

        $profiles_id = (int) $_SESSION['glpiactiveprofile']['id'];
        $DB->delete(Actionprofile::getTable(), ['profiles_id' => $profiles_id]);

        // No restriction for the profile: the actions set up
        $this->assertTrue(Resource_Change::isAllowedAction(Resource_Change::CHANGE_RESOURCEMANAGER));
        $this->assertTrue(Resource_Change::isAllowedAction(Resource_Change::CHANGE_NAME));
        $this->assertFalse(Resource_Change::isAllowedAction(Resource_Change::CHANGE_RESOURCESALE));
        $this->assertFalse(Resource_Change::isAllowedAction(0));
        $this->assertArrayNotHasKey(0, Resource_Change::getAllowedActions());

        // The profile is restricted to the change of name
        $DB->insert(Actionprofile::getTable(), [
            'profiles_id' => $profiles_id,
            'actions_id'  => json_encode([(string) Resource_Change::CHANGE_NAME]),
        ]);
        $this->assertFalse(Resource_Change::isAllowedAction(Resource_Change::CHANGE_RESOURCEMANAGER));
        $this->assertTrue(Resource_Change::isAllowedAction(Resource_Change::CHANGE_NAME));

        $resource = $this->createResource('Change action');
        $before   = (int) $resource->fields['users_id'];
        $this->assertFalse(Resource_Change::startingChange(
            $resource->getID(),
            Resource_Change::CHANGE_RESOURCEMANAGER,
            ['users_id' => getItemByTypeName('User', 'tech', true)],
        ));
        $this->assertTrue($resource->getFromDB($resource->getID()));
        $this->assertSame($before, (int) $resource->fields['users_id']);
    }

    public function testDropdownHidesAnUnreadableResource(): void
    {
        $own     = $this->createResource('Dropdown own', $this->entity('_test_child_1'));
        $foreign = $this->createResource('Dropdown foreign', $this->entity('_test_child_2'));

        $this->setEntity('_test_child_1', false);
        $hidden = (string) Resource::dropdown(['value' => $foreign->getID(), 'display' => false]);
        $this->assertStringNotContainsString('Dropdown foreign', $hidden);
        $this->assertStringContainsString('(' . $foreign->getID() . ')', $hidden);

        $shown = (string) Resource::dropdown(['value' => $own->getID(), 'display' => false]);
        $this->assertStringContainsString('Dropdown own', $shown);
    }

    public function testTemplatePostedAsIdIsTheSourceOfTheClone(): void
    {
        $template = $this->createResource('Posted template', null, [
            'is_template'   => 1,
            'template_name' => 'Posted template',
        ]);
        $plain = $this->createResource('Posted plain resource');
        foreach ([$template, $plain] as $source) {
            $this->assertGreaterThan(0, (new ReportConfig())->add([
                'plugin_resources_resources_id' => $source->getID(),
                'comment'                       => 'Report of ' . $source->fields['name'],
            ]));
        }
        $reports = static fn(int $resources_id): int => countElementsInTable(
            ReportConfig::getTable(),
            ['plugin_resources_resources_id' => $resources_id],
        );

        // The central form creating a resource from a template posts the id of the template
        $resource = $this->createResource('From the posted template', null, [
            'id'           => $template->getID(),
            'withtemplate' => 2,
        ]);
        $this->assertNotSame($template->getID(), $resource->getID());
        $this->assertSame(1, $reports($resource->getID()));

        // Any other id is ignored
        $resource = $this->createResource('From a posted resource', null, ['id' => $plain->getID()]);
        $this->assertNotSame($plain->getID(), $resource->getID());
        $this->assertSame(0, $reports($resource->getID()));
    }

    public function testCoreSearchJoinsOnlyReadableResources(): void
    {
        global $DB;

        $this->assertSame([], plugin_resources_getResourceJoinRestriction(Resource::class, 'glpi_plugin_resources_resources'));
        $restriction = plugin_resources_getResourceJoinRestriction(User::class, 'alias');
        $this->assertSame(0, $restriction['alias.is_deleted']);
        $this->assertSame(0, $restriction['alias.is_template']);

        // A user visible from every entity, whose resource lives in another one
        $user  = $this->createUser('references-search', $this->getTestRootEntity(true), 1);
        $sales = getItemByTypeName('User', 'tech', true);
        $resource = $this->createResource('Search foreign', $this->entity('_test_child_2'), [
            'users_id_sales' => $sales,
        ]);
        $this->assertGreaterThan(0, (new Resource_Item())->add([
            'plugin_resources_resources_id' => $resource->getID(),
            'itemtype'                      => User::class,
            'items_id'                      => $user->getID(),
        ]));

        $sales_of = function () use ($DB, $user): ?string {
            $already_link_tables = [];
            $join = plugin_resources_addLeftJoin(
                User::class,
                'glpi_users',
                'glpi_plugin_resources_salemanagers',
                'users_id_sales',
                $already_link_tables,
            );
            $row = $DB->request([
                'SELECT'    => ['glpi_plugin_resources_salemanagers.name AS sales'],
                'FROM'      => 'glpi_users',
                'LEFT JOIN' => $join['LEFT JOIN'],
                'WHERE'     => ['glpi_users.id' => $user->getID()],
            ])->current();

            return $row['sales'] ?? null;
        };

        $this->assertSame('tech', $sales_of());

        $this->setEntity('_test_child_1', false);
        $this->assertNull($sales_of());

        // The join every other option of a core itemtype goes through carries the restriction
        $already_link_tables = [];
        $join = plugin_resources_addLeftJoin(
            User::class,
            'glpi_users',
            'glpi_plugin_resources_resources',
            'plugin_resources_resources_id',
            $already_link_tables,
        );
        $on = $join['LEFT JOIN']['glpi_plugin_resources_resources']['ON'];
        $this->assertSame(0, $on[0]['AND']['glpi_plugin_resources_resources.is_deleted']);
        $this->assertSame(0, $on[0]['AND']['glpi_plugin_resources_resources.is_template']);
    }

    public function testEmployeeRowsAnswerToTheirResource(): void
    {
        $own     = $this->createResource('Employee own', $this->entity('_test_child_1'));
        $foreign = $this->createResource('Employee foreign', $this->entity('_test_child_2'));
        $employees = [];
        $employer  = $this->createEmployer('Employee rows employer');
        foreach (['own' => $own, 'foreign' => $foreign] as $name => $resource) {
            $employees[$name] = $this->createItem(Employee::class, [
                'plugin_resources_resources_id' => $resource->getID(),
                'plugin_resources_employers_id' => $employer,
            ])->getID();
        }

        // The Employee right in the entity of one resource: the generic entry points (API,
        // massive actions) authorise with can()
        $this->setEntity('_test_child_1', false);
        $this->setSessionRights(['plugin_resources_employee' => ALLSTANDARDRIGHT]);

        $employee = new Employee();
        foreach ([READ, UPDATE, PURGE] as $right) {
            $this->assertTrue($employee->can($employees['own'], $right), "Own row, right $right");
            $this->assertFalse($employee->can($employees['foreign'], $right), "Foreign row, right $right");
        }

        $input = ['plugin_resources_resources_id' => $foreign->getID(), 'plugin_resources_employers_id' => 1002];
        $this->assertFalse($employee->can(-1, CREATE, $input));
        $input = ['plugin_resources_resources_id' => $own->getID(), 'plugin_resources_employers_id' => 1002];
        $this->assertTrue($employee->can(-1, CREATE, $input));

        // The Employee right remains the global one
        $this->setSessionRights(['plugin_resources_employee' => READ]);
        $this->assertFalse($employee->can($employees['own'], UPDATE));
    }

    public function testTaskItemsAnswerToTheirTask(): void
    {
        $tasks = [];
        $links = [];
        foreach (['own' => ['_test_child_1', '_test_pc11'], 'foreign' => ['_test_child_2', '_test_pc21']] as $name => [$entity, $pc]) {
            $resource = $this->createResource("Task item $name", $this->entity($entity));
            $tasks[$name] = (new Task())->add([
                'name'                          => "Task item $name",
                'plugin_resources_resources_id' => $resource->getID(),
            ]);
            $this->assertGreaterThan(0, $tasks[$name]);
            $links[$name] = (new Task_Item())->add([
                'plugin_resources_tasks_id' => $tasks[$name],
                'itemtype'                  => Computer::class,
                'items_id'                  => getItemByTypeName(Computer::class, $pc, true),
            ]);
            $this->assertGreaterThan(0, $links[$name]);
        }
        $readable = getItemByTypeName(Computer::class, '_test_pc11', true);

        $this->setEntity('_test_child_1', false);
        $task_item = new Task_Item();
        foreach ([READ, UPDATE, PURGE] as $right) {
            $this->assertTrue($task_item->can($links['own'], $right), "Own link, right $right");
            $this->assertFalse($task_item->can($links['foreign'], $right), "Foreign link, right $right");
        }
        $input = ['plugin_resources_tasks_id' => $tasks['foreign'], 'itemtype' => Computer::class, 'items_id' => $readable];
        $this->assertFalse($task_item->can(-1, CREATE, $input));
        $input = ['plugin_resources_tasks_id' => $tasks['own'], 'itemtype' => Computer::class, 'items_id' => $readable];
        $this->assertTrue($task_item->can(-1, CREATE, $input));

        // A link is not moved to another task, nor pointed to another object or type
        $this->assertTrue($task_item->update([
            'id'                        => $links['own'],
            'plugin_resources_tasks_id' => $tasks['foreign'],
            'itemtype'                  => User::class,
            'items_id'                  => getItemByTypeName(Computer::class, '_test_pc21', true),
        ]));
        $this->assertTrue($task_item->getFromDB($links['own']));
        $this->assertSame($tasks['own'], (int) $task_item->fields['plugin_resources_tasks_id']);
        $this->assertSame(Computer::class, $task_item->fields['itemtype']);
        $this->assertSame($readable, (int) $task_item->fields['items_id']);
    }

    public function testTransferFollowsTheProfile(): void
    {
        global $DB;

        $profiles_id = (int) $_SESSION['glpiactiveprofile']['id'];
        $DB->delete(Actionprofile::getTable(), ['profiles_id' => $profiles_id]);
        $DB->delete(TransferEntity::getTable(), [new QueryExpression('true')]);

        // Not set up: no entity is opened to transfers
        $this->assertFalse(Resource_Change::isAllowedAction(Resource_Change::CHANGE_TRANSFER));

        // Its setup is TransferEntity, no change action row
        $DB->insert(TransferEntity::getTable(), [
            'entities_id' => $this->entity('_test_child_1'),
            'groups_id'   => 0,
        ]);
        $this->assertTrue(Resource_Change::isAllowedAction(Resource_Change::CHANGE_TRANSFER));
        $this->assertArrayHasKey(Resource_Change::CHANGE_TRANSFER, Resource_Change::getAllActions());

        // A profile restricted to other actions
        $DB->insert(Actionprofile::getTable(), [
            'profiles_id' => $profiles_id,
            'actions_id'  => json_encode([(string) Resource_Change::CHANGE_NAME]),
        ]);
        $this->assertFalse(Resource_Change::isAllowedAction(Resource_Change::CHANGE_TRANSFER));

        $DB->update(
            Actionprofile::getTable(),
            ['actions_id' => json_encode([(string) Resource_Change::CHANGE_NAME, (string) Resource_Change::CHANGE_TRANSFER])],
            ['profiles_id' => $profiles_id],
        );
        $this->assertTrue(Resource_Change::isAllowedAction(Resource_Change::CHANGE_TRANSFER));
    }

    public function testGenerateResourcesMassiveActionCreatesInTheActiveEntity(): void
    {
        $child_1 = $this->entity('_test_child_1');
        $user    = $this->createItem(User::class, [
            'name'          => 'references-generate',
            'realname'      => 'Generated',
            'firstname'     => 'Massive',
            '_profiles_id'  => getItemByTypeName('Profile', 'Self-Service', true),
            '_entities_id'  => $this->getTestRootEntity(true),
            '_is_recursive' => 1,
        ]);

        $this->setEntity('_test_child_1', false);
        $ma = $this->createStub(MassiveAction::class);
        $ma->method('getAction')->willReturn('plugin_resources_generate_resources');
        $ma->method('getItemtype')->willReturn(User::class);
        $ma->method('getInput')->willReturn([
            'itemtype'                          => User::class,
            'plugin_resources_contracttypes_id' => 0,
            'plugin_resources_departments_id'   => 0,
            'users_id_recipient'                => 0,
            // Not a field of the sub form: an entity out of reach of the session
            'entities_id'                       => $this->entity('_test_child_2'),
        ]);

        Resource::processMassiveActionsForOneItemtype($ma, new User(), [$user->getID() => 1]);

        $resource = new Resource();
        $this->assertTrue($resource->getFromDBByCrit(['name' => 'Generated', 'firstname' => 'Massive']));
        $this->assertSame($child_1, (int) $resource->fields['entities_id']);
        $links = (new Resource_Item())->find(['plugin_resources_resources_id' => $resource->getID()]);
        $this->assertCount(1, $links);
        $link = reset($links);
        $this->assertSame(User::class, $link['itemtype']);
        $this->assertSame($user->getID(), (int) $link['items_id']);

        // Without the right to create resources, nothing is created
        $other = $this->createItem(User::class, [
            'name'          => 'references-generate-2',
            'realname'      => 'Generated',
            'firstname'     => 'Refused',
            '_profiles_id'  => getItemByTypeName('Profile', 'Self-Service', true),
            '_entities_id'  => $this->getTestRootEntity(true),
            '_is_recursive' => 1,
        ]);
        $this->setSessionRights(['plugin_resources' => READ | UPDATE]);
        Resource::processMassiveActionsForOneItemtype($ma, new User(), [$other->getID() => 1]);
        $this->assertSame(0, countElementsInTable(Resource::getTable(), ['name' => 'Generated', 'firstname' => 'Refused']));
    }

    public function testApplianceChangeLinksWithoutTheApplianceRight(): void
    {
        global $DB;

        $root = $this->getTestRootEntity(true);
        $DB->insert(Resource_Change::getTable(), [
            'actions_id'        => Resource_Change::CHANGE_RESOURCEITEMAPPLICATION,
            'entities_id'       => $root,
            'itilcategories_id' => 0,
        ]);
        $DB->delete(Actionprofile::getTable(), ['profiles_id' => (int) $_SESSION['glpiactiveprofile']['id']]);

        $resource  = $this->createResource('Appliance change', $this->entity('_test_child_1'));
        $shared    = $this->createItem(Appliance::class, ['name' => 'References shared appliance', 'entities_id' => $root, 'is_recursive' => 1]);
        $elsewhere = $this->createItem(Appliance::class, ['name' => 'References other appliance', 'entities_id' => $this->entity('_test_child_2')]);
        $tickets   = static fn(): int => countElementsInTable(Ticket::getTable(), ['name' => ['LIKE', 'Add application for%']]);
        $linked    = static fn(int $appliances_id): int => countElementsInTable(Resource_Item::getTable(), [
            'plugin_resources_resources_id' => $resource->getID(),
            'itemtype'                      => Appliance::class,
            'items_id'                      => $appliances_id,
        ]);

        // A HR profile declaring the change usually holds no right on appliances
        $this->setSessionRights(['appliance' => 0]);
        $before = $tickets();
        $this->assertTrue(Resource_Change::startingChange(
            $resource->getID(),
            Resource_Change::CHANGE_RESOURCEITEMAPPLICATION,
            ['appliances_id' => $shared->getID()],
        ));
        $this->assertSame(1, $linked($shared->getID()));
        $this->assertSame($before + 1, $tickets());

        // An appliance out of the scope of the entity of the resource: neither link nor ticket
        $this->assertFalse(Resource_Change::startingChange(
            $resource->getID(),
            Resource_Change::CHANGE_RESOURCEITEMAPPLICATION,
            ['appliances_id' => $elsewhere->getID()],
        ));
        $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);
        $this->assertSame(0, $linked($elsewhere->getID()));
        $this->assertSame($before + 1, $tickets());
    }

    public function testTemplateRowsAreCopiedWithoutTheRightsOfTheCreatorOnEachType(): void
    {
        $root     = $this->getTestRootEntity(true);
        $template = $this->createResource('Clone template', $root, [
            'is_template'   => 1,
            'template_name' => 'Clone template',
        ]);
        $need = $this->createItem(ComputerType::class, ['name' => 'References need']);
        $pc   = getItemByTypeName(Computer::class, '_test_pc01', true);
        foreach ([[ComputerType::class, $need->getID()], [Computer::class, $pc]] as [$itemtype, $items_id]) {
            $this->assertGreaterThan(0, (new Resource_Item())->add([
                'plugin_resources_resources_id' => $template->getID(),
                'itemtype'                      => $itemtype,
                'items_id'                      => $items_id,
            ]));
        }
        $template_task = (new Task())->add([
            'name'                          => 'Clone template task',
            'plugin_resources_resources_id' => $template->getID(),
        ]);
        $this->assertGreaterThan(0, $template_task);
        $this->assertGreaterThan(0, (new Task_Item())->add([
            'plugin_resources_tasks_id' => $template_task,
            'itemtype'                  => Computer::class,
            'items_id'                  => $pc,
        ]));

        // A profile declaring arrivals: it creates resources without updating them, and holds
        // no right on the hardware nor on the dropdowns a template lists
        $this->setSessionRights([
            'plugin_resources' => READ | CREATE,
            'computer'         => 0,
            'dropdown'         => 0,
        ]);

        // The wizard posts the template as "template", front/wizard.form.php hands it over as
        // id_template
        $resource = $this->createResource('From the wizard template', $root, [
            'template'    => $template->getID(),
            'id_template' => $template->getID(),
        ]);

        $links = (new Resource_Item())->find(['plugin_resources_resources_id' => $resource->getID()]);
        $this->assertEqualsCanonicalizing(
            [ComputerType::class . '#' . $need->getID(), Computer::class . '#' . $pc],
            array_map(static fn(array $link): string => $link['itemtype'] . '#' . $link['items_id'], array_values($links)),
        );
        $tasks = (new Task())->find(['plugin_resources_resources_id' => $resource->getID()]);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertSame(1, countElementsInTable(Task_Item::getTable(), [
            'plugin_resources_tasks_id' => $task['id'],
            'itemtype'                  => Computer::class,
            'items_id'                  => $pc,
        ]));

        // An object out of the scope of the entity of the new resource is left out, once reported
        $this->setSessionRights(['plugin_resources' => ALLSTANDARDRIGHT, 'computer' => ALLSTANDARDRIGHT]);
        $this->assertGreaterThan(0, (new Resource_Item())->add([
            'plugin_resources_resources_id' => $template->getID(),
            'itemtype'                      => Computer::class,
            'items_id'                      => getItemByTypeName(Computer::class, '_test_pc21', true),
        ]));
        $resource = $this->createResource('From the template, in another entity', $this->entity('_test_child_1'), [
            'id_template' => $template->getID(),
        ]);
        $this->hasSessionMessages(WARNING, [
            'Some items linked to the template are out of the entity of the resource: they were not copied',
        ]);
        $this->assertSame(1, countElementsInTable(Resource_Item::getTable(), [
            'plugin_resources_resources_id' => $resource->getID(),
        ]));
    }

    public function testEmployerOfATemplateIsTheReferenceOfANewResource(): void
    {
        $template = $this->createResource('Employer template', null, [
            'is_template'   => 1,
            'template_name' => 'Employer template',
        ]);
        $stored = $this->createEmployer('Employer of the template');
        $chosen = $this->createEmployer('Employer of the new resource');
        $this->createItem(Employee::class, [
            'plugin_resources_resources_id' => $template->getID(),
            'plugin_resources_employers_id' => $stored,
        ]);
        // The central form creating a resource from a template posts the id of the template
        $posted       = ['id' => $template->getID(), 'withtemplate' => 2];
        $templates_id = Resource::getTemplateSourceId($posted);
        $this->assertSame($template->getID(), $templates_id);

        // The employer of the template posted back by a profile that only sees the field
        $this->setSessionRights(['plugin_resources_employee' => READ]);
        $this->assertNull(Employee::getEmployerWrite(0, $posted + ['plugin_resources_employers_id' => $stored], $templates_id));
        $this->assertFalse(Employee::canChangeEmployer(0));
        try {
            Employee::getEmployerWrite(0, $posted + ['plugin_resources_employers_id' => $chosen], $templates_id);
            $this->fail('An employer was chosen without the Employee right');
        } catch (AccessDeniedHttpException) {
            // expected
        }
        $resource = $this->createResource('Employer from template', null, $posted);
        $this->assertSame($stored, Employee::getEmployerOf($resource->getID()));

        // Another employer, chosen with the right: written over the row copied from the template
        $this->setSessionRights(['plugin_resources_employee' => READ | CREATE]);
        $write = Employee::getEmployerWrite(0, $posted + ['plugin_resources_employers_id' => $chosen], $templates_id);
        $this->assertIsArray($write);
        $resource = $this->createResource('Employer chosen', null, $posted);
        Employee::saveEmployerWrite($resource->getID(), $write);
        $this->assertSame(1, countElementsInTable(Employee::getTable(), ['plugin_resources_resources_id' => $resource->getID()]));
        $this->assertSame($chosen, Employee::getEmployerOf($resource->getID()));
    }

    public function testWizardSecondStepShowsTheStoredEmployer(): void
    {
        $resource = $this->createResource('Wizard employer');
        $stored   = $this->createEmployer('Wizard employer stored');
        $this->createItem(Employee::class, [
            'plugin_resources_resources_id' => $resource->getID(),
            'plugin_resources_employers_id' => $stored,
        ]);

        // Back to the second step, for a profile that sees the field without the right to
        // change the employer: shown read-only, with the value stored, so that the third step
        // posts it back unchanged
        $this->setSessionRights([
            Profile::RIGHT_EMPLOYEE_CORE_FORM => READ,
            'plugin_resources_employee'       => READ,
        ]);
        ob_start();
        try {
            (new Wizard())->wizardSecondStep($resource->getID());
        } finally {
            $html = (string) ob_get_clean();
        }
        $this->assertMatchesRegularExpression("/name='plugin_resources_employers_id' value='$stored'/", $html);
        $this->assertNull(Employee::getEmployerWrite($resource->getID(), ['plugin_resources_employers_id' => (string) $stored]));
    }

    public function testChecklistTaskMassiveActionTakesTheResourceOfTheRow(): void
    {
        $child_1 = $this->entity('_test_child_1');
        $own     = $this->createResource('Checklist task own', $child_1);
        $other   = $this->createResource('Checklist task other', $child_1);
        $row     = $this->createItem(Checklist::class, [
            'name'                              => 'Checklist task row',
            'entities_id'                       => $child_1,
            'plugin_resources_resources_id'     => $own->getID(),
            'plugin_resources_contracttypes_id' => 0,
            'checklist_type'                    => Checklist::RESOURCES_CHECKLIST_IN,
            'is_checked'                        => 0,
        ]);

        // The resource and the entity of the request altered: another resource the session
        // may update, an entity it may not see
        $ma = $this->createStub(MassiveAction::class);
        $ma->method('getAction')->willReturn('add_task');
        $ma->method('getInput')->willReturn([
            'plugin_resources_resources_id' => $other->getID(),
            'checklist_type'                => Checklist::RESOURCES_CHECKLIST_IN,
            'entities_id'                   => $this->entity('_test_child_2'),
            'is_recursive'                  => 1,
            'users_id'                      => 0,
        ]);
        Checklist::processMassiveActionsForOneItemtype($ma, new Checklist(), [$row->getID() => 1]);

        $task = new Task();
        $this->assertTrue($task->getFromDBByCrit(['name' => 'Checklist task row']));
        $this->assertSame($own->getID(), (int) $task->fields['plugin_resources_resources_id']);
        $this->assertSame($child_1, (int) $task->fields['entities_id']);
        $this->assertSame(0, (int) $task->fields['is_recursive']);
        $this->assertTrue($row->getFromDB($row->getID()));
        $this->assertSame($task->getID(), (int) $row->fields['plugin_resources_tasks_id']);
        $this->assertSame(0, countElementsInTable(Task::getTable(), ['plugin_resources_resources_id' => $other->getID()]));
    }

    public function testTaskPlanningUpdateKeepsTheTaskItBelongsTo(): void
    {
        $resource = $this->createResource('Planning owner');
        $tasks    = [];
        foreach (['A' => '2026-04-01', 'B' => '2026-04-02'] as $name => $day) {
            $id = (new Task())->add([
                'name'                          => "Planning owner $name",
                'plugin_resources_resources_id' => $resource->getID(),
                'plan'                          => ['begin' => "$day 09:00:00", 'end' => "$day 10:00:00"],
            ]);
            $this->assertGreaterThan(0, $id);
            $tasks[$name] = $id;
        }
        $planning = new TaskPlanning();
        $this->assertTrue($planning->getFromDBbyTask($tasks['A']));
        $plannings_id = $planning->getID();

        // The planning row of task A updated with the id of task B
        $this->assertTrue($planning->update([
            'id'                        => $plannings_id,
            'plugin_resources_tasks_id' => $tasks['B'],
            'begin'                     => '2026-04-03 09:00:00',
            'end'                       => '2026-04-03 11:00:00',
        ]));

        $this->assertTrue($planning->getFromDB($plannings_id));
        $this->assertSame($tasks['A'], (int) $planning->fields['plugin_resources_tasks_id']);
        $this->assertSame('2026-04-03 09:00:00', $planning->fields['begin']);
        // The duration recomputed is the one of task A; task B keeps its own
        $task = new Task();
        $this->assertTrue($task->getFromDB($tasks['A']));
        $this->assertSame(2 * HOUR_TIMESTAMP, (int) $task->fields['actiontime']);
        $this->assertTrue($task->getFromDB($tasks['B']));
        $this->assertSame(HOUR_TIMESTAMP, (int) $task->fields['actiontime']);
        $this->assertSame(1, countElementsInTable(TaskPlanning::getTable(), ['plugin_resources_tasks_id' => $tasks['B']]));
    }

    public function testChecklistTicketRequiresTheTicketRightAndBoundsPostedValues(): void
    {
        $resource = $this->createResource('Checklist ticket');
        $tech     = getItemByTypeName(User::class, 'tech', true);
        $data     = [
            'plugin_resources_resources_id' => $resource->getID(),
            'tickettemplates_id'            => getItemByTypeName(TicketTemplate::class, 'Default', true),
            'users_id'                      => $tech,
            'actiontime'                    => 10 * DAY_TIMESTAMP,
        ];
        $last_ticket = static function (): int {
            $rows = (new Ticket())->find([], ['id DESC'], 1);

            return (int) (array_key_first($rows) ?? 0);
        };
        $assigned = static fn(int $tickets_id): array => array_map('intval', array_column(
            (new Ticket_User())->find(['tickets_id' => $tickets_id, 'type' => CommonITILActor::ASSIGN]),
            'users_id',
        ));
        $ticket_right = (int) $_SESSION['glpiactiveprofile'][Ticket::$rightname];

        // Without the right to create tickets: nothing is created
        $before = $last_ticket();
        $this->setSessionRights([Ticket::$rightname => $ticket_right & ~CREATE]);
        $this->assertFalse(Checklist::createTicket($data));
        $this->hasSessionMessages(ERROR, [self::NO_PERMISSION]);
        $this->assertSame($before, $last_ticket());

        // With it: the duration stays within the range of the form, the assignee is kept for
        // a session allowed to assign, among the users holding a central profile
        $this->setSessionRights([Ticket::$rightname => $ticket_right]);
        $this->assertTrue(Checklist::createTicket($data));
        $tickets_id = $last_ticket();
        $this->assertGreaterThan($before, $tickets_id);
        $ticket = new Ticket();
        $this->assertTrue($ticket->getFromDB($tickets_id));
        $this->assertSame(DAY_TIMESTAMP, (int) $ticket->fields['actiontime']);
        $this->assertSame([$tech], $assigned($tickets_id));

        // A user the dropdown does not offer: the session user instead
        $this->assertTrue(Checklist::createTicket(
            ['users_id' => getItemByTypeName(User::class, 'post-only', true)] + $data,
        ));
        $this->assertSame([Session::getLoginUserID()], $assigned($last_ticket()));

        // A session not allowed to assign tickets: the session user instead
        $this->setSessionRights([Ticket::$rightname => $ticket_right & ~Ticket::ASSIGN]);
        $this->assertTrue(Checklist::createTicket($data));
        $this->assertSame([Session::getLoginUserID()], $assigned($last_ticket()));
    }

    public function testAddHabilitationMassiveActionFindsTheResourceOfTheUser(): void
    {
        global $DB;

        $root         = $this->getTestRootEntity(true);
        $resource     = $this->createResource('Habilitation of user');
        $habilitation = $this->createItem(Habilitation::class, [
            'name'         => 'References user habilitation',
            'entities_id'  => $root,
            'is_recursive' => 1,
        ])->getID();
        $users = [];
        foreach (['one', 'two'] as $name) {
            $users[$name] = $this->createUser("references-habilitation-$name", $root, 1)->getID();
        }
        $link = static function (int $users_id, int $resources_id) use ($DB): void {
            $DB->insert(Resource_Item::getTable(), [
                'plugin_resources_resources_id' => $resources_id,
                'itemtype'                      => User::class,
                'items_id'                      => $users_id,
            ]);
        };
        $link($users['one'], $resource->getID());
        // The second user is linked to two resources: none of them can be told to be its own
        $second = $this->createResource('Habilitation of user, second');
        $link($users['two'], $resource->getID());
        $link($users['two'], $second->getID());

        $ma = $this->createStub(MassiveAction::class);
        $ma->method('getAction')->willReturn('plugin_resources_add_habilitation');
        $ma->method('getItemtype')->willReturn(User::class);
        $ma->method('getInput')->willReturn(['plugin_resources_habilitations_id' => $habilitation]);

        Resource::processMassiveActionsForOneItemtype($ma, new User(), [$users['one'] => 1]);
        $this->assertSame(1, countElementsInTable(ResourceHabilitation::getTable(), [
            'plugin_resources_resources_id'     => $resource->getID(),
            'plugin_resources_habilitations_id' => $habilitation,
        ]));

        Resource::processMassiveActionsForOneItemtype($ma, new User(), [$users['two'] => 1]);
        $this->assertSame(0, countElementsInTable(ResourceHabilitation::getTable(), [
            'plugin_resources_resources_id' => $second->getID(),
        ]));
        // Applied again: no duplicate
        Resource::processMassiveActionsForOneItemtype($ma, new User(), [$users['one'] => 1]);
        $this->assertSame(1, countElementsInTable(ResourceHabilitation::getTable(), [
            'plugin_resources_resources_id' => $resource->getID(),
        ]));
    }

    public function testEmploymentResourceIsOnlyShownToItsReaders(): void
    {
        global $DB;

        $child_1 = $this->entity('_test_child_1');
        $own     = $this->createResource('Employment column own', $child_1);
        $foreign = $this->createResource('Employment column foreign', $this->entity('_test_child_2'));
        $employments = [];
        foreach (['own' => $own, 'foreign' => $foreign] as $name => $resource) {
            // An employment of the entity of the viewer, whatever the entity of its resource
            $DB->insert(Employment::getTable(), [
                'name'                          => "Employment row $name",
                'entities_id'                   => $child_1,
                'plugin_resources_resources_id' => $resource->getID(),
            ]);
            $employments[$name] = (int) $DB->insertId();
        }

        $this->setEntity('_test_child_1', false);
        $search = fn(array $criteria = []): array => $this->withActivePlugin(static function () use ($criteria): array {
            $data = Search::getDatas(Employment::class, [
                'reset'    => 'reset',
                'criteria' => array_merge([
                    ['field' => 1, 'searchtype' => 'contains', 'value' => 'Employment row'],
                ], $criteria),
            ], [3, 13]);
            $rows = [];
            foreach ($data['data']['rows'] as $row) {
                $rows[(int) $row['id']] = $row;
            }

            return $rows;
        });

        $rows = $search();
        $this->assertEqualsCanonicalizing(array_values($employments), array_keys($rows));
        $this->assertStringContainsString(
            'Employment column own',
            $rows[$employments['own']][Employment::class . '_3']['displayname'],
        );
        $this->assertStringNotContainsString('Employment column foreign', (string) json_encode($rows[$employments['foreign']]));

        // A criterion on the column cannot be used to guess the name either
        $this->assertSame([], array_keys($search([
            ['link' => 'AND', 'field' => 3, 'searchtype' => 'contains', 'value' => 'column foreign'],
        ])));
    }

    public function testResourceNameCellLinksReadableResourcesWithTheirTasks(): void
    {
        $resource = $this->createResource('<b>Name cell</b>');
        foreach ([0, 1] as $is_finished) {
            $this->assertGreaterThan(0, (new Task())->add([
                'name'                          => "Name cell task $is_finished",
                'plugin_resources_resources_id' => $resource->getID(),
                'is_finished'                   => $is_finished,
            ]));
        }

        $cell = $this->withActivePlugin(static function () use ($resource): string {
            $data = Search::getDatas(Resource::class, [
                'reset'    => 'reset',
                'criteria' => [
                    ['field' => 2, 'searchtype' => 'contains', 'value' => 'References'],
                    ['link' => 'AND', 'field' => 1, 'searchtype' => 'contains', 'value' => 'Name cell'],
                ],
            ], [1]);
            foreach ($data['data']['rows'] as $row) {
                if ((int) $row['id'] === $resource->getID()) {
                    return (string) $row[Resource::class . '_1']['displayname'];
                }
            }

            return '';
        });

        // The name escaped, linked to the resource, then the counter of its open tasks linked
        // to the task list filtered on it
        $this->assertStringContainsString('&lt;b&gt;Name cell&lt;/b&gt;', $cell);
        $this->assertStringNotContainsString('<b>Name cell', $cell);
        $this->assertStringContainsString(Resource::getFormURLWithID($resource->getID()), $cell);
        $this->assertStringContainsString("<span class='plugin_resources_date_over_color'>1</span>", $cell);
        $this->assertStringContainsString('/front/task.php?criteria', $cell);
    }
}
