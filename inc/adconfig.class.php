<?php
/*
 *
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
 * Class PluginResourcesConfig
 */
class PluginResourcesAdconfig extends CommonDBTM {

   static $rightname = 'plugin_resources';

   /**
    * functions mandatory
    * getTypeName(), canCreate(), canView()
    * */
   static function getTypeName($nb = 0) {
      return __('Setup LDAP directory','resources');
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
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
   }

   /**
    * PluginResourcesConfig constructor.
    */
   function __construct() {
      global $DB;

      if ($DB->tableExists($this->getTable())) {
         $this->getFromDB(1);
      }
   }

   /**
    * @return bool
    */
   function showForm() {

      if (!$this->canView()) {
         return false;
      }
      if (!$this->canCreate()) {
         return false;
      }

      $canedit = true;

      if ($canedit) {
         $ID = 1;
         $this->getFromDB($ID);
         echo "<form name='form' method='post' action='" . $this->getFormURL() . "'>";
         echo "<input type='hidden' name='id' value='$ID' data-glpicore-ma-tags='common'>";
         echo "<div align='center'><table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='4'>".self::getTypeName()."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('RootDN (for non anonymous binds)');
         echo "</td>";
         echo "<td ><input type='text' name='login'  value=\"".
              $this->fields["login"]."\">";
         echo "</td>";
         echo "<td>";
         echo __('Password');
         echo "</td>";
         echo "<td><input type='password'  name='password' value='' autocomplete='new-password'>";
         echo "<input type='checkbox' name='_blank_passwd' id='_blank_passwd'>&nbsp;"
              . "<label for='_blank_passwd'>" . __('Clear') . "</label>";

         echo "</td>";
         echo "</tr>";
         echo "<tr>";
         echo "<td colspan = ''>" . __('Server') . "</td>";

         echo "<td>";
         Dropdown::show(AuthLDAP::getType(),["name"=>'auth_id',"value"=>$this->getField("auth_id")]);
         echo "</td>";
         echo "<td>";
         echo __('Creation category', 'resources');
         echo "</td>";
         echo "<td>";

         ITILCategory::dropdown(["name"=>"creation_categories_id","value"=>$this->fields["creation_categories_id"]]);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Modification category', 'resources');
         echo "</td>";
         echo "<td>";

         ITILCategory::dropdown(["name"=>"modification_categories_id","value"=>$this->fields["modification_categories_id"]]);

         echo "</td>";

         echo "<td>";
         echo __('Deletion category', 'resources');
         echo "</td>";
         echo "<td>";

         ITILCategory::dropdown(["name"=>"deletion_categories_id","value"=>$this->fields["deletion_categories_id"]]);

         echo "</td>";
         echo "</tr>";

         echo "<tr><th colspan='4'>".__("Login Creation",'resources')."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('First Form');
         echo "</td>";
         echo "<td >";
         $option = ["value"=>$this->fields["first_form"]];
         Dropdown::showFromArray("first_form",$this->loginForm(),$option);
         echo "</td>";
         echo "<td>";
         echo __('Second Form');
         echo "</td>";
         echo "<td >";
         $option = ["value"=>$this->fields["second_form"]];
         Dropdown::showFromArray("second_form",$this->loginForm(),$option);
         echo "</td>";
         echo "</tr>";

         echo "<tr><th colspan='4'>".__('Field mapping','resources')."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Login');
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "logAD",['entity' => -1]);

         echo "</td>";

         echo "<td>";
         echo _n('Department', 'Departments', 1, 'resources');
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "departmentAD",['entity' => -1]);

         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __("Name");
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "nameAD",['entity' => -1]);

         echo "</td>";

         echo "<td>";
         echo __("First name");
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "firstnameAD",['entity' => -1]);

         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __("Phone");
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "phoneAD",['entity' => -1]);

         echo "</td>";

         echo "<td>";
         echo __("Email");
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "mailAD",['entity' => -1]);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo PluginResourcesEmployer::getTypeName(1);
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "companyAD",['entity' => -1]);

         echo "</td>";

         echo "<td>";
         echo __("Departure date",'resources');
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "contractEndAD",['entity' => -1]);

         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Mobile phone');
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "cellPhoneAD",['entity' => -1]);

         echo "</td>";

         echo "<td>";
         echo __("Role",'resources');
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "roleAD",['entity' => -1]);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo _n('Contract type', 'Contract types', 1);
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "contractTypeAD",['entity' => -1]);

         echo "</td>";

         echo "<td>";
         echo "</td>";
         echo "<td>";


         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __("Destination OU on user deactivation",'resources');
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "ouDesactivateUserAD",['entity' => -1]);

         echo "</td>";

         echo "<td>";
         echo __("Destination OU during user creation",'resources');
         echo "</td>";
         echo "<td>";

         Html::autocompletionTextField($this, "ouUser",['entity' => -1]);

         echo "</td>";
         echo "</tr>";

         echo "<tr>";
         echo "<td class='tab_bg_2 center' colspan='4'>";
         echo "<input type='hidden' name='id' value='1' >";
         echo "<input type='submit' name='update_setup' class='submit' value='"._sx('button', 'Update')."' >";
         echo "</td>";
         echo "</tr>";
         echo "</table></div>";
         Html::closeForm();
      }

   }

   function loginForm(){
      $options[0] = Dropdown::EMPTY_VALUE;
      $options[1] = __("first letter of given name + name");
      $options[2] = __("given name + name");
      $options[3] = __("2 letters of given name + 2 letters of name");

      return $options;
   }
   function prepareInputForUpdate($input) {

      if (isset($input["password"])) {
         if (empty($input["password"])) {
            unset($input["password"]);
         } else {
            $input["password"] = Toolbox::sodiumEncrypt(stripslashes($input["password"]));
         }
      }

      if (isset($input["_blank_passwd"]) && $input["_blank_passwd"]) {
         $input['password'] = '';
      }


      return $input;
   }

   /**
    * @return mixed
    */
   function useSecurity() {
      return $this->fields['security_display'];
   }

   /**
    * @return mixed
    */
   function useSecurityCompliance() {
      return $this->fields['security_compliance'];
   }

   /**
    * @return mixed
    */
   function useImportExternalDatas() {
      return $this->fields['import_external_datas'];
   }

   function getArrayAttributes(){

      $array = ["logAD","nameAD","phoneAD","companyAD","departmentAD","firstnameAD","mailAD","contractEndAD","contractTypeAD","cellPhoneAD","roleAD"];
      return $array;
   }








}
