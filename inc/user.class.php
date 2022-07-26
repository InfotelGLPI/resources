<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2020 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

use Sabre\VObject;
use Glpi\Exception\ForgetPasswordException;
use Glpi\Exception\PasswordTooWeakException;

class PluginResourcesUser extends User {

   // From CommonDBTM


   static function getTypeName($nb = 0) {
      return _n('User', 'Users', $nb);
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      switch ($item->getType()) {
         case 'PluginResourcesResource' :
            return __('User');
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI;

      switch ($item->getType()) {
         case 'PluginResourcesResource' :
            $user = new self();
            $resourceItem = new PluginResourcesResource_Item();
            $resourceUsers = $resourceItem->find(['plugin_resources_resources_id' => $item->getID(), 'itemtype' => 'User'], 'id DESC');
            if (count($resourceUsers)>0) {
               //Get last user linked to resource
               foreach ($resourceUsers as $resourceUser){
                  $user->showForm($resourceUser['items_id'],['resourcesID'=>$item->getID()]);
                  break;
               }
            }
            return true;
      }
      return false;
   }

   function showForm($ID, array $options = []) {
      global $CFG_GLPI, $DB;

      // Affiche un formulaire User simplifié
      if (($ID != Session::getLoginUserID()) && !user::canView()) {
         return false;
      }
      $user = new User();

      $user->initForm($ID, $options);
      $this->fields['id']=0;
      $this->showFormHeader($options);
      echo html::input('idResource',['type'=>'hidden', 'value'=>$options['resourcesID']]);
      echo "<td>" . __('Authentication') . "</td><td>";
      echo Auth::getMethodName($user->fields["authtype"], $user->fields["auths_id"]);
      if (!empty($user->fields["date_sync"])) {
         //TRANS: %s is the date of last sync
         echo '<br>'.sprintf(__('Last synchronization on %s'),
                             Html::convDateTime($user->fields["date_sync"]));
      }
      if (!empty($user->fields["user_dn"])) {
         echo '<br>'.sprintf(__('%1$s: %2$s'), __('User DN'), $user->fields["user_dn"]);
      }
      if ($user->fields['is_deleted_ldap']) {
         echo '<br>'.__('User missing in LDAP directory');
      }

      $phonerand = mt_rand();
      echo "<tr class='tab_bg_1'>";
      echo "<td><label for='textfield_phone$phonerand'>" .  Phone::getTypeName(1) . "</label></td><td>";
      echo Html::input('phone', ['value' => $user->fields['phone'], 'size' => 40, 'rand' => $phonerand]);
      echo "</td>";

      echo "<td>" . _n('Email', 'Emails', Session::getPluralNumber());
      echo "</td><td>";
      UserEmail::showForUser($user);
      echo "</td>";
      echo "</tr>";
      $user->showFormButtons($options);
      return true;
   }



   /**
    * @param \PluginPdfSimplePDF $pdf
    * @param \CommonGLPI         $item
    * @param                     $tab
    *
    * @return bool
    */
   static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab) {

      if ($item->getType() == 'PluginResourcesResource') {
         self::pdfForResource($pdf, $item);

      } else {
         return false;
      }
      return true;
   }

   /**
    * Show for PDF an resources : employee informations
    *
    * @param $pdf object for the output
    * @param $appli PluginResourcesResource Class
    */
   static function pdfForResource(PluginPdfSimplePDF $pdf, PluginResourcesResource $appli) {
      global $DB;

      $ID = $appli->fields['id'];

      if (!$appli->can($ID, READ)) {
         return false;
      }

      if (!Session::haveRight("plugin_resources", READ)) {
         return false;
      }

      $query  = "SELECT * 
               FROM `glpi_plugin_resources_resources_items` 
               WHERE `plugin_resources_resources_id` = '$ID'
               AND `itemtype` = 'User'
               ORDER BY ID DESC
               LIMIT 1";
      $result = $DB->query($query);
      $number = $DB->numrows($result);
      $pdf->setColumnsSize(100);

      $pdf->displayTitle('<b>' . self::getTypeName(1) . '</b>');

      $addDisplayTitle = [];
      $addContent = [];
      if(Plugin::isPluginActive('fields')){
         $fieldContainer = new PluginFieldsContainer();
         if($fieldContainer->getFromDBByCrit(['itemtypes' => ['LIKE','%User%']])){
            $nameTable = "PluginFieldsUser".$fieldContainer->getField('name');
            $itemUser = new $nameTable();
            for ($i = 0; $i < $number; $i++) {
               $user_id = $DB->result($result, $i, "items_id");
               if($itemUser->getFromDBByCrit(['items_id' => $user_id, 'itemtype' => 'User'])){
                  foreach ($itemUser->fields as $key => $field){
                     if($key !='id' && $key !='items_id' && $key !='itemtype' && $key !='plugin_fields_containers_id'){
                        $fieldFields = new PluginFieldsField();
                        if($fieldFields->getFromDBByCrit(['name' => $key])){
                           $item = [
                              "itemtype" => "PluginFieldsField",
                              "id"       => $fieldFields->getID()
                           ];
                           $addDisplayTitle[]= PluginFieldsLabelTranslation::getLabelFor($item);
                           $addContent[] = $field;
                        }

                     }
                  }
               }
               break;
            }
         }
      }
      $titles = [ __('Authentication'),
                     __('Last synchronization'),
                     __('User DN'),
                     Phone::getTypeName(1),
                     _n('Email', 'Emails', Session::getPluralNumber())];
      $titles = array_merge($titles,$addDisplayTitle);

      $nbTitle = count($titles);
      $columnSize = floor(100/$nbTitle);
      $arrayColumn = [];
      for ($i=0;$i<$nbTitle;$i++){
         if(($columnSize*$nbTitle) + $i < 100){
            $arrayColumn[] = $columnSize+1;
         } else {
            $arrayColumn[] = $columnSize;
         }
      }
      call_user_func_array([$pdf,"setColumnsSize"],$arrayColumn);

      call_user_func_array([$pdf,"displayTitle"],$titles);
      if (!$number) {
         $pdf->displayLine(__('No item found'));
      } else {
         for ($i = 0; $i < $number; $i++) {
            $user = new User();
            $user_id = $DB->result($result, $i, "items_id");
            $user->getFromDB($user_id);
            $auth =  Auth::getMethodName($user->getField("authtype"), $user->getField("auths_id"));
            $lastSync ="";
            $userDN ="";
            if (!empty($user->getField("date_sync"))) {
               //TRANS: %s is the date of last sync
               $lastSync =  Html::convDateTime($user->fields["date_sync"]);
            }
            if (!empty($user->getField("user_dn"))) {
               $userDN = $user->getField("user_dn");
            }
            if ($user->getField('is_deleted_ldap')) {
               $userDN .= __('User missing in LDAP directory');
            }

            $phone = $user->getField('phone');

            $userEmail = new UserEmail();
            $userEmail->getFromDBByCrit(['users_id' => $user->getID()]);
            $email = $userEmail->getName();
         }

//         $pdf->displayLine($auth,$lastSync,$userDN,$phone,$email,$addContent[0],$addContent[1],$addContent[2]);
         $resultsDisplay = [$auth,$lastSync,$userDN,$phone,$email];
         $resultsDisplay = array_merge($resultsDisplay,$addContent);
         call_user_func_array([$pdf,"displayLine"],$resultsDisplay);
      }

      $pdf->displaySpace();
   }
}
