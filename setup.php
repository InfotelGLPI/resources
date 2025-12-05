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

use Glpi\Plugin\Hooks;
use GlpiPlugin\Behaviors\Common;
use GlpiPlugin\Behaviors\Rule;
use GlpiPlugin\Mydashboard\Menu as DashboardMenu;
use GlpiPlugin\Positions\Position;
use GlpiPlugin\Resources\Dashboard;
use GlpiPlugin\Resources\Directory;
use GlpiPlugin\Resources\Employment;
use GlpiPlugin\Resources\LinkAd;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Metademand;
use GlpiPlugin\Resources\Notification;
use GlpiPlugin\Resources\Profile;
use GlpiPlugin\Resources\Recap;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Resource_Item;
use GlpiPlugin\Resources\ResourcePDF;
use GlpiPlugin\Resources\RuleChecklist;
use GlpiPlugin\Resources\RuleChecklistCollection;
use GlpiPlugin\Resources\RuleContracttype;
use GlpiPlugin\Resources\RuleContracttypeCollection;
use GlpiPlugin\Resources\RuleContracttypeHidden;
use GlpiPlugin\Resources\RuleContracttypeHiddenCollection;
use GlpiPlugin\Resources\Servicecatalog;
use GlpiPlugin\Resources\TaskPlanning;

define('PLUGIN_RESOURCES_VERSION', '4.0.4');

global $CFG_GLPI;

if (!defined("PLUGIN_RESOURCES_DIR")) {
    define("PLUGIN_RESOURCES_DIR", Plugin::getPhpDir("resources"));
    $root = $CFG_GLPI['root_doc'] . '/plugins/resources';
    define("PLUGIN_RESOURCES_WEBDIR", $root);
}

// Init the hooks of the plugins -Needed
function plugin_init_resources()
{
    global $PLUGIN_HOOKS;

    // add autoload for vendor
    include_once(PLUGIN_RESOURCES_DIR . "/vendor/autoload.php");

    $PLUGIN_HOOKS['csrf_compliant']['resources'] = true;
    $PLUGIN_HOOKS['change_profile']['resources'] = [Profile::class, 'initProfile'];
    $PLUGIN_HOOKS['assign_to_ticket']['resources'] = true;

    if (Session::getLoginUserID()) {
        $PLUGIN_HOOKS['pre_item_form']['resources'] = [LinkAd::class, 'messageSolution'];
        $PLUGIN_HOOKS['post_item_form']['resources'] = [LinkAd::class, 'deleteButtton'];
        $noupdate = false;
        if (Session::getCurrentInterface() != 'central') {
            $noupdate = true;
        }

        Plugin::registerClass(Resource::class, [
            //         'linkuser_types'               => true,
            'document_types' => true,
            'ticket_types' => true,
            'helpdesk_visible_types' => true,
            'notificationtemplates_types' => true,
            'unicity_types' => true,
            //         'massiveaction_nodelete_types' => $noupdate,
            //         'massiveaction_noupdate_types' => $noupdate
        ]);

        Plugin::registerClass(Directory::class, [
            //         'massiveaction_nodelete_types' => true,
            //         'massiveaction_noupdate_types' => true
        ]);

        Plugin::registerClass(Recap::class, [
            //         'massiveaction_nodelete_types' => true,
            //         'massiveaction_noupdate_types' => true
        ]);

        Plugin::registerClass(TaskPlanning::class, [
            'planning_types' => true,
        ]);

        Plugin::registerClass(RuleChecklistCollection::class, [
            'rulecollections_types' => true,

        ]);

        Plugin::registerClass(RuleContracttypeCollection::class, [
            'rulecollections_types' => true,

        ]);
        Plugin::registerClass(RuleContracttypeHiddenCollection::class, [
            'rulecollections_types' => true,

        ]);

        Plugin::registerClass(
            Profile::class,
            ['addtabon' => 'Profile']
        );

        Plugin::registerClass(Employment::class, [
            //         'massiveaction_nodelete_types' => true
        ]);

        if (Session::haveRight("plugin_servicecatalog", READ)
            || Session::haveright("plugin_servicecatalog_setup", UPDATE)) {
            $PLUGIN_HOOKS['servicecatalog']['resources'] = [Servicecatalog::class];
        }

        if ((Session::haveRight("plugin_resources", READ)
            || Session::haveright("plugin_resources_employee", UPDATE))) {
            $PLUGIN_HOOKS['helpdesk_menu_entry']['resources'] = PLUGIN_RESOURCES_WEBDIR . '/front/menu.php';
            $PLUGIN_HOOKS['helpdesk_menu_entry_icon']['resources'] = Resource::getIcon();
        }

        if (Session::haveright("plugin_resources_checklist", READ)
            && class_exists(DashboardMenu::class)
        ) {
            $PLUGIN_HOOKS['mydashboard']['resources'] = [Dashboard::class];
        }

        if (class_exists(Position::class)) {
            Position::registerType(Resource::class);
        }

        if (class_exists(Common::class)) {
            Common::addCloneType(RuleChecklist::class, Rule::class);
            Common::addCloneType(RuleContracttype::class, Rule::class);
            Common::addCloneType(RuleContracttypeHidden::class, Rule::class); // TODO Confirm usefull
        }

        if (class_exists('PluginTreeviewConfig')) {
            PluginTreeviewConfig::registerType(Resource::class);
            $PLUGIN_HOOKS['treeview'][Resource::class] = '../resources/pics/miniresources.png';
            $PLUGIN_HOOKS['treeview_params']['resources'] = [Resource::class, 'showResourceTreeview'];
        }

        if ((Session::haveRight("plugin_resources", READ)
            || Session::haveright("plugin_resources_employee", UPDATE))) {
            $PLUGIN_HOOKS['menu_toadd']['resources'] = ['admin' => Menu::class];
        }
        Plugin::registerClass(LinkAd::class, ['addtabon' => 'Ticket']);
        // Resource menu
        if (Session::haveRight("plugin_resources", READ)
            || Session::haveright("plugin_resources_employee", UPDATE)) {
            $PLUGIN_HOOKS['redirect_page']['resources'] = PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php";
        }

        //
        $PLUGIN_HOOKS['use_massive_action']['resources'] = true;
        //      }

        // Config
        if (Session::haveRight("config", UPDATE)) {
            $PLUGIN_HOOKS['config_page']['resources'] = 'front/config.form.php';
        }

        // Add specific files to add to the header : javascript or css
        if (Session::haveRight("plugin_resources", READ)) {
            $PLUGIN_HOOKS[Hooks::ADD_CSS]['resources'] = ["css/resources.css"];
            $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['resources'] = [
                "resources.js",
                "lib/plugins/jquery.address.js",
                "lib/plugins/jquery.mousewheel.js",
                "lib/plugins/jquery.scroll.js",
            ];

            //            if (strpos($_SERVER['REQUEST_URI'], "resource.card.form.php") !== false) {
            //                $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['resources'][] = "lib/resources_card.js";
            //                $PLUGIN_HOOKS[Hooks::ADD_CSS]['resources'][] = "css/resourcecard.css";
            //            }
        }


        //TODO : Check
        $PLUGIN_HOOKS['plugin_pdf'][Resource::class] = ResourcePDF::class;

        //Clean Plugin on Profile delete
        if (class_exists(Resource_Item::class)) { // only if plugin activated
            $PLUGIN_HOOKS['pre_item_purge']['resources'] = [
                Resource::class => [
                    Notification::class,
                    'purgeNotification',
                ],
            ];
            $PLUGIN_HOOKS['plugin_datainjection_populate']['resources'] = 'plugin_datainjection_populate_resources';
        }

        //planning action
        $PLUGIN_HOOKS['planning_populate']['resources'] = [TaskPlanning::class, 'populatePlanning'];
        $PLUGIN_HOOKS['display_planning']['resources'] = [TaskPlanning::class, 'displayPlanningItem'];
        $PLUGIN_HOOKS['migratetypes']['resources'] = 'plugin_datainjection_migratetypes_resources';

        $PLUGIN_HOOKS['metademands']['resources'] = [Metademand::class];
    }


    // End init, when all types are registered
    $PLUGIN_HOOKS['post_init']['resources'] = 'plugin_resources_postinit';
}

// Get the name and the version of the plugin - Needed
/**
 * @return array
 */
function plugin_version_resources()
{
    return [
        'name' => _n('Human Resource', 'Human Resources', 2, 'resources'),
        'version' => PLUGIN_RESOURCES_VERSION,
        'license' => 'GPLv2+',
        'author' => "<a href='https://blogglpi.infotel.com'>Infotel</a>, Xavier CAILLAUD",
        'homepage' => 'https://github.com/InfotelGLPI/resources',
        'requirements' => [
            'glpi' => [
                'min' => '11.0',
                'max' => '12.0',
                'dev' => false,
            ],
        ],
    ];
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
/**
 * @return bool
 */
/**
 * @return bool
 */
function plugin_resources_check_prerequisites()
{
    if (!is_readable(__DIR__ . '/vendor/autoload.php') || !is_file(__DIR__ . '/vendor/autoload.php')) {
        echo "Run composer install --no-dev in the plugin directory<br>";
        return false;
    }

    return true;
}

/**
 * @param $types
 *
 * @return mixed
 */
function plugin_datainjection_migratetypes_resources($types)
{
    $types[4300] = Resource::class;
    return $types;
}
