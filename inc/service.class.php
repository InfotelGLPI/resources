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
class PluginResourcesService extends CommonDropdown {

   static $rightname = 'plugin_resources_role';

   /**
    * @param $nb
    **@since 0.85
    *
    */
   static function getTypeName($nb = 0) {

      return _n('Service', 'Services', $nb, 'resources');
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
         ['name'  => 'departments_services',
          'label' => PluginResourcesDepartment_Service::getTypeName(2),
          'type'  => 'multiple_departments_services',
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
         case 'multiple_departments_services' :
            $department = new PluginResourcesDepartment();
            $values = $department->find(['entities_id' => $_SESSION['glpiactiveentities']]);
            $datas = [];
            foreach ($values as $key => $v) {
               $datas[$v['id']] = $v['name'];
            }
            $department_service = new PluginResourcesDepartment_Service();
            $department_service_values = $department_service->find(['plugin_resources_services_id'=>$this->fields['id']]);
            $values_selected = [];
            foreach ($department_service_values as $department_service_value) {
               $values_selected[] = $department_service_value['plugin_resources_departments_id'];
            }

            Dropdown::showFromArray('departments_services', $datas,
                                    ['values'   => $values_selected,'multiple'=> true,'display' => true]);
            break;
      }
   }

   function post_addItem() {
      $test = true;
      $departments_services = $this->input["departments_services"];
      if(is_array($departments_services)) {
         $department_service = new PluginResourcesDepartment_Service();
         foreach ($departments_services as $key => $id_department) {
            $department_service->add(['plugin_resources_services_id'=>$this->getID(),'plugin_resources_departments_id'=>$id_department]);
         }
      }
   }

   function post_updateItem($history = 1) {
      $departments_services = $this->input["departments_services"];
      $department_service = new PluginResourcesDepartment_Service();
      $roleServices = $department_service->find(['plugin_resources_services_id'=>$this->fields['id']]);
      $current_roles_services = [];
      foreach ($roleServices as $key => $val){
         $current_roles_services[] = $val['plugin_resources_departments_id'];
      }

      foreach ($departments_services as $id_department){
         if(!$department_service->getFromDBByCrit(['plugin_resources_services_id'=>$this->getID(),'plugin_resources_departments_id'=>$id_department])) {
            $department_service->add(['plugin_resources_services_id'=>$this->getID(),'plugin_resources_departments_id'=>$id_department]);
         }
      }

      foreach ($current_roles_services as $id_department) {
         if(!in_array($id_department,$departments_services)) {
            if($department_service->getFromDBByCrit(['plugin_resources_services_id'=>$this->getID(),'plugin_resources_departments_id'=>$id_department])) {
               $department_service->deleteByCriteria(['plugin_resources_services_id'=>$this->getID(),'plugin_resources_departments_id'=>$id_department]);
            }
         }
      }

   }

   static function dropdownFromDepart($departments_id,$opt = []) {
      $department_service = new PluginResourcesDepartment_Service();
      $department_services = $department_service->find(['plugin_resources_departments_id'=>$departments_id]);
      $services = [0];
      foreach ($department_services as $s) {
         $services[] = $s['plugin_resources_services_id'];
      }
$options = array_merge(['condition' => ['id' => $services]],$opt);
      return self::dropdown($options);
   }


}
