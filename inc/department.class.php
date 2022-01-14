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
 * Class PluginResourcesDepartment
 */
class PluginResourcesDepartment extends CommonDropdown {

   var $can_be_translated  = true;



   /**
    * @since 0.85
    *
    * @param $nb
    **/
   static function getTypeName($nb = 0) {

      return _n('Department', 'Departments', $nb, 'resources');
   }

   /**
    * Have I the global right to "view" the Object
    *
    * Default is true and check entity if the objet is entity assign
    *
    * May be overloaded if needed
    *
    * @return booleen
    **/
   static function canView() {
      return Session::haveRight('plugin_resources', READ);
   }

   /**
    * Have I the global right to "create" the Object
    * May be overloaded if needed (ex KnowbaseItem)
    *
    * @return booleen
    **/
   static function canCreate() {
      return Session::haveRightsOr('dropdown', [CREATE, UPDATE, DELETE]);
   }

   function getAdditionalFields() {

      return [
         //         ['name'  => 'plugin_release_typerollbacks_id',
         //            'label' => __('Type test','Type tests', 'release'),
         //            'type'  => 'dropdownRollbacks',
         //         ],
                  ['name'  => 'plugin_resources_employers_id',
                     'label' => _n('Employer', 'Employers', 1, 'resources'),
                     'type'  => 'dropdownEmployers',
                  ],


      ];
   }

   /**
    * @see CommonDropdown::displaySpecificTypeField()
    **/
   function displaySpecificTypeField($ID, $field = [], array $options = []) {

      switch ($field['type']) {
         //         case 'dropdownRollbacks' :
         //            PluginReleaseTypeR::dropdown(["name"=>"plugin_release_typetests_id"]);
         //            break;
         case 'dropdownEmployers' :
            $this->getFromDB($ID);
            PluginResourcesEmployer::dropdown(["name" => "plugin_resources_employers_id","value"=>$this->fields["plugin_resources_employers_id"]]);
            break;

      }
   }

   function rawSearchOptions() {
      $tab = parent::rawSearchOptions();



      $tab[] = [
         'id'       => '103',
         'name'     => _n('Employer', 'Employers', 1, 'resources'),
         'field'    => 'name',
         'table'    => getTableForItemType('PluginResourcesEmployer'),
         'datatype' => 'dropdown'
      ];

      return $tab;
   }

   /**
    * @param $ID
    * @param $entity
    *
    * @return int|\the
    */
   static function transfer($ID, $entity) {
      global $DB;

      if ($ID>0) {
         // Not already transfer
         // Search init item
         $query = "SELECT *
                   FROM `glpi_plugin_resources_departments`
                   WHERE `id` = '$ID'";

         if ($result=$DB->query($query)) {
            if ($DB->numrows($result)) {
               $data = $DB->fetchAssoc($result);
               $data = Toolbox::addslashes_deep($data);
               $input['name'] = $data['name'];
               $input['entities_id']  = $entity;
               $temp = new self();

               $newID    = $temp->getID();

               if ($newID<0) {
                  $newID = $temp->import($input);
               }

               return $newID;
            }
         }
      }
      return 0;
   }
}

