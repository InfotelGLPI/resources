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
 * Class PluginResourcesImportResource
 */
class PluginResourcesImportResource extends CommonDBTM
{

   static $rightname = 'plugin_resources_importresources';

   static $keyInOtherTables = 'plugin_resources_importresources_id';

   const NEW_IMPORTS = 0;
   const CONFLICTED_IMPORTS = 1;

   const IDENTIFIER_LEVELS = 2;


   static function getIndexUrl()
   {
      global $CFG_GLPI;
      return $CFG_GLPI["root_doc"] . "/plugins/resources/front/importresource.php";
   }

   function updateDatas($datas, $importResourceID){

      $pluginResourcesImportResourceData = new PluginResourcesImportResourceData();
      $importResourceDatas = $pluginResourcesImportResourceData->find([PluginResourcesImportResourceData::$items_id => $importResourceID]);

      // Delete all import data
      foreach($importResourceDatas as $importResourceData){

         foreach($datas as $data){

            if($data['name'] != $importResourceData['name']){
               continue;
            }

            if($data['value'] == $importResourceData['value']){
               continue;
            }

            $input = [
               PluginResourcesImportResourceData::getIndexName() => $importResourceData['id'],
               "value" => addslashes($data['value']),
            ];

            $pluginResourcesImportResourceData->update($input);
            break;
         }
      }
   }

   /**
    * Insert or update imports
    *
    * @param $datas
    * @param $importID
    */
   function manageImport($datas, $importID){

      $importResourceID = $this->isExistingImportResourceByDataFromFile($datas, $importResourceID);

      // Override data of existing importResource
      if ($importResourceID) {

         $this->updateDatas($datas, $importResourceID);

      } else {
         // Create new Import Resource
         $newImportId = $this->add([
            "date_creation" => date("Y-m-d H:i:s"),
            PluginResourcesImport::$keyInOtherTables => $importID
         ]);

         $importResourceData = new PluginResourcesImportResourceData();

         foreach ($datas as $item) {

            $input = $importResourceData->prepareInput(
               addslashes($item['name']),
               $item['value'],
               $newImportId,
               $item['plugin_resources_importcolumns_id']
            );

            $importResourceData->add($input);
         }
      }
   }

   /**
    * Search if a resource exist with the same identifiers
    *
    * @param $importResourceID
    */
   function isExistingImportResourceByDataFromFile($columnDatas, &$importResourceID){

      $pluginResourcesImportResourceData = new PluginResourcesImportResourceData();

      // List of existing imports
      $existingImportResources = $this->find();

      foreach($existingImportResources as $existingImportResource){

         $firstLevelIdentifiers = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel($existingImportResource['id'], 1);
         $secondLevelIdentifiers = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel($existingImportResource['id'], 2);

         $firstLevelIdentifierFounded = true;

         foreach($firstLevelIdentifiers as $firstLevelIdentifier){

            foreach($columnDatas as $columnData){

               if ($columnData['name'] != $firstLevelIdentifier['name']) {
                  continue;
               }

               if ($columnData['value'] != $firstLevelIdentifier['value']) {
                  $firstLevelIdentifierFounded = false;
                  break;
               }
            }
         }

         if ($firstLevelIdentifierFounded) {
            return $existingImportResource['id'];
         }

         $secondLevelIdentifierFounded = true;

         foreach($secondLevelIdentifiers as $secondLevelIdentifier){

            foreach($columnDatas as $columnData){

               if ($columnData['name'] != $secondLevelIdentifier['name']) {
                  continue;
               }

               if ($columnData['value'] != $secondLevelIdentifier['value']) {
                  $secondLevelIdentifierFounded = false;
               }
            }
         }

         if($secondLevelIdentifierFounded){
            return $existingImportResource['id'];
         }
      }
      return false;
   }

   function importResourcesFromCSVFile($task)
   {
      // glpi files folder
      $path = GLPI_PLUGIN_DOC_DIR . "/resources/import/";
      // List of files in path
      $files = scandir($path);
      // Exclude dot and dotdot
      $files = array_diff($files, array('.', '..'));

      foreach ($files as $file) {

         $filePath = $path . $file;

         // Just parse files
         if (is_dir($filePath)) {
            continue;
         }

         $import = null;

         if (file_exists($filePath)) {
            $handle = fopen($filePath, 'r');

            $importID = null;
            $header = null;

            $lineIndex = 0;
            while (($line = fgetcsv($handle, 1000, ";")) !== FALSE) {

               if ($lineIndex == 0) {

                  $importID = $this->checkHeader($line);

                  if ($importID <= 0) {
                     break;
                  }
                  $header = $line;

               } else {

                  $datas = $this->parseFileLine($header, $line, $importID);
                  $this->manageImport($datas, $importID);
               }
               $lineIndex++;
            }
         }
      }
      return true;
   }

   function checkHeader($header){

      $pluginResourcesImport = new PluginResourcesImport();
      $pluginResourcesImportColumn = new PluginResourcesImportColumn();

      $imports = $pluginResourcesImport->find();

      foreach ($imports as $import) {
         $nbOfColumns = count($pluginResourcesImportColumn->find([PluginResourcesImport::$keyInOtherTables => $import['id']]));

         if ($nbOfColumns != count($header)) {
            continue;
         }
         $sameColumnNames = true;
         $columnIndex = 0;
         foreach ($header as $item) {

            $name = addslashes($item);
            $name = $this->encodeUtf8($name);

            $pluginResourcesImportColumn->getFromDBByCrit(['name' => $name, PluginResourcesImport::$keyInOtherTables => $import['id']]);
            if ($pluginResourcesImportColumn->getID() == -1) {
               $sameColumnNames = false;
               break;
            }
            $columnIndex++;
         }
         if ($sameColumnNames) {
            return $import['id'];
         }
      }
      return false;
   }

   private function parseFileLine($header, $line, $importID)
   {

      $column = new PluginResourcesImportColumn();
      $datas = [];

      $headerIndex = 0;
      foreach ($header as $columnName) {

         $utf8ColumnName = str_replace("'", "\'", $columnName);
         $utf8ColumnName = $this->encodeUtf8($utf8ColumnName);

         $column->getFromDBByCrit(['name' => $utf8ColumnName, PluginResourcesImport::$keyInOtherTables => $importID]);

         $outType = PluginResourcesResource::getDataType($column->getField('resource_column'));
//         $resColumnName = PluginResourcesResource::getColumnName($column->getField('resource_column'));

         $value = null;
         if ($this->isCastable($column->getField('type'), $outType)) {
            $value = $this->castValue($line[$headerIndex], $column->getField('type'), $outType);
         }

         $datas[] = [
            "name" => $column->getName(),
            "value" => $value,
            "plugin_resources_importcolumns_id" => intval($column->getID()),
         ];

         $headerIndex++;
      }

      return $datas;
   }

   private function isCastable($in, $out){

      switch ($in) {
         case 0: //Integer
            switch ($out) {
               case "String":
                  return true;
               case "Contract":
                  return true;
               case "User":
                  return true;
               case "Location":
                  return true;
               case "PluginResourcesDepartment":
                  return true;
               case "Date":
                  return false;
            }
         case 1: //Decimal
            switch ($out) {
               case "String":
                  return true;
               case "Contract":
                  return false;
               case "User":
                  return false;
               case "Location":
                  return false;
               case "PluginResourcesDepartment":
                  return false;
               case "Date":
                  return false;
            }
         case 2: //String
            switch ($out) {
               case "String":
                  return true;
               case "Contract":
                  return true;
               case "User":
                  return true;
               case "Location":
                  return true;
               case "PluginResourcesDepartment":
                  return true;
               case "Date":
                  return false;
            }
         case 3: //Date
            switch ($out) {
               case "String":
                  return true;
               case "Contract":
                  return false;
               case "User":
                  return false;
               case "Location":
                  return false;
               case "PluginResourcesDepartment":
                  return false;
               case "Date":
                  return true;
            }
      }
      return false;
   }

   private function castValue($value, $in, $out)
   {
      switch ($in) {
         case 0: //Integer
            switch ($out) {
               case "String":
                  return "$value";
               case "Contract":
               case "User":
               case "Location":
               case "PluginResourcesDepartment":
                  return $value;
            }
         case 1: //Decimal
            switch ($out) {
               case "String":
                  return $value;
            }
         case 2: //String

            $utf8String = $this->encodeUtf8($value);

            switch ($out) {
               case "String":
                  return $utf8String;
               case "Contract":
                  // CAREFUL : Contracttype is translated in database
                  return $this->getObjectIDByClassNameAndName(PluginResourcesContractType::class, $utf8String);
               case "User":
                  $userList = $this->getUserByFullname($utf8String);

                  if (count($userList)) {
                     $u = array_pop($userList);
                     return $u['id'];
                  }

                  return -1;
//                  return $this->getObjectIDByClassNameAndName("User", $utf8String);
               case "Location":
                  return $this->getObjectIDByClassNameAndName("Location", $utf8String);
               case "PluginResourcesDepartment":
                  return $this->getObjectIDByClassNameAndName(PluginResourcesDepartment::class, $utf8String);
            }
         case 3: //Date
            switch ($out) {
               case "String":
                  return $value;
               case "Date":
                  return $this->formatDate($value);
            }
      }
      return null;
   }

   private function formatDate($value)
   {
      if (trim($value) != "" && $value != null) {
         return DateTime::createFromFormat('d/m/Y', $value)->format('Y-m-d');
      } else {
         return null;
      }
   }

   private function encodeUtf8($value)
   {
      if (preg_match('!!u', $value)) {
         return $value;
      } else {
         return utf8_encode($value);
      }
   }

   /**
    * The fullname must be firstname + 1 space + lastname
    *
    * @param $fullname
    */
   private function getUserByFullname($fullname)
   {
      global $DB;
      $query =
         "SELECT id FROM " . User::getTable() .
         ' WHERE CONCAT(firstname," ",realname) LIKE "' . $fullname . '"';


      $results = $DB->query($query);
      $temp = [];

      while ($data = $DB->fetch_assoc($results)) {
         $temp[] = $data;
      }
      return $temp;
   }

   private function getObjectIDByClassNameAndName($classname, $name)
   {

      $item = new $classname();

      if ($item) {
         $item->getFromDBByCrit(['name' => $name]);
         return $item->getID();
      }

      // 0 is the default ID of items
      return 0;
   }

   function showHead($type, $import)
   {
      global $CFG_GLPI;
      echo "<thead>";
      echo "<tr>";

      if ($type == self::NEW_IMPORTS) {

         $title = sprintf(__("New Resource from Import named: %s", "resources"), $import['name']);

         echo "<th colspan='16'>" . $title;

         $title = sprintf(__('%1$s : %2$s'),
            __('Be careful, the resources will be created in the entity', 'resources'),
            Dropdown::getDropdownName('glpi_entities', $_SESSION['glpiactive_entity']));

         echo "<br><span class='red'> " . $title . "</span></th>";

      } else if ($type == self::CONFLICTED_IMPORTS) {

         $title = sprintf(_n("Inconsistency from Import named: %s", 'Inconsistencies from Import named: %s', 2, "resources"), $import['name']);

         echo "<th colspan='21'>" . $title . "</th>";
      }
      echo "<tr>";

      echo "<tr>";

      echo "<th>";
      echo Html::getCheckAllAsCheckbox('massimport');
      echo "</th>";

      $resourceColumnNames = PluginResourcesResource::getDataNames();

      $pluginResourcesImportColumn = new PluginResourcesImportColumn();
      $importColumns = $pluginResourcesImportColumn->find([PluginResourcesImport::$keyInOtherTables => $import['id']]);

      for ($i = 0; $i < count($resourceColumnNames); $i++) {
         echo "<th>";
         foreach ($importColumns as $importColumn) {
            if ($importColumn['resource_column'] == $i) {
               echo "<img style='vertical-align: middle;' src='"
                  . $CFG_GLPI["root_doc"] . "/plugins/resources/pics/csv_file.png'"
                  . " title='" . __("Data from file", "resources") . "'"
                  . " width='30' height='30'>";
               break;
            }
         }

         echo "<span style='vertical-align:middle'>" . $resourceColumnNames[$i] . "</span>";
         echo "</th>";
      }
      echo "</tr>";


      echo "</thead>";
   }

   /**
    * Display imports by type of import
    *
    * @param $type
    */
   function showList($type)
   {
      global $CFG_GLPI;

      $pluginResourcesImport = new PluginResourcesImport();

      // Type of imports list
      $imports = $pluginResourcesImport->find();

      // Message when no import configured
      if(!count($imports)){
         $link = $CFG_GLPI["root_doc"] . "/plugins/resources/front/import.php";

         echo "<div class='center'>";
         echo "<h1>".__("No import configured", "resources")."</h1>";
         echo "<a href='$link'>";
         echo __("Configure a new import", "resources");
         echo "</a>";
         echo "</div>";
         return;
      }

      $existingImportResourceID = null;
      $pluginResourcesResource = new PluginResourcesResource();

      foreach ($imports as $import) {

         $formURL = Toolbox::getItemTypeFormURL(PluginResourcesResourceImport::getType());

         // Get imports resource by type
         $importResources = $this->find(['plugin_resources_imports_id' => $import['id']]);

         $resourceID = null;

         foreach ($importResources as $key => $importResource) {

            // Find resource by importData identifiers (level 1 and level 2)
            $resourceID = $pluginResourcesResource->isExistingResourceByImportResourceID($importResource['id']);

            switch ($type) {
               // Resource must not exist when NEW_IMPORTS
               case self::NEW_IMPORTS:
                  if ($resourceID) {
                     unset($importResources[$key]);
                  }
                  break;
               // Resource must exist when CONFLICTED_IMPORTS
               // And resource need to have differencies with importResource
               case self::CONFLICTED_IMPORTS:
                  if (!$resourceID) {
                     unset($importResources[$key]);
                  }else if($resourceID && $pluginResourcesResource->isDifferentFromImportResource($importResource['id'], $resourceID)){
                     unset($importResources[$key]);
                  }
                  break;
            }
         }

         if (count($importResources)) {

            echo "<form name='form' method='post' id='massimport' action ='$formURL' >";
            echo "<div align='center'>";
            echo "<table border='0' class='tab_cadrehov'>";

            $this->showHead($type, $import);

            foreach ($importResources as $importResource) {

               echo "<tr valign='center'>";

               echo "<td width='10'>";
               Html::showCheckbox(["name" => "select[" . $importResource['id'] . "]"]);
               echo "</td>";

               $this->showOne($importResource['id'], $type, $resourceID);

               echo "</tr>";
            }
            echo "</table>";

            switch ($type) {
               case self::NEW_IMPORTS:
                  echo "<input type='submit' name='add' class='submit' value='" . _sx('button', 'Add') . "' >";
                  break;
               case self::CONFLICTED_IMPORTS:
                  echo "<input type='submit' name='update' class='submit' value='" . _sx('button', 'Save') . "' >";
                  break;
            }

//      Html::printPager($limitBegin, $nb, $target, $parameters);
            echo "</div>";
            Html::closeForm();
         } else {
            switch ($type) {
               case self::NEW_IMPORTS:
                  $emptyText = sprintf(__('No new %s Imports', 'resources'), $import['name']);
                  echo "<div class='center'><h2>" . $emptyText . "</h2></div>";
                  break;
               case self::CONFLICTED_IMPORTS:
                  $emptyText = sprintf(__('No inconsistencies from %s Imports', 'resources'), $import['name']);
                  echo "<div class='center'><h2>" . $emptyText . "</h2></div>";
                  break;
            }
         }
      }
   }

   function showOne($importResourceId, $type, $resourceID){

      global $CFG_GLPI;
      $pluginResourcesImportResourceData = new PluginResourcesImportResourceData();

      // Get all import data
      $datas = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel($importResourceId, null, ['resource_column']);

      // Get resource data names
      $resourceColumnNames = PluginResourcesResource::getDataNames();

      if(!is_null($resourceID)){
         $pluginResourcesResource = new PluginResourcesResource();
         $pluginResourcesResource->getFromDB($resourceID);
      }

      /*
       * %s 1 : ImportID
       * %s 2 : ColumnID
       */
      $postValues = "import[$importResourceId][%s][%s]";
      $postOldValues = "resource[$importResourceId][%s][%s]";

      if ($type == self::CONFLICTED_IMPORTS) {
         echo "<input type='hidden' name='resource' value='$resourceID'>";
      }

      $display = [];

      // Add datas of import in associated column
      foreach ($datas as $data) {
         $display[$data['resource_column']][] = $data;
      }

      // Fill empty categories with default values
      for ($i = 0; $i < count($resourceColumnNames); $i++) {

         if (!isset($display[$i])) {
            $display[$i][] = [
               'id' => 0,
               'name' => "",
               'value' => 0,
               'resource_column' => $i
            ];
         }
      }

      // Order the display by index of resourceColumns
      ksort($display);

      foreach ($display as $key => $item) {

         echo "<td style='text-align:center;'>";

         $dataCounter = 0;
         foreach ($item as $key2 => $data) {

            $hId = sprintf($postValues, $data['id'], "id");
            $hName = sprintf($postValues, $data['id'], "name");
            $hValue = sprintf($postValues, $data['id'], "value");
            $hRc = sprintf($postValues, $data['id'], "resource_column");

            $hoValue = sprintf($postOldValues, $data['id'], "value");

            echo "<input type='hidden' name='" . $hId . "' value='" . $data['id'] . "'>";
            echo '<input type="hidden" name="' . $hName . '" value="' . $data['name'] . '">';
            echo "<input type='hidden' name='" . $hRc . "' value='" . $data['resource_column'] . "'>";

            $textInput = "<input name='$hValue' type='hidden' value='%s'>";

            echo "<span>";
            if (!empty($data['name']) && $data['resource_column'] != 10 && $data['value'] == -1) {

               if($type == self::NEW_IMPORTS){
                  echo "<img style='vertical-align:middle' src='"
                     . $CFG_GLPI["root_doc"] . "/plugins/resources/pics/csv_file_red.png'"
                     . "title='" . __("Not Found in GLPI", "resources") . "'"
                     . " width='30' height='30'>";
               }
            }

            switch ($data['resource_column']) {
               case 0:
               case 1:
                  echo sprintf($textInput, $data['value']);
//                  if ($pluginResourcesResource->hasDifferenciesWithValueByDataNameID(
//                     $data['resource_column'],
//                     $data['name'],
//                     $data['value']
//                  )) {
//                     echo "<span style='color:red'>"
//                        . $pluginResourcesResource->getFieldByDataNameID($data['resource_column'])
//                        . "->"
//                        . "</span>";
//                  }
                  echo $data['value'];
                  break;
               case 2:
//                  if ($pluginResourcesResource->hasDifferenciesWithValueByDataNameID(
//                     $data['resource_column'],
//                     $data['name'],
//                     $data['value']
//                  )) {
//                     Dropdown::show(PluginResourcesContractType::class, [
//                        'name' => $hoValue,
//                        'value' => $pluginResourcesResource->getFieldByDataNameID($data['resource_column']),
//                        'entity' => $_SESSION['glpiactive_entity']
//                     ]);
//                  }
                  Dropdown::show(PluginResourcesContractType::class, [
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity']
                  ]);
                  break;
               case 3:
//                  if ($pluginResourcesResource->hasDifferenciesWithValueByDataNameID(
//                     $data['resource_column'],
//                     $data['name'],
//                     $data['value']
//                  )) {
//                     User::dropdown([
//                        'name' => $hoValue,
//                        'value' => $pluginResourcesResource->getFieldByDataNameID($data['resource_column']),
//                        'entity' => $_SESSION['glpiactive_entity'],
//                        'right' => 'all'
//                     ]);
//                  }
                  User::dropdown([
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity'],
                     'right' => 'all'
                  ]);
                  break;
               case 4:
//                  if ($pluginResourcesResource->hasDifferenciesWithValueByDataNameID(
//                     $data['resource_column'],
//                     $data['name'],
//                     $data['value']
//                  )) {
//                     Dropdown::show(Location::class, [
//                        'name' => $hoValue,
//                        'value' => $pluginResourcesResource->getFieldByDataNameID($data['resource_column']),
//                        'entity' => $_SESSION['glpiactive_entity']
//                     ]);
//                  }
                  Dropdown::show(Location::class, [
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity']
                  ]);
                  break;
               case 5:
//                  if ($pluginResourcesResource->hasDifferenciesWithValueByDataNameID(
//                     $data['resource_column'],
//                     $data['name'],
//                     $data['value']
//                  )) {
//                     User::dropdown([
//                        'name' => $hoValue,
//                        'value' => $pluginResourcesResource->getFieldByDataNameID($data['resource_column']),
//                        'entity' => $_SESSION['glpiactive_entity'],
//                        'right' => 'all']);
//                  }
                  User::dropdown([
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity'],
                     'right' => 'all']);
                  break;
               case 6:
//                  if ($pluginResourcesResource->hasDifferenciesWithValueByDataNameID(
//                     $data['resource_column'],
//                     $data['name'],
//                     $data['value']
//                  )) {
//                     Dropdown::show(PluginResourcesDepartment::class, [
//                        'name' => $hoValue,
//                        'value' => $pluginResourcesResource->getFieldByDataNameID($data['resource_column']),
//                        'entity' => $_SESSION['glpiactive_entity']
//                     ]);
//                  }
                  Dropdown::show(PluginResourcesDepartment::class, [
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity']
                  ]);
                  break;
               case 7:
               case 8:
//                  if ($pluginResourcesResource->hasDifferenciesWithValueByDataNameID(
//                     $data['resource_column'],
//                     $data['name'],
//                     $data['value']
//                  )) {
//                     $this->showDateFieldWithoutDiv($hoValue, [
//                        'value' => $pluginResourcesResource->getFieldByDataNameID($data['resource_column']),
//                     ]);
//                  }
                  $this->showDateFieldWithoutDiv($hValue, ['value' => $data['value']]);
                  break;
               case 9:
//                  if ($pluginResourcesResource->hasDifferenciesWithValueByDataNameID(
//                     $data['resource_column'],
//                     $data['name'],
//                     $data['value']
//                  )) {
//                     User::dropdown([
//                        'name' => $hoValue,
//                        'value' => $pluginResourcesResource->getFieldByDataNameID($data['resource_column']),
//                        'entity' => $_SESSION['glpiactive_entity'],
//                        'right' => 'all']);
//                  }
                  User::dropdown([
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity'],
                     'right' => 'all']);
                  break;
               case 10:
                  echo sprintf($textInput, $data['value']);
                  echo "<div>" . $data['name'] . " : ";
//                  if ($pluginResourcesResource->hasDifferenciesWithValueByDataNameID(
//                     $data['resource_column'],
//                     $data['name'],
//                     $data['value']
//                  )) {
//                     echo "<span style='color:red'>"
//                        . $pluginResourcesResource->getResourceImportValueByName($data['name'])
//                        . "->"
//                        . "</span>";
//                  }
                  echo $data['value'] . "</div>";
                  break;
            }
            echo "</span>";
            $dataCounter++;
         }

         echo "</td>";
      }
   }

   /**
    * Copy of html::showDateFieldWithoutDiv
    *
    * Underscore removed from name
    * Change self reference to Html
    *
    **/
   static function showDateFieldWithoutDiv($name, $options = [])
   {
      $p['value'] = '';
      $p['maybeempty'] = true;
      $p['canedit'] = true;
      $p['min'] = '';
      $p['max'] = '';
      $p['showyear'] = true;
      $p['display'] = true;
      $p['rand'] = mt_rand();
      $p['yearrange'] = '';

      foreach ($options as $key => $val) {
         if (isset($p[$key])) {
            $p[$key] = $val;
         }
      }
      $output = "<input id='showdate" . $p['rand'] . "' type='text' size='10' name='$name' " .
         "value='" . Html::convDate($p['value']) . "'>";
      $output .= Html::hidden($name, ['value' => $p['value'],
         'id' => "hiddendate" . $p['rand']]);
      if ($p['maybeempty'] && $p['canedit']) {
         $output .= "<span class='fa fa-times-circle pointer' title='" . __s('Clear') .
            "' id='resetdate" . $p['rand'] . "'>" .
            "<span class='sr-only'>" . __('Clear') . "</span></span>";
      }

      $js = '$(function(){';
      if ($p['maybeempty'] && $p['canedit']) {
         $js .= "$('#resetdate" . $p['rand'] . "').click(function(){
                  $('#showdate" . $p['rand'] . "').val('');
                  $('#hiddendate" . $p['rand'] . "').val('');
                  });";
      }
      $js .= "$( '#showdate" . $p['rand'] . "' ).datepicker({
                  altField: '#hiddendate" . $p['rand'] . "',
                  altFormat: 'yy-mm-dd',
                  firstDay: 1,
                  showOtherMonths: true,
                  selectOtherMonths: true,
                  showButtonPanel: true,
                  changeMonth: true,
                  changeYear: true,
                  showOn: 'both',
                  showWeek: true,
                  buttonText: '<i class=\'far fa-calendar-alt\'></i>'";

      if (!$p['canedit']) {
         $js .= ",disabled: true";
      }

      if (!empty($p['min'])) {
         $js .= ",minDate: '" . self::convDate($p['min']) . "'";
      }

      if (!empty($p['max'])) {
         $js .= ",maxDate: '" . self::convDate($p['max']) . "'";
      }

      if (!empty($p['yearrange'])) {
         $js .= ",yearRange: '" . $p['yearrange'] . "'";
      }

      switch ($_SESSION['glpidate_format']) {
         case 1 :
            $p['showyear'] ? $format = 'dd-mm-yy' : $format = 'dd-mm';
            break;

         case 2 :
            $p['showyear'] ? $format = 'mm-dd-yy' : $format = 'mm-dd';
            break;

         default :
            $p['showyear'] ? $format = 'yy-mm-dd' : $format = 'mm-dd';
      }
      $js .= ",dateFormat: '" . $format . "'";

      $js .= "}).next('.ui-datepicker-trigger').addClass('pointer');";
      $js .= "});";
      $output .= Html::scriptBlock($js);

      if ($p['display']) {
         echo $output;
         return $p['rand'];
      }
      return $output;
   }

////// CRON FUNCTIONS ///////
   //Cron action
   /**
    * @param $name
    *
    * @return array
    */
   static function cronInfo($name)
   {

      switch ($name) {
         case 'ResourceImport':
            return [
               'description' => __('Resource files imports', 'resources')];   // Optional
            break;
      }
      return [];
   }

   /**
    * Cron action
    *
    * @global $DB
    * @global $CFG_GLPI
    *
    * @param  $task for log
    */
   static function cronResourceImport($task = NULL)
   {

      $CronTask = new CronTask();
      if ($CronTask->getFromDBbyName(PluginResourcesImportResource::class, "ResourceImport")) {
         if ($CronTask->fields["state"] == CronTask::STATE_DISABLE) {
            return 0;
         }
      } else {
         return 0;
      }

      $import = new self();
      return $import->importResourcesFromCSVFile($task);
   }

}