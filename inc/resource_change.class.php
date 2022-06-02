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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginResourcesResource_Change
 */
class PluginResourcesResource_Change extends CommonDBTM {

   static $rightname = 'plugin_resources';

   //List of possible actions
   const CHANGE_RESOURCEMANAGER         = 1;
   const CHANGE_ACCESSPROFIL            = 2;
   const CHANGE_CONTRACTTYPE             = 3;
   const CHANGE_AGENCY                  = 4;
   const CHANGE_TRANSFER                = 5;
   const BADGE_RESTITUTION              = 6;
   const CHANGE_RESOURCESALE            = 7;
   const CHANGE_RESOURCEINFORMATIONS    = 8;
   const CHANGE_RESOURCECOMPANY         = 9;
   const CHANGE_RESOURCEDEPARTMENT      = 10;
   const CHANGE_RESOURCEMATERIAL        = 11;
   const CHANGE_RESOURCEITEMAPPLICATION = 12;
   const CHANGE_RESOURCESERVICE         = 13;
   const CHANGE_RESOURCEROLE            = 14;
   const CHANGE_RESOURCEFUNCTION        = 15;
   const CHANGE_RESOURCETEAM            = 16;


   /**
    * Returns all actions
    */
   static function getAllActions($menu = false) {

      $actions                                       = [];
      $actions[0]                                    = self::getNameActions(0);
      $actions[self::CHANGE_RESOURCEMANAGER]         = self::getNameActions(self::CHANGE_RESOURCEMANAGER);
      $actions[self::CHANGE_RESOURCESALE]            = self::getNameActions(self::CHANGE_RESOURCESALE);
      $actions[self::CHANGE_ACCESSPROFIL]            = self::getNameActions(self::CHANGE_ACCESSPROFIL);
      $actions[self::CHANGE_CONTRACTTYPE]             = self::getNameActions(self::CHANGE_CONTRACTTYPE);
      $actions[self::CHANGE_AGENCY]                  = self::getNameActions(self::CHANGE_AGENCY);
      $actions[self::CHANGE_RESOURCEINFORMATIONS]    = self::getNameActions(self::CHANGE_RESOURCEINFORMATIONS);
      $actions[self::CHANGE_RESOURCECOMPANY]         = self::getNameActions(self::CHANGE_RESOURCECOMPANY);
      $actions[self::CHANGE_RESOURCEDEPARTMENT]      = self::getNameActions(self::CHANGE_RESOURCEDEPARTMENT);
      $actions[self::CHANGE_RESOURCEMATERIAL]        = self::getNameActions(self::CHANGE_RESOURCEMATERIAL);
      $actions[self::CHANGE_RESOURCEITEMAPPLICATION] = self::getNameActions(self::CHANGE_RESOURCEITEMAPPLICATION);
      $actions[self::CHANGE_RESOURCESERVICE]         = self::getNameActions(self::CHANGE_RESOURCESERVICE);
      $actions[self::CHANGE_RESOURCEROLE]            = self::getNameActions(self::CHANGE_RESOURCEROLE);
      $actions[self::CHANGE_RESOURCEFUNCTION]        = self::getNameActions(self::CHANGE_RESOURCEFUNCTION);
      $actions[self::CHANGE_RESOURCETEAM]            = self::getNameActions(self::CHANGE_RESOURCETEAM);
      $transfer                                      = new PluginResourcesTransferEntity();
      $dataEntity                                    = $transfer->find();
      if (is_array($dataEntity) && count($dataEntity) > 0) {
         $actions[self::CHANGE_TRANSFER] = self::getNameActions(self::CHANGE_TRANSFER);
      }
      if ($menu == false) {
         foreach ($actions as $key => $val) {
            if ($key == 0) {
               continue;
            }
            $self = new self();
            $conf = [];
            $conf = $self->find(['actions_id' => $key]);
            if (count($conf) == 0) {
               unset($actions[$key]);
            }
         }
      }

      return $actions;
   }

   /**
    * Returns the label of the action
    *
    * @param $actions_id
    *
    * @return \translated
    */
   static function getNameActions($actions_id) {
      switch ($actions_id) {
         case self::CHANGE_RESOURCEMANAGER :
            return __("Change manager", 'resources');
         case self::CHANGE_RESOURCESALE :
            return __("Change the sales manager", 'resources');
         case self::CHANGE_ACCESSPROFIL :
            return __("Change the access profil", 'resources');
         case self::CHANGE_CONTRACTTYPE :
            return __("Change contract type", 'resources');
         case self::CHANGE_AGENCY :
            return __("Change of agency", 'resources');
         case self::CHANGE_TRANSFER :
            return __("Change direction (mutation)", 'resources');
         case self::BADGE_RESTITUTION :
            return __('Badge restitution', 'resources');
         case self::CHANGE_RESOURCEINFORMATIONS :
            return __(' Change information', 'resources');
         case self::CHANGE_RESOURCECOMPANY :
            return __('Change company', 'resources');
         case self::CHANGE_RESOURCEDEPARTMENT :
            return __('Change department ', 'resources');
         case self::CHANGE_RESOURCEMATERIAL :
            return __('Change material', 'resources');
         case self::CHANGE_RESOURCEITEMAPPLICATION :
            return __('Add application', 'resources');
         case self::CHANGE_RESOURCESERVICE :
            return __('Change service', 'resources');
         case self::CHANGE_RESOURCEROLE :
            return __('Change role', 'resources');
         case self::CHANGE_RESOURCEFUNCTION :
            return __('Change function', 'resources');
         case self::CHANGE_RESOURCETEAM :
            return __('Change team', 'resources');
         default :
            return Dropdown::EMPTY_VALUE;
      }
   }

   /**
    * Form for each change
    *
    * @param $action_id
    * @param $plugin_resources_resources_id
    */
   static function setFieldByAction($action_id, $plugin_resources_resources_id) {
      global $CFG_GLPI, $DB;

      if ($plugin_resources_resources_id == 0) {
         echo "<span class='red'>" . __('Please select a resource', 'resources') . "</span>";
         return;
      }
      $resource = new PluginResourcesResource();
      $resource->getFromDB($plugin_resources_resources_id);

      $dbu = new DbUtils();

      //Display for each action
      switch ($action_id) {
         case self::CHANGE_RESOURCEMANAGER :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Manager for the current resource", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo "&nbsp;" . $dbu->getUserName($resource->getField('users_id'));
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New resource manager', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $rand = User::dropdown(['name'      => "users_id",
                                    'entity'    => $resource->fields["entities_id"],
                                    //                                    'entity_sons' => true,
                                    'right'     => 'all',
                                    'used'      => [$resource->getField('users_id')],
                                    'on_change' => 'plugin_resources_load_button_changeresources_manager()']);

            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_manager(){";
            $params = ['load_button_changeresources' => true,
                       'action'                      => self::CHANGE_RESOURCEMANAGER,
                       'users_id'                    => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   'dropdown_users_id' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;

         case self::CHANGE_RESOURCESALE :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Sales manager for the current resource", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo "&nbsp;" . $dbu->getUserName($resource->getField('users_id_sales'));
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New resource sales manager', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $rand = User::dropdown(['name'      => "users_id_sales",
                                    'entity'    => $resource->fields["entities_id"],
                                    //                                    'entity_sons' => true,
                                    'right'     => 'all',
                                    'used'      => [$resource->getField('users_id_sales')],
                                    'on_change' => 'plugin_resources_load_button_changeresources_sale()']);

            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_sale(){";
            $params = ['load_button_changeresources' => true,
                       'action'                      => self::CHANGE_RESOURCESALE,
                       'users_id_sales'              => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   'dropdown_users_id_sales' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;

         case self::CHANGE_ACCESSPROFIL :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Current access profile of the resource", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $query = "SELECT `glpi_plugin_resources_habilitations`.`id` 
                      FROM `glpi_plugin_resources_resourcehabilitations` 
                      LEFT JOIN `glpi_plugin_resources_habilitations` 
                      ON `glpi_plugin_resources_habilitations`.`id` = `glpi_plugin_resources_resourcehabilitations`.`plugin_resources_habilitations_id`
                      LEFT JOIN `glpi_plugin_resources_habilitationlevels` 
                      ON `glpi_plugin_resources_habilitationlevels`.`id` = `glpi_plugin_resources_habilitations`.`plugin_resources_habilitationlevels_id`
                      WHERE `plugin_resources_resources_id` = $plugin_resources_resources_id
                      AND `glpi_plugin_resources_habilitationlevels`.`is_mandatory_creating_resource` = 1";
            $used  = [];
            foreach ($DB->request($query) as $data) {
               echo "&nbsp;" . Dropdown::getDropdownName('glpi_plugin_resources_habilitations', $data['id']) . "<br>";
               $used[] = $data['id'];
            }
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New access profile of the resource', 'resources');
            echo "</div>";

            //level
            $habilitationlevel = new PluginResourcesHabilitationLevel();
            $levels            = $habilitationlevel->find(['is_mandatory_creating_resource' => 1]);
            $condition         = [];
            foreach ($levels as $level) {
               $condition["plugin_resources_habilitationlevels_id"] = $level['id'];
            }

            echo "<div class=\"bt-feature col-md-4 \">";
            $rand = PluginResourcesHabilitation::dropdown(['name'      => "plugin_resources_habilitations_id",
                                                           'entity'    => $resource->fields["entities_id"],
                                                           'right'     => 'all',
                                                           'condition' => $condition,
                                                           'used'      => $used,
                                                           'on_change' => 'plugin_resources_load_button_changeresources_profil()']);

            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_profil(){";
            $params = ['load_button_changeresources'       => true,
                       'action'                            => self::CHANGE_ACCESSPROFIL,
                       'plugin_resources_habilitations_id' => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   'dropdown_plugin_resources_habilitations_id' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;
         case self::CHANGE_CONTRACTTYPE :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Current contract type of the resource", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo "&nbsp;" . Dropdown::getDropdownName('glpi_plugin_resources_contracttypes',
                                                      $resource->getField('plugin_resources_contracttypes_id'));

            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New type of contract', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $rand = PluginResourcesContractType::dropdown(['name'      => "plugin_resources_contracttypes_id",
                                                           'entity'    => $resource->fields["entities_id"],
                                                           'right'     => 'all',
                                                           'used'      => [$resource->getField('plugin_resources_contracttypes_id')],
                                                           'on_change' => 'plugin_resources_load_button_changeresources_contract()']);

            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_contract(){";
            $params = ['load_button_changeresources'       => true,
                       'action'                            => self::CHANGE_CONTRACTTYPE,
                       'plugin_resources_contracttypes_id' => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   'dropdown_plugin_resources_contracttypes_id' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;
         case self::CHANGE_AGENCY :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Current agency of the resource", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo "&nbsp;" . Dropdown::getDropdownName('glpi_locations', $resource->getField('locations_id'));
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New resource agency', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $rand = Location::dropdown(['name'      => "locations_id",
                                        'entity'    => $resource->fields["entities_id"],
                                        'right'     => 'all',
                                        'used'      => [$resource->getField('locations_id')],
                                        'on_change' => 'plugin_resources_load_button_changeresources_agency();']);

            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_agency(){";
            $params = ['load_button_changeresources' => true, 'action' => self::CHANGE_AGENCY, 'locations_id' => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   'dropdown_locations_id' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;

         case self::CHANGE_TRANSFER :
            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_transfer(){";
            $params = ['load_button_changeresources' => true, 'action' => self::CHANGE_TRANSFER];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   "");
            echo "}";
            echo "plugin_resources_load_button_changeresources_transfer();";
            echo "</script>";
            break;

         case self::CHANGE_RESOURCEINFORMATIONS :
            //            echo "<div class=\"form-row\">";
            //            echo "<div class=\"bt-feature col-md-4 \">";
            //            echo __("Current name of the resource", "resources");
            //            echo "</div>";
            //            echo "<div class=\"bt-feature col-md-4 \">";
            //            echo "&nbsp;" . $resource->getField('name');
            //
            //            echo "</div>";
            //            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('Name', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $rand   = mt_rand();
            $option = ['rand'     => $rand,
                       'value'    => $resource->fields["name"],
                       'onChange' => "javascript:this.value=this.value.toUpperCase(); plugin_resources_load_button_changeresources_information(); "];
            $rand1  = Html::input('name', $option);
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('Firstname', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $option = ['rand'     => $rand,
                       'value'    => $resource->fields["firstname"],
                       'onChange' => "'First2UpperCase(this.value); plugin_resources_load_button_changeresources_information();' style='text-transform:capitalize;' "];
            $rand2  = Html::input('firstname', $option);
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('Departure date', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $option = ['onChange' => "javascript:this.value=this.value.toUpperCase();"];
            $rand3  = Html::showDateField("date_end", ['value' => $resource->fields["date_end"]]);
            //            $rand = Html::autocompletionTextField($resource, "firstname", $option);
            echo "</div>";

            echo "</div>";

            echo "<script type='text/javascript'>";
            echo "$('input[name=\"date_end\"]').change(function() {
                  plugin_resources_load_button_changeresources_information();
            });";
            echo "function plugin_resources_load_button_changeresources_information(){";
            $root_doc = PLUGIN_RESOURCES_NOTFULL_WEBDIR;
            echo "$('#plugin_resources_buttonchangeresources').load('$root_doc/ajax/resourcechange.php'
               ,{load_button_changeresources:true,action:8,name:$('#textfield_name" . $rand . "').val(),firstname:$('#textfield_firstname" . $rand . "').val(),date_end:$('input[name=\"date_end\"]').val()}
               )";

            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;
         case self::CHANGE_RESOURCECOMPANY :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Current company of the resource", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $employee = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);
            echo "&nbsp;" . Dropdown::getDropdownName('glpi_plugin_resources_employers', $employee->getField("plugin_resources_employers_id"));
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New resource company', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $rand = PluginResourcesEmployer::dropdown(['name'      => "employer_id",
                                                       'right'     => 'all',
                                                       'used'      => [$employee->getField('plugin_resources_employers_id')],
                                                       'on_change' => 'plugin_resources_load_button_changeresources_company();'
                                                      ]);

            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_company(){";
            $params = ['load_button_changeresources'   => true,
                       'action'                        => self::CHANGE_RESOURCECOMPANY,
                       'plugin_resources_employers_id' => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   'dropdown_employer_id' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;
         case self::CHANGE_RESOURCEDEPARTMENT :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Current department of the resource", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $employee = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);
            echo "&nbsp;" . Dropdown::getDropdownName('glpi_plugin_resources_departments', $resource->getField("plugin_resources_departments_id"));
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New resource department', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $rand = PluginResourcesDepartment::dropdown(['name'      => "department_id",
                                                         'entity'    => $resource->fields["entities_id"],
                                                         // TODO relink departement with resource employer and department with no employer
                                                         //                                                       'condition' => ["plugin_resources_employers_id"=>$employee->getField("plugin_resources_employers_id")],
                                                         'right'     => 'all',
                                                         'used'      => [$resource->getField('plugin_resources_departments_id')],
                                                         'on_change' => 'plugin_resources_load_button_changeresources_department();'
                                                        ]);

            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_department(){";
            $params = ['load_button_changeresources'     => true,
                       'action'                          => self::CHANGE_RESOURCEDEPARTMENT,
                       'plugin_resources_departments_id' => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   'dropdown_department_id' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;
         case self::CHANGE_RESOURCESERVICE :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Current service of the resource", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $employee = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);
            echo "&nbsp;" . Dropdown::getDropdownName('glpi_plugin_resources_services', $resource->getField("plugin_resources_services_id"));
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New resource service', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $rand = PluginResourcesService::dropdownFromDepart($resource->fields["plugin_resources_departments_id"],
                                                               ['name'      => "service_id",
                                                                'value'     => $resource->fields["plugin_resources_services_id"],
                                                                'entity'    => $resource->fields["entities_id"],
                                                                'right'     => 'all',
                                                                'used'      => [$resource->getField('plugin_resources_services_id')],
                                                                'on_change' => 'plugin_resources_load_button_changeresources_service();'
                                                               ]);


            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_service(){";
            $params = ['load_button_changeresources'  => true,
                       'action'                       => self::CHANGE_RESOURCESERVICE,
                       'plugin_resources_services_id' => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   'dropdown_service_id' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;
         case self::CHANGE_RESOURCEROLE :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Current role of the resource", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $employee = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);
            echo "&nbsp;" . Dropdown::getDropdownName('glpi_plugin_resources_roles', $resource->getField("plugin_resources_roles_id"));
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New resource role', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $rand = PluginResourcesRole::dropdownFromService($resource->fields["plugin_resources_services_id"],
                                                             ['name'      => "role_id",
                                                              'value'     => $resource->fields["plugin_resources_roles_id"],
                                                              'entity'    => $resource->fields["entities_id"],
                                                              'right'     => 'all',
                                                              'used'      => [$resource->getField('plugin_resources_roles_id')],
                                                              'on_change' => 'plugin_resources_load_button_changeresources_role();'
                                                             ]);


            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_role(){";
            $params = ['load_button_changeresources' => true,
                       'action'                      => self::CHANGE_RESOURCEROLE,
                       'plugin_resources_roles_id'   => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   'dropdown_role_id' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;
         case self::CHANGE_RESOURCEFUNCTION :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Current function of the resource", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $employee = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_functions_id" => $resource->getID()]);
            echo "&nbsp;" . Dropdown::getDropdownName('glpi_plugin_resources_functions', $resource->getField("plugin_functions_functions_id"));
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New resource function', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $rand = PluginResourcesFunction::dropdown(['name'      => "function_id",
                                                       'entity'    => $resource->fields["entities_id"],
                                                       // TODO relink departement with resource employer and department with no employer
                                                       //                                                       'condition' => ["plugin_resources_employers_id"=>$employee->getField("plugin_resources_employers_id")],
                                                       'right'     => 'all',
                                                       'used'      => [$resource->getField('plugin_resources_functions_id')],
                                                       'on_change' => 'plugin_resources_load_button_changeresources_function();'
                                                      ]);

            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_function(){";
            $params = ['load_button_changeresources'   => true,
                       'action'                        => self::CHANGE_RESOURCEFUNCTION,
                       'plugin_resources_functions_id' => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   'dropdown_function_id' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;
         case self::CHANGE_RESOURCETEAM :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Current team of the resource", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo "&nbsp;" . Dropdown::getDropdownName('glpi_plugin_resources_teams', $resource->getField("plugin_functions_teams_id"));
            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New resource function', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $rand = PluginResourcesTeam::dropdown(['name'      => "team_id",
                                                   'entity'    => $resource->fields["entities_id"],
                                                   // TODO relink departement with resource employer and department with no employer
                                                   //                                                       'condition' => ["plugin_resources_employers_id"=>$employee->getField("plugin_resources_employers_id")],
                                                   'right'     => 'all',
                                                   'used'      => [$resource->getField('plugin_resources_teams_id')],
                                                   'on_change' => 'plugin_resources_load_button_changeresources_team();'
                                                  ]);

            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_team(){";
            $params = ['load_button_changeresources' => true,
                       'action'                      => self::CHANGE_RESOURCETEAM,
                       'plugin_resources_teams_id'   => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources',
                                   PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                   $params,
                                   'dropdown_team_id' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;
         case self::CHANGE_RESOURCEMATERIAL :

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __("Change material", "resources");
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            Html::textarea(['name' => "content"]);
            //            echo "<script type='text/javascript'>";

            $params = ['load_button_changeresources' => true, 'action' => self::CHANGE_RESOURCEMATERIAL];
            //            Ajax::updateItemJsCode();
            echo Ajax::updateItemOnInputTextEvent('content',
                                                  'plugin_resources_buttonchangeresources',
                                                  PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                                                  $params);

            //            echo "</script>";
            echo "</div>";
            echo "</div>";

            break;
         case self::CHANGE_RESOURCEITEMAPPLICATION:

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('New Application to add to the resource', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $appliance     = new Appliance();
            $resource_item = new PluginResourcesResource_Item();

            $resource_items = $resource_item->find(['plugin_resources_resources_id' => $resource->fields['id'], 'itemtype' => Appliance::getType()]);
            $appliances     = [];
            foreach ($resource_items as $it) {
               array_push($appliances, $it["items_id"]);
            }
            $rand = Appliance::dropdown(['name'      => "appliances_id",
                                         'entity'    => $resource->fields["entities_id"],
                                         'right'     => 'all',
                                         'used'      => $appliances,
                                         'on_change' => 'plugin_resources_load_button_changeresources_application();'
                                        ]);

            echo "<script type='text/javascript'>";
            echo "function plugin_resources_load_button_changeresources_application(){";
            $params = ['load_button_changeresources' => true, 'action' => self::CHANGE_RESOURCEITEMAPPLICATION, 'appliances_id' => '__VALUE__'];
            Ajax::updateItemJsCode('plugin_resources_buttonchangeresources', PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php', $params, 'dropdown_appliances_id' . $rand);
            echo "}";
            echo "</script>";
            echo "</div>";
            echo "</div>";
            break;
      }
   }

   /**
    * @param $action_id
    * @param $options
    */
   function loadButtonChangeResources($action_id, $options) {
      $display = false;

      //Display for each action
      switch ($action_id) {
         case self::CHANGE_RESOURCEMANAGER :

            if (isset($options['users_id'])
                && !empty($options['users_id'])
                && $options['users_id'] != 0) {
               $display = true;
            }
            break;
         case self::CHANGE_RESOURCESALE :

            if (isset($options['users_id_sales'])
                && !empty($options['users_id_sales'])
                && $options['users_id_sales'] != 0) {
               $display = true;
            }
            break;

         case self::CHANGE_ACCESSPROFIL :

            if (isset($options['plugin_resources_habilitations_id'])
                && !empty($options['plugin_resources_habilitations_id'])
                && $options['plugin_resources_habilitations_id'] != 0) {
               $display = true;
            }
            break;
         case self::CHANGE_CONTRACTTYPE :
            if (isset($options['plugin_resources_contracttypes_id'])
                && !empty($options['plugin_resources_contracttypes_id'])
                && $options['plugin_resources_contracttypes_id'] != 0) {
               $display = true;
            }
            break;
         case self::CHANGE_AGENCY :
            if (isset($options['locations_id'])
                && !empty($options['locations_id'])
                && $options['locations_id'] != 0) {
               $display = true;
            }
            break;

         case self::CHANGE_RESOURCEMATERIAL:
         case self::CHANGE_RESOURCEITEMAPPLICATION:
         case self::CHANGE_TRANSFER :
            $display = true;
            break;
         case self::CHANGE_RESOURCEINFORMATIONS :
            if (isset($options['name'])
                && !empty($options['name'])
                && isset($options['firstname'])
                && !empty($options['firstname'])) {
               $display = true;
            }

            break;
         case self::CHANGE_RESOURCECOMPANY :
            if (isset($options['plugin_resources_employers_id'])
                && !empty($options['plugin_resources_employers_id'])) {
               $display = true;
            }

            break;
         case self::CHANGE_RESOURCEDEPARTMENT :
            if (isset($options['plugin_resources_departments_id'])
                && !empty($options['plugin_resources_departments_id'])) {
               $display = true;
            }

            break;
         case self::CHANGE_RESOURCESERVICE :
            if (isset($options['plugin_resources_services_id'])
                && !empty($options['plugin_resources_services_id'])) {
               $display = true;
            }
            break;
         case self::CHANGE_RESOURCEROLE :
            if (isset($options['plugin_resources_roles_id'])
                && !empty($options['plugin_resources_roles_id'])) {
               $display = true;
            }
            break;
         case self::CHANGE_RESOURCEFUNCTION :
            if (isset($options['plugin_resources_functions_id'])
                && !empty($options['plugin_resources_functions_id'])) {
               $display = true;
            }

            break;
         case self::CHANGE_RESOURCETEAM :
            if (isset($options['plugin_resources_teams_id'])
                && !empty($options['plugin_resources_teams_id'])) {
               $display = true;
            }

            break;
      }

      if ($display) {
         echo "<div class='next'>";
         echo Html::submit(__s('Starting change', 'resources'), ['name' => 'changeresources', 'class' => 'btn btn-success']);
         echo "</div>";

      }

   }

   /**
    * Launch of change for ticket creation
    *
    * @param       $plugin_resources_resources_id
    * @param       $action_id
    * @param array $options
    */
   static function startingChange($plugin_resources_resources_id, $action_id, $options = []) {
      global $DB;

      $resource = new PluginResourcesResource();
      $resource->getFromDB($plugin_resources_resources_id);

      $dbu = new DbUtils();

      //Preparation of ticket data
      $data                                  = [];
      $data['itilcategories_id']             = 0;
      $data['tickettemplates_id']            = 0;
      $data['entities_id']                   = $resource->fields['entities_id'];
      $data['plugin_resources_resources_id'] = $plugin_resources_resources_id;

      //Search for the entity-related category for that action
      $resource_change = new PluginResourcesResource_Change();
      if ($resource_change->getFromDBByCrit(['actions_id'  => $action_id,
                                             'entities_id' => $resource->fields['entities_id']])) {
         $data['itilcategories_id'] = $resource_change->fields['itilcategories_id'];

         //Search of the ticket template
         $itil_category = new ITILCategory();
         if ($itil_category->getFromDB($data['itilcategories_id'])) {
            $data['tickettemplates_id'] = $itil_category->fields['tickettemplates_id_demand'];
         }
      }

      // name and content of ticket
      switch ($action_id) {
         case self::CHANGE_RESOURCEMANAGER :
            $data['name']    = __("Change manager for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change manager for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";
            $data['content'] .= __("Manager for the current resource", 'resources') . "&nbsp;:&nbsp;" .
                                $dbu->getUserName($resource->getField('users_id')) . "\n";
            $data['content'] .= __("New resource manager", 'resources') . "&nbsp;:&nbsp;" .
                                $dbu->getUserName($options['users_id']) . "\n";

            $input['users_id'] = $options['users_id'];
            break;

         case self::CHANGE_RESOURCESALE :
            $data['name']    = __("Change of sales manager for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change of sales manager for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";
            $data['content'] .= __("Sales manager for the current resource", 'resources') . "&nbsp;:&nbsp;" .
                                $dbu->getUserName($resource->getField('users_id_sales')) . "\n";
            $data['content'] .= __("New sales manager for the resource", 'resources') . "&nbsp;:&nbsp;" .
                                $dbu->getUserName($options['users_id_sales']) . "\n";

            $input['users_id_sales'] = $options['users_id_sales'];
            break;
         case self::CHANGE_ACCESSPROFIL :

            $data['name']    = __("Change the access profile for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change the access profile for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";

            $data['content'] .= __("Current access profile of the resource", 'resources') . "&nbsp;:&nbsp;";
            $query           = "SELECT `glpi_plugin_resources_habilitations`.`id` 
                      FROM `glpi_plugin_resources_resourcehabilitations` 
                      LEFT JOIN `glpi_plugin_resources_habilitations` 
                       ON `glpi_plugin_resources_habilitations`.`id` = `glpi_plugin_resources_resourcehabilitations`.`plugin_resources_habilitations_id`
                      LEFT JOIN `glpi_plugin_resources_habilitationlevels` 
                      ON `glpi_plugin_resources_habilitationlevels`.`id` = `glpi_plugin_resources_habilitations`.`plugin_resources_habilitationlevels_id`
                      WHERE `plugin_resources_resources_id` = $plugin_resources_resources_id
                      AND `glpi_plugin_resources_habilitationlevels`.`is_mandatory_creating_resource` = 1";
            foreach ($DB->request($query) as $habilitation) {
               $data['content'] .= Dropdown::getDropdownName('glpi_plugin_resources_habilitations',
                                                             $habilitation['id']) . "\n";
            }

            $data['content'] .= __("New access profile of the resource", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_habilitations',
                                                          $options['plugin_resources_habilitations_id']) . "\n";

            $input['plugin_resources_habilitations_id'] = $options['plugin_resources_habilitations_id'];
            break;
         case self::CHANGE_CONTRACTTYPE :

            $data['name']    = __("Change the type of contract for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change the type of contract for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";
            $data['content'] .= __("Current contract type of the resource", 'resources') . " " . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_contracttypes',
                                                          $resource->getField('plugin_resources_contracttypes_id')) . "\n";
            $data['content'] .= __("New type of contract", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_contracttypes',
                                                          $options['plugin_resources_contracttypes_id']) . "\n";

            $input['plugin_resources_contracttypes_id'] = $options['plugin_resources_contracttypes_id'];
            break;
         case self::CHANGE_AGENCY :

            $data['name']    = __("Change of agency for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change of agency for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";
            $data['content'] .= __("Current agency of the resource", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_locations', $resource->getField('locations_id')) . "\n";
            $data['content'] .= __("New resource agency", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_locations', $options['locations_id']) . "\n";

            $input['locations_id'] = $options['locations_id'];
            break;
         case self::CHANGE_RESOURCEINFORMATIONS :

            $data['name']    = __("Change information for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change information for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";
            $data['content'] .= __("Current name of the resource", 'resources') . "&nbsp;:&nbsp;" .
                                $resource->getField('name') . "\n";
            $data['content'] .= __("New resource name", 'resources') . "&nbsp;:&nbsp;" .
                                $options['name'] . "\n";
            $data['content'] .= __("Current firstname of the resource", 'resources') . "&nbsp;:&nbsp;" .
                                $resource->getField('firstname') . "\n";
            $data['content'] .= __("New resource firstname", 'resources') . "&nbsp;:&nbsp;" .
                                $options['firstname'] . "\n";
            $data['content'] .= __("Current departure date of the resource", 'resources') . "&nbsp;:&nbsp;" .
                                $resource->getField('date_end') . "\n";
            $data['content'] .= __("New resource departure date", 'resources') . "&nbsp;:&nbsp;" .
                                $options['date_end'] . "\n";

            $input['name']      = $options['name'];
            $input['firstname'] = $options['firstname'];
            $input['date_end']  = $options['date_end'];
            break;
         case self::CHANGE_RESOURCECOMPANY :

            $data['name']    = __("Change of company for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change of company for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";
            $employee        = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id]);
            $data['content'] .= __("Current company of the resource", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_employers', $employee->getField('plugin_resources_employers_id')) . "\n";
            $data['content'] .= __("New resource company", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_employers', $options['employer_id']) . "\n";

            $input['plugin_resources_employers_id'] = $options['employer_id'];
            $input['id']                            = $employee->getID();

            $employee->update($input);

            break;
         case self::CHANGE_RESOURCEDEPARTMENT :

            $data['name']    = __("Change of department for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change of department for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";
            $employee        = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id]);
            $data['content'] .= __("Current department of the resource", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_departments', $resource->getField('plugin_resources_departments_id')) . "\n";
            $data['content'] .= __("New resource department", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_departments', $options['department_id']) . "\n";

            $input['plugin_resources_departments_id'] = $options['department_id'];

            break;
         case self::CHANGE_RESOURCESERVICE :

            $data['name']    = __("Change of service for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change of service for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";
            $employee        = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id]);
            $data['content'] .= __("Current service of the resource", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_services', $resource->getField('plugin_resources_services_id')) . "\n";
            $data['content'] .= __("New resource service", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_services', $options['service_id']) . "\n";

            $input['plugin_resources_services_id'] = $options['service_id'];

            break;
         case self::CHANGE_RESOURCEROLE :

            $data['name']    = __("Change of role for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change of role for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";
            $employee        = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id]);
            $data['content'] .= __("Current role of the resource", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_roles', $resource->getField('plugin_resources_roles_id')) . "\n";
            $data['content'] .= __("New resource role", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_roles', $options['role_id']) . "\n";

            $input['plugin_resources_roles_id'] = $options['role_id'];

            break;
         case self::CHANGE_RESOURCEFUNCTION :

            $data['name']    = __("Change of function for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change of function for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";
            $employee        = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id]);
            $data['content'] .= __("Current function of the resource", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_functions', $resource->getField('plugin_resources_functions_id')) . "\n";
            $data['content'] .= __("New resource function", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_functions', $options['function_id']) . "\n";

            $input['plugin_resources_functions_id'] = $options['function_id'];

            break;
         case self::CHANGE_RESOURCETEAM :

            $data['name']    = __("Change of team for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = __("Change of team for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id) . "\n";

            $data['content'] .= __("Current team of the resource", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_teams', $resource->getField('plugin_resources_teams_id')) . "\n";
            $data['content'] .= __("New resource team", 'resources') . "&nbsp;:&nbsp;" .
                                Dropdown::getDropdownName('glpi_plugin_resources_teams', $options['role_id']) . "\n";

            $input['plugin_resources_teams_id'] = $options['team_id'];

            break;
         case self::CHANGE_RESOURCEMATERIAL :

            $data['name']    = __("Change material for", 'resources') . " " .
                               PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content'] = $options['content'];


            break;
         case self::CHANGE_RESOURCEITEMAPPLICATION :
            $data['name']                               = __("Add application for", 'resources') . " " .
                                                          PluginResourcesResource::getResourceName($plugin_resources_resources_id);
            $data['content']                            = sprintf(__("The added appliance is %s ", 'resources'), Dropdown::getDropdownName('glpi_appliances', $options['appliances_id']));
            $resource_item                              = new PluginResourcesResource_Item();
            $inputInfo                                  = [];
            $inputInfo['itemtype']                      = Appliance::getType();
            $inputInfo['items_id']                      = $options['appliances_id'];
            $inputInfo['plugin_resources_resources_id'] = $plugin_resources_resources_id;
            $resource_item->add($inputInfo);
            break;
      }

      $input['id']                = $plugin_resources_resources_id;
      $input['send_notification'] = 0;
      //update resource
      $resource->update($input);

      self::createTicket($data);

      $linkad = new PluginResourcesLinkAd();
      if ($linkad->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id])) {
         $input2                = [];
         $input2['action_done'] = 0;
         $input2['id']          = $linkad->getID();
         $linkad->update($input2);
      }
   }

   /**
    * Setup form
    */
   function showConfigForm() {

      echo "<form name='form' method='post' action='" . self::getFormURL() . "'>";
      echo "<div align='center'><table class='tab_cadre_fixe'>";
      echo "<tr><th>" . __("Managing change actions", 'resources') . "</th></tr>";
      echo "<tr class='tab_bg_1'><td class='center'>";
      echo "<a href=\"./resource_change.form.php\">" . __('Setup') . "</a>";
      echo "</td></tr></table></div>";
      Html::closeForm();

   }

   /**
    * Setup form for each action
    *
    * @return bool
    */
   function showFormActions() {
      global $CFG_GLPI;

      if (!$this->canView()) {
         return false;
      }
      if (!$this->canCreate()) {
         return false;
      }

      echo "<form name='form' method='post' action='" . self::getFormURL() . "'>";
      echo "<div align='center'><table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='3'>" . __("Managing change actions", 'resources') . "</th></tr>";

      $actions                          = self::getAllActions(true);
      $actions[self::BADGE_RESTITUTION] = self::getNameActions(self::BADGE_RESTITUTION);
      //delete mutation
      unset($actions[self::CHANGE_TRANSFER]);

      $canedit = true;

      echo "<tr class='tab_bg_1'>";
      echo "<td class='center'>";
      echo __('Action') . '&nbsp;';
      $rand = Dropdown::showFromArray('actions_id', $actions, ['on_change' => 'plugin_resources_load_entity();']);
      // Dropdown list according to the entity
      echo "<script type='text/javascript'>";
      echo "function plugin_resources_load_entity(){";
      $params = ['action'     => 'loadEntity',
                 'actions_id' => '__VALUE__'];
      Ajax::updateItemJsCode('plugin_resources_entity_itil_categories',
                             PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                             $params,
                             'dropdown_actions_id' . $rand);
      $params = ['action'     => 'clean',
                 'actions_id' => '__VALUE__'];
      Ajax::updateItemJsCode('plugin_resources_button_add',
                             PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                             $params,
                             'dropdown_actions_id' . $rand);
      echo "}";
      echo "</script>";
      echo "</td>";

      // Dropdown entity
      echo "<td class='center' id='plugin_resources_entity_itil_categories'>";

      echo "</td>";

      echo "<td class='center' id='plugin_resources_button_add'>";

      echo "</td>";

      echo "</tr>";

      echo "</table></div>";
      Html::closeForm();

      self::listItems($canedit);
   }


   /**
    * List of entities and categories already added
    *
    * @param $canedit
    */
   private function listItems($canedit) {
      // Entity already added for this action
      $datas = $this->find([], "actions_id");

      $rand = mt_rand();

      echo "<div class='left'>";
      if ($canedit) {
         Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
         $massiveactionparams = ['item' => __CLASS__, 'container' => 'mass' . __CLASS__ . $rand];
         Html::showMassiveActions($massiveactionparams);
      }
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<th colspan='4'>" . __('List') . "</th>";
      echo "</tr>";
      echo "<tr>";
      echo "<th width='10'>";
      if ($canedit) {
         echo Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
      }
      echo "</th>";
      echo "<th>" . __('Action') . "</th>";
      echo "<th>" . __('Entity') . "</th>";
      echo "<th>" . __('Category') . "</th>";
      echo "</tr>";
      foreach ($datas as $action) {
         echo "<tr class='tab_bg_1'>";
         echo "<td width='10'>";
         if ($canedit) {
            Html::showMassiveActionCheckBox(__CLASS__, $action['id']);
         }
         echo "</td>";
         //DATA LINE
         echo "<td>" . self::getNameActions($action['actions_id']) . "</td>";
         echo "<td>" . Dropdown::getDropdownName('glpi_entities', $action['entities_id']) . "</td>";
         echo "<td>" . Dropdown::getDropdownName('glpi_itilcategories', $action['itilcategories_id']) . "</td>";
         echo "</tr>";
      }
      echo "</table>";
      if ($canedit) {
         $massiveactionparams['ontop'] = false;
         Html::showMassiveActions($massiveactionparams);
         Html::closeForm();
      }
      echo "</div>";
   }

   /**
    * @param $actions_id
    */
   function loadEntity($actions_id) {
      global $CFG_GLPI;

      // Entity already added for this action
      $datas = $this->find(['actions_id' => $actions_id]);

      $used_entities = [];
      if ($datas) {
         foreach ($datas as $field) {
            $used_entities[] = $field['entities_id'];
         }
      }

      echo __('Entity') . '&nbsp;';
      $mrand = Dropdown::show("Entity", ['name'      => 'entities_id',
                                         'used'      => $used_entities,
                                         'on_change' => 'plugin_resources_load_category();']);

      //Dropdown list according to the entity
      echo "<script type='text/javascript'>";
      echo "function plugin_resources_load_category(){";
      $params = ['action' => 'loadCategory', 'entities_id' => '__VALUE__'];
      Ajax::updateItemJsCode('plugin_resource_itil_categories',
                             PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                             $params,
                             'dropdown_entities_id' . $mrand);
      echo "};";
      echo "</script>";

      echo "<span id='plugin_resource_itil_categories'>";
      self::displayCategory($_SESSION['glpiactive_entity']);
      echo "</span>";

   }

   /**
    * Display dropdown list of the category
    *
    * @param $entities_id
    */
   static function displayCategory($entities_id) {
      global $CFG_GLPI;

      echo __('Category') . "&nbsp;";
      $rand = Dropdown::show('ITILCategory', ['name'      => 'itilcategories_id',
                                              'entity'    => $entities_id,
                                              'condition' => ['is_request' => 1],
                                              'on_change' => 'plugin_resources_load_buttonadd();']);

      echo "<script type='text/javascript'>";
      echo "function plugin_resources_load_buttonadd(){";
      $params = ['action' => 'loadButtonAdd', 'itilcategories_id' => '__VALUE__'];
      Ajax::updateItemJsCode('plugin_resources_button_add',
                             PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                             $params,
                             'dropdown_itilcategories_id' . $rand);
      echo "};";
      echo "</script>";
   }

   /**
    * @param $itilcategories_id
    */
   static function displayButtonAdd($itilcategories_id) {
      if ($itilcategories_id != 0) {
         echo Html::submit(_sx('button', 'Add'), ['name' => 'add_entity_category', 'class' => 'btn btn-primary']);
      }
   }

   /**
    * Creation of ticket for change
    *
    * @param $data
    *
    * @return bool
    */
   static function createTicket($data) {

      $result = false;
      $tt     = new TicketTemplate();

      // Create ticket based on ticket template and entity informations of ticketrecurrent
      if ($tt->getFromDB($data['tickettemplates_id'])) {
         // Get default values for ticket
         $input = Ticket::getDefaultValues($data['entities_id']);
         // Apply tickettemplates predefined values
         $ttp        = new TicketTemplatePredefinedField();
         $predefined = $ttp->getPredefinedFields($data['tickettemplates_id'], true);

         if (count($predefined)) {
            foreach ($predefined as $predeffield => $predefvalue) {
               $input[$predeffield] = $predefvalue;
            }
         }
      }

      // Set date to creation date
      $createtime                 = date('Y-m-d H:i:s');
      $input['date']              = $createtime;
      $input['type']              = Ticket::DEMAND_TYPE;
      $input['itilcategories_id'] = $data['itilcategories_id'];
      // Compute time_to_resolve if predefined based on create date
      if (isset($predefined['time_to_resolve'])) {
         $input['time_to_resolve'] = Html::computeGenericDateTimeSearch($predefined['time_to_resolve'], false,
                                                                        strtotime($createtime));
      }
      // Set entity
      $input['entities_id'] = $data['entities_id'];
      $res                  = new PluginResourcesResource();
      if ($res->getFromDB($data['plugin_resources_resources_id'])) {

         $default_use_notif                                      = Entity::getUsedConfig('is_notif_enable_default', $input['entities_id'], '', 1);
         $input['users_id_recipient']                            = Session::getLoginUserID();
         $input['_users_id_requester']                           = [Session::getLoginUserID()];
         $input['_users_id_requester_notif']['use_notification'] = [$default_use_notif];

         $alternativeEmail = '';
         if (filter_var(Session::getLoginUserID(), FILTER_VALIDATE_EMAIL) !== false) {
            $alternativeEmail = Session::getLoginUserID();
         }
         $input['_users_id_requester_notif']['alternative_email'] = [$alternativeEmail];

         $input["items_id"] = ['PluginResourcesResource' => [$data['plugin_resources_resources_id']]];
      }
      $input["name"]    = $data['name'];
      $input["content"] = $data['content'];
      $input["content"] .= addslashes("\n\n");
      $input['id']      = 0;
      $ticket           = new Ticket();
      $input            = Toolbox::addslashes_deep($input);

      if ($tid = $ticket->add($input)) {
         $msg    = __('Create a end treatment ticket', 'resources') . " OK - ($tid)"; // Success
         $result = true;
      } else {
         $msg = __('Failed operation'); // Failure
      }
      if ($tid) {
         $changes[0] = 0;
         $changes[1] = '';
         $changes[2] = addslashes($msg);
         Log::history($data['plugin_resources_resources_id'], "PluginResourcesResource", $changes, '', Log::HISTORY_LOG_SIMPLE_MESSAGE);
      }
      return $result;
   }

}
