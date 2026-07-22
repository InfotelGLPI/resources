<?php

/*
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2015-2026 by the resources Development Team.

 https://github.com/InfotelGLPI/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of resources.

 resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Resources;

use CommonGLPI;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use Html;
use ProfileRight;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Profile
 */
class Profile extends \Profile
{
    public static $rightname = "profile";

    /**
     * @param \CommonGLPI $item
     * @param int $withtemplate
     *
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == 'Profile') {
            return self::createTabEntry(Resource::getTypeName(2));
        }
        return '';
    }

    /**
     * @return string
     */
    public static function getIcon()//self::createTabEntry(
    {
        return "ti ti-friends";
    }

    /**
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if (!$item instanceof \Profile || !self::canView()) {
            return false;
        }

        $profile = new \Profile();
        $profile->getFromDB($item->getID());

        $rights = self::getAllRights(true);

        $twig = TemplateRenderer::getInstance();
        $twig->display('@resources/profile.html.twig', [
            'id' => $item->getID(),
            'profile' => $profile,
            'title' => self::getTypeName(Session::getPluralNumber()),
            'rights' => $rights,
        ]);

        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        Contracttypeprofile::addContracttype($item->getID(), $canedit);
        Actionprofile::addAction($item->getID(), $canedit);

        return true;
    }

    /**
     * @param $profiles_id
     */
    public static function createFirstAccess($profiles_id)
    {
        self::addDefaultProfileInfos($profiles_id, [
            'plugin_resources' => ALLSTANDARDRIGHT + READNOTE + UPDATENOTE,
            'plugin_resources_task' => ALLSTANDARDRIGHT,
            'plugin_resources_checklist' => ALLSTANDARDRIGHT,
            'plugin_resources_employee' => ALLSTANDARDRIGHT,
            'plugin_resources_role' => ALLSTANDARDRIGHT,
            'plugin_resources_resting' => ALLSTANDARDRIGHT,
            'plugin_resources_holiday' => ALLSTANDARDRIGHT,
            'plugin_resources_habilitation' => ALLSTANDARDRIGHT,
            'plugin_resources_employment' => ALLSTANDARDRIGHT,
            'plugin_resources_budget' => ALLSTANDARDRIGHT,
            'plugin_resources_dropdown_public' => ALLSTANDARDRIGHT,
            'plugin_resources_import' => ALLSTANDARDRIGHT,
            'plugin_resources_annuary' => ALLSTANDARDRIGHT,
            'plugin_resources_validation' => ALLSTANDARDRIGHT,
            'plugin_resources_open_ticket' => 1,
            'plugin_resources_all' => 1,
            'plugin_resources_leavinginformation' => 1,
            'plugin_resources_employee_core_form' => 1
        ], true);
    }

    /**
     * @param $profile
     **/
    public static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false)
    {
        $dbu = new DbUtils();
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if ($dbu->countElementsInTable(
                    'glpi_profilerights',
                    ["profiles_id" => $profiles_id, "name" => $right]
                ) && $drop_existing) {
                $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
            }
            if (!$dbu->countElementsInTable(
                'glpi_profilerights',
                ["profiles_id" => $profiles_id, "name" => $right]
            )) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name'] = $right;
                $myright['rights'] = $value;
                $profileRight->add($myright);

                //Add right to the current session
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }



    /**
     * @param bool $all
     * @param array $types
     *
     * @return array
     */
    public static function getAllRights($all = true, $types = [])
    {
        $rights = [
            [
                'itemtype' => Resource::class,
                'label' => _n('Human resource', 'Human resources', 1, 'resources'),
                'field' => 'plugin_resources',
                'type' => 'general'
            ],
            [
                'itemtype' => Task::class,
                'label' => _n('Task', 'Tasks', 1),
                'field' => 'plugin_resources_task',
                'type' => 'general'
            ],
            [
                'itemtype' => Budget::class,
                'label' => _n('Budget', 'Budgets', 1),
                'field' => 'plugin_resources_budget',
                'type' => 'public'
            ],
            [
                'itemtype' => Checklist::class,
                'label' => _n('Checklist', 'Checklists', 1, 'resources'),
                'field' => 'plugin_resources_checklist',
                'type' => 'general'
            ],
            [
                'itemtype' => Employee::class,
                'label' => _n('Employee', 'Employees', 1, 'resources'),
                'field' => 'plugin_resources_employee',
                'type' => 'general'
            ],
            [
                'itemtype' => Role::class,
                'label' => _n('Role', 'Roles', 1, 'resources'),
                'field' => 'plugin_resources_role',
                'type' => 'general'
            ],

            [
                'itemtype' => ResourceResting::class,
                'label' => _n('Non contract period management', 'Non contract periods management', 1, 'resources'),
                'field' => 'plugin_resources_resting',
                'type' => 'ssii'
            ],
            [
                'itemtype' => ResourceHoliday::class,
                'label' => _n('Holiday', 'Holidays', 1, 'resources'),
                'field' => 'plugin_resources_holiday',
                'type' => 'ssii'
            ],
            [
                'itemtype' => ResourceHabilitation::class,
                'label' => _n('Super habilitation', 'Super habilitations', 1, 'resources'),
                'field' => 'plugin_resources_habilitation',
                'type' => 'ssii'
            ],
            [
                'itemtype' => Employment::class,
                'label' => _n('Employment', 'Employments', 1, 'resources'),
                'field' => 'plugin_resources_employment',
                'type' => 'public'
            ],
            [
                'itemtype' => Resource::class,
                'label' => __('Dropdown management', 'resources'),
                'field' => 'plugin_resources_dropdown_public',
                'type' => 'public'
            ],
            [
                'itemtype' => Import::class,
                'label' => __('Import external', 'resources'),
                'field' => 'plugin_resources_import',
                'type' => 'import',
                'rights' => [
                    READ => __('Read'),
                    UPDATE => __('Update'),
                    CREATE => __('Create'),
                    PURGE => __('Purge')
                ]
            ],
            ['itemtype' => Directory::class,
                'label' => __('Annuary', 'resources'),
                'field' => 'plugin_resources_annuary',
                'type' => 'general',
                'rights' => [
                    READ => __('Read')
                ]
            ],
            ['itemtype' => Resource_Validation::class,
                'label' => __('AD Synchronization', 'resources'),
                'field' => 'plugin_resources_validation',
                'type' => 'general',
                'rights' => [
                    READ => __('Read'),
                    UPDATE => __('Update'),
                    CREATE => __('Create'),
                ]
            ]
        ];

        if ($all) {
            $rights[] = [
                'itemtype' => Resource::class,
                'label' => __('All resources access', 'resources'),
                'field' => 'plugin_resources_all',
                'rights' => [
                    READ => __('Read'),
                ]
            ];

            $rights[] = [
                'itemtype' => Resource::class,
                'label' => __('Associable items to a ticket'),
                'field' => 'plugin_resources_open_ticket',
                'rights' => [
                    READ => __('Read'),
                ]
            ];

            $rights[] = [
                'itemtype' => Resource::class,
                'label' => __('Display employee in core form'),
                'field' => 'plugin_resources_employee_core_form',
                'rights' => [
                    READ => __('Read'),
                ]
            ];
        }
        if (!$all) {
            $customRights = [];
            foreach ($rights as $right) {
                if (in_array($right['type'], $types)) {
                    $customRights[] = $right;
                }
            }

            return $customRights;
        }

        return $rights;
    }

    /**
     * Init profiles
     *
     **/

    public static function translateARight($old_right)
    {
        switch ($old_right) {
            case '':
                return 0;
            case 'r':
                return READ;
            case 'w':
                return ALLSTANDARDRIGHT + READNOTE + UPDATENOTE;
            case '0':
            case '1':
                return $old_right;

            default:
                return 0;
        }
    }


    /**
     * @param $profiles_id the profile ID
     * @since 0.85
     * Migration rights from old system to the new one for one profile
     */
    public static function migrateOneProfile($profiles_id)
    {
        global $DB;
        //Cannot launch migration if there's nothing to migrate...
        if (!$DB->tableExists('glpi_plugin_resources_profiles')) {
            return true;
        }

        $it = $DB->request([
            'FROM' => 'glpi_plugin_resources_profiles',
            'WHERE' => ['profiles_id' => $profiles_id]
        ]);
        foreach ($it as $profile_data) {
            $matching = [
                'resources' => 'plugin_resources',
                'task' => 'plugin_resources_task',
                'checklist' => 'plugin_resources_checklist',
                'employee' => 'plugin_resources_employee',
                'resting' => 'plugin_resources_resting',
                'holiday' => 'plugin_resources_holiday',
                'habilitation' => 'plugin_resources_habilitation',
                'employment' => 'plugin_resources_employment',
                'budget' => 'plugin_resources_budget',
                'dropdown_public' => 'plugin_resources_dropdown_public',
                'import' => 'plugin_resources_import',
                'open_ticket' => 'plugin_resources_open_ticket',
                'all' => 'plugin_resources_all'
            ];

            $current_rights = ProfileRight::getProfileRights($profiles_id, array_values($matching));
            foreach ($matching as $old => $new) {
                if (!isset($current_rights[$old])) {
                    $DB->update('glpi_profilerights', ['rights' => self::translateARight($profile_data[$old])], [
                        'name' => $new,
                        'profiles_id' => $profiles_id
                    ]);
                }
            }
        }
    }

    /**
     * Initialize profiles, and migrate it necessary
     */
    public static function initProfile()
    {
        global $DB;
        $profile = new self();
        $dbu = new DbUtils();
        //Add new rights in glpi_profilerights table
        foreach ($profile->getAllRights(true) as $data) {
            if ($dbu->countElementsInTable(
                    "glpi_profilerights",
                    ["name" => $data['field']]
                ) == 0) {
                ProfileRight::addProfileRights([$data['field']]);
            }
        }

        //Migration old rights in new ones
        $it = $DB->request([
            'SELECT' => ['id'],
            'FROM' => 'glpi_profiles'
        ]);
        foreach ($it as $prof) {
            self::migrateOneProfile($prof['id']);
        }
        $it = $DB->request([
            'FROM' => 'glpi_profilerights',
            'WHERE' => [
                'profiles_id' => $_SESSION['glpiactiveprofile']['id'],
                'name' => ['LIKE', '%plugin_resources%']
            ]
        ]);
        foreach ($it as $prof) {
            if (isset($_SESSION['glpiactiveprofile'])) {
                $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
            }
        }
    }

    public static function removeRightsFromSession()
    {
        foreach (self::getAllRights(true) as $right) {
            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }
    }
}
