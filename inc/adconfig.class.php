<?php
/*
 *
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2022 by the resources Development Team.

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
   function showConfigForm() {

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
         echo Html::hidden('id', ['value' => $ID]);
         echo "<div align='center'><table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='4'>".self::getTypeName()."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('RootDN (for non anonymous binds)');
         echo "</td>";
         echo "<td>";
         echo Html::input('login', ['value' => $this->fields['login'], 'size' => 40]);
         echo "</td>";
         echo "<td>";
         echo __('Password');
         echo "</td>";
         echo "<td><input type='password'  name='password' value='' autocomplete='new-password'>";
         echo "<input type='checkbox' name='_blank_passwd' id='_blank_passwd'>&nbsp;"
              . "<label for='_blank_passwd'>" . __('Clear') . "</label>";

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan = ''>" . __('Server') . "</td>";

         echo "<td>";
         Dropdown::show(AuthLDAP::getType(),["name"=>'auth_id',"value"=>$this->getField("auth_id")]);
         echo "</td>";
         echo "<td>";
         echo __('Creation category', 'resources');
         echo "</td>";
         echo "<td>";

         $possible_values = [];
         $ITILCategory = new ITILCategory();
         $cats = $ITILCategory->find([]);
         if (!empty($cats)) {
            foreach ($cats as $cat) {
               $possible_values[$cat['id']] = $cat['completename'];
            }
         }
         $values = json_decode($this->fields['creation_categories_id']);
         if (!is_array($values)) {
            $values = [];
         }
         Dropdown::showFromArray("creation_categories_id",
                                 $possible_values,
                                 ['values'   => $values,
                                  'multiple' => 'multiples']);

         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Modification category', 'resources');
         echo "</td>";
         echo "<td>";
         $possible_values = [];
         $ITILCategory = new ITILCategory();
         $cats = $ITILCategory->find([]);
         if (!empty($cats)) {
            foreach ($cats as $cat) {
               $possible_values[$cat['id']] = $cat['completename'];
            }
         }
         $values = json_decode($this->fields['modification_categories_id']);
         if (!is_array($values)) {
            $values = [];
         }
         Dropdown::showFromArray("modification_categories_id",
                                 $possible_values,
                                 ['values'   => $values,
                                  'multiple' => 'multiples']);


         echo "</td>";

         echo "<td>";
         echo __('Deletion category', 'resources');
         echo "</td>";
         echo "<td>";
         $possible_values = [];
         $ITILCategory = new ITILCategory();
         $cats = $ITILCategory->find([]);
         if (!empty($cats)) {
            foreach ($cats as $cat) {
               $possible_values[$cat['id']] = $cat['completename'];
            }
         }
         $values = json_decode($this->fields['deletion_categories_id']);
         if (!is_array($values)) {
            $values = [];
         }
         Dropdown::showFromArray("deletion_categories_id",
                                 $possible_values,
                                 ['values'   => $values,
                                  'multiple' => 'multiples']);

         echo "</td>";
         echo "</tr>";

         echo "<tr><th colspan='4'>".__("Login Creation",'resources')."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('First Form','resources');
         echo "</td>";
         echo "<td >";
         $option = ["value"=>$this->fields["first_form"]];
         Dropdown::showFromArray("first_form",$this->loginForm(),$option);
         echo "</td>";
         echo "<td>";
         echo __('Second Form', 'resources');
         echo "</td>";
         echo "<td >";
         $option = ["value"=>$this->fields["second_form"]];
         Dropdown::showFromArray("second_form",$this->loginForm(),$option);
         echo "</td>";
         echo "</tr>";
         echo "<tr><th colspan='4'>".__("Mail Creation",'resources')."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Prefix','resources');
         echo "</td>";
         echo "<td >";
         $option = ["value"=>$this->fields["mail_prefix"]];
         Dropdown::showFromArray("mail_prefix",$this->prefixForm(),$option);
         echo "</td>";
         echo "<td>";
         echo __('Suffix','resources');
         echo "</td>";
         echo "<td >";
         $option = ["value"=>$this->fields["mail_suffix"]];
         echo Html::input("mail_suffix",$option);
//         Dropdown::showFromArray(,$this->loginForm(),$option);
         echo "</td>";
         echo "</tr>";

         echo "<tr><th colspan='4'>".__('Field mapping','resources')."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Login');
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['logAD'],
                    'entity' => -1];
         echo Html::input('logAD', $option);
         echo "</td>";

         echo "<td>";
         echo _n('Department', 'Departments', 1, 'resources');
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['departmentAD'],
                    'entity' => -1];
         echo Html::input('departmentAD', $option);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __("Name");
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['nameAD'],
                    'entity' => -1];
         echo Html::input('nameAD', $option);
         echo "</td>";

         echo "<td>";
         echo __("First name");
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['firstnameAD'],
                    'entity' => -1];
         echo Html::input('firstnameAD', $option);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __("Phone");
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['phoneAD'],
                    'entity' => -1];
         echo Html::input('phoneAD', $option);
         echo "</td>";

         echo "<td>";
         echo __("Email");
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['mailAD'],
                    'entity' => -1];
         echo Html::input('mailAD', $option);
         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo PluginResourcesEmployer::getTypeName(1);
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['companyAD'],
                    'entity' => -1];
         echo Html::input('companyAD', $option);
         echo "</td>";

         echo "<td>";
         echo __("Departure date",'resources');
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['contractEndAD'],
                    'entity' => -1];
         echo Html::input('contractEndAD', $option);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Mobile phone');
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['cellPhoneAD'],
                    'entity' => -1];
         echo Html::input('cellPhoneAD', $option);
         echo "</td>";

         echo "<td>";
         echo __("Role",'resources');
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['roleAD'],
                    'entity' => -1];
         echo Html::input('roleAD', $option);
         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo _n('Contract type', 'Contract types', 1);
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['contractTypeAD'],
                    'entity' => -1];
         echo Html::input('contractTypeAD', $option);
         echo "</td>";

         echo "<td>";
         echo PluginResourcesService::getTypeName(1);
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['serviceAD'],
                    'entity' => -1];
         echo Html::input('serviceAD', $option);
         echo "</td>";

         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo Location::getTypeName();
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['locationAD'],
                    'entity' => -1];
         echo Html::input('locationAD', $option);
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
         $option = ['value' => $this->fields['ouDesactivateUserAD'],
                    'entity' => -1];
         echo Html::input('ouDesactivateUserAD', $option);
         echo "</td>";

         echo "<td>";
         echo __("Destination OU during user creation",'resources');
         echo "</td>";
         echo "<td>";
         $option = ['value' => $this->fields['ouUser'],
                    'entity' => -1];
         echo Html::input('ouUser', $option);
         echo "</td>";
         echo "</tr>";

         echo "<tr>";
         echo "<td class='tab_bg_2 center' colspan='4'>";
         echo Html::hidden('id', ['value' => 1]);
         echo Html::submit(_sx('button', 'Update'), ['name' => 'update_setup', 'class' => 'btn btn-primary']);
         echo "</td>";
         echo "</tr>";
         echo "</table></div>";
         Html::closeForm();
      }

   }

   function loginForm(){
      $options[0] = Dropdown::EMPTY_VALUE;
      $options[1] = __("first letter of given name + name",'resources');
      $options[2] = __("given name + name",'resources');
      $options[3] = __("2 letters of given name + 2 letters of name",'resources');

      return $options;
   }
   function prefixForm(){
      $options[0] = Dropdown::EMPTY_VALUE;
      $options[1] = __("given name.name",'resources');
      $options[2] = __("Login");

      return $options;
   }
   /**
    * @param $input
    *
    * @return array|\type
    */
   function prepareInputForAdd($input) {
      return $this->encodeSubtypes($input);
   }



   /**
    * Encode sub types
    *
    * @param type $input
    *
    * @return \type
    */
   function encodeSubtypes($input) {
      if (!empty($input['creation_categories_id'])) {
         $input['creation_categories_id'] = json_encode(array_values($input['creation_categories_id']));
      } else {
         $input['creation_categories_id'] = json_encode([]);
      }
      if (!empty($input['modification_categories_id'])) {
         $input['modification_categories_id'] = json_encode(array_values($input['modification_categories_id']));
      }else {
         $input['modification_categories_id'] = json_encode([]);
      }
      if (!empty($input['deletion_categories_id'])) {
         $input['deletion_categories_id'] = json_encode(array_values($input['deletion_categories_id']));
      }else {
         $input['deletion_categories_id'] = json_encode([]);
      }

      return $input;
   }
   function prepareInputForUpdate($input) {

      if (isset($input["password"])) {
         if (empty($input["password"])) {
            unset($input["password"]);
         } else {
            $input["password"] = (new GLPIKey())->encrypt($input["password"]);
         }
      }

      if (isset($input["_blank_passwd"]) && $input["_blank_passwd"]) {
         $input['password'] = '';
      }

      $input = $this->encodeSubtypes($input);


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

      $array = ["logAD","nameAD","phoneAD","companyAD","departmentAD","firstnameAD","mailAD","contractEndAD","contractTypeAD","cellPhoneAD","roleAD","serviceAD","locationAD"];
      return $array;
   }
   function prepareFields($fields){

         $fields['creation_categories_id'] = json_decode($fields['creation_categories_id']);


         $fields['modification_categories_id'] = json_decode($fields['modification_categories_id']);


         $fields['deletion_categories_id'] = json_decode($fields['deletion_categories_id']);

         return $fields;

   }
}
