<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2016 by the resources Development Team.

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

// Init the hooks of the plugins -Needed
function plugin_init_resources() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['resources']   = true;
   $PLUGIN_HOOKS['change_profile']['resources']   = ['PluginResourcesProfile', 'initProfile'];
   $PLUGIN_HOOKS['assign_to_ticket']['resources'] = true;

   if (Session::getLoginUserID()) {

      $noupdate = false;
      if (Session::getCurrentInterface() != 'central') {
         $noupdate = true;
      }

      Plugin::registerClass('PluginResourcesResource', [
         'linkuser_types'               => true,
         'document_types'               => true,
         'ticket_types'                 => true,
         'helpdesk_visible_types'       => true,
         'notificationtemplates_types'  => true,
         'unicity_types'                => true,
         'massiveaction_nodelete_types' => $noupdate,
         'massiveaction_noupdate_types' => $noupdate
      ]);

      Plugin::registerClass('PluginResourcesDirectory', [
         'massiveaction_nodelete_types' => true,
         'massiveaction_noupdate_types' => true
      ]);

      Plugin::registerClass('PluginResourcesRecap', [
         'massiveaction_nodelete_types' => true,
         'massiveaction_noupdate_types' => true
      ]);

      Plugin::registerClass('PluginResourcesTaskPlanning', [
         'planning_types' => true
      ]);

      Plugin::registerClass('PluginResourcesRuleChecklistCollection', [
         'rulecollections_types' => true

      ]);

      Plugin::registerClass('PluginResourcesRuleContracttypeCollection', [
         'rulecollections_types' => true

      ]);

      Plugin::registerClass('PluginResourcesProfile',
                            ['addtabon' => 'Profile']);

      Plugin::registerClass('PluginResourcesEmployment', [
         'massiveaction_nodelete_types' => true]);

      if (class_exists('PluginServicecatalogDashboard')) {
         $PLUGIN_HOOKS['servicecatalog']['resources'] = ['PluginResourcesServicecatalog'];
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
         PluginBehaviorsCommon::addCloneType('PluginResourcesRuleChecklist', 'PluginBehaviorsRule');
         PluginBehaviorsCommon::addCloneType('PluginResourcesRuleContracttype', 'PluginBehaviorsRule');
      }

      if (class_exists('PluginTreeviewConfig')) {
         PluginTreeviewConfig::registerType('PluginResourcesResource');
         $PLUGIN_HOOKS['treeview']['PluginResourcesResource'] = '../resources/pics/miniresources.png';
         $PLUGIN_HOOKS['treeview_params']['resources']        = ['PluginResourcesResource', 'showResourceTreeview'];
      }

      if (((Session::haveRight("plugin_resources", READ)
           || Session::haveright("plugin_resources_employee", UPDATE))
              && !class_exists('PluginServicecatalogDashboard'))
          || (class_exists('PluginServicecatalogDashboard')
              && !Session::haveRight("plugin_servicecatalog", READ))) {
         $PLUGIN_HOOKS['menu_toadd']['resources'] = ['admin' => 'PluginResourcesResource'];
      }
      // Resource menu
      if (Session::haveRight("plugin_resources", READ) || Session::haveright("plugin_resources_employee", UPDATE)) {

         $PLUGIN_HOOKS['redirect_page']['resources'] = "front/resource.form.php";
      }

      if (((Session::haveRight("plugin_resources", READ)
           || Session::haveright("plugin_resources_employee", UPDATE))
              && !class_exists('PluginServicecatalogDashboard'))
          || (class_exists('PluginServicecatalogDashboard')
              && !Session::haveRight("plugin_servicecatalog", READ))
      ) {
         $PLUGIN_HOOKS['helpdesk_menu_entry']['resources'] = '/front/menu.php';
      }

      //
      $PLUGIN_HOOKS['use_massive_action']['resources'] = true;
      //      }

      // Config
      if (Session::haveRight("config", UPDATE)) {
         $PLUGIN_HOOKS['config_page']['resources'] = 'front/config.form.php';
      }

      // Add specific files to add to the header : javascript or css
      $PLUGIN_HOOKS['add_css']['resources']        = ["resources.css"];
      $PLUGIN_HOOKS['add_javascript']['resources'] = ["resources.js",
                                                           "lib/plugins/jquery.address.js",
                                                           "lib/plugins/jquery.mousewheel.js",
                                                           "lib/plugins/jquery.scroll.js",
                                                           "lib/resources_card.js",
      ];

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

   }
   // End init, when all types are registered
   $PLUGIN_HOOKS['post_init']['resources'] = 'plugin_resources_postinit';

}

// Get the name and the version of the plugin - Needed

/**
 * @return array
 */
/**
 * @return array
 */
function plugin_version_resources() {

   return [
      'name'           => _n('Human Resource', 'Human Resources', 2, 'resources'),
      'version'        => '2.5.2',
      'license'        => 'GPLv2+',
      'author'         => "<a href='http://infotel.com/services/expertise-technique/glpi/'>Infotel</a>",
      'homepage'       => 'https://github.com/InfotelGLPI/resources',
      'minGlpiVersion' => '9.3',
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

   if (version_compare(GLPI_VERSION, '9.3', 'lt')
       || version_compare(GLPI_VERSION, '9.4', 'ge')) {
      echo __('This plugin requires GLPI >= 9.3');
      return false;
   }
   return true;
}

// Uninstall process for plugin : need to return true if succeeded : may display messages or add to message after redirect
/**
 * @return bool
 */
/**
 * @return bool
 */
function plugin_resources_check_config() {
   return true;
}

/**
 * @param $types
 *
 * @return mixed
 */
/**
 * @param $types
 *
 * @return mixed
 */
function plugin_datainjection_migratetypes_resources($types) {
   $types[4300] = 'PluginResourcesResource';
   return $types;
}

