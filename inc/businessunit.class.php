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
 * Class PluginResourcesBudgetType
 */
class PluginResourcesBusinessUnit extends CommonDropdown {

   var $can_be_translated  = true;

   /**
    * @since 0.85
    *
    * @param $nb
    **/
   static function getTypeName($nb = 0) {

      return _n('Business Unit', 'Business Units', $nb, 'resources');
   }

   /**
    * Have I the global right to "create" the Object
    * May be overloaded if needed (ex KnowbaseItem)
    *
    * @return booleen
    **/
   static function canCreate(): bool
   {
      return Session::haveRight('dropdown', UPDATE);
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
      return Session::haveRight('dropdown', READ);
   }

   /**
    * Return Additional Fields for this type
    *
    * @return array
    **/
   function getAdditionalFields() {

      return [
//          ['name'  => 'code',
//                         'label' => __('Code', 'resources'),
//                         'type'  => 'text',
//                         'list'  => true],
                  ];
   }



   /**
    * @return array
    */
   function rawSearchOptions() {

      $tab = parent::rawSearchOptions();

      $tab[] = [
         'id'            => '14',
         'table'         => $this->getTable(),
         'field'         => 'name',
         'name'          => __('Name')
      ];

      return $tab;
   }

}

