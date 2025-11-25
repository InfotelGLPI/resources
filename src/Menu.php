<?php

/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2022 by the resources Development Team.

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

use CommonDBTM;
use Plugin;
use Session;
use Toolbox;

class Menu extends CommonDBTM
{
    public static $rightname = 'plugin_resources';

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return _n('Human resource', 'Human resources', $nb, 'resources');
    }

    public static function canView(): bool
    {
        if (static::$rightname) {
            return Session::haveRight(static::$rightname, READ);
        }
        return false;
    }

    public static function showMenuBlock($title, $actions)
    {
        echo "<div class='container'>";

        Wizard::WizardHeader($title);

        echo "<div class='row'>";

        foreach ($actions as $action => $labels) {

            echo "<div class='col-md-2 mb-2'>";

            echo "<div class='card' style='min-height: 140px;'>";
            echo "<div class='card-body'>";
            echo "<div class='card-text'>";

            echo "<a href='" . $labels['url'] . "'>";
            if (isset($labels['icon']) && !empty($labels['icon'])) {
                echo "<i class='" . $labels['icon'] . "' style='font-size: 3em'></i>";
            } else if (isset($labels['pics']) && !empty($labels['pics'])) {
                echo "<img src='" . $labels['pics'] . "'>";
            }
            echo "<br>" . $labels['title'] . "</a>";
            echo "</div>";
            echo "</div>";
            echo "</div>";

            echo "</div>";
        }

        echo "</div>";

        echo "</div>";
    }
    /**
     * Display menu
     */
    public static function showMenu(CommonDBTM $item)
    {

        echo "<div class='center'>";

        $canresting = Session::haveright('plugin_resources_resting', UPDATE);
        $canholiday = Session::haveright('plugin_resources_holiday', UPDATE);
        $canhabilitation = Session::haveright('plugin_resources_habilitation', UPDATE);
        $canemployment = Session::haveright('plugin_resources_employment', UPDATE);
        $canseeemployment = Session::haveright('plugin_resources_employment', READ);
        $canseebudget = Session::haveright('plugin_resources_budget', READ);
        $canbadges = Session::haveright('plugin_badges', READ) && Plugin::isPluginActive("badges");
        $canImport = Session::haveright('plugin_resources_import', READ);

        if ($item->canCreate()) {

            $config = new Config();
            if (!empty($config->fields["use_meta_for_changes"])
                && Plugin::isPluginActive('metademands')) {
                $url_change = PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?step=2&metademands_id=" . $config->fields["use_meta_for_changes"];
            } else {
                $url_change = PLUGIN_RESOURCES_WEBDIR . '/front/resource.change.php';
            }
            if (!empty($config->fields["use_meta_for_leave"])
                && Plugin::isPluginActive('metademands')) {
                $url_remove = PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?step=2&metademands_id=" . $config->fields["use_meta_for_leave"];
            } else {
                $url_remove = PLUGIN_RESOURCES_WEBDIR . '/front/resource.remove.php';
            }

            $actions = ['new' => ['pics' => PLUGIN_RESOURCES_WEBDIR . '/pics/newresource.png',
                                'title' => __('Declare an arrival','resources'),
                                 'url' => PLUGIN_RESOURCES_WEBDIR . '/front/wizard.form.php'
                                ],
                        'change' => ['pics' => PLUGIN_RESOURCES_WEBDIR . '/pics/newresource.png',
                            'title' => __('Declare a change','resources'),
                            'url' => $url_change
                        ],
                        'remove' => ['pics' => PLUGIN_RESOURCES_WEBDIR . '/pics/removeresource.png',
                            'title' => __('Declare a departure','resources'),
                            'url' => $url_remove
                        ]
                    ];

            self::showMenuBlock("", $actions);
        }

        $confighab = new ConfigHabilitation();
        if (!$confighab->getFromDBByCrit(['entities_id' => $_SESSION['glpiactive_entity'], 'action' => ConfigHabilitation::ACTION_ADD])) {
            $canhabilitation = false;
        }

        $configbadge = new ResourceBadge();
        if (!$configbadge->getFromDBByCrit(['entities_id' => $_SESSION['glpiactive_entity']])) {
            $canbadges = false;
        }

        $actions_declare = [];
        if ($canresting || $canholiday || $canbadges || $canhabilitation) {

            if ($canresting) {
                $actions_declare['resting'] = ['pics' => PLUGIN_RESOURCES_WEBDIR . "/pics/deleteresting.png",
                    'title' =>  _n(
                        'Non contract period management',
                        'Non contract periods management',
                        2,
                        'resources'
                    ),
                    'url' => PLUGIN_RESOURCES_WEBDIR . '/front/resourceresting.form.php?menu'
                ];
            }

            if ($canholiday) {
                $actions_declare['holiday'] = ['pics' => PLUGIN_RESOURCES_WEBDIR . "/pics/holidayresource.png",
                    'title' =>  __(
                        'Forced holiday management',
                        'resources'
                    ),
                    'url' => PLUGIN_RESOURCES_WEBDIR . '/front/resourceholiday.form.php?menu'
                ];
            }

            if ($canhabilitation && Plugin::isPluginActive("metademands")) {
                //Management of a super habilitation
                $actions_declare['habilitation'] = ['pics' => PLUGIN_RESOURCES_WEBDIR . "/pics/habilitation.png",
                    'title' =>  ConfigHabilitation::getTypeName(
                        1
                    ),
                    'url' => PLUGIN_RESOURCES_WEBDIR . '/front/confighabilitation.form.php?menu'
                ];
            }

            if ($canbadges && Plugin::isPluginActive("badges")) {
                $actions_declare['badge'] = ['pics' => PLUGIN_BADGES_WEBDIR . "/badges.png",
                    'title' =>  _n(
                        'Badge management',
                        'Badges management',
                        2,
                        'resources'
                    ),
                    'url' => PLUGIN_RESOURCES_WEBDIR . '/front/resourcebadge.form.php?menu'
                ];
            }

            $title_declare = __('Others declarations', 'resources');
            self::showMenuBlock($title_declare, $actions_declare);

        }

        if ($item->canView()) {

            $actions_others = ['search' => ['pics' => PLUGIN_RESOURCES_WEBDIR . '/pics/resourcelist.png',
                'title' => __('Search resources', 'resources'),
                'url' => PLUGIN_RESOURCES_WEBDIR . '/front/resource.php?reset=reset'
            ],
                'directory' => ['pics' => PLUGIN_RESOURCES_WEBDIR . '/pics/directory.png',
                    'title' => Directory::getTypeName(1),
                    'url' => PLUGIN_RESOURCES_WEBDIR . '/front/directory.php'
                ]
            ];

            $config = new Config();
            if (!$config->fields["hide_view_commercial_resource"]) {

                $opt = [];
                $opt['reset'] = 'reset';
                $opt['criteria'][0]['field'] = 27;
                $opt['criteria'][0]['searchtype'] = 'equals';
                $opt['criteria'][0]['value'] = Session::getLoginUserID();
                $opt['criteria'][0]['link'] = 'AND';

                $url_commercial = PLUGIN_RESOURCES_WEBDIR . "/front/resource.php?" . Toolbox::append_params($opt, '&amp;');

                $actions_others['commercial'] =  ['pics' => '',
                    'icon' => 'ti ti-tie',
                    'title' => __('View my resources as a commercial', 'resources'),
                    'url' => $url_commercial
                ];
            }

            $title = __('Others actions', 'resources');
            self::showMenuBlock($title, $actions_others);

        }

        if ($canseeemployment || $canseebudget) {

            $actions_employment = [];

            if ($canseeemployment) {
                if ($canemployment) {
                    //Add an employment
                    $actions_employment['new'] = ['pics' => PLUGIN_RESOURCES_WEBDIR . "/pics/employment.png",
                        'title' =>  __('Declare an employment', 'resources'),
                        'url' => PLUGIN_RESOURCES_WEBDIR . '/front/employment.form.php'
                    ];
                }
                $actions_employment['listemployment'] = ['pics' => PLUGIN_RESOURCES_WEBDIR . "/pics/employmentlist.png",
                    'title' =>  __('Employment management', 'resources'),
                    'url' => PLUGIN_RESOURCES_WEBDIR . '/front/employment.php'
                ];
            }

            if ($canseebudget) {
                $actions_employment['listbudget'] = ['pics' => PLUGIN_RESOURCES_WEBDIR . "/pics/budgetlist.png",
                    'title' =>  __('Budget management', 'resources'),
                    'url' => PLUGIN_RESOURCES_WEBDIR . '/front/budget.php'
                ];
            }

            if ($canseeemployment) {
                $actions_employment['recap'] = ['pics' => PLUGIN_RESOURCES_WEBDIR . "/pics/recap.png",
                    'title' =>  __('List Employments / Resources', 'resources'),
                    'url' => PLUGIN_RESOURCES_WEBDIR . '/front/recap.php'
                ];
            }

            $title_employment = __('Employments / budgets management', 'resources');
            self::showMenuBlock($title_employment, $actions_employment);

        }

        if ($canImport) {

            $actions_import = ['update' => ['pics' => '',
                'icon' => 'ti ti-user-edit',
                'title' =>  __('Update GLPI Resources', 'resources'),
                'url' => ImportResource::getIndexUrl() . "?type=" . ImportResource::UPDATE_RESOURCES
            ],
                'csv' => ['pics' => PLUGIN_RESOURCES_WEBDIR . "/pics/csv_check.png",
                    'title' => __('Verify CSV file', 'resources'),
                    'url' => ImportResource::getIndexUrl() . "?type=" . ImportResource::VERIFY_FILE
                ],
                'glpi' => ['pics' => PLUGIN_RESOURCES_WEBDIR . "/pics/resource_check.png",
                    'title' => __('Verify GLPI resources', 'resources'),
                    'url' => ImportResource::getIndexUrl() . "?type=" . ImportResource::VERIFY_GLPI
                ],
                'config' => ['pics' => '',
                    'icon' => 'ti ti-settings',
                    'title' => __('Configure Imports', 'resources'),
                    'url' => Import::getIndexUrl()
                ],
                'trash' => ['pics' => '',
                    'icon' => 'ti ti-trash',
                    'title' => __('Purge imported resources', 'resources'),
                    'url' => ImportResource::getFormURL() . "?reset-imports=1"
                ]
            ];

            $title_import = __('Import resources', 'resources');
            self::showMenuBlock($title_import, $actions_import);
        }

        echo "</div>";
    }

    /**
     * get menu content
     *
     * @return array array for menu
     **/
    public static function getMenuContent()
    {
        $plugin_page = PLUGIN_RESOURCES_WEBDIR . "/front/menu.php";

        $menu = [];
        //Menu entry in admin
        $menu['title'] = Resource::getTypeName(2);
        $menu['page'] = $plugin_page;
        $menu['links']['search'] = PLUGIN_RESOURCES_WEBDIR . "/front/resource.php";
        $menu['links']['lists'] = "";
        $menu['lists_itemtype'] = Resource::getType();
        if (Session::haveright("plugin_resources", CREATE)) {
            $menu['links']['add'] = PLUGIN_RESOURCES_WEBDIR . '/front/wizard.form.php';
            $menu['links']['template'] = PLUGIN_RESOURCES_WEBDIR . '/front/setup.templates.php?add=0';
        }

        // Resource directory
        $menu['links']["<i class='far fa-address-book fa-1x' title='" . __(
            'Directory',
            'resources'
        ) . "'></i>"] = PLUGIN_RESOURCES_WEBDIR . '/front/directory.php';

        // Resting
        if (Session::haveright("plugin_resources_resting", UPDATE)) {
            $menu['links']["<i class='ti ti-writing fa-1x' title='" . __(
                'List of non contract periods',
                'resources'
            ) . "'></i>"] = PLUGIN_RESOURCES_WEBDIR . '/front/resourceresting.php';
        }

        // Holiday
        if (Session::haveright("plugin_resources_holiday", UPDATE)) {
            $menu['links']["<i class='ti ti-device-imac-pause fa-1x' title='" . __(
                'List of forced holidays',
                'resources'
            ) . "'></i>"] = PLUGIN_RESOURCES_WEBDIR . '/front/resourceholiday.php';
        }

        // Employment
        if (Session::haveright("plugin_resources_employment", READ)) {
            $menu['links']["<i class='ti ti-list fa-1x' title='" . __(
                'Employment management',
                'resources'
            ) . "'></i>"] = PLUGIN_RESOURCES_WEBDIR . '/front/employment.php';
            $menu['links']["<i class='ti ti-buildings fa-1x' title='" . __(
                'List Employments / Resources',
                'resources'
            ) . "'></i>"] = PLUGIN_RESOURCES_WEBDIR . '/front/recap.php';
        }

        // Budget
        if (Session::haveright("plugin_resources_budget", READ)) {
            $menu['links']["<i class='ti ti-coins fa-1x' title='" . __(
                'Budget management',
                'resources'
            ) . "'></i>"] = PLUGIN_RESOURCES_WEBDIR . '/front/budget.php';
        }

        // Task
        if (Session::haveright("plugin_resources_task", READ)) {
            $menu['links']["<i class='ti ti-list-details fa-1x' title='" . __(
                'Tasks list',
                'resources'
            ) . "'></i>"] = PLUGIN_RESOURCES_WEBDIR . '/front/task.php';
        }

        // Checklist
        if (Session::haveright("plugin_resources_checklist", READ)) {
            $menu['links']["<i class='far fa-calendar-check fa-1x' title='" . _n(
                'Checklist',
                'Checklists',
                2,
                'resources'
            ) . "'></i>"] = PLUGIN_RESOURCES_WEBDIR . '/front/checklistconfig.php';
        }

        $opt = [];
        $opt['reset'] = 'reset';
        $opt['criteria'][0]['field'] = 27; // validation status
        $opt['criteria'][0]['searchtype'] = 'equals';
        $opt['criteria'][0]['value'] = Session::getLoginUserID();
        $opt['criteria'][0]['link'] = 'AND';

        $url = PLUGIN_RESOURCES_WEBDIR . "/front/resource.php?" . Toolbox::append_params($opt, '&amp;');

        $menu['links']["<i class='ti ti-tie fa-1x' title='" . __(
            'View my resources as a commercial',
            'resources'
        ) . "'></i>"] = $url;

        // Import page
        if (Session::haveRight('plugin_resources_import', READ)) {
            $menu['links']["<i class='ti ti-settings fa-1x' title='" . __('Import configuration', 'resources') . "'></i>"]
                = PLUGIN_RESOURCES_WEBDIR . '/front/import.php';
        }

        // Config page
        if (Session::haveRight("config", UPDATE)) {
            $menu['links']['config'] = PLUGIN_RESOURCES_WEBDIR . '/front/config.form.php';
        }

        // Add menu to class
        $menu = Budget::getMenuOptions($menu);
        $menu = Checklist::getMenuOptions($menu);
        $menu = Employment::getMenuOptions($menu);

        $menu['icon'] = Resource::getIcon();

        return $menu;
    }
}
