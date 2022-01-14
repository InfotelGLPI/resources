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
 * Class PluginResourcesRole
 */
class PluginResourcesRole extends CommonDropdown {

   static $rightname = 'plugin_resources_role';

   /**
    * @param $nb
    **@since 0.85
    *
    */
   static function getTypeName($nb = 0) {

      return _n('Role', 'Roles', $nb, 'resources');
   }

   /**
    * @return bool|\booleen
    */
   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   /**
    * @return bool|\booleen
    */
   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
   }

   /**
    * Return Additional Fields for this type
    *
    * @return array
    **/
   function getAdditionalFields() {

      return [
         ['name'  => 'roles_services',
          'label' => PluginResourcesRole_Service::getTypeName(2),
          'type'  => 'multiple_roles_services',
          'list'  => true],
      ];
   }

   /**
    * @return array
    */
   function rawSearchOptions() {

      $tab = parent::rawSearchOptions();


      return $tab;
   }


   /**
    * Display a rank's list depending on profession
    *
    * @static
    *
    * @param $options
    */
   static function showRank($options) {
      global $DB;

      $professionId = $options['plugin_resources_professions_id'];
      $entity       = $options['entity'];
      $rand         = $options['rand'];
      $sort         = $options['sort'];

      if ($professionId > 0) {

         if ($sort) {
            $query = "SELECT `glpi_plugin_resources_ranks`.*
                     FROM `glpi_plugin_resources_ranks`
                     WHERE `glpi_plugin_resources_ranks`.`plugin_resources_professions_id` = '" . $professionId . "'";

            $values[0] = Dropdown::EMPTY_VALUE;
            if ($result = $DB->query($query)) {
               while ($data = $DB->fetchArray($result)) {
                  $values[$data['id']] = $data['name'];
               }
            }
            Dropdown::showFromArray('plugin_resources_ranks_id', $values);

         } else {
            $condition = ['plugin_resources_professions_id' => $professionId];

            Dropdown::show('PluginResourcesRole', ['entity'    => $entity,
                                                   'condition' => $condition]);
         }

      } else {
         echo "<select class='form-select' name='plugin_resources_ranks_id'
                        id='dropdown_plugin_resources_ranks_id$rand'>";
         echo "<option value='0'>" . Dropdown::EMPTY_VALUE . "</option></select>";
      }
   }


   /**
    * when a rank is deleted -> deletion of the linked specialities
    *
    * @return nothing|void
    */
   function cleanDBonPurge() {


   }

   /**
    * is_active = 1 during a creation
    *
    * @return nothing|void
    */
   function post_getEmpty() {

      $this->fields['is_active'] = 1;
   }

   /**
    * @since 0.85
    * @see CommonDropdown::displaySpecificTypeField()
    **/
   function displaySpecificTypeField($ID, $field = [], array $options = []) {

      switch ($field['type']) {
         case 'multiple_roles_services' :
            $service = new PluginResourcesService();
            $values = $service->find(['entities_id' => $_SESSION['glpiactiveentities']]);
            $datas = [];
            foreach ($values as $key => $v) {
               $datas[$v['id']] = $v['name'];
            }
            $role_service = new PluginResourcesRole_Service();
            $role_service_values = $role_service->find(['plugin_resources_roles_id'=>$this->fields['id']]);
            $values_selected = [];
            foreach ($role_service_values as $role_service_value) {
               $values_selected[] = $role_service_value['plugin_resources_services_id'];
            }

            Dropdown::showFromArray('roles_services', $datas,
                                    ['values'   => $values_selected,'multiple'=> true,'display' => true]);
            break;
      }
   }

   function post_addItem() {
      $test = true;
      $roles_services = $this->input["roles_services"];
      if(is_array($roles_services)) {
         $role_service = new PluginResourcesRole_Service();
         foreach ($roles_services as $key => $id_service) {
            $role_service->add(['plugin_resources_roles_id'=>$this->getID(),'plugin_resources_services_id'=>$id_service]);
         }
      }
   }

   function post_updateItem($history = 1) {
      $roles_services = $this->input["roles_services"];
      $role_service = new PluginResourcesRole_Service();
      $roleServices = $role_service->find(['plugin_resources_roles_id'=>$this->fields['id']]);
      $current_roles_services = [];
      foreach ($roleServices as $key => $val){
         $current_roles_services[] = $val['plugin_resources_services_id'];
      }

      foreach ($roles_services as $id_service){
         if(!$role_service->getFromDBByCrit(['plugin_resources_roles_id'=>$this->getID(),'plugin_resources_services_id'=>$id_service])) {
            $role_service->add(['plugin_resources_roles_id'=>$this->getID(),'plugin_resources_services_id'=>$id_service]);
         }
      }

      foreach ($current_roles_services as $id_service) {
         if(!in_array($id_service,$roles_services)) {
            if($role_service->getFromDBByCrit(['plugin_resources_roles_id'=>$this->getID(),'plugin_resources_services_id'=>$id_service])) {
               $role_service->deleteByCriteria(['plugin_resources_roles_id'=>$this->getID(),'plugin_resources_services_id'=>$id_service]);
            }
         }
      }

   }

   static function dropdownFromService($services_id,$opt) {
      $role_service = new PluginResourcesRole_Service();
      $role_services = $role_service->find(['plugin_resources_services_id'=>$services_id]);
      $roles = [0];
      foreach ($role_services as $s) {
         $roles[] = $s['plugin_resources_roles_id'];
      }
      $options = array_merge(['condition' => ['id' => $roles]],$opt);
      return self::dropdown($options);
   }

}
