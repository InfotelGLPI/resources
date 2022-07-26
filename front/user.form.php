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

use Glpi\Event;

include ('../../../inc/includes.php');


$user      = new User();
$groupuser = new Group_User();

if (empty($_GET["id"]) && isset($_GET["name"])) {

   $user->getFromDBbyName($_GET["name"]);
   Html::redirect($user->getFormURLWithID($user->fields['id']));
}

if (empty($_GET["name"])) {
   $_GET["name"] = "";
}

if (isset($_POST["update"])) {
   $user->check($_POST['id'], UPDATE);
   $user->update($_POST);
   Event::log($_POST['id'], "users", 5, "setup",
              //TRANS: %s is the user login
              sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
   $resource        = new PluginResourcesResource();
   $resource->getFromDB($_POST['idResource']);
   $ticket = new Ticket();
   $itemTicket = new Item_Ticket();
   $content = "";
   $ticketResources = $itemTicket->find(['itemtype' => 'PluginResourcesResource', 'items_id'=> $resource->getID()]);
   foreach ($_POST as $key => $value){
      if(strpos($key,'field') > 0 && !is_integer(strpos($key,'plugin_ldapfields'))){
         $field = new PluginFieldsField();
         if($field->getFromDBByCrit(['name' => $key])){
            $content .= $field->getField('label') . " : ". $value.'<br />';
         }
      } else{
         switch($key){
            case 'phone':
               $content .= __('Phone') . " : ".$value."<br />";
               break;
            case '_useremails' :
               if(is_array($value) && !empty($value)) {
                  $content .= _n('Email', 'Emails', 1) . " : ";
                  foreach ($value as $email) {
                     $content .= $email . "<br />";
                  }
               }
               break;
         }
      }
   }

   foreach ($ticketResources as $ticketResource){
      $ticket->getFromDB($ticketResource['tickets_id']);
      if($ticket->getField('status')<5){
         if(Plugin::isPluginActive("escalade")){
            $first_history = PluginEscaladeHistory::getFirstLineForTicket($ticketResource['tickets_id']);
            //add the first history group (if not already exist)
            $group_ticket = new Group_Ticket;
            $condition = [
               'tickets_id' => $ticketResource['tickets_id'],
               'groups_id'  => $first_history['groups_id'],
               'type'       => CommonITILActor::ASSIGN
            ];
            if (!$group_ticket->find($condition)) {
               $group_ticket->add($condition);
            }
         }
         $solution = new ITILSolution();
         $solution->add(['itemtype'=>'Ticket',
                         'items_id' => $ticket->getField('id'),
                         'content' => $content]);
      }
   }
   NotificationEvent::raiseEvent('other', $resource);
   Html::back();

}  else {
   Html::back();
}
