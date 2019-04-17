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

   const IDENTIFIER_LEVELS = 2;


   static function getIndexUrl(){
      global $CFG_GLPI;
      return $CFG_GLPI["root_doc"] . "/plugins/resources/front/importresource.php";
   }

   function updateDatas($datas, $similar){

      $importResourceData = new PluginResourcesImportResourceData();

      foreach($datas as $data){
         $data['plugin_resources_importresources_id'] = $similar['id'];
         $importResourceData->add($data);
      }
   }

   function manageImport($datas, $importID){
      $import = new PluginResourcesImport();
      $import->getFromDB($importID);

      $importResourceData = new PluginResourcesImportResourceData();
      $similars = [];

      // Liste des imports
      $existingImportResources = $this->find();

      // Parcourir les imports
      foreach($existingImportResources as $existingImportResource){

         // Recuperer les colonnes des imports de niveau 1
         $existingDatas = $importResourceData->getResourceDataByImportResource($existingImportResource['id'], 1);

         // Test identifier level 1
         foreach($existingDatas as $existingData){
            foreach($datas as $data){
               if($data['name'] != $existingData['name']){
                  continue;
               }
               if(!empty($data['value']) && $data['value'] == $existingData['value']){
                  $similars[] = $existingData;
                  break;
               }
            }
         }

         if(empty($similars)){
            $existingDatas = $importResourceData->getResourceDataByImportResource($existingImportResource['id'], 2);

            foreach($existingDatas as $existingData){

               foreach($datas as $data){
                  if($data['name'] != $existingData['name']){
                     continue;
                  }
                  if($data['value'] == $existingData['value']){
                     $similars[] = $existingData;
                     break;
                  }
               }
               if(count($similars)) break;
            }
         }

         if(count($similars)) break;
      }

      // Update existing importResource
      if(count($similars)){
         // We keep only the first similar
         $similar = array_pop($similars);

         $this->updateDatas($datas, $similar);

      }else{
         // Creation of a new Import Resource
         $newImportId = $this->add([
            "date_creation" => date("Y-m-d H:i:s"),
            PluginResourcesImport::$keyInOtherTables => $importID
         ]);

         $importResourceData = new PluginResourcesImportResourceData();

         foreach($datas as $item){

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

   function importResourcesFromCSVFile($task){
      // glpi files folder
      $path     = GLPI_PLUGIN_DOC_DIR . "/resources/import/";
      // List of files in path
      $files    = scandir($path);
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

                  if($importID <= 0){
                     break;
                  }
                  $header = $line;

               }else{

                  $datas = $this->parseFileLine($header, $line, $importID);
                  $this->manageImport($datas,$importID);
               }
               $lineIndex++;
            }
         }
      }
      return true;
   }

   function checkHeader($header){

      $import = new PluginResourcesImport();
      $column = new PluginResourcesImportColumn();

      $importsDatas = $import->find();

      foreach($importsDatas as $importDatas){
         $nbOfColumns = count($column->find([PluginResourcesImport::$keyInOtherTables => $importDatas['id']]));

         if($nbOfColumns != count($header)){
            continue;
         }
         $sameColumnNames = true;
         $columnIndex = 0;
         foreach ($header as $item) {

            $name = str_replace("'", "\'", $item);
            $name = $this->encodeUtf8($name);

            $column->getFromDBByCrit(['name' => $name, PluginResourcesImport::$keyInOtherTables => $importDatas['id']]);
            if ($column->getID() == -1){
               $sameColumnNames = false;
               break;
            }
            $columnIndex++;
         }
         if($sameColumnNames){
            return $importDatas['id'];
         }
      }
      return false;
   }

   private function parseFileLine($header, $line, $importID){

      $column = new PluginResourcesImportColumn();
      $datas = [];

      $headerIndex = 0;
      foreach($header as $columnName){

         $utf8ColumnName = str_replace("'", "\'", $columnName);
         $utf8ColumnName = $this->encodeUtf8($utf8ColumnName);

         $column->getFromDBByCrit(['name' => $utf8ColumnName, PluginResourcesImport::$keyInOtherTables => $importID]);

         $outType = PluginResourcesResource::getDataType($column->getField('resource_column'));
//         $resColumnName = PluginResourcesResource::getColumnName($column->getField('resource_column'));

         $value = null;
         if($this->isCastable($column->getField('type'), $outType)){
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

      switch($in){
         case 0: //Integer
            switch($out){
               case "String": return true;
               case "Contract": return true;
               case "User": return true;
               case "Location": return true;
               case "PluginResourcesDepartment": return true;
               case "Date": return false;
            }
         case 1: //Decimal
            switch($out){
               case "String": return true;
               case "Contract": return false;
               case "User": return false;
               case "Location": return false;
               case "PluginResourcesDepartment": return false;
               case "Date": return false;
            }
         case 2: //String
            switch($out){
               case "String": return true;
               case "Contract": return true;
               case "User": return true;
               case "Location": return true;
               case "PluginResourcesDepartment": return true;
               case "Date": return false;
            }
         case 3: //Date
            switch($out){
               case "String": return true;
               case "Contract": return false;
               case "User": return false;
               case "Location": return false;
               case "PluginResourcesDepartment": return false;
               case "Date": return true;
            }
      }
      return false;
   }

   private function castValue($value, $in, $out)
   {
      switch ($in) {
         case 0: //Integer
            switch ($out) {
               case "String": return "$value";
               case "Contract":
               case "User":
               case "Location":
               case "PluginResourcesDepartment": return $value;
            }
         case 1: //Decimal
            switch ($out) {
               case "String": return $value;
            }
         case 2: //String

            $utf8String = $this->encodeUtf8($value);

            switch ($out) {
               case "String": return $utf8String;
               case "Contract":
                  // CAREFUL : Contracttype is translated in database
                  return $this->getObjectIDByClassNameAndName(PluginResourcesContractType::class, $utf8String);
               case "User":
                  $userList = $this->getUserByFullname($utf8String);

                  if(count($userList)){
                     $u = array_pop($userList);
                     return $u['id'];
                  }

                  return -1;
//                  return $this->getObjectIDByClassNameAndName("User", $utf8String);
               case "Location": return $this->getObjectIDByClassNameAndName("Location", $utf8String);
               case "PluginResourcesDepartment": return $this->getObjectIDByClassNameAndName(PluginResourcesDepartment::class, $utf8String);
            }
         case 3: //Date
            switch ($out) {
               case "String": return $value;
               case "Date": return $this->formatDate($value);
            }
      }
   }

   private function formatDate($value){
      if (trim($value) != "" && $value != null) {
         return DateTime::createFromFormat('d/m/Y', $value)->format('Y-m-d');
      } else {
         return null;
      }
   }

   private function encodeUtf8($value){
      if (preg_match('!!u', $value)){
         return $value;
      }else{
         return utf8_encode($value);
      }
   }

   /**
    * The fullname must be firstname + 1 space + lastname
    *
    * @param $fullname
    */
   private function getUserByFullname($fullname){
      global $DB;
      $query =
         "SELECT id FROM ".User::getTable().
         ' WHERE CONCAT(firstname," ",realname) LIKE "'.$fullname.'"';


      $results = $DB->query($query);
      $temp = [];

      while ($data = $DB->fetch_assoc($results)) {
         $temp[] = $data;
      }
      return $temp;
   }

   private function getObjectIDByClassNameAndName($classname, $name){

      $item = new $classname();

      if($item){
         $item->getFromDBByCrit(['name' => $name]);
         return $item->getID();
      }

      // 0 is the default ID of items
      return 0;
   }

   function showHead($type, $import){
      global $CFG_GLPI;
      echo "<thead>";
      echo "<tr>";

      if($type == self::NEW_IMPORTS){

         $title = sprintf(__("New Resource from Import named: %s", "resources"), $import['name']);

         echo "<th colspan='16'>" . $title;

         $title = sprintf(__('%1$s : %2$s'),
            __('Be careful, the resources will be created in the entity', 'resources'),
            Dropdown::getDropdownName('glpi_entities', $_SESSION['glpiactive_entity']));

         echo "<br><span class='red'> " . $title . "</span></th>";

      }
      else if($type == self::CONFLICTED_IMPORTS){

         $title = sprintf(_n("Inconsistency from Import named: %s", 'Inconsistencies from Import named: %s',2, "resources"), $import['name']);

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

      for($i = 0 ; $i < count($resourceColumnNames) ; $i ++){
         echo "<th>";
         foreach($importColumns as $importColumn){
            if($importColumn['resource_column'] == $i){
               echo "<img style='vertical-align: middle;' src='" . $CFG_GLPI["root_doc"] . "/plugins/resources/pics/csv_file.png' width='30' height='30'>";
               break;
            }
         }

         echo "<span style='vertical-align:middle'>".$resourceColumnNames[$i]."</span>";
         echo "</th>";
      }
      echo "</tr>";


      echo "</thead>";
   }

   /**
    *
    *
    * @param $type
    */
   function showList($type){

      $pluginResourcesImport = new PluginResourcesImport();
      $imports = $pluginResourcesImport->find();

      foreach($imports as $import){

         // Limit by the type
         // 0 NEW
         // 1 INCOHERENCE
         $importResources = $this->find(['plugin_resources_imports_id' => $import['id']]);

         echo "<form name='form' method='post' id='massimport' action ='' >";
         echo "<div align='center'>";
         echo "<table border='0' class='tab_cadrehov'>";
         $this->showHead($type, $import);

         foreach($importResources as $importResource){

            echo "<tr valign='center'>";

            echo "<td width='10'>";
            Html::showCheckbox(["name" => "resource[import][".$importResource['id']."]"]);
            echo "</td>";

            $this->showOne($importResource['id']);

            echo "</tr>";
         }

         echo "</table>";
         echo Html::submit(__('Import'), ['name' => 'import']);
//      Html::printPager($limitBegin, $nb, $target, $parameters);
         echo "</div>";
         Html::closeForm();
      }
   }

   function showOne($importResourceId){
      global $CFG_GLPI;
      $pluginResourcesImportResourceData = new PluginResourcesImportResourceData();

      $datas = $pluginResourcesImportResourceData->getResourceDataByImportResource($importResourceId, null, ['resource_column']);

      $resourceColumnNames = PluginResourcesResource::getDataNames();

      $display = [];

      // Add datas of import in associated column
      foreach($datas as $data){
         $display[$data['resource_column']][] = $data;
      }

      // Fill empty categories with default values
      for ($i = 0; $i < count($resourceColumnNames); $i++) {

         if(!isset($display[$i])){
            $display[$i][] = [
               'name' => "",
               'value' => 0,
               'resource_column' => $i
            ];
         }
      }

      // Order the display by index of resourceColumns
      ksort($display);

      foreach ($display as $key => $item) {
         echo "<td style='text-align:center'>";

         for ($i = 0; $i < count($item); $i++) {

            echo "<span>";
            if(!empty($item[$i]['name']) && $item[$i]['resource_column'] != 10){

               if($item[$i]['value'] == -1){
                  echo "<img style='vertical-align:middle' src='" . $CFG_GLPI["root_doc"] . "/plugins/resources/pics/csv_file_red.png' width='30' height='30'>";
               }
            }

            switch ($item[$i]['resource_column']) {
               case 0:
               case 1:
                  echo $item[$i]['value'];
                  break;
               case 2:
                  Dropdown::show('PluginResourcesContractType', [
                     'value' => $item[$i]['value'],
                     'entity' => $_SESSION['glpiactive_entity']
                  ]);
                  break;
               case 3:
                  User::dropdown([
                     'value'  => $item[$i]['value'],
                     'entity' => $_SESSION['glpiactive_entity'],
                     'right'  => 'all'
                  ]);
                  break;
               case 4:
                  Dropdown::show('Location', [
                     'value'  => $item[$i]['value'],
                     'entity' => $_SESSION['glpiactive_entity']
                  ]);
                  break;
               case 5:
                  User::dropdown([
                     'value'  => $item[$i]['value'],
                     'entity' => $_SESSION['glpiactive_entity'],
                     'right'  => 'all']);
                  break;
               case 6:
                  Dropdown::show('PluginResourcesDepartment', [
                     'value'  => $item[$i]['value'],
                     'entity' => $_SESSION['glpiactive_entity']
                  ]);
                  break;
               case 7:
               case 8:
                  $this->showDateFieldWithoutDiv($item[$i]['name'], [
                     'value' => $item[$i]['value']
                  ]);
                  break;
               case 9:
                  User::dropdown([
                     'value'  => $item[$i]['value'],
                     'entity' => $_SESSION['glpiactive_entity'],
                     'right'  => 'all']);
                  break;
               case 10:
                  echo $item[$i]['name'];
                  echo " : ";
                  echo $item[$i]['value'];
                  if ($i < count($item) - 1) {
                     echo "<br>";
                  }
                  break;
            }
            echo "</span>";
         }

         echo "</td>";
      }
   }

   /**
    * Copy of html
    *
    * @return rand value used if displayes else string
    **/
   static function showDateFieldWithoutDiv($name, $options = []) {
      global $CFG_GLPI;

      $p['value']      = '';
      $p['maybeempty'] = true;
      $p['canedit']    = true;
      $p['min']        = '';
      $p['max']        = '';
      $p['showyear']   = true;
      $p['display']    = true;
      $p['rand']       = mt_rand();
      $p['yearrange']  = '';

      foreach ($options as $key => $val) {
         if (isset($p[$key])) {
            $p[$key] = $val;
         }
      }
      $output = "<input id='showdate".$p['rand']."' type='text' size='10' name='_$name' ".
         "value='".Html::convDate($p['value'])."'>";
      $output .= Html::hidden($name, ['value' => $p['value'],
         'id'    => "hiddendate".$p['rand']]);
      if ($p['maybeempty'] && $p['canedit']) {
         $output .= "<span class='fa fa-times-circle pointer' title='".__s('Clear').
            "' id='resetdate".$p['rand']."'>" .
            "<span class='sr-only'>" . __('Clear') . "</span></span>";
      }

      $js = '$(function(){';
      if ($p['maybeempty'] && $p['canedit']) {
         $js .= "$('#resetdate".$p['rand']."').click(function(){
                  $('#showdate".$p['rand']."').val('');
                  $('#hiddendate".$p['rand']."').val('');
                  });";
      }
      $js .= "$( '#showdate".$p['rand']."' ).datepicker({
                  altField: '#hiddendate".$p['rand']."',
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
         $js .= ",minDate: '".self::convDate($p['min'])."'";
      }

      if (!empty($p['max'])) {
         $js .= ",maxDate: '".self::convDate($p['max'])."'";
      }

      if (!empty($p['yearrange'])) {
         $js .= ",yearRange: '". $p['yearrange'] ."'";
      }

      switch ($_SESSION['glpidate_format']) {
         case 1 :
            $p['showyear'] ? $format='dd-mm-yy' : $format='dd-mm';
            break;

         case 2 :
            $p['showyear'] ? $format='mm-dd-yy' : $format='mm-dd';
            break;

         default :
            $p['showyear'] ? $format='yy-mm-dd' : $format='mm-dd';
      }
      $js .= ",dateFormat: '".$format."'";

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
   static function cronResourceImport($task = NULL) {

      $CronTask = new CronTask();
      if ($CronTask->getFromDBbyName("PluginResourcesImportResource", "ResourceImport")) {
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