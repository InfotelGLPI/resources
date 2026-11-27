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

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Resources\Actionprofile;
use GlpiPlugin\Resources\Adconfig;
use GlpiPlugin\Resources\Budget;
use GlpiPlugin\Resources\CheckSchema;
use GlpiPlugin\Resources\Checklist;
use GlpiPlugin\Resources\Choice;
use GlpiPlugin\Resources\Client;
use GlpiPlugin\Resources\Config;
use GlpiPlugin\Resources\ConfigHabilitation;
use GlpiPlugin\Resources\ContractNature;
use GlpiPlugin\Resources\Contracttypeprofile;
use GlpiPlugin\Resources\Cost;
use GlpiPlugin\Resources\Directory;
use GlpiPlugin\Resources\Employment;
use GlpiPlugin\Resources\LeavingInformation;
use GlpiPlugin\Resources\Linkmetademand;
use GlpiPlugin\Resources\Profession;
use GlpiPlugin\Resources\ProfessionCategory;
use GlpiPlugin\Resources\ProfessionLine;
use GlpiPlugin\Resources\Profile;
use GlpiPlugin\Resources\Rank;
use GlpiPlugin\Resources\Recap;
use GlpiPlugin\Resources\ReportConfig;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Resource_Change;
use GlpiPlugin\Resources\Resource_Item;
use GlpiPlugin\Resources\Resource_Leaving;
use GlpiPlugin\Resources\ResourceBadge;
use GlpiPlugin\Resources\ResourceHabilitation;
use GlpiPlugin\Resources\ResourceSituation;
use GlpiPlugin\Resources\ResourceSpeciality;
use GlpiPlugin\Resources\RuleChecklist;
use GlpiPlugin\Resources\Task;
use GlpiPlugin\Resources\TicketCategory;
use GlpiPlugin\Resources\TransferEntity;
use ITILCategory;
use MassiveAction;
use Migration;
use PHPUnit\Framework\Attributes\DataProvider;
use ProfileRight;
use User;

/**
 * Rights of the itemtypes exposed to the generic entry points of the core (tab loader,
 * massive actions, API), which only ask the itemtype itself.
 */
class RightsTest extends DbTestCase
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

    /**
     * Set rights of the session, the other rights being left as they are.
     *
     * @param array<string, int> $rights
     */
    private function setSessionRights(array $rights): void
    {
        foreach ($rights as $name => $value) {
            $_SESSION['glpiactiveprofile'][$name] = $value;
        }
    }

    public static function userListProvider(): iterable
    {
        yield [Directory::class];
        yield [Recap::class];
    }

    #[DataProvider('userListProvider')]
    public function testUserListsRefuseEveryItemOperation(string $itemtype): void
    {
        $users_id = getItemByTypeName(User::class, 'tech', true);
        $before   = getItemByTypeName(User::class, 'tech')->fields;

        $item = new $itemtype();
        $this->assertTrue($itemtype::canView());
        foreach ([READ, UPDATE, DELETE, PURGE] as $right) {
            $this->assertFalse($item->can($users_id, $right), "right $right");
        }
        $input = ['name' => 'forged'];
        $this->assertFalse($item->can(-1, CREATE, $input));
        $this->assertFalse($itemtype::canCreate());
        $this->assertFalse($itemtype::canUpdate());
        $this->assertFalse($itemtype::canDelete());
        $this->assertFalse($itemtype::canPurge());

        // Direct writes are refused as well
        $this->assertFalse($item->add(['name' => 'forged_' . mt_rand()]));
        $this->assertFalse($item->update(['id' => $users_id, 'password' => 'forged', 'is_active' => 0]));
        $this->assertFalse($item->delete(['id' => $users_id], true));
        $after = getItemByTypeName(User::class, 'tech')->fields;
        $this->assertSame($before['password'], $after['password']);
        $this->assertSame($before['is_active'], $after['is_active']);

        // Nothing User hides may come out of these lists
        $this->assertSame([], array_diff(User::$undisclosedFields, $itemtype::$undisclosedFields));
        // Nor anything else than the columns the list shows: the legacy API hands over a whole
        // row of glpi_users after this method only
        $row = $before + ['_keys_names' => ['auths_id' => 'Directory']];
        $itemtype::unsetUndisclosedFields($row);
        $this->assertSame([], array_diff(array_keys($row), $itemtype::getDisclosedFields()));
        $this->assertSame($users_id, (int) $row['id']);
        foreach (['password', 'api_token', 'user_dn', 'authtype', 'auths_id', 'last_login', 'name', '_keys_names'] as $field) {
            $this->assertArrayNotHasKey($field, $row);
        }

        // No standard massive action is offered
        $actions = MassiveAction::getAllMassiveActions($itemtype);
        $this->assertIsArray($actions);
        foreach (array_keys($actions) as $action) {
            $this->assertStringStartsNotWith(MassiveAction::class . MassiveAction::CLASS_ACTION_SEPARATOR, $action);
        }
    }

    public function testDirectoryRequiresTheAnnuaryRight(): void
    {
        $this->setSessionRights([
            'plugin_resources'         => ALLSTANDARDRIGHT,
            'plugin_resources_annuary' => 0,
        ]);
        $this->assertFalse(Directory::canView());
        $this->assertSame([], (new Directory())->getSpecificMassiveActions());

        $this->setSessionRights(['plugin_resources_annuary' => READ]);
        $this->assertTrue(Directory::canView());
    }

    public static function setupItemtypeProvider(): iterable
    {
        foreach ([Config::class, Adconfig::class, TransferEntity::class, Linkmetademand::class, Resource_Change::class, TicketCategory::class] as $itemtype) {
            yield [$itemtype, true];
        }
        // The restrictions of each profile (change actions, contract types)
        yield [Actionprofile::class, true];
        yield [Contracttypeprofile::class, true];
        // Readable with their own right, written from the setup page only
        yield [ResourceBadge::class, false];
        yield [ConfigHabilitation::class, false];
    }

    #[DataProvider('setupItemtypeProvider')]
    public function testSetupItemtypesRequireTheConfigRight(string $itemtype, bool $view_is_setup): void
    {
        $this->setSessionRights([
            'config'                        => READ,
            'plugin_resources'              => ALLSTANDARDRIGHT,
            'plugin_resources_checklist'    => ALLSTANDARDRIGHT,
            'plugin_resources_habilitation' => ALLSTANDARDRIGHT,
        ]);
        $this->assertSame(!$view_is_setup, $itemtype::canView());
        $this->assertFalse($itemtype::canCreate());
        $this->assertFalse($itemtype::canUpdate());
        $this->assertFalse($itemtype::canDelete());
        $this->assertFalse($itemtype::canPurge());

        $this->setSessionRights(['config' => READ | UPDATE]);
        $this->assertTrue($itemtype::canView());
        $this->assertTrue($itemtype::canCreate());
        $this->assertTrue($itemtype::canUpdate());
        $this->assertTrue($itemtype::canDelete());
        $this->assertTrue($itemtype::canPurge());
    }

    public function testMetademandOptionsStillWriteTheMapping(): void
    {
        // Metademand::saveOptions() writes the mapping from the options form of the meta-demand
        // field with add() / update(), without can(): the setup right does not stand in its way.
        $this->setSessionRights(['config' => READ]);

        $link = new Linkmetademand();
        $id   = $link->add([
            'plugin_metademands_fields_id'      => 999999,
            'plugin_metademands_metademands_id' => 1,
            'check_value'                       => '1',
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertTrue($link->update(['id' => $id, 'is_leaving_resource' => 1]));
    }

    public function testSchemaCheckTabRequiresTheConfigRight(): void
    {
        $this->setSessionRights([
            'config'           => READ,
            'plugin_resources' => ALLSTANDARDRIGHT,
        ]);
        $config = new Config();

        $this->assertFalse(CheckSchema::canView());
        $this->assertSame('', (new CheckSchema())->getTabNameForItem($config));

        ob_start();
        CheckSchema::displayTabContentForItem($config);
        $this->assertSame('', ob_get_clean());

        $this->setSessionRights(['config' => READ | UPDATE]);
        $this->assertNotSame('', (new CheckSchema())->getTabNameForItem($config));
    }

    public static function publicDropdownProvider(): iterable
    {
        foreach ([Cost::class, Rank::class, Profession::class, ProfessionLine::class, ProfessionCategory::class, ResourceSituation::class, ResourceSpeciality::class, ContractNature::class] as $itemtype) {
            yield [$itemtype];
        }
    }

    #[DataProvider('publicDropdownProvider')]
    public function testPublicDropdownsCheckEveryBitOfTheDropdownRight(string $itemtype): void
    {
        $this->setSessionRights([
            'plugin_resources'          => ALLSTANDARDRIGHT,
            Profile::RIGHT_DROPDOWN_PUBLIC => READ,
        ]);
        $this->assertTrue($itemtype::canView());
        $this->assertFalse($itemtype::canCreate());
        $this->assertFalse($itemtype::canUpdate());
        $this->assertFalse($itemtype::canDelete());
        $this->assertFalse($itemtype::canPurge());

        // One bit, one operation
        $this->setSessionRights([Profile::RIGHT_DROPDOWN_PUBLIC => READ | UPDATE]);
        $this->assertTrue($itemtype::canUpdate());
        $this->assertFalse($itemtype::canCreate());
        $this->assertFalse($itemtype::canPurge());

        $this->setSessionRights([Profile::RIGHT_DROPDOWN_PUBLIC => READ | CREATE]);
        $this->assertTrue($itemtype::canCreate());
        $this->assertFalse($itemtype::canUpdate());

        // The plugin right is still required
        $this->setSessionRights([
            'plugin_resources'          => READ,
            Profile::RIGHT_DROPDOWN_PUBLIC => ALLSTANDARDRIGHT,
        ]);
        $this->assertTrue($itemtype::canView());
        $this->assertFalse($itemtype::canCreate());
        $this->assertFalse($itemtype::canPurge());

        // To create, as a writer of resources: its CREATE bit declares resources, which a
        // profile editing them only may be denied (the migration leaves that bit alone)
        $this->setSessionRights(['plugin_resources' => READ | UPDATE]);
        $this->assertTrue($itemtype::canCreate());
        $this->setSessionRights(['plugin_resources' => READ | CREATE]);
        $this->assertTrue($itemtype::canCreate());
        $this->setSessionRights(['plugin_resources' => READ | DELETE | PURGE]);
        $this->assertFalse($itemtype::canCreate());
    }

    public function testLeavingInformationRequiresItsRight(): void
    {
        // The tab and its form require the dedicated right: so do the generic entry points
        $this->setSessionRights([
            'plugin_resources'                => ALLSTANDARDRIGHT,
            Profile::RIGHT_LEAVINGINFORMATION => 0,
        ]);
        $this->assertFalse(LeavingInformation::canView());
        $this->assertFalse(LeavingInformation::canCreate());
        $this->assertFalse(LeavingInformation::canUpdate());
        $this->assertFalse(LeavingInformation::canDelete());
        $this->assertFalse(LeavingInformation::canPurge());

        $this->setSessionRights([Profile::RIGHT_LEAVINGINFORMATION => READ]);
        $this->assertTrue(LeavingInformation::canView());
        $this->assertTrue(LeavingInformation::canCreate());
        $this->assertTrue(LeavingInformation::canUpdate());
        $this->assertTrue(LeavingInformation::canPurge());

        // Creating a row is still an update of the resource
        $this->setSessionRights(['plugin_resources' => READ | CREATE]);
        $this->assertFalse(LeavingInformation::canCreate());
    }

    public function testPublicSectorFieldsKeepTheFormerTest(): void
    {
        // The required-field rules of the public sector fields used Rank::canCreate(), which
        // the rights above changed: the fields a requester has to fill must not change with it.
        // Former test: CREATE, UPDATE or DELETE on the resources and UPDATE on the dropdowns.
        $this->setSessionRights([
            'plugin_resources'             => READ | UPDATE,
            Profile::RIGHT_DROPDOWN_PUBLIC => READ | UPDATE,
        ]);
        $this->assertTrue(Rank::canFillPublicSectorFields());
        $this->assertFalse(Rank::canCreate());

        $this->setSessionRights([Profile::RIGHT_DROPDOWN_PUBLIC => READ | CREATE]);
        $this->assertFalse(Rank::canFillPublicSectorFields());

        $this->setSessionRights([
            'plugin_resources'             => READ | DELETE,
            Profile::RIGHT_DROPDOWN_PUBLIC => READ | UPDATE,
        ]);
        $this->assertTrue(Rank::canFillPublicSectorFields());

        $this->setSessionRights(['plugin_resources' => READ | PURGE]);
        $this->assertFalse(Rank::canFillPublicSectorFields());
    }

    public static function createRightProvider(): iterable
    {
        yield [Employment::class, 'plugin_resources_employment', READ | UPDATE | DELETE];
        yield [Budget::class, 'plugin_resources_budget', READ | UPDATE | DELETE];
        yield [Task::class, 'plugin_resources_task', READ | UPDATE | DELETE];
        yield [Checklist::class, 'plugin_resources_checklist', READ | UPDATE | PURGE];
        yield [RuleChecklist::class, 'plugin_resources_rule', READ | UPDATE | PURGE];
        yield [Client::class, 'dropdown', READ | UPDATE | PURGE];
    }

    #[DataProvider('createRightProvider')]
    public function testCreateRequiresTheCreateBit(string $itemtype, string $right, int $without_create): void
    {
        $this->setSessionRights([$right => $without_create]);
        $this->assertFalse($itemtype::canCreate());

        $this->setSessionRights([$right => $without_create | CREATE]);
        $this->assertTrue($itemtype::canCreate());
    }

    public static function resourceChildProvider(): iterable
    {
        foreach ([Choice::class, LeavingInformation::class, ReportConfig::class, ResourceHabilitation::class, Resource_Item::class, Resource_Leaving::class] as $itemtype) {
            yield [$itemtype];
        }
    }

    #[DataProvider('resourceChildProvider')]
    public function testResourceRowsAreCreatedWithTheUpdateBit(string $itemtype): void
    {
        // Editing resources without declaring them: the rows of a resource stay available
        $this->setSessionRights(['plugin_resources' => READ | UPDATE]);
        $this->assertTrue($itemtype::canCreate());
        $this->assertFalse(Resource::canCreate());

        // Neither CREATE, nor DELETE or PURGE stand for it any longer
        $this->setSessionRights(['plugin_resources' => READ | CREATE | DELETE | PURGE]);
        $this->assertFalse($itemtype::canCreate());
    }

    public function testChangeActionSetupIsValidated(): void
    {
        $root    = getItemByTypeName('Entity', '_test_root_entity', true);
        $child_1 = getItemByTypeName('Entity', '_test_child_1', true);
        $child_2 = getItemByTypeName('Entity', '_test_child_2', true);

        $inherited = $this->createItem(ITILCategory::class, [
            'name'         => 'Change setup inherited',
            'entities_id'  => $root,
            'is_recursive' => 1,
            'is_request'   => 1,
        ]);
        $other_entity = $this->createItem(ITILCategory::class, [
            'name'         => 'Change setup other entity',
            'entities_id'  => $child_1,
            'is_recursive' => 0,
            'is_request'   => 1,
        ]);

        $change = new Resource_Change();
        $valid  = [
            'actions_id'        => Resource_Change::CHANGE_RESOURCEMANAGER,
            'entities_id'       => $child_2,
            'itilcategories_id' => $inherited->getID(),
        ];
        $this->assertGreaterThan(0, $change->add($valid));
        // Not offered by the setup form
        $this->assertFalse($change->add(['actions_id' => 999] + $valid));
        $this->assertFalse($change->add(['actions_id' => Resource_Change::CHANGE_TRANSFER] + $valid));
        // Badge restitution is set up here too
        $this->assertGreaterThan(0, $change->add(['actions_id' => Resource_Change::BADGE_RESTITUTION] + $valid));
        // Category not visible from the entity of the row, or missing
        $this->assertFalse($change->add(['itilcategories_id' => $other_entity->getID()] + $valid));
        $this->assertFalse($change->add(['itilcategories_id' => 0] + $valid));

        $this->hasSessionMessages(
            ERROR,
            array_fill(0, 4, 'The action, the entity or the category of this change action is not valid'),
        );
    }

    public function testEnforcedRightsMigration(): void
    {
        $profile = $this->createItem(\Profile::class, [
            'name'      => 'Resources rights migration',
            'interface' => 'central',
        ]);
        $reader = $this->createItem(\Profile::class, [
            'name'      => 'Resources rights migration, no plugin read',
            'interface' => 'central',
        ]);
        ProfileRight::updateProfileRights($profile->getID(), [
            'plugin_resources'             => READ | UPDATE,
            'plugin_resources_annuary'     => 0,
            'plugin_resources_checklist'   => READ | PURGE,
            'plugin_resources_task'        => READ,
            Profile::RIGHT_DROPDOWN_PUBLIC => READ | UPDATE,
        ]);
        ProfileRight::updateProfileRights($reader->getID(), [
            'plugin_resources'         => 0,
            'plugin_resources_annuary' => 0,
        ]);

        Profile::migrateEnforcedRights(new Migration('4.2.3'));

        $rights = ProfileRight::getProfileRights($profile->getID(), [
            'plugin_resources',
            'plugin_resources_annuary',
            'plugin_resources_checklist',
            'plugin_resources_task',
            Profile::RIGHT_DROPDOWN_PUBLIC,
        ]);
        // Its CREATE bit declares resources: never granted by the migration
        $this->assertSame(READ | UPDATE, (int) $rights['plugin_resources']);
        $this->assertSame(READ, (int) $rights['plugin_resources_annuary']);
        $this->assertSame(READ | PURGE | CREATE, (int) $rights['plugin_resources_checklist']);
        // Neither UPDATE nor DELETE: nothing to carry over
        $this->assertSame(READ, (int) $rights['plugin_resources_task']);
        $this->assertSame(READ | UPDATE | CREATE, (int) $rights[Profile::RIGHT_DROPDOWN_PUBLIC]);

        $rights = ProfileRight::getProfileRights($reader->getID(), ['plugin_resources_annuary']);
        $this->assertSame(0, (int) $rights['plugin_resources_annuary']);

        // plugin_resources_install() runs the migration once per site, guarded by the
        // enforced_rights_migrated marker it sets at the end of every install and update. GLPI
        // only runs it when the version of the files is newer than the installed one: the release
        // bumps it (not checked here, the version in setup.php is only changed to publish one)
    }
}
