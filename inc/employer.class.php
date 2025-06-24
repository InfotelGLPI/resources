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
 * Class PluginResourcesEmployer
 */
class PluginResourcesEmployer extends CommonTreeDropdown {

   var $can_be_translated  = true;

   /**
    * @since 0.85
    *
    * @param $nb
    **/
   static function getTypeName($nb = 0) {

      return _n('Employer', 'Employers', $nb, 'resources');
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
   static function canView(): bool
   {
      return Session::haveRight('plugin_resources', READ);
   }

   /**
    * Have I the global right to "create" the Object
    * May be overloaded if needed (ex KnowbaseItem)
    *
    * @return booleen
    **/
   static function canCreate(): bool
   {
      return Session::haveRightsOr('dropdown', [CREATE, UPDATE, DELETE]);
   }

   /**
    * Return Additional Fileds for this type
    **/
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
                'list'  => true],
               ['name'  => 'second_list',
                'label' => __('Second part list of employer','resources'),
                'type'  => 'bool',
                'list'  => true],

      ];
   }

   /**
    * Get search function for the class
    *
    * @return array of search option
    **/
   function rawSearchOptions() {

      $tab = parent::rawSearchOptions();

      foreach ($tab as $key => $t) {
         if ($t['id']==13) {
            unset($tab[$key]);
         }
      }

      $tab[] = [
         'id'       => '15',
         'table'    => $this->getTable(),
         'field'    => 'short_name',
         'name'     => __('Short name', 'resources'),
         'datatype' => 'text'
      ];
      $tab[] = [
         'id'       => '17',
         'table'    => $this->getTable(),
         'field'    => 'second_list',
         'name'     => __('Second part list of employer', 'resources'),
         'datatype' => 'bool'
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


   /**
    * @param $ID
    * @param $entity
    *
    * @return int|\the
    */
   static function transfer($ID, $entity) {
      global $DB;

       if ($ID > 0) {
           $table = self::getTable();
           $iterator = $DB->request([
               'FROM'   => $table,
               'WHERE'  => ['id' => $ID]
           ]);

           foreach ($iterator as $data) {
               $input['name']        = $data['name'];
               $input['entities_id'] = $entity;
               $temp                 = new self();
               $newID                = $temp->getID();
               if ($newID < 0) {
                   $newID = $temp->import($input);
               }

               return $newID;
           }
       }
      return 0;
   }
}

