<?php
/**
-------------------------------------------------------------------------
Resources plugin for GLPI
Copyright (C) 2009-2026 by the Resources Development Team.

-------------------------------------------------------------------------

LICENSE

This file is part of Resources.

Resources is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

Resources is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Resources. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

$_REQUEST["id"]       = $_REQUEST["plugin_resources_resources_id"];
$_REQUEST["valid_resource_information"]       = 1;
unset($_REQUEST["plugin_resources_resources_id"]);
$resource = new PluginResourcesResource();
$resource->update($_REQUEST);
$resource->getFromDB($_REQUEST["id"]);

$config = new PluginResourcesConfig();
$config->getFromDB(1);

$ticket = new Ticket();

$tt = $ticket->getITILTemplateToUse(0, Ticket::DEMAND_TYPE, $config->fields["categories_id"]);
if (isset($tt->predefined) && count($tt->predefined)) {
    foreach ($tt->predefined as $predeffield => $predefvalue) {
        // Load template data
        $ticket->fields[$predeffield] = Toolbox::addslashes_deep($predefvalue);
    }
}

$ticket->fields["name"] =Toolbox::addslashes_deep( __("Arrival of",'resources')." ".$resource->fields['name']." ".$resource->fields['firstname']);
$ticket->fields["itilcategories_id"] = $config->fields["categories_id"];

$content = str_replace(PHP_EOL,'<br>', $config->fields['text_ticket_validation']);
foreach (PluginResourcesConfig::getAvailablevariable() as $key => $value) {
    switch ($key) {
        case '##resource_gender##':
            $content = str_replace($key, PluginResourcesResource::getGenderByValue($resource->fields['gender']), $content);
            break;
        case '##resource_locations_id##' :
            $content = str_replace($key, Location::getFriendlyNameById($resource->fields['locations_id']), $content);
            break;
        case '##resource_users_id##' :
        case '##resource_users_id_sales##' :
            $field = str_replace("##resource_", "", $key);
            $field = str_replace("##", "", $field);
            $content = str_replace($key, User::getFriendlyNameById($resource->fields[$field]), $content);
            break;
        case '##resource_plugin_resources_departments_id##' :
            $content = str_replace($key, PluginResourcesDepartment::getFriendlyNameById($resource->fields['plugin_resources_departments_id']), $content);
            break;
        case '##resource_plugin_resources_services_id##' :
            $content = str_replace($key, PluginResourcesService::getFriendlyNameById($resource->fields['plugin_resources_services_id']),$content);
            break;
        case '##resource_plugin_resources_functions_id##' :
            $content = str_replace($key, PluginResourcesFunction::getFriendlyNameById($resource->fields['plugin_resources_functions_id']),$content);
            break;
        case '##resource_plugin_resources_teams_id##' :
            $content = str_replace($key, PluginResourcesTeam::getFriendlyNameById($resource->fields['plugin_resources_teams_id']),$content);
            break;
        case '##resource_date_begin##' :
        case '##resource_date_end##' :
            $field = str_replace("##resource_", "", $key);
            $field = str_replace("##", "", $field);
            $date = new DateTime($resource->fields[$field]);
            $content = str_replace($key, $date->format('Y-m-d'), $content);
            break;
        case '##resource_plugin_resources_resourcesituations_id##' :
            $content = str_replace($key, PluginResourcesResourceSituation::getFriendlyNameById($resource->fields['plugin_resources_resourcesituations_id']),$content);
            break;
        case '##resource_plugin_resources_contractnatures_id##' :
            $content = str_replace($key, PluginResourcesContractNature::getFriendlyNameById($resource->fields['plugin_resources_contractnatures_id']),$content);
            break;
        case '##resource_plugin_resources_ranks_id##' :
            $content = str_replace($key, PluginResourcesRank::getFriendlyNameById($resource->fields['plugin_resources_ranks_id']),$content);
            break;
        case '##resource_plugin_resources_resourcespecialities_id##' :
            $content = str_replace($key, PluginResourcesResourceSpeciality::getFriendlyNameById($resource->fields['plugin_resources_resourcespecialities_id']),$content);
            break;
        case '##resource_plugin_resources_roles_id##' :
            $content = str_replace($key, PluginResourcesRole::getFriendlyNameById($resource->fields['plugin_resources_roles_id']),$content);
            break;
        default:
            $field = str_replace("##resource_", "", $key);
            $field = str_replace("##", "", $field);
            $content = str_replace($key, $resource->fields[$field], $content);
            break;
    }
}

$ticket->fields["content"] = $content;

$ticket->fields['users_id_recipient']  = Session::getLoginUserID();
$ticket->fields['_users_id_requester'] = Session::getLoginUserID();
$ticket->fields["type"] = Ticket::DEMAND_TYPE;
$ticket->fields["entities_id"] = $_SESSION['glpiactive_entity'];
$ticket->fields['items_id'] = ['PluginResourcesResource' => [$resource->fields['id']]];
unset($ticket->fields["id"]);
$ticket_id = $ticket->add($ticket->fields);


$authldap = new AuthLDAP();
$auth = $authldap->find();
if (count($auth) > 0) {
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

        //Création
        $ldap = new PluginResourcesLDAP();
        $res = $ldap->createUserAD($value);
        if($res){
            $value["action_done"] = 1;
            $linkad->add($value);
            $fup = new ITILFollowup();

            $toadd = ['type'       => "new",
                'items_id' => $value["ticket_id"],
                'itemtype' => 'Ticket',
                'is_private' => 1];


            $content = Toolbox::addslashes_deep(sprintf(__('%1$s %2$s have been added in the LDAP directory','resources'),$value["firstname"],$value["name"]));
            $toadd["content"] = htmlentities($content,ENT_NOQUOTES);

            $fup->add($toadd);
            $message = __('the user has been added to the LDAP directory','resources');
            Session::addMessageAfterRedirect($message, false, INFO);
        }else{
            $message = __('the user has not been added to the LDAP directory','resources');
            Session::addMessageAfterRedirect($message, false, ERROR);
        }
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
            $fup = new ITILFollowup();

            $toadd = ['type'       => "new",
                'items_id' => $value["ticket_id"],
                'itemtype' => 'Ticket',
                'is_private' => 1];

            $content = Toolbox::addslashes_deep(sprintf(__('%1$s %2$s have been updated in the LDAP directory','resources'),$value["firstname"],$value["name"]));
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
    }
}
