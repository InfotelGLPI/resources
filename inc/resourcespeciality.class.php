<?php
/*
 * @version $Id: resourcespeciality.class.php 480 2012-11-09 tynet $
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

class PluginResourcesResourceSpeciality extends CommonDropdown {
   
   var $can_be_translated  = true;
   
   static function getTypeName($nb=0) {

      return _n('Speciality', 'Specialities', $nb, 'resources');
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

      return array(array('name'  => 'plugin_resources_ranks_id',
                        'label' => __('Rank', 'resources'),
                        'type'  => 'dropdownValue',
                        'list'  => true),
                  );
   }

   /**
    * Display list of specialities depending on rank
    *
    * @static
    * @param $options
    */
   static function showSpeciality($options){

      $rankId = $options['plugin_resources_ranks_id'];
      $entity = $options['entity'];
      $rand = $options['rand'];

      if ($rankId>0) {

         $condition = " `plugin_resources_ranks_id` = '".$rankId."'";

         Dropdown::show('PluginResourcesResourceSpeciality', array('entity' => $entity,
                  'condition' => $condition));

         } else {
            echo "<select name='plugin_resources_resourcespecialities_id'
                        id='dropdown_plugin_resources_resourcespecialities_id$rand'>";
            echo "<option value='0'>".Dropdown::EMPTY_VALUE."</option></select>";
      }
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
                   FROM `glpi_plugin_resources_resourcespecialities`
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

      $tab[17]['table']         = 'glpi_plugin_resources_ranks';
      $tab[17]['field']         = 'name';
      $tab[17]['name']          = __('Rank', 'resources');
      $tab[17]['datatype']      = 'dropdown';

      return $tab;
   }


}

?>