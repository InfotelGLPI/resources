<?php
/*
 * @version $Id: resourcesituation.class.php 480 2012-11-09 tynet $
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

class PluginResourcesResourceSituation extends CommonDropdown {
   
   var $can_be_translated  = true;
   
   static function getTypeName($nb=0) {

      return _n('Public status', 'Public statuses', $nb, 'resources');
   }

   static function canCreate() {
      if (Session::haveRight('dropdown',UPDATE)
         && Session::haveRight('plugin_resources_dropdown_public', UPDATE)){
         return true;
      }
      return false;
   }

   static function canView() {
      if (Session::haveRight('plugin_resources_dropdown_public', READ)){
         return true;
      }
      return false;
   }

   function getAdditionalFields() {
   
      return array(array('name'  => 'code',
                         'label' => __('Code', 'resources'),
                         'type'  => 'text',
                         'list'  => true),
                  array('name'  => 'short_name',
                        'label' => __('Short name', 'resources'),
                        'type'  => 'text',
                        'list'  => true),
                  array('name'  => 'is_contract_linked',
                        'label' => __('Is linked to a contract', 'resources'),
                        'type'  => 'bool',
                        'list'  => true),
                  );
   }

   /**
    * During resource's transfer
    *
    * @static
    * @param $ID
    * @param $entity
    * @return ID|int|the
    */
   static function transfer($ID, $entity) {
      global $DB;

      if ($ID>0) {
         // Not already transfer
         // Search init item
         $query = "SELECT *
                   FROM `glpi_plugin_resources_resourcesituations`
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

   function getSearchOptions() {

      $tab = parent::getSearchOptions();

      $tab[14]['table']         = $this->getTable();
      $tab[14]['field']         = 'code';
      $tab[14]['name']          = __('Code', 'resources');

      $tab[16]['table']         = $this->getTable();
      $tab[16]['field']         = 'short_name';
      $tab[16]['name']          = __('Short name', 'resources');

      $tab[17]['table']         = $this->getTable();
      $tab[17]['field']         = 'is_contract_linked';
      $tab[17]['name']          = __('Is linked to a contract', 'resources');
      $tab[17]['datatype']      = 'bool';

      return $tab;
   }



}

?>