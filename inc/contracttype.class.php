<?php
/*
 * @version $Id: contracttype.class.php 480 2012-11-09 tynet $
 -------------------------------------------------------------------------
 Resources plugin for GLPI
 Copyright (C) 2006-2012 by the Resources Development Team.

 https://forge.indepnet.net/projects/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Resources.

 Resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access directly to this file");
}

// Class for a Dropdown
class PluginResourcesContractType extends CommonDropdown {

   static function getTypeName($nb=0) {

      return _n('Type of contract', 'Types of contract', $nb, 'resources');
   }
   
   static function canView() {
      return Session::haveRight('plugin_resources', READ);
   }

   static function canCreate() {
      return Session::haveRightsOr('entity_dropdown', array(CREATE, UPDATE, DELETE));
   }
   
   function getAdditionalFields() {

      $tab = array(array('name'  => 'code',
                         'label' => __('Code', 'resources'),
                         'type'  => 'text',
                         'list'  => true),
                     array('name' => "",
                         'label' => __('Wizard resource creation', 'resources'),
                         'type'  => '',
                         'list'  => false),
                     array('name'  => 'use_employee_wizard',
                         'label' => __('Enter employer information about the resource', 'resources'),
                         'type'  => 'bool',
                         'list'  => true),
                     array('name'  => 'use_need_wizard',
                         'label' => __('Enter the computing needs of the resource', 'resources'),
                         'type'  => 'bool',
                         'list'  => true),
                     array('name'  => 'use_picture_wizard',
                         'label' => __('Add a picture', 'resources'),
                         'type'  => 'bool',
                         'list'  => true)
                   );
      
      return $tab;
   }

   function getSearchOptions() {

      $tab = parent::getSearchOptions();

      $tab[14]['table']         = $this->getTable();
      $tab[14]['field']         = 'code';
      $tab[14]['name']          = __('Code', 'resources');

      $tab[15]['table']         = $this->getTable();
      $tab[15]['field']         = 'use_employee_wizard';
      $tab[15]['name']          = __('Enter general information about the resource', 'resources');
      $tab[15]['datatype']      = 'bool';

      $tab[16]['table']         = $this->getTable();
      $tab[16]['field']         = 'use_need_wizard';
      $tab[16]['name']          = __('Enter employer information about the resource', 'resources');
      $tab[16]['datatype']      = 'bool';

      $tab[17]['table']         = $this->getTable();
      $tab[17]['field']         = 'use_picture_wizard';
      $tab[17]['name']          = __('Add a picture', 'resources');
      $tab[17]['datatype']      = 'bool';
      
      return $tab;
   }

   static function checkWizardSetup($ID,$field) {
      global $DB;

      if ($ID>0) {
         $resource = new PluginResourcesResource();
         $self = new self();
         
         if ($resource->getFromDB($ID)) {
            if ($self->getFromDB($resource->fields["plugin_resources_contracttypes_id"])) {
               if ($self->fields[$field] > 0)
                  return true;
            }
         }
      }
      return false;
   }
   
   static function transfer($ID, $entity) {
      global $DB;

      if ($ID>0) {
         // Not already transfer
         // Search init item
         $query = "SELECT *
                   FROM `glpi_plugin_resources_contracttypes`
                   WHERE `id` = '$ID'";

         if ($result=$DB->query($query)) {
            if ($DB->numrows($result)) {
               $data = $DB->fetch_assoc($result);
               $data = Toolbox::addslashes_deep($data);
               $input['name'] = $data['name'];
               $input['entities_id']  = $entity;
               $temp = new self();
               $newID    = $temp->getID($input);

               if ($newID<0) {
                  $newID = $temp->import($input);
               }

               return $newID;
            }
         }
      }
      return 0;
   }
   
   function dropdownContractType($name, $value = 0) {
      
      $restrict=" 1 = 1 ";
      $restrict.=getEntitiesRestrictRequest(" AND ",$this->getTable(),'','',$this->maybeRecursive());
      $restrict.=" ORDER BY `name`";
      $types = getAllDatasFromTable($this->getTable(),$restrict);
      
      $option[0] = __('Without contract', 'resources');
      
      if (!empty($types)) {
         
         foreach ($types as $type) {
            $option[$type["id"]] = $type["name"];
         }
      }
      
      return Dropdown::showFromArray($name, $option, array('value'  => $value));
   }
   
   function getContractTypeName($value) {
      
      switch ($value) {
         case 0 :
            return __('Without contract', 'resources');
         default :
            if($this->getFromDB($value)) {
               $name = "";
               if (isset($this->fields["name"])) {
                  $name = $this->fields["name"];
               }
               return $name;
            }
      }
   }
   
}

?>