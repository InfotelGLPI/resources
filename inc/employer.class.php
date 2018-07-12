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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginResourcesEmployer extends CommonTreeDropdown {

   var $can_be_translated  = true;

   static function getTypeName($nb = 0) {

      return _n('Employer', 'Employers', $nb, 'resources');
   }

   static function canView() {
      return Session::haveRight('plugin_resources', READ);
   }

   static function canCreate() {
      return Session::haveRightsOr('dropdown', [CREATE, UPDATE, DELETE]);
   }

   function getAdditionalFields() {

      return [ [ 'name'  => $this->getForeignKeyField(),
                 'label' => __('As child of'),
                 'type'  => 'parent',
                 'list'  => false],
               ['name'  => 'short_name',
                'label' => __('Short name', 'resources'),
                'type'  => 'text',
                'list'  => true],
               ['name'  => 'locations_id',
                'label' => __('Location'),
                'type'  => 'dropdownValue',
                'list'  => true]];
   }

   function rawSearchOptions() {

      $tab = [];

      $tab[] = [
         'id'   => 'common',
         'name' => self::getTypeName(2)
      ];

      $tab[] = [
         'id'       => '14',
         'table'    => $this->getTable(),
         'field'    => 'short_name',
         'name'     => __('Short name', 'resources'),
         'datatype' => 'text'
      ];
      $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

      return $tab;
   }

   /**
    * @param $field
    * @param $values
    * @param $options   array
    *
    * @return return|status|string
    */
   static function getSpecificValueToDisplay($field, $values, array $options = []) {
      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'id':
           return dropdown::getYesNo(PluginResourcesClient::isSecurityCompliance($values[$field]));
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }



   static function transfer($ID, $entity) {
      global $DB;

      if ($ID>0) {
         // Not already transfer
         // Search init item
         $query = "SELECT *
                   FROM `glpi_plugin_resources_employers`
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
}

