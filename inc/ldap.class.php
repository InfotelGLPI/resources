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
 * Class PluginResourcesBudget
 */
class PluginResourcesLDAP extends CommonDBTM {

   static $rightname = 'plugin_resources_budget';
   // From CommonDBTM
   public $dohistory = true;

   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @param integer $nb Number of items
    *
    * @return string
    **/
   static function getTypeName($nb = 0) {

      return __('LDAP','resources');
   }

   /**
    * Have I the global right to "view" the Object
    *
    * Default is true and check entity if the objet is entity assign
    *
    * May be overloaded if needed
    *
    * @return bool
    **/
   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   /**
    * Have I the global right to "create" the Object
    * May be overloaded if needed (ex KnowbaseItem)
    *
    * @return bool
    **/
   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
   }

   /**
    * Display Tab for each budget
    *
    * @param array $options
    *
    * @return array
    */
//   function defineTabs($options = []) {
//
//      $ong = [];
//
//      $this->addDefaultFormTab($ong);
//      $this->addStandardTab('Document', $ong, $options);
//      $this->addStandardTab('Log', $ong, $options);
//
//      return $ong;
//   }

   /**
    * allow to control data before adding in bdd
    *
    * @param $input
    * @return array
    */
//   function prepareInputForAdd($input) {
//
//      if (!isset($input["plugin_resources_professions_id"]) || $input["plugin_resources_professions_id"] == '0') {
//         Session::addMessageAfterRedirect(__('The profession for the budget must be filled', 'resources'), false, ERROR);
//         return [];
//      }
//
//      return $input;
//   }

   /**
    * allow to control data before updating in bdd
    *
    * @param $input
    * @return array
    */
//   function prepareInputForUpdate($input) {
//
//      if (!isset($input["plugin_resources_professions_id"]) || $input["plugin_resources_professions_id"] == '0') {
//         Session::addMessageAfterRedirect(__('The profession for the budget must be filled', 'resources'), false, ERROR);
//         return [];
//      }
//
//      return $input;
//   }

   /**
    * PluginInsightvmInsightvm constructor.
    */
   function __construct() {

   }

   function connect($authsId){
      $ldap = new AuthLDAP;
      $ldap->getFromDB($authsId);
      $ldap_connection = $ldap->connect();
      return $ldap_connection;
   }

   private static function getConfig(){
      $config_ldap = new AuthLDAP();
      $configAD = new PluginResourcesAdconfig();
      $configAD->getFromDB(1);
      $authID = $configAD->fields["auth_id"];
      $res         = $config_ldap->getFromDB($authID);



      // Create a configuration array.
      if(($ret =strpos($config_ldap->fields['host'],'ldaps://') ) !== false){
         $host = str_replace('ldaps://','',$config_ldap->fields['host']);
         $ssl = true;
      }else if(($ret = strpos($config_ldap->fields['host'],'ldap://'))!== false){
         $host = str_replace('ldap://','',$config_ldap->fields['host']);
         $ssl = false;
      }else{
         $host = $config_ldap->fields['host'];
         $ssl = false;
      }
      if($config_ldap->fields['deref_option']){
         $deref = true;
      }else{
         $deref = false;
      }
      if($config_ldap->fields['use_tls']){
         $tls = true;
      }else{
         $tls = false;
      }

      $config = [
         // An array of your LDAP hosts. You can use either
         // the host name or the IP address of your host.
         'hosts'    => [$host],
         'port'    => $config_ldap->fields['port'],
         'use_tls'    => $tls,
         'use_ssl'    => $ssl,
         'follow_referrals'    =>$deref,

         // The base distinguished name of your domain to perform searches upon.
         'base_dn'  => $config_ldap->fields['basedn'],

         // The account to use for querying / modifying LDAP records. This
         // does not need to be an admin account. This can also
         // be a full distinguished name of the user account.
         'username' => $config_ldap->fields['rootdn'],
         'password' =>  Toolbox::sodiumDecrypt($config_ldap->fields['rootdn_passwd']),
      ];
//      Toolbox::logWarning($config);
      return $config;
   }

   function getUserInformation($authID){
      // Construct new Adldap instance.
      $ad = new \Adldap\Adldap();
      $config_ldap = new AuthLDAP();
      $res         = $config_ldap->getFromDB($authID);



      // Create a configuration array.
      if(($ret =strpos($config_ldap->fields['host'],'ldaps://') ) !== false){
         $host = str_replace('ldaps://','',$config_ldap->fields['host']);
         $ssl = true;
      }else if(($ret = strpos($config_ldap->fields['host'],'ldap://'))!== false){
         $host = str_replace('ldap://','',$config_ldap->fields['host']);
         $ssl = false;
      }else{
         $host = $config_ldap->fields['host'];
         $ssl = false;
      }

      $config = [
         // An array of your LDAP hosts. You can use either
         // the host name or the IP address of your host.
         'hosts'    => [$host],
         'port'    => $config_ldap->fields['port'],
         'use_tls'    => !!$config_ldap->fields['use_tls'],
         'use_ssl'    => $ssl,
         'follow_referrals'    => !!$config_ldap->fields['deref_option'],
         'version'    => 3,

         // The base distinguished name of your domain to perform searches upon.
         'base_dn'  => $config_ldap->fields['basedn'],

         // The account to use for querying / modifying LDAP records. This
         // does not need to be an admin account. This can also
         // be a full distinguished name of the user account.
         'username' => $config_ldap->fields['rootdn'],
         'password' =>  Toolbox::sodiumDecrypt($config_ldap->fields['rootdn_passwd']),
      ];

      // Add a connection provider to Adldap.
      $ad->addProvider($config);

      try {
         // If a successful connection is made to your server, the provider will be returned.
         $provider = $ad->connect();

         // Performing a query.
         $results = $provider->search()->where('samaccountname', '=', 'ales')->get();

//         Toolbox::logWarning($results);
      } catch (\Adldap\Auth\BindException $e) {

         // There was an issue binding / connecting to the server.

      }
   }

   function existingUser($login){
      $find = false;
      $adConfig = new PluginResourcesAdconfig();
      $adConfig->getFromDB(1);
      $ad = new \Adldap\Adldap();
      $config = self::getConfig();
      $ad->addProvider($config);


      try {
         $provider = $ad->connect();
         $search =  $provider->search();
         $record = $search->findByOrFail($adConfig->getField("logAD"), $login);

         $find = true;

      } catch (Adldap\Models\ModelNotFoundException $e) {
         // Record wasn't found!
         $find = false;
      }
      return $find;
   }

   function createUserAD( $data){

      $adConfig = new PluginResourcesAdconfig();
      $adConfig->getFromDB(1);
      $ad = new \Adldap\Adldap();
      $config = self::getConfig();
      $ad->addProvider($config);
      try {
         $provider = $ad->connect();
         $user = $provider->make()->user();

         // Create the users distinguished name.
         // We're adding an OU onto the users base DN to have it be saved in the specified OU.
         $dn = $user->getDnBuilder()->addOu($adConfig->getField("ouUser")); // Built DN will be: "CN=John Doe,OU=Users,DC=acme,DC=org";
         $dn->addCn($data["firstname"]." ".$data["name"]);
         // Set the users DN, account name.
         $user->setDn($dn);
         $user->setAccountName($data['login']);
         $user->setCommonName($data["firstname"]." ".$data["name"]);


         $attributes = [];
         $attr = $adConfig->getArrayAttributes();
         foreach ($attr as $at){
            if(!empty($adConfig->getField($at))){
               $a = PluginResourcesLinkAd::getMapping($at);
               if(isset($data[$a]) && !empty($data[$a])){
                  if($at == "contractEndAD"){
                     $win_time = 0;
                     if(!empty($data[$a])){
                        $unix_time = strtotime($data[$a]);
                        $win_time = $this->unixTimeToLdapTime($unix_time);
                     }
                     $data[$a] = $win_time;

                  }
                  $attributes[$adConfig->getField($at)] = $data[$a];
//                  if(empty($data[$a])){
//                     $attributes[$adConfig->getField($at)] = array();
//                  }
               }

            }

         }
         $user->fill($attributes);

         if($user->save()){
            return true;
         }else{
            return false;
         }

      } catch (Adldap\Models\ModelNotFoundException $e) {
         // Record wasn't found!
         return false;
      }

   }

   function updateUserAD($data){
      $adConfig = new PluginResourcesAdconfig();
      $adConfig->getFromDB(1);
      $ad = new \Adldap\Adldap();
      $config = self::getConfig();
      $ad->addProvider($config);
      try {
         $provider = $ad->connect();
         $user = $provider->search()->whereEquals($adConfig->getField("logAD"), $data["login"])->firstOrFail();

         // Create the users distinguished name.
         // We're adding an OU onto the users base DN to have it be saved in the specified OU.
//         $user->setCommonName($data["firstname"]." ".$data["name"]);


         $attributes = [];
         $attr = $adConfig->getArrayAttributes();
         foreach ($attr as $at){
            if(!empty($adConfig->getField($at))){
               $a = PluginResourcesLinkAd::getMapping($at);
               if(isset($data[$a])){


                  if(empty($data[$a]) && $at !="contractEndAD"){
                     $user->setAttribute($adConfig->getField($at), null);
                     $attributes[$adConfig->getField($at)] = array();
                  }else{
                     if($at == "contractEndAD"){
                        $win_time = 0;
                        if(!empty($data[$a])){
                           $unix_time = strtotime($data[$a]);
                           $win_time = $this->unixTimeToLdapTime($unix_time);
                        }
                        $data[$a] = $win_time;

                     }
                     $user->setAttribute($adConfig->getField($at), $data[$a]);
                     $attributes[$adConfig->getField($at)] = $data[$a];
                  }
               }

            }

         }
         $rename = false;
         if(count($dirty = $user->getDirty())){
            if(isset($dirty[$adConfig->getField("firstnameAD")]) || isset($dirty[$adConfig->getField("nameAD")])){
               $rename = true;
            }
         }
         if($user->save()){
            if($rename){
               $ncn = "cn=".$data["firstname"]." ".$data["name"];
               if($user->rename($ncn)){
                  return [true,$dirty];
               }
               return [false,$dirty];
            }

            return [true,$dirty];
         }else{
            return [false,$dirty];
         }

      } catch (Adldap\Models\ModelNotFoundException $e) {
         // Record wasn't found!
         return [false,[]];
      }
   }

   function disableUserAD($data){
      $adConfig = new PluginResourcesAdconfig();
      $adConfig->getFromDB(1);
      $ad = new \Adldap\Adldap();
      $config = self::getConfig();
      $ad->addProvider($config);
      try {
         $provider = $ad->connect();
         $user = $provider->search()->whereEquals($adConfig->getField("logAD"), $data["login"])->firstOrFail();



         $attributes = [];
         $attr = $adConfig->getArrayAttributes();
         $ac = $user->getUserAccountControlObject();

         // Mark the account as enabled (normal).
         $ac->accountIsDisabled();
         $user->setUserAccountControl($ac);

         if($user->save()){
            $newParentDn = $user->getDnBuilder()->addOu($adConfig->getField("ouDesactivateUserAD"));
            $newParentDn = $newParentDn->removeOu($adConfig->getField("ouUser"));
            $newParentDn = $newParentDn->removeCn($user->getCommonName());
            if($user->move($newParentDn->get())){
               return true;
            }
            return false;
         }else{
            return false;
         }

      } catch (Adldap\Models\ModelNotFoundException $e) {
         // Record wasn't found!
         return false;
      }
   }





   function ldapTimeToUnixTime($ldapTime) {
      $secsAfterADEpoch = $ldapTime / 10000000;
      $ADToUnixConverter = ((1970 - 1601) * 365 - 3 + round((1970 - 1601) / 4)) * 86400;
      return intval($secsAfterADEpoch - $ADToUnixConverter);
   }

   function unixTimeToLdapTime($unixTime) {
      $ADToUnixConverter = ((1970 - 1601) * 365 - 3 + round((1970 - 1601) / 4)) * 86400;
      $secsAfterADEpoch = intval($ADToUnixConverter + $unixTime);
      return $secsAfterADEpoch * 10000000;
   }

   function modifyInformationUserAD(PluginResourcesResource $resource){
      $ds = $this->connect($authiD);
      $config_ldap = new self();
      $res         = $config_ldap->getFromDB($authiD);
      $ldapBase = $config_ldap->fields['basedn'];

      ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
      $sr = ldap_search($ds, $ldapBase, "(samaccountname=$username)");
      $ent= ldap_get_entries($ds,$sr);
      $dn=$ent[0]["dn"];
      // Deactivate
      if(!empty($resource->fields["date_end"])){
         $unix_time = strtotime($resource->fields["date_end"]);
         $win_time = $this->unixTimeToLdapTime($unix_time);
      }else{
         $win_time = 0;
      }
      $userdata=array();
      $userdata["sn"]=$resource->fields['name'] ;
      $userdata["givenName"]=$resource->fields['firstname'];
      $userdata["cn"]=$resource->fields['name']." ".$resource->fields['firstname'];
      $userdata["accountExpires"]=$win_time;
      $status = ldap_modify($ds, $dn, $userdata); //change state

      return $status; //return current status (1=enabled, 0=disabled)
   }

   function modifyManagerUserAD(PluginResourcesResource $resource){

      $ds = $this->connect($authiD);
      $config_ldap = new self();
      $res         = $config_ldap->getFromDB($authiD);
      $ldapBase = $config_ldap->fields['basedn'];

      ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
      $sr = ldap_search($ds, $ldapBase, "(samaccountname=$username)");
      $ent= ldap_get_entries($ds,$sr);
      $dn=$ent[0]["dn"];
      // Deactivate
      $dn2 ="";
      if(isset($resource->fields["users_id"])){
         $sr2 = ldap_search($ds, $ldapBase, "(samaccountname=$username)");
         $ent2= ldap_get_entries($ds,$sr2);
         $dn2=$ent2[0]["dn"];
      }
      $userdata=array();
      $userdata["manager"]=$dn2 ;
      $status = ldap_modify($ds, $dn, $userdata); //change state

      return $status; //return current status (1=enabled, 0=disabled)
   }

   function modifyServiceUserAD(PluginResourcesResource $resource){
      $ds = $this->connect($authiD);
      $config_ldap = new self();
      $res         = $config_ldap->getFromDB($authiD);
      $ldapBase = $config_ldap->fields['basedn'];

      ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
      $sr = ldap_search($ds, $ldapBase, "(samaccountname=$username)");
      $ent= ldap_get_entries($ds,$sr);
      $dn=$ent[0]["dn"];
      // Deactivate

      $userdata=array();
      if(!empty($resource->fields["plugin_resources_departments_id"])){
         $userdata["department"] = Dropdown::getDropdownName('glpi_plugin_resources_departments', $resource->getField("plugin_resources_departments_id"));
      }else{
         $userdata["department"] ="";
      }

      $status = ldap_modify($ds, $dn, $userdata); //change state

      return $status; //return current status (1=enabled, 0=disabled)
   }
   function modifyCompanyUserAD(PluginResourcesResource $resource){
      $ds = $this->connect($authiD);
      $config_ldap = new self();
      $res         = $config_ldap->getFromDB($authiD);
      $ldapBase = $config_ldap->fields['basedn'];

      ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
      $sr = ldap_search($ds, $ldapBase, "(samaccountname=$username)");
      $ent= ldap_get_entries($ds,$sr);
      $dn=$ent[0]["dn"];
      // Deactivate

      $userdata=array();
      $employee = new PluginResourcesEmployee();

      if($employee->getFromDBByCrit(["plugin_resources_resources_id"=>$resource->getID()])){
         $userdata["company"] = Dropdown::getDropdownName('glpi_plugin_resources_employers', $employee->getField("plugin_resources_employers_id"));
      }else{
         $userdata["company"] ="";
      }

      $status = ldap_modify($ds, $dn, $userdata); //change state

      return $status; //return current status (1=enabled, 0=disabled)
   }

   function modifyStateUserAD( PluginResourcesResource $resource){
      $ds = $this->connect($authiD);
      $config_ldap = new self();
      $res         = $config_ldap->getFromDB($authiD);
      $ldapBase = $config_ldap->fields['basedn'];

      ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
      $sr = ldap_search($ds, $ldapBase, "(samaccountname=$username)");
      $ent= ldap_get_entries($ds,$sr);
      $dn=$ent[0]["dn"];
      // Deactivate

      $userdata=array();
      $employee = new PluginResourcesEmployee();

      if($employee->getFromDBByCrit(["plugin_resources_resources_id"=>$resource->getID()])){
         $userdata["organizationalStatus"] = Dropdown::getDropdownName('glpi_plugin_resources_contracttypes', $employee->getField("plugin_resources_contracttypes_id"));
      }else{
         $userdata["organizationalStatus"] ="";
      }

      $status = ldap_modify($ds, $dn, $userdata); //change state

      return $status; //return current status (1=enabled, 0=disabled)
   }
   /**
    * allow search management
    */
   function rawSearchOptions() {

      $tab = parent::rawSearchOptions();

      $tab[] = [
         'id'            => '2',
         'table'         => $this->getTable(),
         'field'         => 'id',
         'name'          => __('ID'),
         'datatype'      => 'number',
         'massiveaction' => false
      ];

      $tab[] = [
         'id'            => '3',
         'table'         => 'glpi_plugin_resources_ranks',
         'field'         => 'name',
         'name'          => __('Rank', 'resources'),
         'massiveaction' => false,
         'datatype'      => 'dropdown'
      ];

      $tab[] = [
         'id'            => '4',
         'table'         => 'glpi_plugin_resources_professions',
         'field'         => 'name',
         'name'          => __('Profession', 'resources'),
         'massiveaction' => false,
         'datatype'      => 'dropdown'
      ];

      $tab[] = [
         'id'       => '5',
         'table'    => 'glpi_plugin_resources_budgettypes',
         'field'    => 'name',
         'name'     => __('Budget type', 'resources'),
         'datatype' => 'dropdown'
      ];

      $tab[] = [
         'id'       => '6',
         'table'    => $this->getTable(),
         'field'    => 'begin_date',
         'name'     => __('Begin date'),
         'datatype' => 'date'
      ];

      $tab[] = [
         'id'       => '7',
         'table'    => $this->getTable(),
         'field'    => 'end_date',
         'name'     => __('End date'),
         'datatype' => 'date'
      ];

      $tab[] = [
         'id'    => '8',
         'table' => $this->getTable(),
         'field' => 'volume',
         'name'  => __('Budget volume', 'resources')
      ];

      $tab[] = [
         'id'       => '9',
         'table'    => 'glpi_plugin_resources_budgetvolumes',
         'field'    => 'name',
         'name'     => __('Type of budget volume', 'resources'),
         'datatype' => 'dropdown'
      ];

      $tab[] = [
         'id'            => '10',
         'table'         => $this->getTable(),
         'field'         => 'date_mod',
         'name'          => __('Last update'),
         'datatype'      => 'datetime',
         'massiveaction' => false
      ];

      $tab[] = [
         'id'       => '80',
         'table'    => 'glpi_entities',
         'field'    => 'completename',
         'name'     => __('Entity'),
         'datatype' => 'dropdown'
      ];

      return $tab;
   }

   /**
    * Display the budget form
    *
    * @param $ID integer ID of the item
    * @param $options array
    *     - target filename : where to go when done.
    *     - withtemplate boolean : template or basic item
    *
    * @return boolean item found
    * */
   function showForm($ID, $options = [""]) {
      global $CFG_GLPI;

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Name')."</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "name", ['value' => $this->fields["name"]]);
      echo "</td>";

      echo "<td>".__('Budget type', 'resources')."</td>";
      echo "<td>";
      Dropdown::show('PluginResourcesBudgetType', ['value'  => $this->fields["plugin_resources_budgettypes_id"],
          'entity' => $this->fields["entities_id"]]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Profession', 'resources')."</td>";
      echo "<td>";
      $params = ['name'    => 'plugin_resources_professions_id',
                      'value'   => $this->fields['plugin_resources_professions_id'],
                      'entityt' => $this->fields["entities_id"],
                      'action'  => $CFG_GLPI["root_doc"]."/plugins/resources/ajax/dropdownRank.php",
                      'span'    => 'span_rank',
                      'sort'    => true];
      PluginResourcesResource::showGenericDropdown('PluginResourcesProfession', $params);

      echo "</td>";
      echo "<td>".__('Rank', 'resources')."</td><td>";
      echo "<span id='span_rank' name='span_rank'>";
      if ($this->fields["plugin_resources_ranks_id"] > 0) {
         echo Dropdown::getDropdownName('glpi_plugin_resources_ranks', $this->fields["plugin_resources_ranks_id"]);
      } else {
         echo __('None');
      }
      echo "</span></td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Budget volume', 'resources')."</td>";
      echo "<td>";
      $options = ['value' => 0];
      Html::autocompletionTextField($this, 'volume', $options);
      echo "</td><td>".__('Type of budget volume', 'resources')."</td><td>";
      Dropdown::show('PluginResourcesBudgetVolume', ['value'  => $this->fields["plugin_resources_budgetvolumes_id"],
          'entity' => $this->fields["entities_id"]]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Begin date')."</td>";
      echo "<td>";
      Html::showDateField("begin_date", ['value' => $this->fields["begin_date"]]);
      echo "</td>";
      echo "<td>".__('End date')."</td>";
      echo "<td>";
      Html::showDateField("end_date", ['value' => $this->fields["end_date"]]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='center' colspan='6'>";
      printf(__('Last update on %s'), Html::convDateTime($this->fields["date_mod"]));
      echo "</td>";
      echo "</tr>";

      if (Session::getCurrentInterface() != 'central') {
         $options['candel'] = false;
      }
      $this->showFormButtons($options);

      return true;
   }

   /**
    * @param $menu
    *
    * @return mixed
    */
   static function getMenuOptions($menu) {

      $plugin_page                                   = '/plugins/resources/front/budget.php';
      $itemtype                                      = strtolower(self::getType());

      //Menu entry in admin
      $menu['options'][$itemtype]['title']           = self::getTypeName();
      $menu['options'][$itemtype]['page']            = $plugin_page;
      $menu['options'][$itemtype]['links']['search'] = $plugin_page;

      if (Session::haveright(self::$rightname, UPDATE)) {
         $menu['options'][$itemtype]['links']['add'] = '/plugins/resources/front/budget.form.php';
      }

      return $menu;
   }

}

