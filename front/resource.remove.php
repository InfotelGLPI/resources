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

include('../../../inc/includes.php');


if (Session::getCurrentInterface() == 'central') {
   Html::header(PluginResourcesResource::getTypeName(2), '', "admin", PluginResourcesMenu::getType());
} else {
   if (Plugin::isPluginActive('servicecatalog')) {
      PluginServicecatalogMain::showDefaultHeaderHelpdesk(PluginResourcesMenu::getTypeName(2));
   } else {
      Html::helpHeader(PluginResourcesResource::getTypeName(2));
   }
}

if (empty($_POST["date_end"])) {
   $_POST["date_end"] = date("Y-m-d");
}

$resource        = new PluginResourcesResource();
$checklistconfig = new PluginResourcesChecklistconfig();

if (isset($_POST["removeresources"]) && $_POST["plugin_resources_resources_id"] != 0) {
   if (!isset($_POST["plugin_resources_leavingreasons_id"])) {
      $_POST["plugin_resources_leavingreasons_id"] = 0;
   }
   $date     = date("Y-m-d H:i:s");
   $CronTask = new CronTask();
   $CronTask->getFromDBbyName("PluginResourcesEmployment", "ResourcesLeaving");

   $input["id"]       = $_POST["plugin_resources_resources_id"];
   $input["date_end"] = $_POST["date_end"];
   if (($_POST["date_end"] < $date)
       || ($CronTask->fields["state"] == CronTask::STATE_DISABLE)) {
      $input["is_leaving"]               = "1";
      $input["date_declaration_leaving"] = date('Y-m-d H:i:s');
   } else {
      $input["is_leaving"]               = "0";
      $input["date_declaration_leaving"] = null;
   }
   $input["plugin_resources_leavingreasons_id"] = $_POST["plugin_resources_leavingreasons_id"];
   $input["withtemplate"]                       = "0";
   $input["users_id_recipient_leaving"]         = Session::getLoginUserID();
   $input['send_notification']                  = 1;
   $resource->update($input);

   //test it
   $resource->getFromDB($_POST["plugin_resources_resources_id"]);
   $resources_checklist = PluginResourcesChecklist::checkIfChecklistExist($_POST["plugin_resources_resources_id"], PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT);
   if (!$resources_checklist) {
      $checklistconfig->addChecklistsFromRules($resource, PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT);
   }
   $config = new PluginResourcesConfig();
   $config->getFromDB(1);
   Session::addMessageAfterRedirect(__('Declaration of resource leaving OK', 'resources'));
   if ($config->fields["create_ticket_departure"]) {
      $ticket = new Ticket();

      $tt = $ticket->getITILTemplateToUse(0, Ticket::DEMAND_TYPE, $config->fields["categories_id"]);
      if (isset($tt->predefined) && count($tt->predefined)) {
         foreach ($tt->predefined as $predeffield => $predefvalue) {
            // Load template data
            $ticket->fields[$predeffield] = Toolbox::addslashes_deep($predefvalue);
         }
      }
      $resource->getFromDB($input["id"]);
      $ticket->fields["name"] =Toolbox::addslashes_deep( __("Departure of",'resources')." ".$resource->fields['name']." ".$resource->fields['firstname']);
      $ticket->fields["itilcategories_id"] = $config->fields["categories_id"];

      $ticket->fields["content"] = $resource->fields['name']." ".$resource->fields['firstname']." ".__("leave on","resources")." ".Html::convDate($input["date_end"]);
      if(isset($input['plugin_resources_leavingreasons_id']) && !empty($input['plugin_resources_leavingreasons_id'])) {
         $ticket->fields["content"] .= "<br>".PluginResourcesLeavingReason::getTypeName(0)." : ".Dropdown::getDropdownName(PluginResourcesLeavingReason::getTable(),$input["plugin_resources_leavingreasons_id"]);
      }
      if(($resource->fields['plugin_resources_contracttypes_id']) != 0) {
         $ticket->fields["content"] .= "<br>".PluginResourcesContractType::getTypeName(0)." : ".Dropdown::getDropdownName(PluginResourcesContractType::getTable(),$resource->fields['plugin_resources_contracttypes_id']);
      } else {
         $ticket->fields["content"] .= "<br>".PluginResourcesContractType::getTypeName(0)." : ". __("Without contract",'resources');
      }
      $ticket->fields['users_id_recipient']  = Session::getLoginUserID();
      $ticket->fields['_users_id_requester'] = Session::getLoginUserID();
      $ticket->fields["type"] = Ticket::DEMAND_TYPE;
      $ticket->fields["entities_id"] = $_SESSION['glpiactive_entity'];
      $ticket->fields['items_id'] = ['PluginResourcesResource' => [$input['id']]];
      unset($ticket->fields["id"]);
      $ticket->add($ticket->fields);
      $linkad = new PluginResourcesLinkAd();
      if ($linkad->getFromDBByCrit(["plugin_resources_resources_id" => $input['id']])) {
         $input2                = [];
         $input2['action_done'] = 0;
         $input2['id']          = $linkad->getID();
         $linkad->update($input2);
      }
   }

   Html::back();

} else {
   if ($resource->canView() || Session::haveRight("config", UPDATE)) {
      //show remove resource form
      $resource->showResourcesToRemove();
   }
}

if (Session::getCurrentInterface() != 'central'
    && Plugin::isPluginActive('servicecatalog')) {

   PluginServicecatalogMain::showNavBarFooter('resources');
}

if (Session::getCurrentInterface() == 'central') {
   Html::footer();
} else {
   Html::helpFooter();
}
