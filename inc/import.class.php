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
 * Class PluginResourcesImport
 */
class PluginResourcesImport extends CommonDBTM {

   static $rightname = 'plugin_resources_import';

   protected $usenotepad = true;

   public $dohistory = true;

   const ACTION_ADD         = "checkAdd";
   const ACTION_INCOHERENCE = "checkIncoherences";
   const ACTION_DELETE      = "checkDelete";

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
         $import = new self();
         $import->showDatas($item->getID());
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

      echo "<tr class='tab_bg_1'>";
      echo "<th colspan='4'>" . self::getTypeName() . "</th></tr>";
      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __("Administrative number") . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "matricule_external",
                                    ["value" => $resource->getField('matricule_external')]);
      echo "</td>";

      echo "<td>" . __('External ID', 'resources') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "id_external",
                                    ["value" => $resource->getField('id_external')]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td>" . __('Branch agency', 'resources') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "branching_agency_external",
                                    ["value" => $resource->getField('branching_agency_external')]);
      echo "</td>";

      echo "<td>" . __('Email') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "email_external",
                                    ["value" => $resource->getField('email_external')]);
      echo "</td>";

      echo "</tr>";

      if ($this->canUpdate()) {
         echo "<tr class='tab_bg_2 center'>";
         echo "<td colspan='4'>";
         echo Html::submit(__('Save'), ['name' => 'update']);
         echo Html::hidden('id', ['value' => $ID]);
         echo "</td>";

         echo "</tr>";
      }

      echo "</table>";

      Html::closeForm();

      echo "</div>";
   }

   /**
    * Returns the name of the interface according to the action
    *
    * @param $action
    *
    * @return string
    */
   static function getNameInterface($action) {
      switch ($action) {
         case self::ACTION_ADD :
            return __('Import new resource', 'resources');

         case self::ACTION_INCOHERENCE :
            return _n('Inconsistency', 'Inconsistencies', 2, 'resources');

         case self::ACTION_DELETE:
            return __('Outgoing resources', 'resources');
      }
   }

   /**
    * Display result table
    *
    * @return nothing
    */
   function showListDatas($actionImport) {
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

      $req = self::initSQL($actionImport, $limitBegin, $limitNb);

      if ($res = $DB->query($req)) {
         if ($res->num_rows > 0) {

            $reqRows = self::initSQL($actionImport);

            if ($resRows = $DB->query($reqRows)) {
               if ($count = $DB->numrows($resRows)) {
                  $nbRows = $count;
               } else {
                  $nbRows = 0;
               }
               echo "<form name='form' method='post' id='massimport'
                  action ='" . $CFG_GLPI["root_doc"] . "/plugins/resources/front/import.php?actionImport=" . $actionImport . "' >";
               $target     = $CFG_GLPI['root_doc'] . '/plugins/resources/front/import.php';
               $parameters = "actionImport=$actionImport";

               Html::printPager($limitBegin, $nbRows, $target, $parameters);
            }

            $this->showHead($actionImport);

            echo "<tr>";
            while ($datas = $DB->fetch_assoc($res)) {
               foreach ($datas as $field => $value) {

                  // color for checkIncoherence when data GLPI <> data External
                  if ($actionImport == self::ACTION_INCOHERENCE) {
                     $fieldsToCheck = ["branching_agency_external" => "branching_agency_external_resources",
                                       "users_id_sales_imports"    => "users_id_sales_resources",
                                       "date_begin_imports"        => "date_begin_resources",
                                       "date_end_imports"          => "date_end_resources",
                                       "email_external"            => "email_external_resources"];
                     $showDiff      = $this->showDiffField($fieldsToCheck, $datas, $field);
                  }

                  // Checkbox for the first colomn
                  if ($field == "id") {
                     echo "<td width='10' valign='top'>";
                     Html::showCheckbox(["name" => "resource[import][$value]"]);
                     echo "</td>";
                  } else {
                     $this->listContent($field, $value, $datas, $options, $showDiff);
                  }
               }

               if ($actionImport == self::ACTION_ADD) {
                  //if the "origin" value is present in the standard contract, it will be selected by default
                  $contract_type_id = 0;
                  $contract_type    = new PluginResourcesContractType();
                  if ($contract_type->getFromDBByCrit(['name' => $datas['origin']])) {
                     $contract_type_id = $contract_type->getID();
                  }
                  $this->dropdownField("PluginResourcesContractType", "plugin_resources_contracttypes_id",
                                       $options, $datas['id'], $contract_type_id);

                  echo "<td valign='top'>";
                  $options["name"] = "resource[values][" . $datas['id'] . "][locations_id]";
                  Dropdown::show('Location', $options);
                  echo "</td>";
               }
               echo "</tr>";
            }
            echo "</tbody></table>";
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
    * @param       $value
    * @param bool  $noIf
    *
    * @return bool
    */
   function dropdownField($itemType, $sqlField, $options, $id, $value, $style = "") {
      echo "<td $style valign='top'>";
      $options["value"] = $value;

      $options["name"] = "resource[values][$id][$sqlField]";
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
   function showDiffField($fieldsToCheck, $datas, $field) {

      foreach ($fieldsToCheck as $fieldImport => $fieldResource) {
         if ($datas[$fieldResource] != $datas[$fieldImport] &&
             ($field == $fieldResource || $field == $fieldImport)) {
            $showDiff[$fieldResource]['showDiff'] = "font-weight:bold;color:red;";
            $showDiff[$fieldImport]['showDiff']   = "font-weight:bold;color:red;";
         } else {
            $showDiff[$fieldResource]['showDiff'] = "";
            $showDiff[$fieldImport]['showDiff']   = "";
         }
      }
      return $showDiff;
   }

   /**
    * show header list
    *
    * @param $actionImport
    */
   function showHead($actionImport) {

      echo "<input type='hidden' name='actionImport' value='" . $actionImport . "'>";
      echo "<div align='center'>";
      echo "<table border='0' class='tab_cadrehov'>";

      echo "<tr></tr>";

      //Title
      echo "<tr>";
      switch ($actionImport) {
         case self::ACTION_ADD :
            //Title
            //Alert message for the resource creation entity
            echo "<th colspan='15'>" . self::getNameInterface(self::ACTION_ADD) .
                 "<br><span class='red'> " . sprintf(__('%1$s : %2$s'),
                                                     __('Be careful, the resources will be created in the entity'),
                                                     Dropdown::getDropdownName('glpi_entities', $_SESSION['glpiactive_entity'])) . "</span></th>";
            break;
         case self::ACTION_INCOHERENCE :

            echo "<th colspan='20'>" . self::getNameInterface(self::ACTION_INCOHERENCE) . "</th>";
            echo "</tr><tr>";
            echo "<th colspan='12'>" . __("Datas external file", 'resources') . "</th>";
            echo "<th style='border-left:2px solid black' colspan='8'>" . __("GLPI resource datas", 'resources') . "</th>";
            break;
         case self::ACTION_DELETE :
            echo "<th colspan='12'>" . self::getNameInterface(self::ACTION_DELETE) . "</th>";
            break;
      }
      echo "</tr>";

      echo "<th>";
      echo Html::getCheckAllAsCheckbox('massimport');
      echo "</th>";
      echo "<th>" . __("External ID", 'resources') . "</th>";
      echo "<th>" . __("Administrative number") . "</th>";
      echo "<th>" . __("Name") . "</th>";
      echo "<th>" . __("First name") . "</th>";
      echo "<th>" . __("Origin", "resources") . "</th>";
      echo "<th>" . __("Branch agency", "resources") . "</th>";
      echo "<th>" . __("Sales manager", "resources") . "</th>";
      echo "<th>" . __("Begin date") . "</th>";
      echo "<th>" . __("End date") . "</th>";
      echo "<th>" . __("Company", "resources") . "</th>";
      echo "<th>" . __("Email") . "</th>";

      //add fields for interface Inconsistencies
      if ($actionImport == self::ACTION_INCOHERENCE) {
         echo "<th style='border-left:2px solid black'>" . __("Contract type") . "</th>";
         echo "<th>" . __("Branch agency", "resources") . "</th>";
         echo "<th>" . __("Sales manager", "resources") . "</th>";
         echo "<th>" . __("Begin date") . "</th>";
         echo "<th>" . __("End date") . "</th>";
         echo "<th>" . __("Email") . "</th>";
         echo "<th>" . _n("Affected client", "Affected clients", 1, "resources") . "</th>";
         echo "<th>" . __("Location") . "</th>";

         //add fields for interface add resources
      } else if ($actionImport == self::ACTION_ADD) {
         echo "<th>" . _n("Affected client", "Affected clients", 1, "resources") . "</th>";
         echo "<th>" . __("Contract type") . "</th>";
         echo "<th>" . __("Location") . "</th>";
      }

      echo "</tr><tbody>";
   }

   /**
    * @param $field
    * @param $data
    * @param $datas
    * @param $options
    * @param $color
    */
   function listContent($field, $value, $datas, $options, $showDiff) {
      if (isset($showDiff[$field]['showDiff'])) {
         $showDiff = $showDiff[$field]['showDiff'];
      } else {
         $showDiff = "";
      }
      switch ($field) {
         case "contracttypes_id" :
            $this->dropdownField("PluginResourcesContractType", "plugin_resources_contracttypes_id",
                                 $options, $datas['id'], $value, "style='border-left:2px solid black'");
            break;
         case 'clients_id' :
            $this->dropdownField("PluginResourcesClient", "plugin_resources_clients_id", $options,
                                 $datas['id'], $value);
            break;
         case 'client_name':
            $client = new PluginResourcesClient();
            if ($client->getFromDBByCrit(['name' => $value])) {
               $value = $client->getID();
            }
            $this->dropdownField("PluginResourcesClient", "plugin_resources_clients_id", $options,
                                 $datas['id'], $value);

            break;
         case "locations_id" :
            $this->dropdownField("Location", "locations_id", $options, $datas['id'], $value);
            break;
         case "users_id_sales_resources" :
         case "users_id_sales_imports" :
            $resp = __("No sales manager", "resources");
            $user = new User();
            if ($user->getFromDB($value)) {
               $resp = $user->getField("firstname") . " " . $user->getField("realname");
            }
            echo "<td valign='top' style='" . $showDiff . "'>" . $resp . "</td>";
            break;
         case "date_begin_imports" :
         case "date_begin_resources" :
         case "date_end_imports" :
         case "date_end_resources" :
            if ($value != "" && $value != null) {
               echo "<td valign='top' style='" . $showDiff . "'>" . Html::convDate($value) . "</td>";
            } else {
               echo "<td></td>";
            }
            break;
         default :
            echo "<td valign='top' style='" . $showDiff . "'>" . $value . "</td>";
            break;
      }
   }

   /**
    * @param $values
    * @param $action
    */
   function processResources($id, $values, $action) {

      $resource = new PluginResourcesResource();
      $import   = new PluginResourcesImport();
      $import->getFromDB($id);

      $values = array_merge($import->fields, $values);
      $input  = [];

      foreach ($values as $field => $val) {
         if (strpos($field, "imports")) {
            $updateKeys         = substr($field, 0, strpos($field, "imports") - 1);
            $input[$updateKeys] = $val;
         } else if (strpos($field, "resources")) {
            $input[$field] = $val;

         } else {
            //save fields for resource in import
            if ($field == "origin") {
               $input['contracttype_external'] = $val;
            } else if ($field == "matricule") {
               $input["matricule_external"] = $val;
            } else if ($field == "branching_agency") {
               $input["branching_agency_external"] = $val;
            } else if ($field == "email") {
               $input["email_external"] = $val;
            } else {
               $input[$field] = $val;
            }
         }
      }

      switch ($action) {
         //Add resource
         case self::ACTION_ADD :
            $input['entities_id'] = $_SESSION['glpiactive_entity'];
            unset($input['id']);
            if ($resource_id = $resource->add($input)) {
               //add employee
               if (isset($input['plugin_resources_clients_id'])
                   && $input['plugin_resources_clients_id'] > 0) {
                  $employee                               = new PluginResourcesEmployee();
                  $input['plugin_resources_resources_id'] = $resource_id;
                  $employee->add($input);
               }
               //delete line in import
               $import->deleteFromDB();
               Session::addMessageAfterRedirect(__('Resource successfully imported', 'resources'), true, INFO);
            } else {
               Session::addMessageAfterRedirect(__('Unable to import the resource', 'resources'), true, ERROR);
            }
            break;
         case self::ACTION_INCOHERENCE :
            //update resource
            $resource->getFromDBByCrit(["id_external" => $input["id_external"]]);
            $input['id'] = $resource->getField("id");

            if ($resource->update($input)) {

               //add employee
               if (isset($input['plugin_resources_clients_id'])
                   && $input['plugin_resources_clients_id'] > 0) {
                  $employee = new PluginResourcesEmployee();
                  if ($employee->getFromDBByCrit(['plugin_resources_resources_id' => $resource->getID()])) {
                     //update
                     $input['id'] = $employee->getID();
                     $employee->update($input);
                  } else {
                     //add employee
                     $input['plugin_resources_resources_id'] = $resource->getID();
                     $employee->add($input);
                  }

               }
               //delete line in import
               $import->deleteFromDB();
               Session::addMessageAfterRedirect(__('Resource successfully updated', 'resources'), true, INFO);
            } else {
               Session::addMessageAfterRedirect(__('Unable to update the resource', 'resources'), true, ERROR);
            }
            break;
         case self::ACTION_DELETE :
            //update resource with date_end
            $resource->getFromDBByCrit(["id_external" => $input["id_external"]]);
            $input['id']                         = $resource->getField("id");
            $input['is_leaving']                 = 1;
            $input['users_id_recipient_leaving'] = Session::getLoginUserID();
            if ($resource->update($input)) {
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
    * @param     $actionImport
    * @param int $limitBegin
    * @param int $limitNb
    *
    * @return string
    */
   static function initSQL($actionImport, $limitBegin = 0, $limitNb = 0) {

      $table_import    = "glpi_plugin_resources_imports";
      $table_resources = "glpi_plugin_resources_resources";
      $entities_id     = $_SESSION['glpiactiveentities'];

      $SELECT = "SELECT $table_import.id as id, 
                        $table_import.id_external as id_external_imports,
                        $table_import.matricule as matricule_external, 
                        $table_import.name as name_imports, 
                        $table_import.firstname as firstname_imports,
                        $table_import.origin as origin,
                        $table_import.branching_agency as branching_agency_external,
                        $table_import.users_id_sales as users_id_sales_imports,
                        $table_import.date_begin as date_begin_imports,
                        $table_import.date_end as date_end_imports,
                        $table_import.affected_client as affected_client,
                        $table_import.email as email_external ";
      $FROM   = "FROM $table_import ";

      $JOIN = "INNER JOIN $table_resources 
                  ON $table_resources.id_external = $table_import.id_external ";

      $WHERE   = "";
      $GROUPBY = "";
      $ORDER   = " ORDER BY $table_import.id";

      if ($actionImport == self::ACTION_ADD) {

         $SELECT .= ",$table_import.affected_client as client_name ";
         //select id_external that are not present in resource
         $WHERE .= "WHERE id_external NOT IN(
                              SELECT id_external 
                              FROM $table_resources
                              WHERE id_external != '') ";

      } else if ($actionImport == self::ACTION_INCOHERENCE) {
         //add fields for interface inconsistencies
         $SELECT .= ",$table_resources.plugin_resources_contracttypes_id as contracttypes_id,
                      $table_resources.branching_agency_external as branching_agency_external_resources,
                      $table_resources.users_id_sales as users_id_sales_resources,
                      $table_resources.date_begin as date_begin_resources,
                      $table_resources.date_end as date_end_resources,
                      $table_resources.email_external as email_external_resources,
                      glpi_plugin_resources_employees.plugin_resources_clients_id as clients_id,
                      $table_resources.locations_id ";

         $FROM .= $JOIN;
         $FROM .= " INNER JOIN `glpi_plugin_resources_employees` 
                  ON `glpi_plugin_resources_employees`.`plugin_resources_resources_id` = $table_resources.id ";

         $WHERE   .= "WHERE $table_resources.entities_id IN (" . implode(",", $entities_id) . ") 
                     AND ($table_resources.branching_agency_external != $table_import.branching_agency
                    OR $table_resources.users_id_sales != $table_import.users_id_sales 
                    OR $table_resources.date_begin != $table_import.date_begin 
                    OR $table_resources.date_end != $table_import.date_end 
                    OR $table_resources.email_external != $table_import.email) ";
         $GROUPBY = " GROUP BY $table_import.id";

      } else if ($actionImport == self::ACTION_DELETE) {
         //select resource who do not have a departure date or whose departure date is different
         $FROM  .= $JOIN;
         $WHERE .= "WHERE $table_resources.entities_id IN (" . implode(",", $entities_id) . ")
                     AND $table_resources.date_end IS NULL 
                     AND $table_import.date_end > 0
                    OR $table_resources.date_end < $table_import.date_end";
      }

      $LIMIT = "";
      if ($limitNb) {
         $LIMIT = " LIMIT " . $limitBegin . "," . $limitNb;
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
    * Get the data from CSV and create or update object GLPI
    *
    * @return array output
    */
   function initCsvDataAndImportGLPI($task) {
      $path     = GLPI_PLUGIN_DOC_DIR . "/resources/import/";
      $files    = scandir($path);
      $nbRowAdd = 0;

      $dbu = new DbUtils();

      $no_file = true;
      // All files in the folder
      foreach ($files as $file) {
         if (!is_dir($path . "/" . $file)) {
            $no_file = true;

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
                  if (($arrayCorrespondance[$key] == 'date_begin'
                       || $arrayCorrespondance[$key] == 'date_end')) {
                     if (trim($raw) != "" && $raw != null) {

                        $raw = DateTime::createFromFormat('d/m/Y', $raw)->format('Y-m-d');
                     } else {
                        $raw = 'NULL';
                     }
                  }
                  if ($arrayCorrespondance[$key] == 'matricule' && $datas["origin"] == "Interne") {
                     $raw = "C" . $raw;
                  }
                  $datas[$arrayCorrespondance[$key]] = preg_replace("/\s+/", " ",
                                                                    Html::cleanInputText($raw));
               }

               //If there is no row with the id
               if (!$import->getFromDBByCrit(['id_external' => $datas['id_external']])) {
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
                  $cnt       = $dbu->countElementsInTable('glpi_users', ['firstname' => $firstname,
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
                  if ($resource->getFromDBByCrit(["id_external" => $datas['id_external']])) {
                     //if datas are identical with ressource data we skip the raw
                     if ($datas['date_begin'] == $resource->getField('date_begin') &&
                         $datas['date_end'] == $resource->getField('date_end') &&
                         $datas['branching_agency'] == $resource->getField('branching_agency_external') &&
                         $datas['users_id_sales'] == $resource->getField('users_id_sales') &&
                         $datas['email'] == $resource->getField('email_external')) {
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
      if ($no_file) {
         $task->log(__('No file found', 'resources'));
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