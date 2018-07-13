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

class PluginResourcesClient extends CommonDropdown {

   static function getTypeName($nb = 0) {

      return _n('Affected client', 'Affected clients', $nb, 'resources');
   }

   static function canView() {
      return Session::haveRight('plugin_resources', READ);
   }

   static function canCreate() {
      return Session::haveRightsOr('dropdown', [CREATE, UPDATE, DELETE]);
   }

   public function defineTabs($options = []) {
      $ong = parent::defineTabs();
      $this->addStandardTab('PluginResourcesResource', $ong, $options);
      $this->addStandardTab('Document_Item', $ong, $options);

      return $ong;
   }

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

   function rawSearchOptions() {

      $tab = parent::rawSearchOptions();

      $tab[] = [
         'id'         => '14',
         'table'      => $this->getTable(),
         'field'      => 'security_and',
         'name'       => __('AND - Certificate of non-dissimulation', 'resources'),
         'injectable' => true,
         'datatype'   => 'bool'
      ];

      $tab[] = [
         'id'         => '15',
         'table'      => $this->getTable(),
         'field'      => 'security_fifour',
         'name'       => __('FIFOUR - Supplier\'s sheet', 'resources'),
         'injectable' => true,
         'datatype'   => 'bool'
      ];

      $tab[] = [
         'id'         => '18',
         'table'      => $this->getTable(),
         'field'      => 'security_gisf',
         'name'       => __('GISF - Supplier security incident management', 'resources'),
         'injectable' => true,
         'datatype'   => 'bool'
      ];

      $tab[] = [
         'id'         => '17',
         'table'      => $this->getTable(),
         'field'      => 'security_cfi',
         'name'       => __('CFI - Supplier card', 'resources'),
         'injectable' => true,
         'datatype'   => 'bool'
      ];


      return $tab;
   }

   static function isSecurityCompliance($id) {
      $client = new self();

      return $client->isSecurityAND($id) && $client->isSecurityFIFOUR($id)
             && $client->isSecurityGISF($id) && $client->isSecurityCFI($id);

   }

   static function isSecurityAND($id) {
      $client = new self();

      if ($client->getFromDB($id)) {
         return $client->fields['security_and'];
      }
      return false;

   }

   static function isSecurityFIFOUR($id) {
      $client = new self();

      if ($client->getFromDB($id)) {
         return $client->fields['security_fifour'];
      }
      return false;

   }

   static function isSecurityGISF($id) {
      $client = new self();

      if ($client->getFromDB($id)) {
         return $client->fields['security_gisf'];
      }
      return false;

   }

   static function isSecurityCFI($id) {
      $client = new self();

      if ($client->getFromDB($id)) {
         return $client->fields['security_cfi'];
      }
      return false;

   }
}

