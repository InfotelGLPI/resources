<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
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

include ('../../../inc/includes.php');

if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}


$linkad = new PluginResourcesLinkAd();

//from central
//update checklist
if (isset($_POST["add"])) {
   $linkad->add($_POST);
   Html::back();

} else if (isset($_POST["update"])) {
   if ($linkad->canCreate()) {
      $linkad->update($_POST);
   }
   $ldap = new PluginResourcesLDAP();
   $ldap->getUserInformation($_POST["auth_id"]);
   Html::back();

} else if(isset($_POST["createAD"])) {
   $ldap = new PluginResourcesLDAP();
   $res = $ldap->createUserAD($_POST);
   if($res){
      $_POST["action_done"] = 1;
      $linkad->add($_POST);
      $fup = new ITILFollowup();

      $toadd = ['type'       => "new",
                'items_id' => $_POST["ticket_id"],
                'itemtype' => 'Ticket',
                'is_private' => 1];


      $content = Toolbox::addslashes_deep(sprintf(__('%1$s %2$s have been added in the LDAP directory','resources'),$_POST["firstname"],$_POST["name"]));
      $toadd["content"] = htmlentities($content,ENT_NOQUOTES);

      $fup->add($toadd);
      $message = __('the user has been added to the LDAP directory','resources');
      Session::addMessageAfterRedirect($message, false, INFO);
   }else{
      $message = __('the user has not been added to the LDAP directory','resources');
      Session::addMessageAfterRedirect($message, false, ERROR);
   }
   Html::back();
} else if(isset($_POST["updateAD"])) {
   $ldap = new PluginResourcesLDAP();
   $linkad->getFromDB($_POST['id']);
   $_POST["login"] = $linkad->getField("login");
   $res = $ldap->updateUserAD($_POST);
   if($res[0]){

      $_POST["action_done"] = 1;
      $linkad->update($_POST);
      $fup = new ITILFollowup();

      $toadd = ['type'       => "new",
                'items_id' => $_POST["ticket_id"],
                'itemtype' => 'Ticket',
                'is_private' => 1];

      $content = Toolbox::addslashes_deep(sprintf(__('%1$s %2$s have been updated in the LDAP directory','resources'),$_POST["firstname"],$_POST["name"]));
      $content .= __("Data changed",'resources')." <br />";
      foreach ($res[1] as $key => $oldData){
         $i =1;
         $nb =count($oldData);
         $content .= $key." : ";
         foreach ($oldData as $data){
            if($key == "accountexpires"){
               $time = $ldap->ldapTimeToUnixTime($data);
               $data = date('Y-m-d',$time);
               $data =  Html::convDate($data);

            }
            $content.=$data;
            if($i<$nb){
               $content.=", ";
            }
            $i++;
         }
         $content .= "<br />";

      }
      $toadd["content"] = htmlentities($content,ENT_NOQUOTES);

      $fup->add($toadd);
      $message = __('the user has been updated to the LDAP directory','resources');
      Session::addMessageAfterRedirect($message, false, INFO);
   }else{
      $message = __('the user has not been updated to the LDAP directory','resources');
      Session::addMessageAfterRedirect($message, false, ERROR);
   }
   Html::back();
} else if(isset($_POST["disableAD"])) {
   $ldap = new PluginResourcesLDAP();
   $linkad->getFromDB($_POST['id']);
   $_POST["login"] = $linkad->getField("login");
   $res = $ldap->disableUserAD($_POST);
   if($res){
      $_POST["action_done"] = 1;
      $linkad->update($_POST);
      $fup = new ITILFollowup();

      $toadd = ['type'       => "new",
                'items_id' => $_POST["ticket_id"],
                'itemtype' => 'Ticket',
                'is_private' => 1];

      $content = Toolbox::addslashes_deep(sprintf(__('%1$s %2$s have been disabled and moved in the LDAP directory','resources'),$_POST["firstname"],$_POST["name"]));
      $toadd["content"] = htmlentities($content,ENT_NOQUOTES);

      $fup->add($toadd);
      $message = __('the user has been disabled and moved to the LDAP directory','resources');
      Session::addMessageAfterRedirect($message, false, INFO);
   }else{
      $message = __('the user has not been disabled and moved to the LDAP directory','resources');
      Session::addMessageAfterRedirect($message, false, ERROR);
   }
   Html::back();
}


