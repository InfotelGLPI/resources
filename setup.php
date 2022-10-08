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

define('PLUGIN_RESOURCES_VERSION', '3.0.4');

if (!defined("PLUGIN_RESOURCES_DIR")) {
   define("PLUGIN_RESOURCES_DIR", Plugin::getPhpDir("resources"));
   define("PLUGIN_RESOURCES_NOTFULL_DIR", Plugin::getPhpDir("resources",false));
   define("PLUGIN_RESOURCES_WEBDIR", Plugin::getWebDir("resources"));
   define("PLUGIN_RESOURCES_NOTFULL_WEBDIR", Plugin::getWebDir("resources",false));
}

// Init the hooks of the plugins -Needed
function plugin_init_resources() {
   global $PLUGIN_HOOKS;

   // add autoload for vendor
   include_once(PLUGIN_RESOURCES_DIR . "/vendor/autoload.php");

   $PLUGIN_HOOKS['csrf_compliant']['resources']   = true;
   $PLUGIN_HOOKS['change_profile']['resources']   = [PluginResourcesProfile::class, 'initProfile'];
   $PLUGIN_HOOKS['assign_to_ticket']['resources'] = true;

   if (Session::getLoginUserID()) {

      $PLUGIN_HOOKS['pre_item_form']['resources']  = [PluginResourcesLinkAd::class, 'messageSolution'];
      $PLUGIN_HOOKS['post_item_form']['resources'] = [PluginResourcesLinkAd::class, 'deleteButtton'];
      $noupdate                                    = false;
      if (Session::getCurrentInterface() != 'central') {
         $noupdate = true;
      }

      Plugin::registerClass(PluginResourcesResource::class, [
//         'linkuser_types'               => true,
         'document_types'               => true,
         'ticket_types'                 => true,
         'helpdesk_visible_types'       => true,
         'notificationtemplates_types'  => true,
         'unicity_types'                => true,
//         'massiveaction_nodelete_types' => $noupdate,
//         'massiveaction_noupdate_types' => $noupdate
      ]);

      Plugin::registerClass(PluginResourcesDirectory::class, [
//         'massiveaction_nodelete_types' => true,
//         'massiveaction_noupdate_types' => true
      ]);

      Plugin::registerClass(PluginResourcesRecap::class, [
//         'massiveaction_nodelete_types' => true,
//         'massiveaction_noupdate_types' => true
      ]);

      Plugin::registerClass(PluginResourcesTaskPlanning::class, [
         'planning_types' => true
      ]);

      Plugin::registerClass(PluginResourcesRuleChecklistCollection::class, [
         'rulecollections_types' => true

      ]);

      Plugin::registerClass(PluginResourcesRuleContracttypeCollection::class, [
         'rulecollections_types' => true

      ]);
      Plugin::registerClass(PluginResourcesRuleContracttypeHiddenCollection::class, [
         'rulecollections_types' => true

      ]);

      Plugin::registerClass(PluginResourcesProfile::class,
                            ['addtabon' => 'Profile']);

      Plugin::registerClass(PluginResourcesEmployment::class, [
//         'massiveaction_nodelete_types' => true
      ]);

      if (Session::haveRight("plugin_servicecatalog", READ)
          || Session::haveright("plugin_servicecatalog_setup", UPDATE)) {
         $PLUGIN_HOOKS['servicecatalog']['resources'] = ['PluginResourcesServicecatalog'];
      }

      if ((Session::haveRight("plugin_resources", READ)
           || Session::haveright("plugin_resources_employee", UPDATE))) {
         $PLUGIN_HOOKS['helpdesk_menu_entry']['resources'] = PLUGIN_RESOURCES_NOTFULL_DIR.'/front/menu.php';
         $PLUGIN_HOOKS['helpdesk_menu_entry_icon']['resources'] = PluginResourcesResource::getIcon();
      }

      if (Session::haveright("plugin_resources_checklist", READ)
          && class_exists('PluginMydashboardMenu')
      ) {
         $PLUGIN_HOOKS['mydashboard']['resources'] = ["PluginResourcesDashboard"];
      }

      if (class_exists('PluginPositionsPosition')) {
         PluginPositionsPosition::registerType('PluginResourcesResource');
         //$PLUGIN_HOOKS['plugin_positions']['PluginResourcesResource']='plugin_resources_positions_pics';
      }

      if (class_exists('PluginBehaviorsCommon')) {
         PluginBehaviorsCommon::addCloneType(PluginResourcesRuleChecklist::class, 'PluginBehaviorsRule');
         PluginBehaviorsCommon::addCloneType(PluginResourcesRuleContracttype::class, 'PluginBehaviorsRule');
         PluginBehaviorsCommon::addCloneType(PluginResourcesRuleContracttypeHidden::class, 'PluginBehaviorsRule'); // TODO Confirm usefull
      }

      if (class_exists('PluginTreeviewConfig')) {
         PluginTreeviewConfig::registerType(PluginResourcesResource::class);
         $PLUGIN_HOOKS['treeview']['PluginResourcesResource'] = '../resources/pics/miniresources.png';
         $PLUGIN_HOOKS['treeview_params']['resources']        = [PluginResourcesResource::class, 'showResourceTreeview'];
      }

      if ((Session::haveRight("plugin_resources", READ)
           || Session::haveright("plugin_resources_employee", UPDATE))) {
         $PLUGIN_HOOKS['menu_toadd']['resources'] = ['admin' => 'PluginResourcesMenu'];
      }
      Plugin::registerClass(PluginResourcesLinkAd::class, ['addtabon' => 'Ticket']);
      // Resource menu
      if (Session::haveRight("plugin_resources", READ)
          || Session::haveright("plugin_resources_employee", UPDATE)) {
         $PLUGIN_HOOKS['redirect_page']['resources'] = PLUGIN_RESOURCES_NOTFULL_DIR."/front/resource.form.php";
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
         $PLUGIN_HOOKS['add_css']['resources']        = ["css/resources.css"];
         $PLUGIN_HOOKS['add_javascript']['resources'] = ["resources.js",
                                                         "lib/plugins/jquery.address.js",
                                                         "lib/plugins/jquery.mousewheel.js",
                                                         "lib/plugins/jquery.scroll.js",
         ];

         if (strpos($_SERVER['REQUEST_URI'], "resource.card.form.php") !== false) {
            $PLUGIN_HOOKS['add_javascript']['resources'][] = "lib/resources_card.js";
            $PLUGIN_HOOKS['add_css']['resources'][]        = "css/resourcecard.css";
         }
      }


      //TODO : Check
      $PLUGIN_HOOKS['plugin_pdf']['PluginResourcesResource'] = 'PluginResourcesResourcePDF';

      //Clean Plugin on Profile delete
      if (class_exists('PluginResourcesResource_Item')) { // only if plugin activated
         $PLUGIN_HOOKS['pre_item_purge']['resources']                = ['PluginResourcesResource' => ['PluginResourcesNotification', 'purgeNotification']];
         $PLUGIN_HOOKS['plugin_datainjection_populate']['resources'] = 'plugin_datainjection_populate_resources';
      }

      //planning action
      $PLUGIN_HOOKS['planning_populate']['resources'] = ['PluginResourcesTaskPlanning', 'populatePlanning'];
      $PLUGIN_HOOKS['display_planning']['resources']  = ['PluginResourcesTaskPlanning', 'displayPlanningItem'];
      $PLUGIN_HOOKS['migratetypes']['resources']      = 'plugin_datainjection_migratetypes_resources';

      $PLUGIN_HOOKS['metademands']['resources'] = ['PluginResourcesMetademand'];
   }


   // End init, when all types are registered
   $PLUGIN_HOOKS['post_init']['resources'] = 'plugin_resources_postinit';

}

// Get the name and the version of the plugin - Needed
/**
 * @return array
 */
function plugin_version_resources() {

   return [
      'name'         => _n('Human Resource', 'Human Resources', 2, 'resources'),
      'version'      => PLUGIN_RESOURCES_VERSION,
      'license'      => 'GPLv2+',
      'author'       => "<a href='http://infotel.com/services/expertise-technique/glpi/'>Infotel</a>",
      'homepage'     => 'https://github.com/InfotelGLPI/resources',
      'requirements' => [
         'glpi' => [
            'min' => '10.0',
            'max' => '11.0',
            'dev' => false
         ]
      ]
   ];
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
/**
 * @return bool
 */
/**
 * @return bool
 */
function plugin_resources_check_prerequisites() {

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
function plugin_datainjection_migratetypes_resources($types) {
   $types[4300] = PluginResourcesResource::class;
   return $types;
}

