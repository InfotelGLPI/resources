<?php

/*
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2015-2026 by the resources Development Team.

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

global $CFG_GLPI, $DB;

use GlpiPlugin\Resources\Adconfig;
use GlpiPlugin\Resources\Checklist;
use GlpiPlugin\Resources\Checklistconfig;
use GlpiPlugin\Resources\Config;
use GlpiPlugin\Resources\ContractType;
use GlpiPlugin\Resources\Employment;
use GlpiPlugin\Resources\LeavingInformation;
use GlpiPlugin\Resources\LeavingReason;
use GlpiPlugin\Resources\LinkAd;
use GlpiPlugin\Servicecatalog\Main;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

if (Session::getCurrentInterface() == 'central') {
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class);
} else {
    if (Plugin::isPluginActive('servicecatalog')) {
        Main::showDefaultHeaderHelpdesk(Menu::getTypeName(2));
    } else {
        Html::helpHeader(Resource::getTypeName(2));
    }
}

if (empty($_POST["date_end"])) {
    if (isset($_POST["effective_leaving_date"]) && !empty($_POST["effective_leaving_date"])) {
        $_POST["date_end"] = $_POST["effective_leaving_date"];
    } else if (isset($_POST["resignation_date"]) && !empty($_POST["resignation_date"])) {
        $_POST["date_end"] = $_POST["resignation_date"];
    } else {
        $_POST["date_end"] = date("Y-m-d");
    }
}

$resource = new Resource();
$checklistconfig = new Checklistconfig();

if (isset($_POST["removeresources"]) && $_POST["plugin_resources_resources_id"] != 0) {
    if (!isset($_POST["plugin_resources_leavingreasons_id"])) {
        $_POST["plugin_resources_leavingreasons_id"] = 0;
    }
    $config = new Config();
    $date = date("Y-m-d H:i:s");
    $CronTask = new CronTask();
    $CronTask->getFromDBbyName(Employment::class, "ResourcesLeaving");

    $input["id"] = $_POST["plugin_resources_resources_id"];
    $input["date_end"] = $_POST["date_end"];
    if (!$config->fields['remove_at_midnight']) {
        $input["date_end"] .=" 23:59:59";
    }
    $input["remove_manager"] = $_POST["remove_manager"] ?? 0;
    if (($_POST["date_end"] < $date)
        || ($CronTask->fields["state"] == CronTask::STATE_DISABLE)) {
        $input["is_leaving"] = "1";
        $input["date_declaration_leaving"] = date('Y-m-d H:i:s');
    } else {
        $input["is_leaving"] = "0";
        $input["date_declaration_leaving"] = null;
    }
    $input["plugin_resources_leavingreasons_id"] = $_POST["plugin_resources_leavingreasons_id"];
    $input["withtemplate"] = "0";
    $input["users_id_recipient_leaving"] = Session::getLoginUserID();
    $input['send_notification'] = 1;
    $resource->update($input);
    $leavingInformation = new LeavingInformation();

    $inputleaving = $_POST;
    if ($leavingInformation->getFromDBByCrit(['plugin_resources_resources_id' => $input['id']])) {
        $inputleaving['id'] = $leavingInformation->getID();
        $leavingInformation->update($inputleaving);
    } else {
        $leavingInformation->add($inputleaving);
    }


    //test it
    $resource->getFromDB($_POST["plugin_resources_resources_id"]);
    $resources_checklist = Checklist::checkIfChecklistExist(
        $_POST["plugin_resources_resources_id"],
        Checklist::RESOURCES_CHECKLIST_OUT
    );
    if (!$resources_checklist) {
        $checklistconfig->addChecklistsFromRules($resource, Checklist::RESOURCES_CHECKLIST_OUT);
    }
    $config = new Config();
    $config->getFromDB(1);
    Session::addMessageAfterRedirect(__('Declaration of resource leaving OK', 'resources'));

    if ($config->fields["create_ticket_departure"]) {
        $ticket = new Ticket();

        $tt = $ticket->getITILTemplateToUse(0, Ticket::DEMAND_TYPE, $config->fields["categories_id"]);
        if (isset($tt->predefined) && count($tt->predefined)) {
            foreach ($tt->predefined as $predeffield => $predefvalue) {
                // Load template data
                $ticket->fields[$predeffield] = $predefvalue;
            }
        }
        $resource->getFromDB($input["id"]);
        $ticket->fields["name"] = __(
                "Departure of",
                'resources'
            ) . " " . $resource->fields['name'] . " " . $resource->fields['firstname'];
        $ticket->fields["itilcategories_id"] = $config->fields["categories_id"];

        $ticket->fields["content"] = $resource->fields['name'] . " " . $resource->fields['firstname'] . " " . __(
                "leave on",
                "resources"
            ) . " " . Html::convDate($input["date_end"]);
        if (isset($input['plugin_resources_leavingreasons_id']) && !empty($input['plugin_resources_leavingreasons_id'])) {
            $ticket->fields["content"] .= "<br>" . LeavingReason::getTypeName(0) . " : " . Dropdown::getDropdownName(
                    LeavingReason::getTable(),
                    $input["plugin_resources_leavingreasons_id"]
                );
        }
        if (($resource->fields['plugin_resources_contracttypes_id']) != 0) {
            $ticket->fields["content"] .= "<br>" . ContractType::getTypeName(0) . " : " . Dropdown::getDropdownName(
                    ContractType::getTable(),
                    $resource->fields['plugin_resources_contracttypes_id']
                );
        } else {
            $ticket->fields["content"] .= "<br>" . ContractType::getTypeName(0) . " : " . __(
                    "Without contract",
                    'resources'
                );
        }
        $ticket->fields['users_id_recipient'] = Session::getLoginUserID();
        $ticket->fields['_users_id_requester'] = Session::getLoginUserID();
        $ticket->fields["type"] = Ticket::DEMAND_TYPE;
        $ticket->fields["entities_id"] = $_SESSION['glpiactive_entity'];
        $ticket->fields['items_id'] = [Resource::class => [$input['id']]];
        unset($ticket->fields["id"]);
        $ticket->add($ticket->fields);



        $linkad = new LinkAd();
        if ($linkad->getFromDBByCrit(["plugin_resources_resources_id" => $input['id']])) {
            $input2 = [];
            $input2['action_done'] = 0;
            $input2['id'] = $linkad->getID();
            $linkad->update($input2);
        }
    }

    if ($config->fields["create_ticket_departure_instructions"]) {
        if (isset($resource->input['send_notification'])
            && $resource->input['send_notification'] == 1
        ) {
            if ($CFG_GLPI["notifications_mailing"]) {
                $resource->playnotification($resource);
            }
        }
    }

    if ((new Adconfig())->fields['auth_id'] > 0) {
        //Update AD
        $authLDAP = new AuthLDAP();
        $auth = $authLDAP->find();
        if (count($auth)) {
            $config = new Config();
            $configAD = new Adconfig();
            $config->getFromDB(1);
            $configAD->getFromDB(1);
            $configAD->fields = $configAD->prepareFields($configAD->fields);
            $canedit = $resource->can($resource->fields['id'], UPDATE);
            $entities_id = $resource->fields["entities_id"];
            $plugin_resources_contracttypes_id = $resource->fields["plugin_resources_contracttypes_id"];
            $rand = mt_rand();
            $enddate = $resource->getField("date_end");

            $linkAD = new LinkAd();
            $linkAD->getEmpty();
            $islink = $linkAD->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);

            $ID = $linkAD->getID();
            $value = [
                'enddate' => $enddate,
                'id' => $ID,
                'login' => $linkAD->fields["login"],

            ];

            $linkad = new LinkAd();

            if (!$islink) {
                $message = __('the user has not been updated to the LDAP directory', 'resources');
                Session::addMessageAfterRedirect($message, false, ERROR);
            } else {

                //update
                $ldap = new LDAP();
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

                    $content = $DB->escape(sprintf(__('%1$s %2$s have been updated in the LDAP directory', 'resources'), $value["firstname"], $value["name"]));
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

//                    $fup->add($toadd);
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
    if ($resource->canView() || Session::haveRight("config", UPDATE)) {
        //show remove resource form
        $resource->showResourcesToRemove();
    }
}

if (Session::getCurrentInterface() != 'central'
    && Plugin::isPluginActive('servicecatalog')) {
    Main::showNavBarFooter('resources');
}

if (Session::getCurrentInterface() == 'central') {
    Html::footer();
} else {
    Html::helpFooter();
}
