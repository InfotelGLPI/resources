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

if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}
if (!isset($_GET["withtemplate"])) {
   $_GET["withtemplate"] = "";
}

$resource        = new PluginResourcesResource();
$checklist       = new PluginResourcesChecklist();
$checklistconfig = new PluginResourcesChecklistconfig();
$employee        = new PluginResourcesEmployee();
$choice          = new PluginResourcesChoice();
$resource_item   = new PluginResourcesResource_Item();
$cat             = new PluginResourcesTicketCategory();
$task            = new PluginResourcesTask();
/////////////////////////////////resource from helpdesk///////////////////////////////

if (isset($_POST["secondary_services"])) {
   $_POST["secondary_services"] = json_encode($_POST["secondary_services"]);
} else{
   $_POST["secondary_services"] = "";
}
if (isset($_POST["resend"])) {
   $resource->reSendResourceCreation($_POST);
   $resource->redirectToList();

   //from helpdesk
   //add items needs of a resource
} else if (isset($_POST["addhelpdeskitem"])) {
   if ($_POST['plugin_resources_choiceitems_id'] > 0 && $_POST['plugin_resources_resources_id'] > 0) {
      if ($resource->canCreate()) {
         $choice->addHelpdeskItem($_POST);
      }
   }
   Html::back();
} //from helpdesk
//delete items needs of a resource
else if (isset($_POST["deletehelpdeskitem"])) {
   if ($resource->canCreate()) {
      $choice->delete(['id' => $_POST["id"]]);
   }
   Html::back();

   ///////////////////////////////employees///////////////////////////////
   //from central
   // add employee and resource if adding employee informations from user details form
} else if (isset($_POST["addressourceandemployee"])) {
   if ($employee->canCreate()) {
      $User = new user();
      $User->getFromDB($_POST["users_id"]);
      //Check unicity by criteria control
      $_POST["name"]                              = $User->fields["realname"];
      $_POST["firstname"]                         = $User->fields["firstname"];
      $_POST["entities_id"]                       = $_SESSION["glpiactive_entity"];
      $_POST["plugin_resources_contracttypes_id"] = 0;
      $_POST["users_id"]                          = 0;
      $_POST["users_id_sales"]                    = 0;
      $_POST["date_end"]                          = date('Y-m-d');
      $_POST["departments_id"]                    = 0;
      $_POST["is_leaving"]                        = 0;
      $_POST["users_id_recipient_leaving"]        = 0;
      $_POST["comment"]                           = "";
      $_POST["notes"]                             = "";
      $_POST["is_template"]                       = 0;
      $_POST["template_name"]                     = "";
      $_POST["is_deleted"]                        = 0;
      $_POST["withtemplate"]                      = 0;

      if ($_POST["templates_id"] > 0) {
         $resource->getFromDB($_POST["templates_id"]);
         unset($resource->fields["is_template"]);
         unset($resource->fields["date_mod"]);

         $fields = [];
         foreach ($resource->fields as $key => $value) {
            if ($value != '' && (!isset($fields[$key]) || $fields[$key] == '' || $fields[$key] == 0)) {
               $_POST[$key] = $value;
            }
         }

         $_POST["withtemplate"] = 1;
      }
      //for not create employee informations with template
      $_POST["add_from_helpdesk"]  = 1;
      $_POST["comment"]            = addslashes($_POST["comment"]);
      $_POST["locations_id"]       = $User->fields["locations_id"];
      $_POST["date_begin"]         = date('Y-m-d');
      $_POST["users_id_recipient"] = Session::getLoginUserID();
      $_POST["date_declaration"]   = date('Y-m-d');

      //add resources
      $newID = $resource->add($_POST);

      if ($newID) {
         //add link with User
         $opt["plugin_resources_resources_id"] = $newID;
         $opt["items_id"]                      = $User->fields["id"];
         $opt["itemtype"]                      = 'User';

         $resource_item->addItem($opt);

         //add employee
         $values["plugin_resources_resources_id"] = $newID;
         $values["plugin_resources_employers_id"] = $_POST["plugin_resources_employers_id"];
         $values["plugin_resources_clients_id"]   = $_POST["plugin_resources_clients_id"];
         $values["is_template"]                   = 0;
         $employee->add($values);
      }
   }

   Html::back();

   //from central
   //add employee informations from user details form or resource form
} else if (isset($_POST["addemployee"])) {
   if ($_POST['plugin_resources_resources_id'] > 0) {
      if ($employee->canCreate()) {
         $values["plugin_resources_resources_id"] = $_POST["plugin_resources_resources_id"];
         $values["plugin_resources_employers_id"] = $_POST["plugin_resources_employers_id"];
         $values["plugin_resources_clients_id"]   = $_POST["plugin_resources_clients_id"];
         $newID                                   = $employee->add($values);
      }
   }
   Html::back();
} //from central OR helpdesk
//update employee informations from user details form or resource form
else if (isset($_POST["updateemployee"])) {
   if ($_POST['plugin_resources_resources_id'] > 0) {
      if ($employee->canCreate()) {
         $values["id"]                            = $_POST['id'];
         $values["plugin_resources_resources_id"] = $_POST["plugin_resources_resources_id"];
         $values["plugin_resources_employers_id"] = $_POST["plugin_resources_employers_id"];
         $values["plugin_resources_clients_id"]   = $_POST["plugin_resources_clients_id"];
         $employee->update($values);
      }
   }
   Html::back();
} //from central
//delete employee informations from user details form or resource form
else if (isset($_POST["deleteemployee"])) {
   if ($employee->canCreate()) {
      $employee->delete($_POST, 1);
   }
   Html::back();

   /////////////////////////////////resource from central///////////////////////////////
   //add resource
} else if (isset($_POST["add"])) {
   $resource->check(-1, UPDATE, $_POST);
   $newID = $resource->add($_POST);
   if (isset($_POST['plugin_resources_employers_id'])) {
      $employee = new PluginResourcesEmployee();
      $employee->add(['plugin_resources_employers_id' => $_POST['plugin_resources_employers_id'],
                      'plugin_resources_resources_id' => $newID,
                      'plugin_resources_clients_id'   => 0]);
   }
   Html::back();
} //from central
//update resource
else if (isset($_POST["update"])) {
   $resource->check($_POST['id'], UPDATE);
   $resource->update($_POST);
   if (isset($_POST['plugin_resources_employers_id'])) {
      $employee = new PluginResourcesEmployee();
      if ($employee->getFromDBByCrit(['plugin_resources_resources_id' => $_POST['id']])) {
         $employee->update(['id'                            => $employee->getID(),
                            'plugin_resources_employers_id' => $_POST['plugin_resources_employers_id'],
                            'plugin_resources_resources_id' => $_POST['id'],
                            'plugin_resources_clients_id'   => 0]);
      } else {
         $employee->add(['plugin_resources_employers_id' => $_POST['plugin_resources_employers_id'],
                         'plugin_resources_resources_id' => $_POST['id'],
                         'plugin_resources_clients_id'   => 0]);
      }
   }

   Html::back();
} //from central
//delete resource
else if (isset($_POST["delete"])) {
   $resource->check($_POST['id'], UPDATE);
   if (!empty($_POST["withtemplate"])) {
      $resource->delete($_POST, 1);
   } else {
      $resource->delete($_POST);
   }

   if (!empty($_POST["withtemplate"])) {
      Html::redirect(PLUGIN_RESOURCES_WEBDIR. "/front/setup.templates.php?add=0");
   } else {
      $resource->redirectToList();
   }
} //from central
//restore resource
else if (isset($_POST["restore"])) {
   $resource->check($_POST['id'], UPDATE);
   $resource->restore($_POST);
   $resource->redirectToList();
} //from central
//purge resource template
else if (isset($_POST["purge"])) {
   $resource->check($_POST['id'], UPDATE);
   $resource->delete($_POST, 1);
   if (!empty($_POST["withtemplate"])) {
      Html::redirect(PLUGIN_RESOURCES_WEBDIR. "/front/setup.templates.php?add=0");
   } else {
      $resource->redirectToList();
   }
} //from central
//purge resource
else if (isset($_POST["purge"])) {
   $resource->check($_POST['id'], UPDATE);
   $resource->delete($_POST, 1);
   $resource->redirectToList();
} //from central
//add items of a resource
else if (isset($_POST["additem"])) {
   if (!empty($_POST['itemtype']) && !empty($_POST['items_id'])) {
      $resource_item->addItem($_POST);
   }
   Html::back();
} //from central
//update comment of item of a resource
else if (isset($_POST["updatecomment"])) {
   foreach ($_POST["updatecomment"] as $key => $val) {
      $varcomment = "comment" . $key;
      $resource_item->updateItem($key, $_POST[$varcomment]);
   }
   Html::back();
} //from central
//delete item of a resource
else if (isset($_POST["deleteitem"])) {

   foreach ($_POST["item"] as $key => $val) {
      if ($val == 1) {
         $resource_item->check($key, UPDATE);
         $resource_item->deleteItem($key);
      }
   }

   Html::back();
} //from central
//delete item of a resource form items detail
else if (isset($_POST["deleteresources"])) {
   $input = ['id' => $_POST["id"]];
   $resource_item->check($_POST["id"], UPDATE);
   $resource_item->deleteItem($_POST["id"]);
   Html::back();
} //from central
//add checklist from resource form
else if (isset($_POST["add_checklist_resources"])) {
   if ($checklist->canCreate()) {
      $resource->getFromDB($_POST["id"]);

      $checklistconfig->addChecklistsFromRules($resource, PluginResourcesChecklist::RESOURCES_CHECKLIST_IN);
      $checklistconfig->addChecklistsFromRules($resource, PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT);
      $checklistconfig->addChecklistsFromRules($resource, PluginResourcesChecklist::RESOURCES_CHECKLIST_TRANSFER);
   }
   Html::back();
} ///////////////////////////////checklists///////////////////////////////
//from central
//add checklist
else if (isset($_POST["add_checklist"])) {
   if ($checklist->canCreate()) {
      $newID = $checklist->add($_POST);
   }
   Html::back();

   //from central
   //close checklist
} else if (isset($_POST["close_checklist"])) {
   $isfinished = PluginResourcesChecklist::checkifChecklistFinished($_POST);

   if ($isfinished) {
      PluginResourcesChecklist::createTicket($_POST);
   } else {
      Session::addMessageAfterRedirect(__('The checklist is not finished', 'resources'), true, ERROR);
   }
   Html::back();

   //from central
   //open checklist
} else if (isset($_POST["open_checklist"])) {
   if ($checklist->canCreate()) {
      $checklist->openFinishedChecklist($_POST);
   }
   Html::back();

   //from central
   //up / down checklist
} else if (isset($_POST["move"])) {
   $checklist->changeRank($_POST);
   Html::back();

} else if (isset($_POST["report"])) {
   $restrict   = ["itemtype"                      => 'User',
                  "plugin_resources_resources_id" => $_POST["id"]];
   $dbu        = new DbUtils();
   $linkeduser = $dbu->getAllDataFromTable('glpi_plugin_resources_resources_items', $restrict);

   if (!empty($linkeduser)) {
      $resource->sendReport($_POST);
      Session::addMessageAfterRedirect(__('Notification sent', 'resources'), true);
   } else {
      Session::addMessageAfterRedirect(__('The notification is not sent because the resource is not linked with a user', 'resources'), true, ERROR);
   }
   Html::back();

} else if (isset($_POST["delete_picture"])) {
   if (isset($_POST['picture'])) {
      $filename = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $_POST['picture'];
      if (file_exists($filename)) {
         if (unlink($filename)) {
            $_POST['picture'] = 'NULL';
            $resource->check($_POST['id'], UPDATE);
            $resource->update($_POST);
         }
      }
   }
   Html::back();

} else if (isset($_POST["synchActiveDirectory"])) {
    $resource->getFromDB($_POST["plugin_resources_resources_id"] );

    $config          = new PluginResourcesConfig();
    $configAD        = new PluginResourcesAdconfig();
    $config->getFromDB(1);
    $configAD->getFromDB(1);
    $configAD->fields = $configAD->prepareFields($configAD->fields);
    $canedit                           = $resource->can($resource->fields['id'], UPDATE);
    $entities_id                       = $resource->fields["entities_id"];
    $plugin_resources_contracttypes_id = $resource->fields["plugin_resources_contracttypes_id"];
    $rand                              = mt_rand();
    $enddate                           = $resource->getField("date_end");

    $linkAD                            = new PluginResourcesLinkAd();
    $linkAD->getEmpty();
    $islink = $linkAD->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);

    if (!$islink) {
        $ret                     = PluginResourcesLinkAd::processLogin($resource);
        $linkAD->fields["login"] = $ret[0];
        $logAvailable            = $ret[1];

        $mail                       = PluginResourcesLinkAd::processMail($resource, $linkAD->fields["login"]);
        $linkAD->fields["mail"]     = $mail;
        $role                       = Dropdown::getDropdownName(PluginResourcesRole::getTable(), $resource->fields['plugin_resources_roles_id']);
        $linkAD->fields["role"]     = $role;
        $service                    = Dropdown::getDropdownName(PluginResourcesService::getTable(), $resource->fields['plugin_resources_services_id']);
        $linkAD->fields["service"]  = $service;
        $location                   = Dropdown::getDropdownName(Location::getTable(), $resource->fields['locations_id']);
        $linkAD->fields["location"] = $location;
    }


    $ID = $linkAD->getID();
    $value = [
        'plugin_resources_resources_id' => $resource->fields['id'],
        'plugin_resources_contracttypes_id' => $plugin_resources_contracttypes_id,
        'entities_id' => $entities_id,
        'enddate' => $enddate,
        'id' => $ID,
        'login' => $linkAD->fields["login"],
        'department' => Dropdown::getDropdownName('glpi_plugin_resources_departments', $resource->getField("plugin_resources_departments_id")),
        'glpi_plugin_resources_departments' => $resource->getField("plugin_resources_departments_id"),
        'name' => $resource->getField("name"),
        'firstname' => $resource->getField("firstname"),
        'phone' => $resource->getField('phone'),
        'mail' => $linkAD->fields["mail"],
        'contract' => Dropdown::getDropdownName('glpi_plugin_resources_contracttypes', $resource->getField("plugin_resources_contracttypes_id")),
        'glpi_plugin_resources_contracttypes' => $resource->getField("plugin_resources_contracttypes_id"),
        'cellphone' => $resource->getField("cellphone"),
        'role' => $resource->getField('plugin_resources_roles_id'),
        'service' => $resource->getField('plugin_resources_services_id'),
        'location' => $resource->getField('locations_id'),
    ];

    $linkad = new PluginResourcesLinkAd();

    if (!$islink) {
        $message = __('the user has not been updated to the LDAP directory','resources');
        Session::addMessageAfterRedirect($message, false, ERROR);
    }
    else {

        //update
        $ldap = new PluginResourcesLDAP();
        $linkad->getFromDB($value['id']);
        $value["login"] = $linkad->getField("login");
        $res = $ldap->updateUserAD($value);
        if($res[0]){

            $value["action_done"] = 1;
            $linkad->update($value);

            $message = __('the user has been updated to the LDAP directory','resources');
            Session::addMessageAfterRedirect($message, false, INFO);
        }else{
            $message = __('the user has not been updated to the LDAP directory','resources');
            Session::addMessageAfterRedirect($message, false, ERROR);
        }
    }

} else if (isset($_POST["validOrderLeaving"])) {
    $_POST["id"]       = $_POST["plugin_resources_resources_id"];
    unset($_POST["plugin_resources_resources_id"]);
    unset($_POST["date_declaration_leaving"]);
    $resource->update($_POST);

    $config = new PluginResourcesConfig();
    $config->getFromDB(1);
    if ($config->fields["create_ticket_departure_instructions"]) {
        $resource->getFromDB($_POST["id"]);
        $ticket = new Ticket();

        $tt = $ticket->getITILTemplateToUse(0, Ticket::DEMAND_TYPE, $config->fields["categories_id"]);
        if (isset($tt->predefined) && count($tt->predefined)) {
            foreach ($tt->predefined as $predeffield => $predefvalue) {
                // Load template data
                $ticket->fields[$predeffield] = Toolbox::addslashes_deep($predefvalue);
            }
        }
        $resource->getFromDB($_POST["id"]);
        $ticket->fields["name"] = Toolbox::addslashes_deep(__("Departure of", 'resources') . " " . $resource->fields['name'] . " " . $resource->fields['firstname']);
        $ticket->fields["itilcategories_id"] = $config->fields["categories_id"];

        $dateend = new DateTime($resource->fields['date_end']);
        $ticket->fields["content"] = $resource->fields['name'] . " " . $resource->fields['firstname'] . " " . __("leave on", "resources") . " " . Html::convDate($dateend->format('Y-m-d'));
        if (isset($resource->fields['plugin_resources_leavingreasons_id']) && !empty($resource->fields['plugin_resources_leavingreasons_id'])) {
            $ticket->fields["content"] .= "<br>" . PluginResourcesLeavingReason::getTypeName(0) . " : " . Dropdown::getDropdownName(PluginResourcesLeavingReason::getTable(), $resource->fields["plugin_resources_leavingreasons_id"]);
        }
        if (($resource->fields['plugin_resources_contracttypes_id']) != 0) {
            $ticket->fields["content"] .= "<br>" . PluginResourcesContractType::getTypeName(0) . " : " . Dropdown::getDropdownName(PluginResourcesContractType::getTable(), $resource->fields['plugin_resources_contracttypes_id']);
        } else {
            $ticket->fields["content"] .= "<br>" . PluginResourcesContractType::getTypeName(0) . " : " . __("Without contract", 'resources');
        }
        $ticket->fields["content"] .= "<br>" . __("Order", 'resources') . " : " . $resource->fields['remove_order'];
        $ticket->fields['users_id_recipient'] = Session::getLoginUserID();
        $ticket->fields['_users_id_requester'] = Session::getLoginUserID();
        $ticket->fields["type"] = Ticket::DEMAND_TYPE;
        $ticket->fields["entities_id"] = $_SESSION['glpiactive_entity'];
        $ticket->fields['items_id'] = ['PluginResourcesResource' => [$_POST['id']]];
        unset($ticket->fields["id"]);
        $ticket_id = $ticket->add($ticket->fields);


        //Update AD

        $authldap = new AuthLDAP();
        $auth = $authldap->find();
        if (count($auth) > 0) {
            $config = new PluginResourcesConfig();
            $configAD = new PluginResourcesAdconfig();
            $config->getFromDB(1);
            $configAD->getFromDB(1);
            $configAD->fields = $configAD->prepareFields($configAD->fields);
            $canedit = $resource->can($resource->fields['id'], UPDATE);
            $entities_id = $resource->fields["entities_id"];
            $plugin_resources_contracttypes_id = $resource->fields["plugin_resources_contracttypes_id"];
            $rand = mt_rand();
            $enddate = $resource->getField("date_end");

            $linkAD = new PluginResourcesLinkAd();
            $linkAD->getEmpty();
            $islink = $linkAD->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);

            $ID = $linkAD->getID();
            $value = [
                'plugin_resources_resources_id' => $resource->fields['id'],
                'tickets_id' => $ticket_id,
                'plugin_resources_contracttypes_id' => $plugin_resources_contracttypes_id,
                'entities_id' => $entities_id,
                'enddate' => $enddate,
                'id' => $ID,
                'login' => $linkAD->fields["login"],
                'department' => Dropdown::getDropdownName('glpi_plugin_resources_departments', $resource->getField("plugin_resources_departments_id")),
                'glpi_plugin_resources_departments' => $resource->getField("plugin_resources_departments_id"),
                'name' => $resource->getField("name"),
                'firstname' => $resource->getField("firstname"),
                'phone' => $resource->getField('phone'),
                'mail' => $linkAD->fields["mail"],
                'contract' => Dropdown::getDropdownName('glpi_plugin_resources_contracttypes', $resource->getField("plugin_resources_contracttypes_id")),
                'glpi_plugin_resources_contracttypes' => $resource->getField("plugin_resources_contracttypes_id"),
                'cellphone' => $resource->getField("cellphone"),
                'role' => $resource->getField('plugin_resources_roles_id'),
                'service' => $resource->getField('plugin_resources_services_id'),
                'location' => $resource->getField('locations_id'),
            ];

            $linkad = new PluginResourcesLinkAd();

            if (!$islink) {
                $message = __('the user has not been updated to the LDAP directory', 'resources');
                Session::addMessageAfterRedirect($message, false, ERROR);
            } else {

                //update
                $ldap = new PluginResourcesLDAP();
                $linkad->getFromDB($value['id']);
                $value["login"] = $linkad->getField("login");
                $res = $ldap->updateUserAD($value);
                if ($res[0]) {

                    $value["action_done"] = 1;
                    $linkad->update($value);
                    $fup = new ITILFollowup();

                    $toadd = ['type' => "new",
                        'items_id' => $value["ticket_id"],
                        'itemtype' => 'Ticket',
                        'is_private' => 1];

                    $content = Toolbox::addslashes_deep(sprintf(__('%1$s %2$s have been updated in the LDAP directory', 'resources'), $value["firstname"], $value["name"]));
                    $content .= __("Data changed", 'resources') . " <br />";
                    foreach ($res[1] as $key => $oldData) {
                        $i = 1;
                        $nb = count($oldData);
                        $content .= $key . " : ";
                        foreach ($oldData as $data) {
                            if ($key == "accountexpires") {
                                $time = $ldap->ldapTimeToUnixTime($data);
                                $data = date('Y-m-d', $time);
                                $data = Html::convDate($data);

                            }
                            $content .= $data;
                            if ($i < $nb) {
                                $content .= ", ";
                            }
                            $i++;
                        }
                        $content .= "<br />";

                    }
                    $toadd["content"] = htmlentities($content, ENT_NOQUOTES);

                    $fup->add($toadd);
                    $message = __('the user has been updated to the LDAP directory', 'resources');
                    Session::addMessageAfterRedirect($message, false, INFO);
                } else {
                    $message = __('the user has not been updated to the LDAP directory', 'resources');
                    Session::addMessageAfterRedirect($message, false, ERROR);
                }
            }
        }
    }

    Html::back();
} else {
   $resource->checkGlobal(READ);
   if (Session::getCurrentInterface() == 'central') {
      //from central
      Html::header(PluginResourcesResource::getTypeName(2), '', "admin", PluginResourcesMenu::getType());
   } else {
      //from helpdesk
      if (Plugin::isPluginActive('servicecatalog')) {
         PluginServicecatalogMain::showDefaultHeaderHelpdesk(PluginResourcesMenu::getTypeName(2), true);
      } else {
         Html::helpHeader(PluginResourcesResource::getTypeName(2));
      }
   }

   $resource->display(['id' => $_GET["id"], 'withtemplate' => $_GET["withtemplate"]]);

   if (Session::getCurrentInterface() != 'central'
       && Plugin::isPluginActive('servicecatalog')) {

      PluginServicecatalogMain::showNavBarFooter('resources');
   }

   if (Session::getCurrentInterface() == 'central') {
      Html::footer();
   } else {
      Html::helpFooter();
   }
}
