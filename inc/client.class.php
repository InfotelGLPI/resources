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
 * Class PluginResourcesClient
 */
class PluginResourcesClient extends CommonDropdown {

   /**
    * @since 0.85
    *
    * @param $nb
    **/
   static function getTypeName($nb = 0) {

      return _n('Affected client', 'Affected clients', $nb, 'resources');
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

   /**
    * @param array $options
    *
    * @return array
    */
   public function defineTabs($options = []) {
      $ong = parent::defineTabs();
      $this->addStandardTab('PluginResourcesResource', $ong, $options);
      $this->addStandardTab('Document_Item', $ong, $options);

      return $ong;
   }

   /**
    * Return Additional Fields for this type
    *
    * @return array
    **/
   function getAdditionalFields() {

      $config = new PluginResourcesConfig();
      if ($config->useSecurityCompliance()) {
         return [['name'  => 'security_and',
                            'label' => __('AND - Certificate of non-dissimulation', 'resources'),
                            'type'  => 'bool',
                            'list'  => true],
                      ['name'  => 'security_fifour',
                            'label' => __('FIFOUR - Supplier\'s sheet', 'resources'),
                            'type'  => 'bool',
                            'list'  => true],
                      ['name'  => 'security_gisf',
                            'label' => __('GISF - Supplier security incident management', 'resources'),
                            'type'  => 'bool',
                            'list'  => true],
                      ['name'  => 'security_cfi',
                            'label' => __('CFI - Supplier card', 'resources'),
                            'type'  => 'bool',
                            'list'  => true],
         ];
      } else {
         return [];
      }
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
         // Not already transfer
         // Search init item
         $query = "SELECT *
                   FROM `glpi_plugin_resources_clients`
                   WHERE `id` = '$ID'";

         if ($result = $DB->query($query)) {
            if ($DB->numrows($result)) {
               $data                 = $DB->fetch_assoc($result);
               $data                 = Toolbox::addslashes_deep($data);
               $input['name']        = $data['name'];
               $input['entities_id'] = $entity;
               $temp                 = new self();
               $newID                = $temp->getID($input);

               if ($newID < 0) {
                  $newID = $temp->import($input);
               }

               return $newID;
            }
         }
      }
      return 0;
   }

   /**
    * @return array
    */
   function rawSearchOptions() {

      $tab = parent::getSearchOptions();

      $tab[14]['table']         = $this->getTable();
      $tab[14]['field']         = 'security_and';
      $tab[14]['name']          = __('AND - Certificate of non-dissimulation', 'resources');
      $tab[14]['injectable']    = true;
      $tab[14]['datatype']      = 'bool';

      $tab[15]['table']         = $this->getTable();
      $tab[15]['field']         = 'security_fifour';
      $tab[15]['name']          = __('FIFOUR - Supplier\'s sheet', 'resources');
      $tab[15]['injectable']    = true;
      $tab[15]['datatype']      = 'bool';

      $tab[16]['table']         = $this->getTable();
      $tab[16]['field']         = 'security_gisf';
      $tab[16]['name']          = __('GISF - Supplier security incident management', 'resources');
      $tab[16]['injectable']    = true;
      $tab[16]['datatype']      = 'bool';

      $tab[17]['table']         = $this->getTable();
      $tab[17]['field']         = 'security_cfi';
      $tab[17]['name']          = __('CFI - Supplier card', 'resources');
      $tab[17]['injectable']    = true;
      $tab[17]['datatype']      = 'bool';

      return $tab;
   }

   /**
    * @param $id
    *
    * @return bool
    */
   static function isSecurityCompliance($id) {
      $client = new self();

      return $client->isSecurityAND($id) && $client->isSecurityFIFOUR($id)
             && $client->isSecurityGISF($id) && $client->isSecurityCFI($id);

   }

   /**
    * @param $id
    *
    * @return bool
    */
   static function isSecurityAND($id) {
      $client = new self();

      if ($client->getFromDB($id)) {
         return $client->fields['security_and'];
      }
      return false;

   }

   /**
    * @param $id
    *
    * @return bool
    */
   static function isSecurityFIFOUR($id) {
      $client = new self();

      if ($client->getFromDB($id)) {
         return $client->fields['security_fifour'];
      }
      return false;

   }

   /**
    * @param $id
    *
    * @return bool
    */
   static function isSecurityGISF($id) {
      $client = new self();

      if ($client->getFromDB($id)) {
         return $client->fields['security_gisf'];
      }
      return false;

   }

   /**
    * @param $id
    *
    * @return bool
    */
   static function isSecurityCFI($id) {
      $client = new self();

      if ($client->getFromDB($id)) {
         return $client->fields['security_cfi'];
      }
      return false;

   }
}

