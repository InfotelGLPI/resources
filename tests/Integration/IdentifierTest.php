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

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Tests\DbTestCase;
use GlpiPlugin\Resources\Checklist;
use GlpiPlugin\Resources\Choice;
use GlpiPlugin\Resources\Employee;
use GlpiPlugin\Resources\Habilitation;
use GlpiPlugin\Resources\HabilitationLevel;
use GlpiPlugin\Resources\Profile;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Resource_Change;
use GlpiPlugin\Resources\ResourceHabilitation;
use GlpiPlugin\Resources\Wizard;
use MassiveAction;
use PHPUnit\Framework\Attributes\DataProvider;
use Session;

/**
 * The id a right is checked on is the id the operation then uses.
 *
 * Posted ids that are not plain positive integers are refused rather than cast, and the model
 * methods reached from the controllers only act on the integer they were given.
 */
class IdentifierTest extends DbTestCase
{
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

    private function createResource(string $name, ?int $entities_id = null): Resource
    {
        return $this->createItem(Resource::class, [
            'name'               => $name,
            'firstname'          => 'Identifier',
            'entities_id'        => $entities_id ?? $this->getTestRootEntity(true),
            'users_id_recipient' => Session::getLoginUserID(),
            'comment'            => 'Comment of ' . $name,
            'withtemplate'       => 0,
        ], ['withtemplate']);
    }

    /**
     * Run a call that has to be refused as a bad request.
     */
    private function assertBadRequest(callable $call, string $message = ''): void
    {
        try {
            $call();
        } catch (BadRequestHttpException) {
            $this->addToAssertionCount(1);
            return;
        }
        $this->fail('The call was not refused. ' . $message);
    }

    public static function validIdProvider(): iterable
    {
        yield [5, 5];
        yield ['5', 5];
        yield ['1234', 1234];
        yield [PHP_INT_MAX, PHP_INT_MAX];
        // Absent, or the empty choice of a dropdown
        yield [null, 0];
        yield ['', 0];
        yield ['0', 0];
        yield [0, 0];
    }

    #[DataProvider('validIdProvider')]
    public function testPostedIdAcceptsPlainIds(mixed $value, int $expected): void
    {
        $this->assertSame($expected, Resource::getPostedId($value));
    }

    public static function invalidIdProvider(): iterable
    {
        yield ['x123'];
        yield ['5x3'];
        yield ['4.0'];
        yield ['4.6'];
        yield ['1e3'];
        yield ['0123'];
        yield [' 7'];
        yield ['7 '];
        yield ['+7'];
        yield ['-5'];
        yield [-5];
        yield ['0x1A'];
        yield ['99999999999999999999'];
        yield [5.0];
        yield [true];
        yield [[7]];
        yield [['>', 0]];
        yield [[1, 2]];
    }

    #[DataProvider('invalidIdProvider')]
    public function testPostedIdRefusesAnythingElse(mixed $value): void
    {
        $this->expectException(BadRequestHttpException::class);
        Resource::getPostedId($value);
    }

    public function testOwnershipChecksAreMadeOnAnExistingResource(): void
    {
        $resource = $this->createResource('Ownership');
        $id       = $resource->getID();

        // The id returned is the one checked, as an integer
        $this->assertSame($id, Resource::checkOwnership($id));
        $this->assertSame($id, Resource::checkOwnership((string) $id));

        // On 0, check() would only test the right to create a resource
        foreach ([0, '0', null, '', "x$id", "{$id}x", "$id.0", [$id], ['>', 0]] as $forged) {
            $this->assertBadRequest(fn() => Resource::checkOwnership($forged), var_export($forged, true));
        }
    }

    public function testCreateRightNoLongerStandsForAnotherRight(): void
    {
        $resource = $this->createResource('Create only');
        $id       = $resource->getID();

        // A profile that may only declare resources
        $this->setSessionRights(['plugin_resources' => READ | CREATE]);

        // A value read as 0 by a cast used to turn the PURGE test into a creation test
        foreach ([0, "x$id", "$id.0"] as $forged) {
            $this->assertBadRequest(
                fn() => Resource::checkPostedItem(new Resource(), $forged, PURGE),
                var_export($forged, true),
            );
        }

        $this->expectException(AccessDeniedHttpException::class);
        Resource::checkPostedItem(new Resource(), $id, PURGE);
    }

    public function testChildOwnershipReturnsTheRowChecked(): void
    {
        global $DB;

        $resource = $this->createResource('Child owner');
        $employee = $this->createItem(Employee::class, [
            'plugin_resources_resources_id' => $resource->getID(),
        ]);
        $id = $employee->getID();

        $child = new Employee();
        $this->assertSame($id, Resource::checkChildOwnership($child, (string) $id));
        // The child is left loaded with that row
        $this->assertSame($id, $child->getID());

        foreach ([0, "x$id", "$id.0", [$id]] as $forged) {
            $this->assertBadRequest(
                fn() => Resource::checkChildOwnership(new Employee(), $forged),
                var_export($forged, true),
            );
        }

        // A row attached to no resource has no owner to check: refused, where check(0)
        // used to test the right to create one
        $DB->insert(Employee::getTable(), ['plugin_resources_resources_id' => 0]);
        $orphan = $DB->insertId();
        $this->assertBadRequest(fn() => Resource::checkChildOwnership(new Employee(), $orphan));
    }

    public function testMassiveActionKeysThatAreNotPlainIdsAreReportedAsFailed(): void
    {
        $failed = [];
        $ma = $this->createStub(MassiveAction::class);
        $ma->method('itemDone')->willReturnCallback(
            function ($itemtype, $id, $result) use (&$failed) {
                $this->assertSame(MassiveAction::ACTION_KO, $result);
                $failed[] = $id;
            },
        );

        // PHP turns plain integer keys into int keys, the other ones stay strings
        $ids = ['12x' => 1, 7 => 1, '0123' => 1, '4.0' => 1, '-2' => 1, 9 => 1];
        $this->assertSame([7 => 1, 9 => 1], Resource::getMassiveActionIds($ma, new Resource(), $ids));
        $this->assertSame(['12x', '0123', '4.0', -2], $failed);
    }

    public function testHabilitationsAreOnlyReplacedOnTheResourceChecked(): void
    {
        $root  = $this->getTestRootEntity(true);
        $a     = $this->createResource('Habilitation A')->getID();
        $b     = $this->createResource('Habilitation B')->getID();
        $level = $this->createItem(HabilitationLevel::class, [
            'name'         => 'Identifier level',
            'entities_id'  => $root,
            'is_recursive' => 1,
        ])->getID();
        $habilitations = [];
        foreach (['first', 'second'] as $name) {
            $habilitations[] = $this->createItem(Habilitation::class, [
                'name'                                   => "Identifier $name",
                'entities_id'                            => $root,
                'is_recursive'                           => 1,
                'plugin_resources_habilitationlevels_id' => $level,
            ])->getID();
        }
        [$first, $second] = $habilitations;
        foreach ([$a, $b] as $resources_id) {
            $this->createItem(ResourceHabilitation::class, [
                'plugin_resources_resources_id'     => $resources_id,
                'plugin_resources_habilitations_id' => $first,
            ]);
        }

        $links = static fn(int $resources_id): array => array_column(
            (new ResourceHabilitation())->find(['plugin_resources_resources_id' => $resources_id]),
            'plugin_resources_habilitations_id',
        );

        // An array or an operator form reached the deletion criteria and purged the links
        // of every resource listed, or of all of them
        $model = new ResourceHabilitation();
        foreach ([[$a, $b], ['>', 0], "{$b}x", "0$a"] as $forged) {
            $input = ['plugin_resources_resources_id' => $forged, 'habilitation_' . $level => $second];
            $this->assertBadRequest(fn() => $model->addResourceHabilitation($input), var_export($forged, true));
            $this->assertBadRequest(fn() => $model->checkRequiredFields($input), var_export($forged, true));
        }
        $this->assertFalse($model->addResourceHabilitation(['plugin_resources_resources_id' => 0]));
        $this->assertEquals([$first], $links($a));
        $this->assertEquals([$first], $links($b));

        $this->assertTrue($model->addResourceHabilitation([
            'plugin_resources_resources_id' => $a,
            'habilitation_' . $level        => $second,
        ]));
        $this->assertEquals([$second], $links($a));
        $this->assertEquals([$first], $links($b));
    }

    /**
     * @return array<string, int> checklist rows, by name
     */
    private function createChecklists(array $rows): array
    {
        $ids = [];
        foreach ($rows as $name => $resources_id) {
            $ids[$name] = $this->createItem(Checklist::class, [
                'name'                              => "Checklist $name",
                'entities_id'                       => $this->getTestRootEntity(true),
                'plugin_resources_resources_id'     => $resources_id,
                'plugin_resources_contracttypes_id' => 0,
                'checklist_type'                    => Checklist::RESOURCES_CHECKLIST_IN,
                'is_checked'                        => 1,
            ])->getID();
        }

        return $ids;
    }

    public function testOnlyTheChecklistOfTheResourceCheckedIsReopened(): void
    {
        $a   = $this->createResource('Checklist A')->getID();
        $b   = $this->createResource('Checklist B')->getID();
        $ids = $this->createChecklists(['a1' => $a, 'a2' => $a, 'b1' => $b]);

        $checked = static function (int $id): int {
            $checklist = new Checklist();
            $checklist->getFromDB($id);
            return (int) $checklist->fields['is_checked'];
        };

        $checklist = new Checklist();
        $forged_inputs = [
            ['plugin_resources_resources_id' => ['>', 0], 'checklist_type' => Checklist::RESOURCES_CHECKLIST_IN],
            ['plugin_resources_resources_id' => [$a, $b], 'checklist_type' => Checklist::RESOURCES_CHECKLIST_IN],
            ['plugin_resources_resources_id' => "{$b}x", 'checklist_type' => Checklist::RESOURCES_CHECKLIST_IN],
            ['plugin_resources_resources_id' => $a, 'checklist_type' => ['>', 0]],
            ['plugin_resources_resources_id' => $a, 'checklist_type' => 9],
        ];
        foreach ($forged_inputs as $input) {
            $this->assertBadRequest(fn() => $checklist->openFinishedChecklist($input), var_export($input, true));
            $this->assertBadRequest(fn() => Checklist::checkifChecklistFinished($input), var_export($input, true));
        }
        foreach ($ids as $id) {
            $this->assertSame(1, $checked($id));
        }

        $this->assertTrue($checklist->openFinishedChecklist([
            'plugin_resources_resources_id' => $a,
            'checklist_type'                => Checklist::RESOURCES_CHECKLIST_IN,
        ]));
        $this->assertSame(0, $checked($ids['a1']));
        $this->assertSame(0, $checked($ids['a2']));
        $this->assertSame(1, $checked($ids['b1']));
    }

    public function testRankIsOnlyChangedOnTheRowLookedUp(): void
    {
        $a   = $this->createResource('Rank A')->getID();
        $b   = $this->createResource('Rank B')->getID();
        $ids = $this->createChecklists(['a1' => $a, 'a2' => $a, 'b1' => $b, 'b2' => $b]);

        $ranks = static function () use ($ids): array {
            $ranks = [];
            foreach ($ids as $name => $id) {
                $checklist = new Checklist();
                $checklist->getFromDB($id);
                $ranks[$name] = (int) $checklist->fields['rank'];
            }
            return $ranks;
        };
        $before = $ranks();
        $this->assertSame(['a1' => 1, 'a2' => 2, 'b1' => 1, 'b2' => 2], $before);

        $move = static fn(mixed $id, int $resources_id): array => [
            'id'                            => $id,
            'plugin_resources_resources_id' => $resources_id,
            'checklist_type'                => Checklist::RESOURCES_CHECKLIST_IN,
            'action'                        => 'up',
        ];

        $checklist = new Checklist();
        // The lookup cast the id while the update did not
        foreach (["{$ids['a2']}x", "{$ids['a2']}.0", [$ids['a2']]] as $forged) {
            $this->assertBadRequest(fn() => $checklist->changeRank($move($forged, $a)), var_export($forged, true));
        }
        // A row of another resource is not found under the resource checked
        $this->assertFalse($checklist->changeRank($move($ids['b2'], $a)));
        $this->assertSame($before, $ranks());

        $this->assertTrue($checklist->changeRank($move($ids['a2'], $a)));
        $this->assertSame(['a1' => 2, 'a2' => 1, 'b1' => 1, 'b2' => 2], $ranks());
    }

    public function testTransferRequiresTheUpdateRightOnTheResource(): void
    {
        $root     = $this->getTestRootEntity(true);
        $child    = getItemByTypeName('Entity', '_test_child_1', true);
        $resource = $this->createResource('Transfer', $root);
        $id       = $resource->getID();

        // A profile that may only declare resources moved any of them
        $this->setSessionRights(['plugin_resources' => READ | CREATE]);
        $this->assertFalse((new Resource())->transferResource($id, $child));
        $this->assertFalse((new Resource())->transferResource(0, $child));
        $resource->getFromDB($id);
        $this->assertSame($root, (int) $resource->fields['entities_id']);

        $this->setSessionRights(['plugin_resources' => ALLSTANDARDRIGHT]);
        $this->assertTrue((new Resource())->transferResource($id, $child));
        $resource->getFromDB($id);
        $this->assertSame($child, (int) $resource->fields['entities_id']);
        $this->hasSessionMessages(ERROR, [
            'The notification is not sent because the resource is not linked with a user',
        ]);
    }

    public function testChangeRequiresTheUpdateRightOnTheResource(): void
    {
        $resource = $this->createResource('Change');
        $id       = $resource->getID();
        $before   = (int) $resource->fields['users_id'];

        $this->setSessionRights(['plugin_resources' => READ | CREATE]);
        $options = ['users_id' => getItemByTypeName('User', 'tech', true)];
        $this->assertFalse(Resource_Change::startingChange($id, Resource_Change::CHANGE_RESOURCEMANAGER, $options));
        $this->assertFalse(Resource_Change::startingChange(0, Resource_Change::CHANGE_RESOURCEMANAGER, $options));

        $resource->getFromDB($id);
        $this->assertSame($before, (int) $resource->fields['users_id']);
    }

    public function testEmployeeStaysUnderItsResource(): void
    {
        $a        = $this->createResource('Employee A')->getID();
        $b        = $this->createResource('Employee B')->getID();
        $employee = $this->createItem(Employee::class, ['plugin_resources_resources_id' => $a]);

        $this->assertTrue($employee->update([
            'id'                            => $employee->getID(),
            'plugin_resources_resources_id' => $b,
        ]));
        $employee->getFromDB($employee->getID());
        $this->assertSame($a, (int) $employee->fields['plugin_resources_resources_id']);
    }

    public function testCommentIsWrittenOnTheResourceGiven(): void
    {
        $a = $this->createResource('Comment A');
        $b = $this->createResource('Comment B');

        $this->assertTrue((new Choice())->addComment($a, 'More needs'));

        $a->getFromDB($a->getID());
        $b->getFromDB($b->getID());
        $this->assertStringContainsString('Comment of Comment A', $a->fields['comment']);
        $this->assertStringContainsString('More needs', $a->fields['comment']);
        $this->assertSame('Comment of Comment B', $b->fields['comment']);

        // No resource loaded, nothing written
        $this->assertFalse((new Choice())->addComment(new Resource(), 'Lost'));
        $this->assertFalse((new Choice())->addHelpdeskItem(0, 1));
    }

    public function testWizardStepsRefuseAnIdThatIsNotPlain(): void
    {
        $id     = $this->createResource('Wizard step')->getID();
        $wizard = new Wizard();

        // Such a value skipped the READ check (it does not compare as > 0) and was then loaded
        foreach (["x$id", [$id], "$id.0"] as $forged) {
            $this->assertBadRequest(fn() => $wizard->wizardFourStep($forged), var_export($forged, true));
            $this->assertBadRequest(fn() => $wizard->wizardSixStep($forged), var_export($forged, true));
        }
    }
}
