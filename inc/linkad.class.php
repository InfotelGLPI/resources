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
 * Class PluginResourcesChecklist
 */
class PluginResourcesLinkAd extends CommonDBTM {

   static $rightname = 'plugin_resources_checklist';

   const RESOURCES_CHECKLIST_IN       = 1;
   const RESOURCES_CHECKLIST_OUT      = 2;
   const RESOURCES_CHECKLIST_TRANSFER = 3;

   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @param integer $nb Number of items
    *
    * @return string
    **/
   static function getTypeName($nb = 0) {

      return __('Active Directory', 'resources');
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
   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
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
    * @param CommonGLPI $item Item on which the tab need to be displayed
    * @param boolean    $withtemplate is a template object ? (default 0)
    *
    * @return string tab name
    **@since 0.83
    *
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if (!$withtemplate) {
         if ($item->getID() && $this->canView()) {
            if ($_SESSION['glpishow_count_on_tabs']) {
               return self::createTabEntry(self::getTypeName(2), self::countForItem($item));
            }
            return self::getTypeName(2);
         }
      }
      return '';
   }

   /**
    * show Tab content
    *
    * @param CommonGLPI $item Item on which the tab need to be displayed
    * @param integer    $tabnum tab number (default 1)
    * @param boolean    $withtemplate is a template object ? (default 0)
    *
    * @return boolean
    **@since 0.83
    *
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      $ID = $item->getField('id');

      if($item->getType() == Ticket::getType()){
         $items = new Item_Ticket();
         if($items->getFromDBByCrit(["tickets_id"=>$ID,"itemtype"=>PluginResourcesResource::getType()])){
            self::showFromResources($items->getField("items_id"),$item);
         }


         return true;
      }
      return false;
   }

   /**
    * @param $item
    *
    * @return int
    */
   static function countForItem($item) {

      if ($item->getField('is_leaving') == 1) {
         $checklist_type = self::RESOURCES_CHECKLIST_OUT;
      } else {
         $checklist_type = self::RESOURCES_CHECKLIST_IN;
      }
      $dbu      = new DbUtils();
      $restrict = ["plugin_resources_resources_id" => $item->getField('id'),
                   "checklist_type"                => $checklist_type,
                   "NOT"                           => ["is_checked" => 1]];
      $nb       = $dbu->countElementsInTable(['glpi_plugin_resources_checklists'], $restrict);

      return $nb;
   }



   /**
    * Prepare input datas for adding the item
    *
    * @param array $input datas used to add the item
    *
    * @return array the modified $input array
    **/
   function prepareInputForAdd($input) {
      global $DB;


      return $input;
   }

   /**
    * @param $ID
    */
   static function showAddForm($ID) {

      echo "<div align='center'>";
      echo "<form action='" . Toolbox::getItemTypeFormURL('PluginResourcesResource') . "' method='post'>";
      echo "<table class='tab_cadre' width='50%'>";
      echo "<tr>";
      echo "<th colspan='2'>";
      echo __('Create checklists', 'resources');
      echo "</th></tr>";
      echo "<tr class='tab_bg_2 center'>";
      echo "<td colspan='2'>";
      echo "<input type='submit' name='add_checklist_resources' value='" . _sx('button', 'Post') . "' class='submit' />";
      echo "<input type='hidden' name='id' value='" . $ID . "'>";
      echo "</td></tr></table>";
      Html::closeForm();
      echo "</div>";
   }



   /**
    * @param       $ID
    * @param array $options
    *
    * @return bool
    */
   function showForm($ID, $options = []) {

      if (!$this->canView()) {
         return false;
      }

      $plugin_resources_contracttypes_id = -1;
      if (isset($options['plugin_resources_contracttypes_id'])) {
         $plugin_resources_contracttypes_id = $options['plugin_resources_contracttypes_id'];
      }

      $checklist_type = -1;
      if (isset($options['checklist_type'])) {
         $checklist_type = $options['checklist_type'];
      }

      $plugin_resources_resources_id = -1;

      if (isset($options['plugin_resources_resources_id'])) {
         $plugin_resources_resources_id = $options['plugin_resources_resources_id'];
         $item                          = new PluginResourcesResource();
         if ($item->getFromDB($plugin_resources_resources_id)) {
            $options["entities_id"] = $item->fields["entities_id"];
         }
      }

      if ($ID > 0) {
         $this->check($ID, READ);
      } else {
         // Create item
         $this->check(-1, UPDATE, $input);
      }

      $this->showFormHeader($options);

      echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
      if ($ID > 0) {
         echo "<input type='hidden' name='plugin_resources_contracttypes_id' value='" . $this->fields["plugin_resources_contracttypes_id"] . "'>";
         echo "<input type='hidden' name='checklist_type' value='" . $this->fields["checklist_type"] . "'>";
      } else {
         echo "<input type='hidden' name='plugin_resources_contracttypes_id' value='$plugin_resources_contracttypes_id'>";
         echo "<input type='hidden' name='checklist_type' value='$checklist_type'>";
      }

      echo "<tr class='tab_bg_1'>";

      echo "<td >" . __('Name') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "name", ['size' => "40"]);
      echo "</td>";

      echo "<td>";
      echo __('Important', 'resources');
      echo "</td><td>";
      Dropdown::showYesNo("tag", $this->fields["tag"]);
      echo "</td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td >" . __('Link', 'resources') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "address", ['size' => "75"]);
      echo "</td>";

      echo "<td></td>";
      echo "<td></td>";

      echo "</tr>";

      echo "<tr class='tab_bg_1'>";

      echo "<td class='left' colspan = '4'>";
      echo __('Description') . "<br>";
      echo "<textarea cols='150' rows='6' name='comment'>" . $this->fields["comment"] . "</textarea>";
      echo "</td>";

      echo "</tr>";

      $this->showFormButtons($options);
      return true;
   }

   /**
    * show from resources
    *
    * @param        $plugin_resources_resources_id
    * @param        $checklist_type
    * @param string $withtemplate
    *
    * @return bool
    */
   static function showFromResources($plugin_resources_resources_id,$ticket) {
      global $CFG_GLPI;

      if (!self::canView()) {
         return false;
      }

      $target          = "./resource.form.php";
      $targetchecklist = "./checklist.form.php";
      $targettask      = "./task.form.php";
      $config = new PluginResourcesConfig();
      $configAD = new PluginResourcesAdconfig();
      $config->getFromDB(1);
      $configAD->getFromDB(1);
      $resource        = new PluginResourcesResource();
      $resource->getFromDB($plugin_resources_resources_id);
      $canedit                           = $resource->can($plugin_resources_resources_id, UPDATE);
      $entities_id                       = $resource->fields["entities_id"];
      $plugin_resources_contracttypes_id = $resource->fields["plugin_resources_contracttypes_id"];
      $rand                              = mt_rand();
      $enddate = $resource->getField("date_end");
      $linkAD = new self();
      $linkAD->getEmpty();
      $islink = $linkAD->getFromDBByCrit(["plugin_resources_resources_id"=>$resource->getID()]);
      if(!$islink){
         $ret = self::processLogin($resource);
         $linkAD->fields["login"] = $ret[0];
         $logAvailable = $ret[1];
      }
      $ID = $linkAD->getID();
      echo "<form name='form' method='post' action='" . Toolbox::getItemTypeFormURL(self::getType()) . "'>";
      echo "<table class='tab_cadre_fixe'>";



      $dbu        = new DbUtils();

      if (($islink) || !$islink) {
         echo "<input type='hidden' name='plugin_resources_resources_id' value='$plugin_resources_resources_id' data-glpicore-ma-tags='common'>";
         echo "<input type='hidden' name='id' value='$ID' data-glpicore-ma-tags='common'>";
         echo "<input type='hidden' name='ticket_id' value='".$ticket->getID()."' data-glpicore-ma-tags='common'>";
         echo "<input type='hidden' name='plugin_resources_contracttypes_id' value='$plugin_resources_contracttypes_id' data-glpicore-ma-tags='common'>";
         echo "<input type='hidden' name='entities_id' value='$entities_id' data-glpicore-ma-tags='common'>";
         echo "<input type='hidden' name='enddate' value='$enddate' data-glpicore-ma-tags='common'>";

         // Actions on finished checklist
         if (self::canCreate() && $canedit) {
            echo "<tr><th colspan='4'>".__('Resources data','resources')."</th></tr>";
            echo "<tr>";
            echo "<td colspan = ''>" . __('Login') . "</td>";
            echo "<td>";
            $option = ["option"=>"disabled"];
            if(!$islink){
               $option = [];
            }

            Html::autocompletionTextField($linkAD, "login",$option);
            echo "</td>";
            echo "<td colspan = ''>" . __('Department','resources') . "</td>";

            echo "<td>";
            echo "<input type='hidden' name='department' value='".Dropdown::getDropdownName('glpi_plugin_resources_departments', $resource->getField("plugin_resources_departments_id"))."' data-glpicore-ma-tags='common'>";
            echo Dropdown::getDropdownName('glpi_plugin_resources_departments', $resource->getField("plugin_resources_departments_id"));
            echo "</td>";
            echo "</tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Name') . "</td>";
            echo "<td>";
            $option = ['rand'=> $rand,'option' => "onChange=\"javascript:this.value=this.value.toUpperCase();\" "];
            $rand1 = Html::autocompletionTextField($resource, "name", $option);
            echo "</td>";
            echo "<td>" . __('Firstname','resources') . "</td>";
            echo "<td>";
            $option = ['rand'=> $rand,'option' => "onChange='First2UpperCase(this.value); plugin_resources_load_button_changeresources_information();' style='text-transform:capitalize;' "];
            $rand2 = Html::autocompletionTextField($resource, "firstname", $option);
            echo "</td>";

            echo "</tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Phone','resources') . "</td>";

            echo "<td><input type='text' name='phone'value='".$linkAD->fields["phone"]."'></td>";

            echo "<td>" . __('Mail') . "</td>";

            echo "<td><input type='email' name='mail'value='".$linkAD->fields["mail"]."'>";

            echo "</tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Company','resources') . "</td>";
            echo "<td>";
            $employee = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id"=>$resource->getID()]);

            echo "<input type='hidden' name='company' value='".Dropdown::getDropdownName('glpi_plugin_resources_employers', $employee->getField("plugin_resources_employers_id"))."' data-glpicore-ma-tags='common'>";

            echo Dropdown::getDropdownName('glpi_plugin_resources_employers', $employee->getField("plugin_resources_employers_id"));
            echo "</td>";
            echo "<td>" . _n('Contract type', 'Contract types', 1) . "</td>";
            echo "<td>";
            $employee = new PluginResourcesEmployee();
            $employee->getFromDBByCrit(["plugin_resources_resources_id"=>$resource->getID()]);

            echo "<input type='hidden' name='contract' value='".Dropdown::getDropdownName('glpi_plugin_resources_contracttypes', $resource->getField("plugin_resources_contracttypes_id"))."' data-glpicore-ma-tags='common'>";

            echo Dropdown::getDropdownName('glpi_plugin_resources_contracttypes', $resource->getField("plugin_resources_contracttypes_id"));
            echo "</td>";

            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Cell phone','resources') . "</td>";

            echo "<td><input type='text' name='cellphone'value='".$linkAD->fields["cellphone"]."'></td>";

            echo "<td>" . __('Role','resources') . "</td>";

            echo "<td><input type='text' name='role'value='".$linkAD->fields["role"]."'></td>";


            echo "</tr>";


            if(!$islink && !$linkAD->fields["action_done"] && $ticket->fields["itilcategories_id"] == $configAD->fields["creation_categories_id"] && $logAvailable){
               echo "<tr class='tab_bg_2'>";
               echo "<td colspan='4' class='center'><input type='submit' class='submit' value='" . _sx('button', 'Create user in AD') . "' name='createAD'></td>";
            }
            if($islink && !$linkAD->fields["action_done"] && $ticket->fields["itilcategories_id"] == $configAD->fields["modification_categories_id"] ){
               echo "<tr class='tab_bg_2'>";
               echo "<td colspan='4' class='center'><input type='submit' class='submit' value='" . _sx('button', 'Modify user in AD') . "' name='updateAD'></td>";
            }

            if($islink && !$linkAD->fields["action_done"] && $ticket->fields["itilcategories_id"] == $configAD->fields["deletion_categories_id"] ){
               echo "<tr class='tab_bg_2'>";
               echo "<td colspan='4' class='center'><input type='submit' class='submit' value='" . _sx('button', 'Disable user in AD') . "' name='disableAD'></td>";
            }
            echo "</tr>";
         }
      }else{

      }

      echo "</table>";
      Html::closeForm();
      echo "<br>";
   }












   static function processLogin(PluginResourcesResource $resource){
      $name = strtolower($resource->fields["name"]);
      $firstnametab = explode(" ",strtolower($resource->fields["firstname"]));
      $firstname ="";
      $firstname2 ="";
      foreach($firstnametab as $namepart){
         $firstname .= substr($namepart, 0, 1);
          $firstname2 .=$namepart;
      }
      //TEST INFOTEL
      $firstname = strtolower($resource->fields["firstname"]);
      $name = substr($name, 0, 2);
      $firstname = substr($firstname, 0, 2);
     // FIN TEST INFOTEL
      $login = $firstname.$name;
      $ldap =new PluginResourcesLDAP();
      $exist = $ldap->existingUser($login);
      if($exist){
         $login =$firstname2.$name;
         $exist = $ldap->existingUser($login);
         if($exist){
            return [__("existing login","resources"),false];
         }else{
            return [$login,true];
         }
      }else {
         return [$login,true];
      }

   }

   static function getMapping($val){
      $mapping["logAD"] = "login";
      $mapping["nameAD"] = "name";
      $mapping["phoneAD"] = "phone";

      $mapping["firstnameAD"] = "firstname";
      $mapping["mailAD"] = "mail";

      $mapping["cellPhoneAD"] = "cellphone";
      $mapping["roleAD"] = "role";
      $mapping["companyAD"] = "company";
      $mapping["departmentAD"] = "department";
      $mapping["contractTypeAD"] = "contract";
      $mapping["contractEndAD"] = "enddate";

      if(isset($mapping[$val])){
         return $mapping[$val];
      }
      return null;
   }

   static function getNameMapping($val){
      $mapping["login"] = __('Login');
      $mapping["firstname"] = __('Firstname','resources');
      $mapping["phone"] = Phone::getTypeName(1);

      $mapping["name"] = __('Name');
      $mapping["mail"] = __('Mail');

      $mapping["cellphone"] = __('Mobile phone');
      $mapping["role"] = __('Role','resources');
      $mapping["contract"] = __("Contract type");
      $mapping["company"] = __('Company','resources') ;
      $mapping["department"] = __('Department','resources');

      $mapping["enddate"] = __('Departure date', 'resources');

      if(isset($mapping[$val])){
         return $mapping[$val];
      }
      return null;
   }

}

