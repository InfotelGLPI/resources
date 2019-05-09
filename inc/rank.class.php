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

/**
 * Class PluginResourcesRank
 */
class PluginResourcesRank extends CommonDropdown {

   /**
    * @since 0.85
    *
    * @param $nb
    **/
   static function getTypeName($nb = 0) {

      return _n('Rank', 'Ranks', $nb, 'resources');
   }

   /**
    * Have I the global right to "create" the Object
    * May be overloaded if needed (ex KnowbaseItem)
    *
    * @return booleen
    **/
   static function canCreate() {
      if (Session::haveRight('dropdown', UPDATE)
         && Session::haveRight('plugin_resources_dropdown_public', UPDATE)) {
         return true;
      }
      return false;
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
      if (Session::haveRight('plugin_resources_dropdown_public', READ)) {
         return true;
      }
      return false;
   }

   /**
    * Return Additional Fields for this type
    *
    * @return array
    **/
   function getAdditionalFields() {

      return [['name'  => 'code',
                         'label' => __('Code', 'resources'),
                         'type'  => 'text',
                         'list'  => true],
                  ['name'  => 'short_name',
                        'label' => __('Short name', 'resources'),
                        'type'  => 'text',
                        'list'  => true],
                  ['name'  => 'plugin_resources_professions_id',
                        'label' => __('Profession', 'resources'),
                        'type'  => 'dropdownValue',
                        'list'  => true],
                  ['name'  => 'begin_date',
                        'label' => __('Begin date'),
                        'type'  => 'date',
                        'list'  => false],
                  ['name'  => 'end_date',
                        'label' => __('End date'),
                        'type'  => 'date',
                        'list'  => false],
                  ['name'  => 'is_active',
                        'label' => __('Active'),
                        'type'  => 'bool',
                        'list'  => true],
                  ];
   }

   /**
    * @return array
    */
   function getSearchOptions() {

      $tab = parent::getSearchOptions();

      $tab[24]['table']         = $this->getTable();
      $tab[24]['field']         = 'code';
      $tab[24]['name']          = __('Code', 'resources');

      $tab[25]['table']         = $this->getTable();
      $tab[25]['field']         = 'short_name';
      $tab[25]['name']         = __('Short name', 'resources');

      $tab[27]['table']         = 'glpi_plugin_resources_professions';
      $tab[27]['field']         = 'name';
      $tab[27]['name']          = __('Profession', 'resources');
      $tab[27]['datatype']      = 'dropdown';

      $tab[28]['table']         = $this->getTable();
      $tab[28]['field']         = 'is_active';
      $tab[28]['name']          = __('Active');
      $tab[28]['datatype']      = 'bool';

      $tab[29]['table']         = $this->getTable();
      $tab[29]['field']         = 'begin_date';
      $tab[29]['name']          = __('Begin date');
      $tab[29]['datatype']      = 'date';

      $tab[30]['table']         = $this->getTable();
      $tab[30]['field']         = 'end_date';
      $tab[30]['name']          = __('End date');
      $tab[30]['datatype']      = 'date';

      return $tab;
   }


   /**
    * Display a rank's list depending on profession
    *
    * @static
    * @param $options
    */
   static function showRank($options) {
      global $DB;

      $professionId = $options['plugin_resources_professions_id'];
      $entity = $options['entity'];
      $rand = $options['rand'];
      $sort = $options['sort'];

      if ($professionId>0) {

         if ($sort) {
            $query = "SELECT `glpi_plugin_resources_ranks`.*
                     FROM `glpi_plugin_resources_ranks`
                     WHERE `glpi_plugin_resources_ranks`.`plugin_resources_professions_id` = '" . $professionId . "'";

            $values[0] = Dropdown::EMPTY_VALUE;
            if ($result = $DB->query($query)) {
               while ($data = $DB->fetch_array($result)) {
                  $values[$data['id']] = $data['name'];
               }
            }
            Dropdown::showFromArray('plugin_resources_ranks_id', $values);

         } else {
            $condition = " `plugin_resources_professions_id` = '".$professionId."'";

            Dropdown::show('PluginResourcesRank', ['entity' => $entity,
               'condition' => $condition]);
         }

      } else {
         echo "<select name='plugin_resources_ranks_id'
                        id='dropdown_plugin_resources_ranks_id$rand'>";
         echo "<option value='0'>".Dropdown::EMPTY_VALUE."</option></select>";
      }
   }

   /**
    * During resource or employment transfer
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
                   FROM `glpi_plugin_resources_ranks`
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

               //transfert of the linked profession
               $profession = PluginResourcesProfession::transfer($temp->fields["plugin_resources_professions_id"], $entity);
               if ($profession > 0) {
                  $values["id"] = $newID;
                  $values["plugin_resources_professions_id"] = $profession;
                  $temp->update($values);
               }

               return $newID;
            }
         }
      }
      return 0;
   }

   /**
    * when a rank is deleted -> deletion of the linked specialities
    *
    * @return nothing|void
    */
   function cleanDBonPurge() {

      $temp = new PluginResourcesResourceSpeciality();
      $temp->deleteByCriteria(['plugin_resources_ranks_id' => $this->fields['id']]);

   }

   /**
    * is_active = 1 during a creation
    *
    * @return nothing|void
    */
   function post_getEmpty() {

      $this->fields['is_active'] = 1;
   }

}
