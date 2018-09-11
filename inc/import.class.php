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
 * Class PluginResourcesResource
 */
class PluginResourcesImport extends CommonDBTM {

   static $rightname = 'plugin_resources_import';

   protected $usenotepad = true;

   public $dohistory = true;

   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @return string
    **/
   static function getTypeName($nb = 0) {

      return __('Import');
   }

   /**
    * @param string $interface
    *
    * @return array
    */
   function getRights($interface = 'central') {

      $values = parent::getRights();

      unset($values[CREATE], $values[DELETE], $values[PURGE], $values[READNOTE], $values[UPDATENOTE]);
      return $values;
   }

   /**
    * For other plugins, add a type to the linkable types
    *
    *
    * @param $type string class name
    **/
   static function registerType($type) {
      if (!in_array($type, self::$types)) {
         self::$types[] = $type;
      }
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
      return Session::haveRight(self::$rightname, READ);
   }

   /**
    * Have I the global right to "create" the Object
    * May be overloaded if needed (ex KnowbaseItem)
    *
    * @return booleen
    **/
   static function canUpdate() {
      return Session::haveRightsOr(self::$rightname, [UPDATE]);
   }

   /**
    * Clean object veryfing criteria (when a relation is deleted)
    *
    * @param $crit array of criteria (should be an index)
    */
   public function clean($crit) {
      global $DB;

      foreach ($DB->request($this->getTable(), $crit) as $data) {
         $this->delete($data);
      }
   }

   /**
    * Get Tab Name used for itemtype
    *
    * NB : Only called for existing object
    *      Must check right on what will be displayed + template
    *
    * @since 0.83
    *
    * @param CommonGLPI $item Item on which the tab need to be displayed
    * @param boolean    $withtemplate is a template object ? (default 0)
    *
    * @return string tab name
    **/
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if (!$withtemplate) {
         if ($item->getID() && $this->canView()) {
            return self::getTypeName(2);
         }
      }
      return '';
   }

   public function rawSearchOptions() {
      $tab = [];

      $tab[] = [
         'id'   => 'common',
         'name' => __('Characteristics')
      ];


      //Données de l'import External
      $tab[] = [
         'id'            => '1',
         'table'         => $this->getTable(),
         'field'         => 'id',
         'name'          => __('ID'),
         'massiveaction' => false,
         'datatype'      => 'number'
      ];

      $tab[] = [
         'id'            => '2',
         'table'         => $this->getTable(),
         'field'         => 'id_external',
         'name'          => __('ID External', 'resources'),
         'massiveaction' => false,
         'datatype'      => 'text'
      ];

      $tab[] = [
         'id'            => '3',
         'table'         => $this->getTable(),
         'field'         => 'name',
         'name'          => __('Name'),
         'massiveaction' => false,
         'datatype'      => 'text'
      ];

      $tab[] = [
         'id'            => '4',
         'table'         => $this->getTable(),
         'field'         => 'firstname',
         'name'          => __('Firstname'),
         'massiveaction' => false,
         'datatype'      => 'text'
      ];

      $tab[] = [
         'id'            => '5',
         'table'         => $this->getTable(),
         'field'         => 'matricule',
         'name'          => __('Matricule'),
         'massiveaction' => false,
         'datatype'      => 'text'
      ];

      $tab[] = [
         'id'            => '6',
         'table'         => $this->getTable(),
         'field'         => 'users_id_sales',
         'name'          => __('Sales manager External', 'resources'),
         'massiveaction' => false,
         'datatype'      => 'text'
      ];

      $tab[] = [
         'id'            => '8',
         'table'         => $this->getTable(),
         'field'         => 'date_begin',
         'name'          => __('Arrival date External', 'resources'),
         'massiveaction' => false,
         'datatype'      => 'date'
      ];

      $tab[] = [
         'id'            => '10',
         'table'         => $this->getTable(),
         'field'         => 'date_end',
         'name'          => __('Departure date External', 'resources'),
         'massiveaction' => false,
         'datatype'      => 'date'
      ];

      $tab[] = [
         'id'            => '12',
         'table'         => $this->getTable(),
         'field'         => 'branching_agency',
         'name'          => __('Agence de rattachement External', 'resources'),
         'massiveaction' => false,
         'datatype'      => 'text'
      ];

      $tab[] = [
         'id'            => '13',
         'table'         => "glpi_locations",
         'field'         => 'name',
         'name'          => __('Location'),
         'massiveaction' => false,
         'datatype'      => 'text'
      ];

      $tab[] = [
         'id'            => '15',
         'table'         => $this->getTable(),
         'field'         => 'origin',
         'name'          => __('Type Contrat External', 'resources'),
         'massiveaction' => false,
         'datatype'      => 'text'
      ];

      $tab[] = [
         'id'            => '16',
         'table'         => 'glpi_plugin_resources_contracttypes',
         'field'         => 'name',
         'name'          => __('Type de contrat', 'resources'),
         'massiveaction' => false,
         'datatype'      => 'dropdown'
      ];


      $tab[] = [
         'id'            => '18',
         'table'         => $this->getTable(),
         'field'         => 'email',
         'name'          => __('Email External', 'resources'),
         'massiveaction' => false,
         'datatype'      => 'text'
      ];

      //Données de GLPI
      if ($_SESSION['actionImport'] == "checkIncoherences") {
         $tab[] = [
            'id'            => '7',
            'table'         => "glpi_plugin_resources_resources",
            'field'         => 'users_id_sales',
            'name'          => __('Sales manager GLPI', 'resources'),
            'massiveaction' => false,
            'datatype'      => 'text'
         ];

         $tab[] = [
            'id'            => '9',
            'table'         => "glpi_plugin_resources_resources",
            'field'         => 'date_begin',
            'name'          => __('Arrival date GLPI', 'resources'),
            'massiveaction' => false,
            'datatype'      => 'text'
         ];

         $tab[] = [
            'id'            => '11',
            'table'         => "glpi_plugin_resources_resources",
            'field'         => 'date_end',
            'name'          => __('Departure date GLPI', 'resources'),
            'massiveaction' => false,
            'datatype'      => 'text'
         ];

         $tab[] = [
            'id'            => '14',
            'table'         => "glpi_plugin_resources_resources",
            'field'         => 'branching_agency_External',
            'name'          => __('Agence de rattachement GLPI', 'resources'),
            'massiveaction' => false,
            'datatype'      => 'text'
         ];

         $tab[] = [
            'id'            => '17',
            'table'         => "glpi_plugin_resources_resources",
            'field'         => 'contracttype_External',
            'name'          => __('Type Contrat GLPI', 'resources'),
            'massiveaction' => false,
            'datatype'      => 'text'
         ];

         $tab[] = [
            'id'            => '19',
            'table'         => "glpi_plugin_resources_resources",
            'field'         => 'email_External',
            'name'          => __('Email GLPI', 'resources'),
            'massiveaction' => false,
            'datatype'      => 'text'
         ];
      }

      return $tab;
   }


   /**
    * show Tab content
    *
    * @since 0.83
    *
    * @param CommonGLPI $item Item on which the tab need to be displayed
    * @param integer    $tabnum tab number (default 1)
    * @param boolean    $withtemplate is a template object ? (default 0)
    *
    * @return boolean
    **/
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType() == 'PluginResourcesResource') {
         $id     = $item->getID();
         $import = new self();
         if ($import->canUpdate()) {
            $import->showAddForm($id);
         } else {
            $import->showDatas($id);
         }
         return true;
      }
   }

   /**
    * @param       $ID
    * @param array $options
    */
   function showDatas($ID, $options = []) {
      if (!$this->canView()) {
         return false;
      }

      $resource = new PluginResourcesResource();
      $resource->getFromDB($ID);

      echo "<div align='center'>";
      echo "<form action='" . Toolbox::getItemTypeFormURL('PluginResourcesResource') . "' method='post'>";
      echo "<table class='tab_cadre' width='50%'>";

      //$this->showFormHeader($options);
      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Registration number', 'resources') . "</td>";
      echo "<td>";
      echo $resource->getField('matricule_External');
      echo "</td>";

      echo "<td>" . __('External ID', 'resources') . "</td>";
      echo "<td>";
      echo $resource->getField('id_external');
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Branching Agency', 'resources') . "</td>";
      echo "<td>";
      echo $resource->getField('branching_agency_External');
      echo "</td>";

      echo "<td>" . __('Email') . "</td>";
      echo "<td>";
      echo $resource->getField('email_External');
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_2 center'>";
      echo "<td colspan='4'>";
      echo "<input type='submit' name='update' value='" . __('Save') . "' class='submit' />";
      echo "<input type='hidden' name='id' value='" . $ID . "'>";
      echo "</td>";

      echo "</tr></table>";

      Html::closeForm();

      echo "</div>";

      return true;
   }

   /**
    * @param       $ID
    * @param array $options
    *
    * @return bool
    */
   function showAddForm($ID, $options = []) {

      if (!$this->canView()) {
         return false;
      }
      $resource = new PluginResourcesResource();
      $resource->getFromDB($ID);

      echo "<div align='center'>";
      echo "<form action='" . Toolbox::getItemTypeFormURL('PluginResourcesResource') . "' method='post'>";
      echo "<table class='tab_cadre' width='50%'>";

      //$this->showFormHeader($options);
      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Registration number', 'resources') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "matricule_External", ["value" => $resource->getField('matricule_External')]);
      echo "</td>";

      echo "<td>" . __('Externa  ID', 'resources') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "id_external", ["value" => $resource->getField('id_external')]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Branching Agency', 'resources') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "branching_agency_External", ["value" => $resource->getField('branching_agency_External')]);
      echo "</td>";

      echo "<td>" . __('Email') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "email_External", ["value" => $resource->getField('email_External')]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_2 center'>";
      echo "<td colspan='4'>";
      echo "<input type='submit' name='update' value='" . __('Save') . "' class='submit' />";
      echo "<input type='hidden' name='id' value='" . $ID . "'>";
      echo "</td>";

      echo "</tr></table>";

      Html::closeForm();

      echo "</div>";

      return true;
   }

   /**
    * Display result table
    *
    * @return nothing
    */
   function showListDatas() {
      global $DB, $CFG_GLPI;

      $options["value"]    = 0;
      $options["comments"] = false;
      $showDiff            = [];
      $limitBegin          = 0;
      if (isset($_GET['start'])) {
         $limitBegin = $_GET['start'];
      }
      if (isset($_SESSION['glpilist_limit'])) {
         $limitNb = $_SESSION['glpilist_limit'];
      } else {
         $limitNb = 0;
      }

      $req = self::initSQL(true, $limitBegin, $limitNb);

      if ($res = $DB->query($req)) {
         if ($res->num_rows > 0) {

            $reqRows = self::initSQL(false, $limitBegin, $limitNb);

            if ($resRows = $DB->query($reqRows)) {
               if ($count = $DB->numrows($resRows)) {
                  $nbRows = $count;
               } else {
                  $nbRows = 0;
               }

               $target     = $CFG_GLPI['root_doc'] . '/plugins/resources/front/import.php';
               $parameters = "actionImport=" . $_SESSION['actionImport'];
               Html::printPager($limitBegin, $nbRows, $target, $parameters);
            }

            $this->listHead();

            echo "<tr>";
            while ($datas = $DB->fetch_assoc($res)) {
               foreach ($datas as $field => $data) {

                  // color for checkIncoherence when data GLPI <> data External
                  if ($_SESSION['actionImport'] == "checkIncoherences") {
                     $fieldsToCheck = ["branching_agency_External" => "branching_agency_External_resources",
                                       "users_id_sales_imports"    => "users_id_sales_resources",
                                       "date_begin_imports"        => "date_begin_resources",
                                       "date_end_imports"          => "date_end_resources",
                                       "email_External"            => "email_External_resources"];
                     $showDiff      = $this->ShowDiffField($fieldsToCheck, $datas, $field);
                  }

                  // Checkbox for the first colomn
                  if ($field == "id") {
                     echo "<td width='10' valign='top'>
                               <span class='form-group-checkbox'>
                                   <input type='checkbox' class='new_checkbox' id='checkall_imports" . $data . "' name='resource[import][" . $data . "]' value='1' data-glpicore-ma-tags='common'>
                                   <label class='label-checkbox' title='' for='checkall_imports" . $data . "'> 
                                      <span class='check'></span> 
                                      <span class='box'></span>&nbsp;
                                   </label>
                               </span>
                           </td>";
                  } else {
                     $this->listContent($field, $data, $datas, $options, $showDiff);
                  }
               }
               if ($_SESSION['actionImport'] == "checkAdd") {
                  $this->dropdownField("PluginResourcesContractType", "",
                                       "plugin_resources_contracttypes_id", $options,
                                       $datas, "Externe");

                  echo "<td valign='top'>";
                  $options["name"] = "resource[values][" . $datas['id'] . "][locations_id]";
                  Dropdown::show('Location', $options);
                  echo "</td>";
               }
               echo "</tr>";
            }
            echo "</tbody></table>";
            if ($_SESSION['actionImport'] == "checkIncoherences") {
               echo Html::submit(__('Export datas (CSV)', 'resource'), ['name' => 'exportCSV']) . "   ";
               echo Html::submit(__('Export datas (PDF)', 'resource'), ['name' => 'exportPDF']) . "   ";
            }
            echo Html::submit(__('Import'), ['name' => 'import']);
            echo "</div>";
            Html::closeForm();
         } else {
            echo "<br /><div class='center b'>" . __('No item found') . "</div>";
         }
      }
   }

   /**
    * @param       $itemType
    * @param       $find
    * @param       $sqlField
    * @param array $options
    * @param       $datas
    * @param       $data
    * @param bool  $noIf
    *
    * @return bool
    */
   function dropdownField($itemType, $find, $sqlField, $options = [], $datas, $data, $noIf = false) {
      echo "<td valign='top'>";
      $itemTypeObj      = new $itemType();
      $findRes          = $itemTypeObj->find($find);
      $options["value"] = 0;
      foreach ($findRes as $findVal) {
         if ($findVal['name'] == $data || $noIf) {
            if ($itemType == "PluginResourcesClient") {
               $options["value"] = $findVal['id'];
            } else if ($itemType == "PluginResourcesContractType") {
               if ($datas["origin"] == "Externe") {
                  $options["value"] = $findVal['id'];
               }
            } else {
               $options["value"] = $findVal['id'];
            }

         }
      }
      $options["name"] = "resource[values][" . $datas['id'] . "][$sqlField]";
      Dropdown::show($itemType, $options);
      echo "</td>";
   }

   /**
    * @param $fieldsToCheck
    * @param $datas
    * @param $field
    *
    * @return string
    */
   function ShowDiffField($fieldsToCheck, $datas, $field) {
      foreach ($fieldsToCheck as $fieldImport => $fieldResource) {
         if ($datas[$fieldResource] != $datas[$fieldImport] &&
             ($field == $fieldResource || $field == $fieldImport)) {
            $showDiff[$fieldResource]['showDiff'] = "font-weight:bold;";
            $showDiff[$fieldImport]['showDiff']   = "font-weight:bold;";
         } else {
            $showDiff[$fieldResource]['showDiff'] = "";
            $showDiff[$fieldImport]['showDiff']   = "";
         }
      }
      return $showDiff;
   }

   /**
    * Init header list
    */
   function listHead() {
      global $CFG_GLPI;
      echo "<form name='form' method='post'
         action ='" . $CFG_GLPI["root_doc"] . "/plugins/resources/front/import.php?actionImport=" . $_SESSION['actionImport'] . "' >";
      echo "<input type='hidden' name='actionImport' value='" . $_SESSION['actionImport'] . "'>";
      echo "<div align='center'>
                <table boreder='0' class='tab_cadrehov'>";


      $entete = 0;
      if ($_SESSION['actionImport'] == "checkIncoherences") {
         echo "<tr>";
         echo "<th colspan='12'>Données Fichier External</th>";
         echo "<th style='border-left:2px solid black' colspan='8'>Données ressource GLPI</th>";
         echo "</tr>";
      }
      echo "<tr>";
      echo "<th class=''>
                <div class='form-group-checkbox'>
                   <input title='Tout cocher comme' type='checkbox' class='new_checkbox' name='checkall_imports' id='checkall_imports' 
                   onclick='checkAll(this.checked);' >";
      echo "<script>
      function checkAll(state) {
         var cases = document.getElementsByTagName('input');
         for(var i=0; i<cases.length; i++){
           if(cases[i].type == 'checkbox'){
                cases[i].checked = state;   
            } 
         }
     }       
      </script>";
      echo "<label class='label-checkbox' for='checkall_imports' title='Tout cocher comme'>
                      <span class='check'></span>
                      <span class='box'></span>
                   </label>
                </div>
              </th>";
      echo "<th>" . __("External ID", 'resources') . "</th>";
      echo "<th>" . __("Registration number", 'resources') . "</th>";
      echo "<th>" . __("Name") . "</th>";
      echo "<th>" . __("First name") . "</th>";
      echo "<th>" . __("Origin") . "</th>";
      echo "<th>" . __("Branching Agency", "resources") . "</th>";
      echo "<th>" . __("Sales manager", "resources") . "</th>";
      echo "<th>" . __("Begin date") . "</th>";
      echo "<th>" . __("End date") . "</th>";
      echo "<th>" . _n("Affected client", "Affected clients", 1, "resources") . "</th>";
      echo "<th>" . __("Email") . "</th>";
      if ($_SESSION['actionImport'] == "checkIncoherences") {
         echo "<th style='border-left:2px solid black'>" . __("Contract type External") . "</th>";
         echo "<th>" . __("Contract type") . "</th>";
         echo "<th>" . __("Branching Agency", "resources") . "</th>";
         echo "<th>" . __("Sales manager", "resources") . "</th>";
         echo "<th>" . __("Begin date") . "</th>";
         echo "<th>" . __("End date") . "</th>";
         echo "<th>" . __("Email") . "</th>";
         echo "<th>" . __("Location") . "</th>";
      } else if ($_SESSION['actionImport'] == "checkAdd") {
         echo "<th>" . __("Contract type") . "</th>";
         echo "<th>" . __("Location") . "</th>";
      }

      echo "</tr></thead><tbody>";
   }

   /**
    * @param $field
    * @param $data
    * @param $datas
    * @param $options
    * @param $color
    */
   function listContent($field, $data, $datas, $options, $showDiff) {
      if (isset($showDiff[$field]['showDiff'])) {
         $showDiff = $showDiff[$field]['showDiff'];
      } else {
         $showDiff = "";
      }
      switch ($field) {
         case "contracttype_External" :
            echo "<td style='border-left:2px solid black' valign='top'>" . $data . "</td>";
            break;
         case "affected_client_imports" :
            $this->dropdownField("PluginResourcesClient", "",
                                 "affected_client_imports", $options,
                                 $datas, $data);
            break;
         case "name_contracttypes" :
            $this->dropdownField("PluginResourcesContractType", "name = '$data'",
                                 "plugin_resources_contracttypes_id", $options,
                                 $datas, $data, true);
            break;
         case "name_locations" :
            $this->dropdownField("Location", "",
                                 "locations_id", $options,
                                 $datas, $data);
            break;
         case "users_id_sales_resources" :
         case "users_id_sales_imports" :
            $resp = "Pas de responsable";
            $user = new User();
            if ($user->getFromDB($data)) {
               $resp = $user->getField("firstname") . " " . $user->getField("realname");
            }
            echo "<td valign='top' style='" . $showDiff . "'><input type='hidden' value='" . $data . "' 
                        name='resource[values][" . $datas['id'] . "][" . $field . "]'>" . $resp . "</td>";
            break;
         case "date_begin_imports" :
         case "date_begin_resources" :
         case "date_end_imports" :
         case "date_end_resources" :
            if ($data != "" && $data != null) {
               echo "<td valign='top' style='" . $showDiff . "'><input type='hidden' value='" . $data . "' 
                                    name='resource[values][" . $datas['id'] . "][" . $field . "]'>" . date("d/m/Y", strtotime($data)) . "</td>";
            } else {
               echo "<td></td>";
            }
            break;
         default :
            //            echo html::input("resource[values][".$datas['id']."][".$field."]",["value"=>$data]);
            echo "<td valign='top' style='" . $showDiff . "'><input type='hidden' value='" . $data . "' 
                     name='resource[values][" . $datas['id'] . "][" . $field . "]'>" . $data . "</td>";
            break;
      }
   }

   /**
    * @param $values
    * @param $action
    */
   function processResources($values, $action) {
      $resource         = new PluginResourcesResource();
      $import           = new PluginResourcesImport();
      $valuesUpdateKeys = [];
      foreach ($values as $field => $val) {
         if (strpos($field, "imports")) {
            $updateKeys                               = substr($field, 0, strpos($field, "imports") - 1);
            $valuesUpdateKeys['imports'][$updateKeys] = $val;
         } else if (strpos($field, "resources")) {
            if ($field == "plugin_resources_contracttypes_id") {
               $valuesUpdateKeys['imports'][$field] = $val;
            } else {
               $updateKeys                                 = substr($field, 0, strpos($field, "resources") - 1);
               $valuesUpdateKeys['resources'][$updateKeys] = $val;
            }
         } else {
            if ($field == "origin") {
               $valuesUpdateKeys['imports']['contracttype_External'] = $val;
            } else {
               $valuesUpdateKeys['imports'][$field] = $val;
            }
         }
      }
      switch ($action) {
         case "checkAdd" :
            $valuesUpdateKeys['imports']['entities_id'] = 0;
            if ($resource->add($valuesUpdateKeys['imports'])) {
               $import->getFromDBByCrit(["id_external" => $valuesUpdateKeys['imports']["id_external"]]);
               $import->deleteFromDB();
               Session::addMessageAfterRedirect(__('Resource Successfully imported', 'resources'), true, INFO);
            } else {
               Session::addMessageAfterRedirect(__('Unable to import the resource', 'resources'), true, ERROR);
            }
            break;
         case "checkIncoherences" :
            $resource->getFromDBByCrit(["id_external" => $valuesUpdateKeys['imports']["id_external"]]);
            $valuesUpdateKeys['imports']['id'] = $resource->getField("id");
            if ($resource->update($valuesUpdateKeys['imports'])) {
               $import->getFromDBByCrit(["id_external" => $valuesUpdateKeys['imports']["id_external"]]);
               $import->deleteFromDB();
               Session::addMessageAfterRedirect(__('Resource Successfully updated', 'resources'), true, INFO);
            } else {
               Session::addMessageAfterRedirect(__('Unable to update the resource', 'resources'), true, ERROR);
            }
            break;
         case "importIncoherencesPDF" :
         case "importIncoherencesCSV" :
            return $valuesUpdateKeys;
            break;
            break;
         case "checkDelete" :
            $resource->getFromDBByCrit(["id_external" => $valuesUpdateKeys['imports']["id_external"]]);
            $valuesUpdateKeys['imports']['id'] = $resource->getField("id");
            if ($resource->update($valuesUpdateKeys['imports'])) {
               $import->getFromDBByCrit(["id_external" => $valuesUpdateKeys['imports']["id_external"]]);
               $import->deleteFromDB();
               Session::addMessageAfterRedirect(__('Resource end date successfully update', 'resources'), true, INFO);
            } else {
               Session::addMessageAfterRedirect(__('Unable to update the resource', 'resources'), true, ERROR);
            }
            break;
      }
   }

   /**
    * Construct SQL request depending of search parameters
    *
    * add to data array a field sql containing an array of requests :
    *      search : request to get items limited to wanted ones
    *      count : to count all items based on search criterias
    *                    may be an array a request : need to add counts
    *                    maybe empty : use search one to count
    *
    * @since version 0.85
    *
    * @param $data    array of search datas prepared to generate SQL
    *
    * @return nothing
    **/
   static function initSQL($limit, $limitBegin, $limitNb) {
      $SELECT  = "SELECT imp.id as id, 
                        imp.id_external as id_external_imports,
                        imp.matricule as matricule_External, 
                        imp.name as name_imports, 
                        imp.firstname as firstname_imports,
                        imp.origin as origin,
                        imp.branching_agency as branching_agency_External,
                        imp.users_id_sales as users_id_sales_imports,
                        imp.date_begin as date_begin_imports,
                        imp.date_end as date_end_imports,
                        imp.affected_client as affected_client_imports,
                        imp.email as email_External ";
      $FROM    = "FROM glpi_plugin_resources_imports imp ";
      $JOIN    = "INNER JOIN glpi_plugin_resources_resources ON glpi_plugin_resources_resources.id_external = imp.id_external 
               INNER JOIN glpi_plugin_resources_contracttypes ON glpi_plugin_resources_resources.plugin_resources_contracttypes_id = glpi_plugin_resources_contracttypes.id                
               ";
      $WHERE   = "";
      $GROUPBY = "";
      $ORDER   = " ORDER BY imp.id";
      if ($_SESSION['actionImport'] == 'checkAdd') {
         $WHERE .= "WHERE id_external NOT IN(
                              SELECT id_external 
                              FROM glpi_plugin_resources_resources
                              WHERE id_external!='') ";
      } else if ($_SESSION['actionImport'] == 'checkIncoherences') {
         $resource = new PluginResourcesResource();

         $SELECT  .= ",glpi_plugin_resources_resources.contracttype_External as contracttype_External,
                      glpi_plugin_resources_contracttypes.name as name_contracttypes,
                      glpi_plugin_resources_resources.branching_agency_External as branching_agency_External_resources,
                      glpi_plugin_resources_resources.users_id_sales as users_id_sales_resources,
                      glpi_plugin_resources_resources.date_begin as date_begin_resources,
                      glpi_plugin_resources_resources.date_end as date_end_resources,
                      glpi_plugin_resources_resources.email_External as email_External_resources,
                      (SELECT name FROM glpi_locations WHERE glpi_plugin_resources_resources.locations_id = glpi_locations.id ) as name_locations ";
         $FROM    .= $JOIN;
         $WHERE   .= "WHERE glpi_plugin_resources_resources.branching_agency_External != imp.branching_agency
                    OR glpi_plugin_resources_resources.users_id_sales != imp.users_id_sales 
                    OR glpi_plugin_resources_resources.date_begin != imp.date_begin 
                    OR glpi_plugin_resources_resources.date_end != imp.date_end 
                    OR glpi_plugin_resources_resources.email_External != imp.email 
                    OR glpi_plugin_resources_resources.contracttype_External != imp.origin ";
         $GROUPBY = " GROUP BY imp.id";
      } else if ($_SESSION['actionImport'] == 'checkDelete') {
         $SELECT .= " ";
         $FROM   .= $JOIN;
         $WHERE  .= "WHERE  glpi_plugin_resources_resources.date_end IS NULL AND imp.date_end > 0
                    OR glpi_plugin_resources_resources.date_end < imp.date_end";
      }

      if ($limit) {
         $LIMIT = " LIMIT " . $limitBegin . "," . $limitNb;
      } else {
         $LIMIT = "";
      }

      $QUERY = $SELECT . $FROM . $WHERE . $GROUPBY . $ORDER . $LIMIT;
      return $QUERY;
   }

   /**
    * Get all CSV Data from file $file
    *
    * @param $file
    *
    * @return array $res
    */
   function getCsvDatas($path, $file) {
      $res    = $entetes = [];
      $rowNum = 1;
      if (($handle = fopen($path . $file, "r")) !== false) {
         while (($data = fgetcsv($handle, 1000, ";")) !== false) {
            $nbRows = count($data);
            for ($i = 0; $i < $nbRows; $i++) {
               if ($rowNum == 1) {
                  $entetes[] = utf8_encode($data[$i]);
               } else {
                  $res[$rowNum][$entetes[$i]] = utf8_encode($data[$i]);
               }
            }

            $rowNum++;
         }
         fclose($handle);
      }

      return $res;
   }

   /**
    * @param $array
    * @param $delimiter
    */
   function array_download($array, $delimiter) {

      $entete = ["id_external"           => "External ID",
                 "matricule_External"    => "Matricule",
                 "name"                  => "Nom",
                 "firstname"             => "Prenom",
                 "contracttype_External" => "Type contrat",
                 "users_id_sales"        => "Resp comm",
                 "date_begin"            => "Date Debut",
                 "date_end"              => "Date Fin",
                 "affected_client"       => "Client affecte",
                 "email_External"        => "Email"];
      ksort($entete);
      $f = fopen('php://temp', 'w');
      foreach ($array as $id => $resources) {
         $arrayResource[$id] = $resources['imports'];
         foreach ($entete as $keyTitle => $title) {
            if (!array_key_exists($keyTitle, $resources['imports'])) {
               $arrayResource[$id][$keyTitle] = "";
            }
         }
         foreach ($resources['imports'] as $key => $resource) {
            if (!array_key_exists($key, $entete)) {
               unset($arrayResource[$id][$key]);
            }

         }
         ksort($arrayResource[$id]);
      }
      // generate csv lines from the array
      if ($delimiter != "") {
         fputcsv($f, $entete, $delimiter);
         foreach ($arrayResource as $val) {
            fputcsv($f, $val, $delimiter);
         }

         fseek($f, 0);

         header('Content-Type: application/csv');
         header('Content-Disposition: attachment; filename="export.csv";');

         fpassthru($f);
      } else {
         $pdf = new FPDF();
         $pdf->AddPage("L");
         $pdf->SetFont('Arial', '', 8);
         foreach ($entete as $keyEntete => $rowEntet) {
            if ($keyEntete == "email_External") {
               $pdf->Cell(40, 5, $rowEntet);
            } else {
               $pdf->Cell(25, 5, $rowEntet);
            }
         }
         $pdf->Ln();

         foreach ($arrayResource as $rowResource) {
            foreach ($rowResource as $key => $row) {
               if ($key == "email_External") {
                  $pdf->Cell(40, 5, $row);
               } else {
                  $pdf->Cell(25, 5, $row);
               }

               if ($key == "users_id_sales") {
                  $pdf->Ln();
               }
            }
         }
         $pdf->Output();

         header('Content-Type: application/pdf');
         header('Content-Disposition: attachment; filename="export.pdf";');
      }


      fclose($f);
      exit;
   }

   /**
    * Get the data from CSV and create or update object GLPI
    *
    * @return array output
    */
   function initCsvDataAndImportGLPI($task) {
      $path      = GLPI_PLUGIN_DOC_DIR . "/resources/import/";
      $files     = scandir($path);
      $nbRowAdd  = 0;
      $fileDatas = "";
      // All files in the folder
      foreach ($files as $file) {
         if (!is_dir($path . "/" . $file)) {
            $fileDatas = $this->getCsvDatas($path, $file);
            //Translate files data for inserting in Database
            $arrayCorrespondance = [
               'id'                     => 'id_external',
               'Origine'                => 'origin',
               'Matricule'              => 'matricule',
               'Nom'                    => 'name',
               'Prénom'                 => 'firstname',
               'Agence de rattachement' => 'branching_agency',
               'Responsable commercial' => 'users_id_sales',
               'Date d\'Entrée'         => 'date_begin',
               'Date de Sortie'         => 'date_end',
               'Société'                => 'affected_client',
               'eMail'                  => 'email',
            ];

            $import = new self();
            $datas  = [];
            //Get all datas from files
            foreach ($fileDatas as $fileData) {
               foreach ($fileData as $key => $raw) {

                  //Format in date format date_begin & date_end values
                  if (($arrayCorrespondance[$key] == 'date_begin' || $arrayCorrespondance[$key] == 'date_end')) {
                     if (trim($raw) != "" && $raw != null) {
                        $raw = DateTime::createFromFormat('d/m/Y', $raw)->format('Y-m-d');
                     } else {
                        $raw = 'NULL';
                     }
                  }
                  if ($arrayCorrespondance[$key] == 'matricule' && $datas["origin"] == "Interne") {
                     $raw = "C" . $raw;
                  }
                  $datas[$arrayCorrespondance[$key]] = preg_replace("/\s+/", " ", Html::cleanInputText($raw));
               }

               //If there is no row with the id
               if (!$import->getFromDBByCrit(['id_external"' => $datas['id_external'],
                                              '"is_active'   => 1])) {
                  $nbRowAdd++;

                  $resource = new PluginResourcesResource();

                  //Import id of commercial resp
                  $resp      = new User();
                  $firstname = substr($datas['users_id_sales'],
                                      0,
                                      strpos($datas['users_id_sales'], " "));
                  $realname  = substr($datas['users_id_sales'],
                                      strpos($datas['users_id_sales'],
                                             " ") + 1);
                  $cnt       = countElementsInTable('glpi_users', ['firstname' => $firstname,
                                                                   'realname'  => $realname,
                                                                   'is_active' => 1]);

                  if ($cnt == 1) {
                     $resp->getFromDBByCrit(["firstname" => $firstname,
                                             "realname"  => $realname,
                                             "is_active" => 1]);
                     $datas['users_id_sales'] = $resp->getField('id');
                  } else {
                     $datas['users_id_sales'] = 0;
                  }

                  // Process for adding datas in import table
                  if ($resource = $resource->getFromDBByCrit(["id_external" => $datas['id_external'],
                                                              "is_active"   => 1])) {

                     //if datas are identical with ressource data we skip the raw
                     if ($datas['date_begin'] == $resource->getField('date_begin') &&
                         $datas['date_end'] == $resource->getField('date_end') &&
                         $datas['branching_agency'] == $resource->getField('branching_agency_External') &&
                         $datas['users_id_sales'] == $resource->getField('users_id_sales') &&
                         $datas['email'] == $resource->getField('email_External')) {
                        continue;
                     }
                     $import->add($datas);
                  } else {
                     $import->add($datas);
                  }
               }
            }
            if ($nbRowAdd > 0) {
               if ($task) {
                  $task->addVolume($nbRowAdd);
                  $task->log(__('External datas successfully imported', 'resources'));
                  rename($path . $file, $path . "/done/" . $file);
               }
            } else {
               $task->log(__('No item selected', 'resources'));
            }
         }
      }
      if ($fileDatas == "") {
         $task->log(__('No file found', 'resources'));
      }
   }

   /**
    * Get the specific massive actions
    *
    * @since version 0.84
    *
    * @param $checkitem link item to check right   (default NULL)
    *
    * @return an array of massive actions
    * */
   function getSpecificMassiveActions($checkitem = null) {
      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);

      if ($isadmin && Session::getCurrentInterface() == 'central') {
         if ($_SESSION["actionImport"] == "checkAdd") {
            $actions['PluginResourcesImport' . MassiveAction::CLASS_ACTION_SEPARATOR . 'ImportAdd'] = __("Import ressources", 'resources');
         } else if ($_SESSION["actionImport"] == "checkIncoherences") {
            $actions['PluginResourcesImport' . MassiveAction::CLASS_ACTION_SEPARATOR . 'ImportUpdate'] = __("Update inconsistencies", 'resources');
         } else if ($_SESSION["actionImport"] == "checkDelete") {
            $actions['PluginResourcesImport' . MassiveAction::CLASS_ACTION_SEPARATOR . 'ImportDelete'] = __("Import end date of ressources", 'resources');

         }
      }
      return $actions;
   }

   /**
    * @since version 0.84
    **/
   function getForbiddenStandardMassiveAction() {

      $forbidden   = parent::getForbiddenStandardMassiveAction();
      $forbidden[] = 'update';
      return $forbidden;
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
    * */
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {

      switch ($ma->getAction()) {
         case "ImportAdd" :

            break;
         case "ImportUpdate" :

            break;
         case "ImportDelete" :

            break;
      }
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
         case 'ImportExternal':
            return [
               'description' => __('External files imports', 'resources')];   // Optional
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
   static function cronImportExternal($task = NULL) {
      global $DB, $CFG_GLPI;

      $CronTask = new CronTask();
      if ($CronTask->getFromDBbyName("PluginResourcesImport", "ImportExternal")) {
         if ($CronTask->fields["state"] == CronTask::STATE_DISABLE) {
            return 0;
         }
      } else {
         return 0;
      }

      $import = new self();
      $import->initCsvDataAndImportGLPI($task);
      return 1;
   }

}