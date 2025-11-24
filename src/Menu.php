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
use Html;
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

    /**
     * Display menu
     */
    public static function showMenu(CommonDBTM $item)
    {
        global $CFG_GLPI;

//        echo Html::css(PLUGIN_RESOURCES_WEBDIR . "/css/style_bootstrap_main.css");
//        echo Html::css(PLUGIN_RESOURCES_WEBDIR . "/css/style_bootstrap_ticket.css");

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
            echo "<h3><div class='alert alert-secondary' role='alert'>";
            echo "<i class='ti ti-friends'></i>&nbsp;";
            echo __('Resources management', 'resources');
            echo "</div></h3>";

            echo "<table class='tab_cadre_fixe resources_menu' style='width: 400px;'>";

            echo "<tr class=''>";

            //Add a resource
            echo "<td class=' center' colspan='2' width='200'>";
            echo "<a href=\"./wizard.form.php\">";
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png' alt='" . __(
                    'Declare an arrival',
                    'resources'
                ) . "'>";
            echo "<br>" . __('Declare an arrival', 'resources') . "</a>";
            echo "</td>";

            //Add a change
            echo "<td class=' center' colspan='2'  width='200'>";
            $config = new Config();
            if (!empty($config->fields["use_meta_for_changes"]) && Plugin::isPluginActive('metademands')) {
                $url = PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?step=2&metademands_id=" . $config->fields["use_meta_for_changes"];
                echo "<a href=\"" . $url . "\">";
            } else {
                echo "<a href=\"./resource.change.php\">";
            }
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/recap.png' alt='" . __(
                    'Declare a change',
                    'resources'
                ) . "'>";
            echo "<br>" . __('Declare a change', 'resources') . "</a>";
            echo "</td>";

            //Remove resources
            echo "<td class=' center' colspan='2'  width='200'>";
            if (!empty($config->fields["use_meta_for_leave"]) && Plugin::isPluginActive('metademands')) {
                $url = PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?step=2&metademands_id=" . $config->fields["use_meta_for_leave"];
                echo "<a href=\"" . $url . "\">";
            } else {
                echo "<a href=\"./resource.remove.php\">";
            }
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/removeresource.png' alt='" . __(
                    'Declare a departure',
                    'resources'
                ) . "'>";
            echo "<br>" . __('Declare a departure', 'resources') . "</a>";
            echo "</td>";

            echo "</tr>";
            echo " </table>";
        }

        if ($canresting || $canholiday || $canbadges || $canhabilitation) {
            echo "<br><h3><div class='alert alert-secondary' role='alert'>";
            echo "<i class='ti ti-friends'></i>&nbsp;";
            echo __('Others declarations', 'resources');
            echo "</div></h3>";

            echo "<table class='tab_cadre_fixe resources_menu' style='width: 400px;'>";

            $num_col = 0;
            if ($canresting) {
                $num_col += 1;
            }
            if ($canholiday) {
                $num_col += 1;
            }
            if ($canhabilitation && Plugin::isPluginActive("metademands")) {
                $num_col += 1;
            }
            if ($canbadges && Plugin::isPluginActive("badges")) {
                $num_col += 1;
            }
            if ($num_col == 0) {
                $colspan = 0;
            } else {
                $colspan = floor(6 / $num_col);
            }

            echo "<tr class=''>";
            if ($colspan == 1) {
                echo "<td></td>";
            }
            if ($canresting) {
                //Management of a non contract period
                echo "<td colspan=$colspan class=' center'>";
                echo "<a href=\"./resourceresting.form.php?menu\">";
                echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/deleteresting.png' alt='" . _n(
                        'Non contract period management',
                        'Non contract periods management',
                        2,
                        'resources'
                    ) . "'>";
                echo "<br>" . _n(
                        'Non contract period management',
                        'Non contract periods management',
                        2,
                        'resources'
                    ) . "</a>";
                echo "</td>";
            }

            if ($canholiday) {
                //Management of a non contract period
                echo "<td colspan=$colspan class=' center'>";
                echo "<a href=\"./resourceholiday.form.php?menu\">";
                echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/holidayresource.png' alt='" . __(
                        'Forced holiday management',
                        'resources'
                    ) . "'>";
                echo "<br>" . __('Forced holiday management', 'resources') . "</a>";
                echo "</td>";
            }

            if ($canhabilitation && Plugin::isPluginActive("metademands")) {
                //Management of a super habilitation
                echo "<td colspan=$colspan class=' center'>";
                echo "<a href=\"./confighabilitation.form.php?menu\">";
                echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/habilitation.png' alt='" . ConfigHabilitation::getTypeName(
                        1
                    ) . "'>";
                echo "<br>" . ConfigHabilitation::getTypeName(1) . "</a>";
                echo "</td>";
            }

            if ($canbadges && Plugin::isPluginActive("badges")) {
                //Management of a non contract period
                echo "<td colspan=$colspan class=' center'>";
                echo "<a href=\"./resourcebadge.form.php?menu\">";
                echo "<img src='" . PLUGIN_BADGES_WEBDIR . "/badges.png' alt='" . _n(
                        'Badge management',
                        'Badges management',
                        2,
                        'resources'
                    ) . "'>";
                echo "<br>" . _n('Badge management', 'Badges management', 2, 'resources') . "</a>";
                echo "</td>";
            }
            if ($colspan == 1) {
                echo "<td></td>";
            }
            echo "</tr>";
            echo " </table>";
        }

        if ($item->canView()) {
            echo "<br><h3><div class='alert alert-secondary' role='alert'>";
            echo "<i class='ti ti-friends'></i>&nbsp;";
            echo __('Others actions', 'resources');
            echo "</div></h3>";

            echo "<table class='tab_cadre_fixe resources_menu' style='width: 400px;'>";

            echo "<tr class=''>";

            $opt = [];
            $opt['reset'] = 'reset';
            $opt['criteria'][0]['field'] = 27;
            $opt['criteria'][0]['searchtype'] = 'equals';
            $opt['criteria'][0]['value'] = Session::getLoginUserID();
            $opt['criteria'][0]['link'] = 'AND';

            $url = PLUGIN_RESOURCES_WEBDIR . "/front/resource.php?" . Toolbox::append_params($opt, '&amp;');
            $config = new Config();
            if (!$config->fields["hide_view_commercial_resource"]) {
                echo "<td class=' center'>";
                echo "<a href=\"$url\">";
                echo "<i class='ti ti-tie' style='font-size:4em' title='" . __(
                        'View my resources as a commercial',
                        'resources'
                    ) . "'></i>";
                echo "<br>" . __('View my resources as a commercial', 'resources') . "</a>";
                echo "</td>";
            }

            //See resources
            echo "<td class=' center'>";
            echo "<a href=\"./resource.php?reset=reset\">";
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/resourcelist.png' alt='" . __(
                    'Search resources',
                    'resources'
                ) . "'>";
            echo "<br>" . __('Search resources', 'resources') . "</a>";
            echo "</td>";

            //         echo "<td class=' center'>";
            //         echo "<a href=\"./resource.card.form.php\">";
            //         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/detailresource.png' alt='" . __('See details of a resource', 'resources') . "'>";
            //         echo "<br>" . __('See details of a resource', 'resources') . "</a>";
            //         echo "</td>";

            echo "<td class=' center'>";
            echo "<a href=\"./directory.php\">";
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/directory.png' alt='" . Directory::getTypeName(
                    1
                ) . "'>";
            echo "<br>" . Directory::getTypeName(1) . "</a>";
            echo "</td>";

            echo "</tr>";
            echo " </table>";
        }

        if ($canseeemployment || $canseebudget) {
            $colspan = 0;

            echo "<br><h3><div class='alert alert-secondary' role='alert'>";
            echo "<i class='ti ti-friends'></i>&nbsp;";
            echo __('Employments / budgets management', 'resources');
            echo "</div></h3>";

            echo "<table class='tab_cadre_fixe resources_menu' style='width: 400px;'>";

            echo "<tr class=''>";
            echo "<td class='center'>";
            echo "</td>";

            if ($canseeemployment) {
                if ($canemployment) {
                    //Add an employment
                    echo "<td class=' center'>";
                    echo "<a href=\"./employment.form.php\">";
                    echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/employment.png' alt='" . __(
                            'Declare an employment',
                            'resources'
                        ) . "'>";
                    echo "<br>" . __('Declare an employment', 'resources') . "</a>";
                    echo "</td>";
                } else {
                    $colspan += 1;
                }
                //See managment employments
                echo "<td class=' center'>";
                echo "<a href=\"./employment.php\">";
                echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/employmentlist.png' alt='" . __(
                        'Employment management',
                        'resources'
                    ) . "'>";
                echo "<br>" . __('Employment management', 'resources') . "</a>";
                echo "</td>";
            } else {
                $colspan += 1;
            }
            if ($canseebudget) {
                //See managment budgets
                echo "<td class=' center'>";
                echo "<a href=\"./budget.php\">";
                echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/budgetlist.png' alt='" . __(
                        'Budget management',
                        'resources'
                    ) . "'>";
                echo "<br>" . __('Budget management', 'resources') . "</a>";
                echo "</td>";
            } else {
                $colspan += 1;
            }

            if ($canseeemployment) {
                //See recap ressource / employment
                echo "<td class=' center'>";
                echo "<a href=\"./recap.php\">";
                echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/recap.png' alt='" . __(
                        'List Employments / Resources',
                        'resources'
                    ) . "'>";
                echo "<br>" . __('List Employments / Resources', 'resources') . "</a>";
                echo "</td>";
            } else {
                $colspan += 1;
            }

            echo "<td class='center' colspan='" . ($colspan + 1) . "'></td>";

            echo "</tr>";
            echo " </table>";
        }

        if ($canImport) {
            echo "<br><h3><div class='alert alert-secondary' role='alert'>";
            echo "<i class='ti ti-friends'></i>&nbsp;";
            echo __('Import resources', 'resources');
            echo "</div></h3>";

            echo "<table class='tab_cadre_fixe resources_menu' style='width: 400px;'>";

            echo "<tr class=''>";
            echo "<td class=' center' colspan='2'>";
            echo "<a href='" . ImportResource::getIndexUrl() . "?type=" . ImportResource::UPDATE_RESOURCES . "'>";
            echo "<i class=\"ti ti-user-edit\" style='font-size: 4em'></i>";
            echo "<br>" . __('Update GLPI Resources', 'resources') . "</a>";
            echo "</td>";

            echo "<td class=' center' colspan='2'>";
            echo "<a href='" . ImportResource::getIndexUrl() . "?type=" . ImportResource::VERIFY_FILE . "'>";
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/csv_check.png' />";
            echo "<br>" . __('Verify CSV file', 'resources') . "</a>";
            echo "</td>";

            echo "<td class=' center' colspan='2'>";
            echo "<a href='" . ImportResource::getIndexUrl() . "?type=" . ImportResource::VERIFY_GLPI . "'>";
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/resource_check.png' />";
            echo "<br>" . __('Verify GLPI resources', 'resources') . "</a>";
            echo "</td>";

            echo "</tr>";

            echo "<tr class=''>";

            echo "<td class=' center' colspan='2'>";
            echo "<a href='" . Import::getIndexUrl() . "'>";
            echo "<i class=\"ti ti-settings\" style='font-size: 3em'></i>";
            echo "<br>" . __('Configure Imports', 'resources') . "</a>";
            echo "</td>";

            echo "<td class=' center' colspan='2'>";
            echo "<a href='" . ImportResource::getFormURL() . "?reset-imports=1'>";
            echo "<i class=\"ti ti-trash\" style='font-size: 3em'></i>";
            echo "<br>" . __('Purge imported resources', 'resources') . "</a>";
            echo "</td>";

            echo "<td colspan='2'></td>";

            echo "</tr>";

            echo " </table>";
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
