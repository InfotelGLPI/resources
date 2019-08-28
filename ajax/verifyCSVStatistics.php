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

include ('../../../inc/includes.php');
header("Content-Type: application/json; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

if (isset($_GET['page']) && isset($_GET['file'])) {

   $pluginResourcesImportResource = new PluginResourcesImportResource();

   $absoluteFilePath = $pluginResourcesImportResource::getLocationOfVerificationFiles() . "/" . $_GET['file'];

   if (file_exists($absoluteFilePath)) {

      $importId = $pluginResourcesImportResource->verifyFileHeader($absoluteFilePath);

      // First line is header
      $lines = $pluginResourcesImportResource->readCSVLines($absoluteFilePath, 1, INF);

      foreach($lines as $keyLine=>$line){
         foreach($line as $keyData=>$data){
            if(is_string($data) && !empty($data)){
               $temp = iconv(mb_detect_encoding($data, mb_detect_order(), true),"UTF-8", $data);
               $lines[$keyLine][$keyData] = $temp;
            }
         }
      }

      // Recover the header of file FIRST LINE
      $temp = $pluginResourcesImportResource->readCSVLines($absoluteFilePath, 0, 0);
      $header = array_shift($temp);

      switch($_GET['page']){

         case PluginResourcesImportResource::VERIFY_FILE:

            $result = [
               'identical' => 0,
               'different' => 0,
               'not_found' => 0,
               'total' => 0
            ];

            $result['total'] = count($lines);

            foreach ($lines as $line) {

               $datas = $pluginResourcesImportResource->parseFileLine($header, $line, $importId);

               // Find identifiers
               $firstLevelIdentifiers = [];
               $secondLevelIdentifiers = [];
               $allDatas = [];

               foreach ($datas as $data) {

                  $pluginResourcesImportColumn = new PluginResourcesImportColumn();
                  $pluginResourcesImportColumn->getFromDB($data['plugin_resources_importcolumns_id']);

                  $element = [
                     'name' => $data['name'],
                     'value' => $data['value'],
                     'type' => $data['plugin_resources_importcolumns_id'],
                     'resource_column' => $pluginResourcesImportColumn->getField('resource_column')
                  ];

                  $allDatas[] = $element;

                  switch ($pluginResourcesImportColumn->getField('is_identifier')) {
                     case 1:
                        $firstLevelIdentifiers[] = $element;
                        break;
                     case 2:
                        $secondLevelIdentifiers[] = $element;
                        break;
                  }
               }

               $status = null;

               $resourceID = $pluginResourcesImportResource->findResource($firstLevelIdentifiers);
               if (is_null($resourceID)) {
                  $resourceID = $pluginResourcesImportResource->findResource($secondLevelIdentifiers);
               }

               $pluginResourcesResource = new PluginResourcesResource();

               if (!$resourceID) {
                  $result['not_found'] ++;
               } else {
                  // Test Field in resources
                  if ($pluginResourcesResource->isDifferentFromImportResourceDatas($resourceID, $allDatas)) {
                     $result['different'] ++;
                  } else {
                     $result['identical'] ++;
                  }
               }
            }

            echo json_encode($result);
            break;
         case PluginResourcesImportResource::VERIFY_GLPI:

            $result = [
               'found_first_identifier' => 0,
               'found_second_identifier' => 0,
               'not_found' => 0,
               'total' => 0
            ];

            // Resource identifiers
            $pluginResourcesImportColumn = new PluginResourcesImportColumn();
            $crit = [$pluginResourcesImportColumn::$items_id => $importId];
            $columns = $pluginResourcesImportColumn->find($crit);

            // Get resources
            $pluginResourcesResource = new PluginResourcesResource();
            $resources = $pluginResourcesResource->find(['is_deleted' => 0], ['date_declaration DESC']);
            $nbOfResources = (new DBUtils)->countElementsInTable(PluginResourcesResource::getTable(), ['is_deleted' => 0]);

            $result['total'] = $nbOfResources;

            $firstLevelResourceColumns = [];
            $secondLevelResourceColumns = [];

            foreach ($columns as $column) {

               // Target : table Resource or ResourceImport
               // Name : name of the column in table
               $identifier = [
                  'target' => null,
                  'name' => null
               ];

               switch ($column['resource_column']) {
                  case 10:
                     $identifier['target'] = PluginResourcesResourceImport::class;
                     $identifier['name'] = $column['name'];
                     break;
                  default:
                     $identifier['target'] = PluginResourcesResource::class;
                     $identifier['name'] = PluginResourcesResource::getColumnName($column['resource_column']);
                     break;
               }

               switch ($column['is_identifier']) {
                  case 1:
                     $firstLevelResourceColumns[] = $identifier;
                     break;
                  case 2:
                     $secondLevelResourceColumns[] = $identifier;
                     break;
               }
            }

            $pluginResourcesResourceImport = new PluginResourcesResourceImport();

            foreach ($resources as $resource) {

               $firstLevel = false;
               $secondLevel = false;

               $firstLevelResourceDatas = [];
               $secondLevelResourceDatas = [];

               // First level identifier
               foreach ($firstLevelResourceColumns as $firstLevelResourceColumn) {

                  switch ($firstLevelResourceColumn['target']) {
                     case PluginResourcesResourceImport::class:
                        $crit = [
                           $pluginResourcesResourceImport::$items_id => $resource['id'],
                           'name' => iconv(mb_detect_encoding($firstLevelResourceColumn['name'], mb_detect_order(), true),"UTF-8", $firstLevelResourceColumn['name'])
                        ];

                        if ($pluginResourcesResourceImport->getFromDBByCrit($crit)) {
                           $firstLevelResourceDatas[] = $pluginResourcesResourceImport->getField('value');
                        }
                        break;
                     case PluginResourcesResource::class:

                        $firstLevelResourceDatas[] = $resource[$firstLevelResourceColumn['name']];
                        break;
                  }
               }

               // Second level identifier
               foreach ($secondLevelResourceColumns as $secondLevelResourceColumn) {

                  switch ($secondLevelResourceColumn['target']) {
                     case PluginResourcesResourceImport::class:

                        $crit = [
                           $pluginResourcesResourceImport::$items_id => $resource['id'],
                           'name' => iconv(mb_detect_encoding($secondLevelResourceColumn['name'], mb_detect_order(), true),"UTF-8", $secondLevelResourceColumn['name'])
                        ];

                        if ($pluginResourcesResourceImport->getFromDBByCrit($crit)) {
                           $secondLevelResourceDatas[] = $pluginResourcesResourceImport->getField('value');
                        }

                        break;
                     case PluginResourcesResource::class:

                        $secondLevelResourceDatas[] = $resource[$secondLevelResourceColumn['name']];
                        break;
                  }
               }



               $foundedLineIndex = null;

               foreach ($lines as $key=>$line) {

                  $firstLevelFound = 0;
                  $firstLevelToFind = count($firstLevelResourceDatas);

                  $secondLevelFound = 0;
                  $secondLevelToFind = count($secondLevelResourceDatas);

                  // Find identifier in line
                  foreach ($line as $data) {

                     foreach ($firstLevelResourceDatas as $firstLevelResourceData) {

                        if (is_string($data) && empty($data)) {
                           continue;
                        }

                        if (strcasecmp($data, $firstLevelResourceData) == 0) {
                           $firstLevelFound++;
                           break;
                        }
                     }
                     if ($firstLevelToFind === $firstLevelFound) {
                        $firstLevel = true;
                        $foundedLineIndex = $key;
                        break 2;
                     } else {
                        // We check second level identifiers when first was not found
                        foreach ($secondLevelResourceDatas as $secondLevelResourceData) {

                           if (is_string($data) && empty($data)) {
                              continue;
                           }

                           if (strcasecmp($data, $secondLevelResourceData) == 0) {
                              $secondLevelFound++;
                              break;
                           }
                        }
                        if ($secondLevelToFind === $secondLevelFound) {
                           $secondLevel = true;
                           $foundedLineIndex = $key;
                           break 2;
                        }
                     }
                  }
               }

               if(!is_null($foundedLineIndex)){
                  unset($lines[$foundedLineIndex]);
               }

               if (!$firstLevel && !$secondLevel) {
                  $result['not_found'] ++;
               } else {
                  if ($firstLevel) {
                     $result['found_first_identifier'] ++;
                  } else if ($secondLevel) {
                     $result['found_second_identifier'] ++;
                  }
               }
            }

            echo json_encode($result);
            break;
      }
   }
}