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
class PluginResourcesImportResource extends CommonDBTM {

   static $rightname = 'plugin_resources_importresources';

   static $keyInOtherTables = 'plugin_resources_importresources_id';

   const NEW_IMPORTS = 0;
   const CONFLICTED_IMPORTS = 1;

   private $existingImports = null;

   static function getIndexUrl() {
      global $CFG_GLPI;
      return $CFG_GLPI["root_doc"] . "/plugins/resources/front/importresource.php";
   }

   private function resetExistingImportsArray(){
      $this->existingImports = null;
   }

   private function initExistingImportsArray(){
      if(is_null($this->existingImports)){
         $this->existingImports = $this->find();
      }
   }


   function updateDatas($datas, $importResourceID) {

      $pluginResourcesImportResourceData = new PluginResourcesImportResourceData();

      $crit = [
         PluginResourcesImportResourceData::$items_id => $importResourceID
      ];

      $importResourceDatas = $pluginResourcesImportResourceData->find($crit);

      // Delete all import data
      foreach ($importResourceDatas as $importResourceData) {

         foreach ($datas as $data) {

            if ($data['name'] != $importResourceData['name']) {
               continue;
            }

            if ($data['value'] == $importResourceData['value']) {
               continue;
            }

            $input = [
               PluginResourcesImportResourceData::getIndexName() => $importResourceData['id'],
               "value" => addslashes($data['value'])
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
   function manageImport($datas, $importID) {

      $importResourceID = $this->isExistingImportResourceByDataFromFile($datas);

      // Override data of existing importResource
      if (!is_null($importResourceID)) {

         $this->updateDatas($datas, $importResourceID);

      } else {
         // Create new Import Resource
         $importResourceInput = [
            "date_creation" => date("Y-m-d H:i:s"),
            PluginResourcesImport::$keyInOtherTables => $importID
         ];

         $newImportId = $this->add($importResourceInput);

         $importResourceData = new PluginResourcesImportResourceData();

         // Create new Import resource data
         foreach ($datas as $item) {

            $importResourceDataInput = $importResourceData->prepareInput(
               addslashes($item['name']),
               addslashes($item['value']),
               $newImportId,
               $item['plugin_resources_importcolumns_id']
            );

            $importResourceData->add($importResourceDataInput);
         }
      }
   }

   /**
    * Search if a resource exist with the same identifiers
    */
   function isExistingImportResourceByDataFromFile($columnDatas) {

      $pluginResourcesImportResourceData = new PluginResourcesImportResourceData();

      // List of existing imports
      $this->initExistingImportsArray();

      foreach ($this->existingImports as $existingImportResource) {

         $firstLevelIdentifiers = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel($existingImportResource['id'], 1);

         $firstLevelIdentifierFounded = true;

         foreach ($firstLevelIdentifiers as $firstLevelIdentifier) {

            foreach ($columnDatas as $columnData) {

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

         $secondLevelIdentifiers = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel($existingImportResource['id'], 2);
         $secondLevelIdentifierFounded = true;

         foreach ($secondLevelIdentifiers as $secondLevelIdentifier) {

            foreach ($columnDatas as $columnData) {

               if ($columnData['name'] != $secondLevelIdentifier['name']) {
                  continue;
               }

               if ($columnData['value'] != $secondLevelIdentifier['value']) {
                  $secondLevelIdentifierFounded = false;
               }
            }
         }

         if ($secondLevelIdentifierFounded) {
            return $existingImportResource['id'];
         }
      }
      return null;
   }

   function importResourcesFromCSVFile($task) {
      // glpi files folder
      $path = GLPI_PLUGIN_DOC_DIR . "/resources/import/";
      // List of files in path
      $files = scandir($path);
      // Exclude dot and dotdot
      $files = array_diff($files, array('.', '..'));

      foreach ($files as $file) {

         $importSuccess = true;

         $filePath = $path . $file;

         // Just parse files
         if (is_dir($filePath)) {
            continue;
         }

         $import = null;

         if (file_exists($filePath)) {
            $handle = fopen($filePath, 'r');

            // Initialize existingImports Array
            $this->resetExistingImportsArray();
            $this->initExistingImportsArray();

            $importID = null;
            $header = null;

            $lineIndex = 0;
            while (($line = fgetcsv($handle, 1000, ";")) !== FALSE) {

               if ($lineIndex == 0) {

                  $importID = $this->checkHeader($line);

                  if ($importID <= 0) {
                     $importSuccess = false;
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
         if ($importSuccess) {
            // Move file to done folder
            Toolbox::logDebug("TODO move file to done");
         } else {
            // Move file to fail folder
            Toolbox::logDebug("TODO move file to fail");
         }
      }

      return true;
   }

   /**
    * Verify the header of the csv file
    *
    * Return the index of the configured import that match to this header
    *
    * @param $header
    * @return bool
    */
   function checkHeader($header) {

      $pluginResourcesImport = new PluginResourcesImport();
      $pluginResourcesImportColumn = new PluginResourcesImportColumn();

      $imports = $pluginResourcesImport->find();

      foreach ($imports as $import) {

         $crit = [
            PluginResourcesImport::$keyInOtherTables => $import['id']
         ];

         $nbOfColumns = count($pluginResourcesImportColumn->find($crit));

         if ($nbOfColumns != count($header)) {
            continue;
         }
         $sameColumnNames = true;
         $columnIndex = 0;
         foreach ($header as $item) {

            $name = addslashes($item);
            $name = $this->encodeUtf8($name);

            $crit = [
               'name' => $name,
               PluginResourcesImport::$keyInOtherTables => $import['id']
            ];

            $pluginResourcesImportColumn->getFromDBByCrit($crit);
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

   /**
    * Transform data in csv file to match glpi data types
    *
    * @param $header
    * @param $line
    * @param $importID
    * @return array
    */
   private function parseFileLine($header, $line, $importID) {

      $column = new PluginResourcesImportColumn();
      $datas = [];

      $headerIndex = 0;
      foreach ($header as $columnName) {

         $utf8ColumnName = addslashes($columnName);
         $utf8ColumnName = $this->encodeUtf8($utf8ColumnName);

         $crit = [
            'name' => $utf8ColumnName,
            PluginResourcesImport::$keyInOtherTables => $importID
         ];

         if (!$column->getFromDBByCrit($crit)) {
            Html::displayErrorAndDie("Import column not found");
         }

         $outType = PluginResourcesResource::getDataType($column->getField('resource_column'));

         $value = null;
         if ($this->isCastable($column->getField('type'), $outType)) {
            $value = $this->castValue($line[$headerIndex], $column->getField('type'), $outType);
         }

         $datas[] = [
            "name" => $column->getName(),
            "value" => $value,
            "plugin_resources_importcolumns_id" => intval($column->getID())
         ];

         $headerIndex++;
      }

      return $datas;
   }

   /**
    * Test if input type is castable to output type
    *
    * @param $in
    * @param $out
    * @return bool
    */
   private function isCastable($in, $out) {

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
               case PluginResourcesDepartment::class:
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
               case PluginResourcesDepartment::class:
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
               case PluginResourcesDepartment::class:
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
               case PluginResourcesDepartment::class:
                  return false;
               case "Date":
                  return true;
            }
      }
      return false;
   }

   /**
    * Cast value from input type to output type
    *
    * @param $value
    * @param $in
    * @param $out
    * @return int|string|null
    */
   private function castValue($value, $in, $out) {
      switch ($in) {
         case 0: //Integer
            switch ($out) {
               case "String":
                  return "$value";
               case "Contract":
               case "User":
               case "Location":
               case PluginResourcesDepartment::class:
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
               case PluginResourcesDepartment::class:
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

   private function formatDate($value) {
      if (trim($value) != "" && $value != null) {
         return DateTime::createFromFormat('d/m/Y', $value)->format('Y-m-d');
      } else {
         return null;
      }
   }

   private function encodeUtf8($value) {
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
   private function getUserByFullname($fullname) {
      global $DB;
      $query = "SELECT id FROM " . User::getTable() . ' WHERE CONCAT(firstname," ",realname) LIKE "' . $fullname . '"';


      $results = $DB->query($query);
      $temp = [];

      while ($data = $DB->fetch_assoc($results)) {
         $temp[] = $data;
      }
      return $temp;
   }

   /**
    * Recover object from database by class and name
    *
    * @param $classname
    * @param $name
    * @return int
    */
   private function getObjectIDByClassNameAndName($classname, $name) {

      $item = new $classname();

      if ($item) {
         $item->getFromDBByCrit(['name' => $name]);
         return $item->getID();
      }

      // 0 is the default ID of items
      return 0;
   }

   /**
    * Display the header
    *
    * @param $type
    * @param $import
    */
   function showHead($type, $import) {

      global $CFG_GLPI;
      echo "<thead>";
      echo "<tr>";

      if ($type == self::NEW_IMPORTS) {

         $title = sprintf(__("New Resource from Import named: %s", "resources"), $import['name']);

         echo "<th colspan='16'>" . $title;

         $title = sprintf(
            __('%1$s : %2$s'),
            __('Be careful, the resources will be created in the entity', 'resources'),
            Dropdown::getDropdownName('glpi_entities', $_SESSION['glpiactive_entity'])
         );

         echo "<br><span class='red'> " . $title . "</span></th>";

      } else if ($type == self::CONFLICTED_IMPORTS) {

         $title = sprintf(
            _n("Inconsistency from Import named: %s", 'Inconsistencies from Import named: %s', 2, "resources"),
            $import['name']);

         echo "<th colspan='21'>" . $title . "</th>";
      }
      echo "<tr>";

      echo "<tr>";

      echo "<th>";
      echo Html::getCheckAllAsCheckbox('massimport');
      echo "</th>";

      if ($type == self::CONFLICTED_IMPORTS) {
         echo "<th>";
         echo __('Resource', 'resources');
         echo "</th>";
      }

      $resourceColumnNames = PluginResourcesResource::getDataNames();

      $pluginResourcesImportColumn = new PluginResourcesImportColumn();

      $importColumns = $pluginResourcesImportColumn->getColumnsByImport($import['id'], true);

      foreach ($importColumns as $importColumn) {
         echo "<th>";
         echo "<img style='vertical-align: middle;' src='" .
            $CFG_GLPI["root_doc"] . "/plugins/resources/pics/csv_file.png'" .
            " title='" . __("Data from file", "resources") . "'" .
            " width='30' height='30'>";

         $name = $resourceColumnNames[$importColumn['resource_column']];

         echo "<span style='vertical-align:middle'>" . $name . "</span>";
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
   function showList($type, $limit) {
      global $CFG_GLPI;

      $pluginResourcesImport = new PluginResourcesImport();

      // Type of imports list
      $imports = $pluginResourcesImport->find();

      // Message when no import configured
      if (!count($imports)) {
         $link = $CFG_GLPI["root_doc"] . "/plugins/resources/front/import.php";

         echo "<div class='center'>";
         echo "<h1>" . __("No import configured", "resources") . "</h1>";
         echo "<a href='$link'>";
         echo __("Configure a new import", "resources");
         echo "</a>";
         echo "</div>";
         return;
      }

      $existingImportResourceID = null;
      $pluginResourcesResource = new PluginResourcesResource();

      // For each type of import
      foreach ($imports as $import) {

         $formURL = Toolbox::getItemTypeFormURL(PluginResourcesResourceImport::getType());

         // Get imports resource by type
         $importResources = $this->find(['plugin_resources_imports_id' => $import['id']]);

         Html::printPager(0, $limit, $_SERVER['PHP_SELF'], "type=".$type);

         // For each import resource of type
         foreach ($importResources as $key => $importResource) {

            // Find resource by importData identifiers (level 1 and level 2)
            $importResources[$key]['resource_id'] = $pluginResourcesResource->isExistingResourceByImportResourceID($importResource['id']);
            switch ($type) {
               // Resource must not exist when NEW_IMPORTS
               case self::NEW_IMPORTS:
                  if ($importResources[$key]['resource_id']) {
                     unset($importResources[$key]);
                  }
                  break;
               // Resource must exist when CONFLICTED_IMPORTS
               // And resource need to have differencies with importResource
               case self::CONFLICTED_IMPORTS:
                  if (!$importResources[$key]['resource_id']) {
                     unset($importResources[$key]);
                  } else if ($importResources[$key]['resource_id']
                     && !$pluginResourcesResource->isDifferentFromImportResource(
                        $importResources[$key]['resource_id'],
                        $importResource['id'])) {
                     unset($importResources[$key]);
                  }
                  break;
            }
         }

         if (count($importResources)) {

            $importResources = array_splice($importResources, 0, $limit);

            echo "<form name='form' method='post' id='massimport' action ='$formURL' >";
            echo "<div align='center'>";
            echo "<table border='0' class='tab_cadrehov'>";

            $this->showHead($type, $import);

            foreach ($importResources as $importResource) {

               echo "<tr valign='center'>";

               $this->showOne($importResource['id'], $type, $importResource['resource_id']);

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

   /**
    * Display an import line
    *
    * @param $importResourceId
    * @param $type
    * @param $resourceID
    */
   function showOne($importResourceId, $type, $resourceID) {

      global $CFG_GLPI;

      $oldCSS = "display:block;border-bottom:solid 1px red";
      $newCSS = "display:block;border-top:solid 1px green;margin-top:1px;";

      $pluginResourcesImportResourceData = new PluginResourcesImportResourceData();

      // Get all import data
      $datas = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel($importResourceId, null, ['resource_column']);

      if (!is_null($resourceID)) {
         $pluginResourcesResource = new PluginResourcesResource();
         $pluginResourcesResource->getFromDB($resourceID);
      }

      /*
       * %s 1 : ImportID
       * %s 2 : ColumnID
       */
      $postValues = "import[$importResourceId][%s][%s]";

      if ($type == self::CONFLICTED_IMPORTS) {
         $postResourceID = "resource[$importResourceId]";
         echo "<input type='hidden' name='$postResourceID' value='$resourceID'>";
      }

      echo "<td width='10'>";
      Html::showCheckbox(["name" => "select[" . $importResourceId . "]"]);
      echo "</td>";

      if($type == self::CONFLICTED_IMPORTS){

         $pluginResourcesResource = new PluginResourcesResource();
         $pluginResourcesResource->getFromDB($resourceID);

         $link = Toolbox::getItemTypeFormURL(PluginResourcesResource::getType());

         echo "<td style='text-align:center'><a href='$link'>".$resourceID."</a></td>";
      }

      $numberOfOthersValues = 0;

      foreach ($datas as $data){
         if($data['resource_column'] == 10){
            $numberOfOthersValues++;
         }
      }

      $otherIndex = 0;

      foreach ($datas as $key => $data) {

         echo "<td style='text-align:center;padding:0;'>";

            $hId = sprintf($postValues, $data['id'], "id");
            $hName = sprintf($postValues, $data['id'], "name");
            $hValue = sprintf($postValues, $data['id'], "value");
            $hRc = sprintf($postValues, $data['id'], "resource_column");

            echo "<input type='hidden' name='" . $hId . "' value='" . $data['id'] . "'>";
            echo '<input type="hidden" name="' . $hName . '" value="' . $data['name'] . '">';
            echo "<input type='hidden' name='" . $hRc . "' value='" . $data['resource_column'] . "'>";

            $textInput = "<input name='$hValue' type='hidden' value='%s'>";

            echo "<span>";
            if (!empty($data['name']) && $data['resource_column'] != 10 && $data['value'] == -1) {

               if ($type == self::NEW_IMPORTS) {
                  echo "<img style='vertical-align:middle' src='".
                     $CFG_GLPI["root_doc"] . "/plugins/resources/pics/csv_file_red.png'".
                     "title='" . __("Not Found in GLPI", "resources")."'".
                     " width='30' height='30'>";
               }
            }

            $oldValues = $resourceID && $pluginResourcesResource->hasDifferenciesWithValueByDataNameID(
                  $resourceID,
                  $data['resource_column'],
                  $data['name'],
                  $data['value']
               );

            if ($type == self::CONFLICTED_IMPORTS) {
               $needToUpdate = "to_update[$importResourceId][" . $data['id'] . "]";
               echo "<input type='hidden' name='$needToUpdate' value='" . intval($oldValues) . "'>";
            }

            switch ($data['resource_column']) {
               case 0:
               case 1:
                  echo sprintf($textInput, $data['value']);

                  if ($oldValues) {
                     echo "<ul>";
                     echo "<li style='$oldCSS'>";
                     $pluginResourcesResource->getFieldByDataNameID($data['resource_column']);
                     echo "</li>";
                     echo "<li style='$newCSS'>";
                  }
                  echo $data['value'];
                  if ($oldValues) {
                     echo "</li>";
                     echo "</ul>";
                  }
                  break;
               case 2:
                  if ($oldValues) {
                     echo "<ul>";
                     echo "<li style='$oldCSS'>";

                     $pluginResourcesContractType = new PluginResourcesContractType();
                     $pluginResourcesContractType->getFromDB($pluginResourcesResource->getFieldByDataNameID($data['resource_column']));
                     echo $pluginResourcesContractType->getName();

                     echo "</li>";
                     echo "<li style='$newCSS'>";
                  }
                  Dropdown::show(PluginResourcesContractType::class, [
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity']
                  ]);
                  if ($oldValues) {
                     echo "</li>";
                     echo "</ul>";
                  }
                  break;
               case 3:
                  if ($oldValues) {
                     echo "<ul>";
                     echo "<li style='$oldCSS'>";

                     $user = new User();
                     $user->getFromDB($pluginResourcesResource->getFieldByDataNameID($data['resource_column']));
                     echo $user->getName();

                     echo "</li>";
                     echo "<li style='$newCSS'>";
                  }
                  User::dropdown([
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity'],
                     'right' => 'all'
                  ]);
                  if ($oldValues) {
                     echo "</li>";
                     echo "</ul>";
                  }
                  break;
               case 4:
                  if ($oldValues) {
                     echo "<ul>";
                     echo "<li style='$oldCSS'>";

                     $location = new Location();
                     $location->getFromDB($pluginResourcesResource->getFieldByDataNameID($data['resource_column']));

                     echo $location->getName();
                     echo "</li>";
                     echo "<li style='$newCSS'>";
                  }
                  Dropdown::show(Location::class, [
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity']
                  ]);
                  if ($oldValues) {
                     echo "</li>";
                     echo "</ul>";
                  }
                  break;
               case 5:
                  if ($oldValues) {
                     echo "<ul>";
                     echo "<li style='$oldCSS'>";

                     $user = new User();
                     $user->getFromDB($pluginResourcesResource->getFieldByDataNameID($data['resource_column']));
                     echo $user->getName();

                     echo "</li>";
                     echo "<li style='$newCSS'>";
                  }
                  User::dropdown([
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity'],
                     'right' => 'all'
                  ]);
                  if ($oldValues) {
                     echo "</li>";
                     echo "</ul>";
                  }
                  break;
               case 6:
                  if ($oldValues) {
                     echo "<ul>";
                     echo "<li style='$oldCSS'>";

                     $pluginResourcesDepartment = new PluginResourcesDepartment();
                     $pluginResourcesDepartment->getFromDB($pluginResourcesResource->getFieldByDataNameID($data['resource_column']));
                     echo $pluginResourcesDepartment->getName();

                     echo "</li>";
                     echo "<li style='$newCSS'>";
                  }
                  Dropdown::show(PluginResourcesDepartment::class, [
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity']
                  ]);
                  if ($oldValues) {
                     echo "</li>";
                     echo "</ul>";
                  }
                  break;
               case 7:
               case 8:
                  if ($oldValues) {
                     echo "<ul>";
                     echo "<li style='$oldCSS'>";

                     echo $pluginResourcesResource->getFieldByDataNameID($data['resource_column']);
                     echo "</li>";
                     echo "<li style='$newCSS'>";
                  }
                  $this->showDateFieldWithoutDiv($hValue, ['value' => $data['value']]);
                  if ($oldValues) {
                     echo "</li>";
                     echo "</ul>";
                  }
                  break;
               case 9:
                  if ($oldValues) {
                     echo "<ul>";
                     echo "<li style='$oldCSS'>";

                     $user = new User();
                     $user->getFromDB($pluginResourcesResource->getFieldByDataNameID($data['resource_column']));
                     echo $user->getName();

                     echo "</li>";
                     echo "<li style='$newCSS'>";
                  }
                  User::dropdown([
                     'name' => $hValue,
                     'value' => $data['value'],
                     'entity' => $_SESSION['glpiactive_entity'],
                     'right' => 'all'
                  ]);
                  if ($oldValues) {
                     echo "</li>";
                     echo "</ul>";
                  }
                  break;
               case 10:
                  echo sprintf($textInput, $data['value']);

                  if($otherIndex == 0){
                     echo "<table class='tab_cadrehov' style='margin:0;width:100%;'>";
                  }

                  echo "<tr>";

                  echo "<td>".$data['name']."</td>";

                  echo "<td style='color: red;'>";

                  if ($oldValues) {
                     echo $pluginResourcesResource->getResourceImportValueByName($resourceID, $data['name']);
                  }
                  echo "</td>";

                  echo "<td style='color: green;'>".$data['value']."</td>";

                  echo "</tr>";

                  if($otherIndex == $numberOfOthersValues -1){
                     echo "</table>";
                  }

                  $otherIndex++;
                  break;
            }
            echo "</span>";


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
   static function showDateFieldWithoutDiv($name, $options = []) {
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
      $output = "<input id='showdate" . $p['rand'] . "' type='text' size='10' name='$name' " . "value='" . Html::convDate($p['value']) . "'>";
      $output .= Html::hidden($name, ['value' => $p['value'], 'id' => "hiddendate" . $p['rand']]);
      if ($p['maybeempty'] && $p['canedit']) {
         $output .= "<span class='fa fa-times-circle pointer' title='" . __s('Clear') . "' id='resetdate" . $p['rand'] . "'>" . "<span class='sr-only'>" . __('Clear') . "</span></span>";
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
   static function cronInfo($name) {

      switch ($name) {
         case 'ResourceImport':
            return ['description' => __('Resource files imports', 'resources')];   // Optional
            break;
      }
      return [];
   }

   /**
    * Cron action
    *
    * @param  $task for log
    * @global $CFG_GLPI
    *
    * @global $DB
    */
   static function cronResourceImport($task = NULL) {

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