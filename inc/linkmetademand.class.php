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
 * Class PluginResourcesBudget
 */
class PluginResourcesLinkmetademand extends CommonDBTM {

   static $rightname = 'plugin_resources_checklist';
   // From CommonDBTM
   public $dohistory = true;

   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @param integer $nb Number of items
    *
    * @return string
    **/
   static function getTypeName($nb = 0) {

      return __('Link metademands','resources');
   }

   /**
    * Have I the global right to "view" the Object
    *
    * Default is true and check entity if the objet is entity assign
    *
    * May be overloaded if needed
    *
    * @return bool
    **/
   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   /**
    * Have I the global right to "create" the Object
    * May be overloaded if needed (ex KnowbaseItem)
    *
    * @return bool
    **/
   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
   }


   /**
    * @param      $metademands_id
    * @param      $selected_value
    * @param bool $display
    * @param      $idF
    *
    * @return int|string
    */
   static function showChecklistInDropdown($metademands_id, $selected_value, $idF, $display = true) {


      $fields      = new self();
      $fields_data = $fields->find(['plugin_metademands_metademands_id' => $metademands_id,"plugin_metademands_fields_id"=>$idF]);
      $data        = [Dropdown::EMPTY_VALUE];
      $checlist = new PluginResourcesChecklistconfig();
      $checlists = $checlist->find();
      foreach ($checlists as $id => $value) {
            $data[$id] = urldecode(html_entity_decode($value['name']));
      }

      return Dropdown::showFromArray('checklist_in[]', $data, ['value' => $selected_value, 'display' => $display]);
   }

   /**
    * @param      $metademands_id
    * @param      $selected_value
    * @param bool $display
    * @param      $idF
    *
    * @return int|string
    */
   static function showChecklistOutDropdown($metademands_id, $selected_value, $idF, $display = true) {


      $fields      = new self();
      $fields_data = $fields->find(['plugin_metademands_metademands_id' => $metademands_id,"plugin_metademands_fields_id"=>$idF]);
      $data        = [Dropdown::EMPTY_VALUE];
      $checlist = new PluginResourcesChecklistconfig();
      $checlists = $checlist->find();
      foreach ($checlists as $id => $value) {
         $data[$id] = urldecode(html_entity_decode($value['name']));
      }

      return Dropdown::showFromArray('checklist_out[]', $data, ['value' => $selected_value, 'display' => $display]);
   }


   /**
    * @param      $metademands_id
    * @param      $selected_value
    * @param bool $display
    * @param      $idF
    *
    * @return int|string
    */
   static function showHabilitationDropdown($metademands_id, $selected_value, $idF, $display = true) {

      $fields      = new self();
      $data        = [Dropdown::EMPTY_VALUE];
      $habilitation = new PluginResourcesHabilitation();
      $habilitations = $habilitation->find();
      foreach ($habilitations as $id => $value) {
         $data[$id] = urldecode(html_entity_decode($value['name']));
      }

      return Dropdown::showFromArray('habilitation[]', $data, ['value' => $selected_value, 'display' => $display]);
   }






}

