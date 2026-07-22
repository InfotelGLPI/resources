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

 global $DB;

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

use GlpiPlugin\Resources\Adconfig;
use GlpiPlugin\Resources\ContractNature;
use GlpiPlugin\Resources\Department;
use GlpiPlugin\Resources\LDAP;
use GlpiPlugin\Resources\LinkAd;
use GlpiPlugin\Resources\Rank;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\ResourceFunction;
use GlpiPlugin\Resources\ResourceSituation;
use GlpiPlugin\Resources\ResourceSpeciality;
use GlpiPlugin\Resources\Role;
use GlpiPlugin\Resources\Service;
use GlpiPlugin\Resources\Team;
use GlpiPlugin\Resources\Config;

Session::checkRight('plugin_resources', READ);

$resource_id = (int)($_REQUEST["plugin_resources_resources_id"] ?? 0);
$resource = new Resource();
$resource->check($resource_id, UPDATE);
$resource->update(['id' => $resource_id, 'valid_resource_information' => 1]);
$resource->getFromDB($resource_id);

$config = new Config();
$config->getFromDB(1);

$ticket = new Ticket();

$tt = $ticket->getITILTemplateToUse(0, Ticket::DEMAND_TYPE, $config->fields["categories_id"]);
if (isset($tt->predefined) && count($tt->predefined)) {
    foreach ($tt->predefined as $predeffield => $predefvalue) {
        // Load template data
        $ticket->fields[$predeffield] = $DB->escape($predefvalue);
    }
}

$ticket->fields["name"] =$DB->escape( __("Arrival of",'resources')." ".$resource->fields['name']." ".$resource->fields['firstname']);
$ticket->fields["itilcategories_id"] = $config->fields["categories_id"];

$content = str_replace(PHP_EOL,'<br>', $config->fields['text_ticket_validation']);
foreach (Config::getAvailablevariable() as $key => $value) {
    switch ($key) {
        case '##resource_gender##':
            $content = str_replace($key, Resource::getGenderByValue($resource->fields['gender']), $content);
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
            $content = str_replace($key, Department::getFriendlyNameById($resource->fields['plugin_resources_departments_id']), $content);
            break;
        case '##resource_plugin_resources_services_id##' :
            $content = str_replace($key, Service::getFriendlyNameById($resource->fields['plugin_resources_services_id']),$content);
            break;
        case '##resource_plugin_resources_functions_id##' :
            $content = str_replace($key, ResourceFunction::getFriendlyNameById($resource->fields['plugin_resources_functions_id']),$content);
            break;
        case '##resource_plugin_resources_teams_id##' :
            $content = str_replace($key, Team::getFriendlyNameById($resource->fields['plugin_resources_teams_id']),$content);
            break;
        case '##resource_date_begin##' :
        case '##resource_date_end##' :
            $field = str_replace("##resource_", "", $key);
            $field = str_replace("##", "", $field);
            $date = new DateTime($resource->fields[$field]);
            $content = str_replace($key, $date->format('Y-m-d'), $content);
            break;
        case '##resource_plugin_resources_resourcesituations_id##' :
            $content = str_replace($key, ResourceSituation::getFriendlyNameById($resource->fields['plugin_resources_resourcesituations_id']),$content);
            break;
        case '##resource_plugin_resources_contractnatures_id##' :
            $content = str_replace($key, ContractNature::getFriendlyNameById($resource->fields['plugin_resources_contractnatures_id']),$content);
            break;
        case '##resource_plugin_resources_ranks_id##' :
            $content = str_replace($key, Rank::getFriendlyNameById($resource->fields['plugin_resources_ranks_id']),$content);
            break;
        case '##resource_plugin_resources_resourcespecialities_id##' :
            $content = str_replace($key, ResourceSpeciality::getFriendlyNameById($resource->fields['plugin_resources_resourcespecialities_id']),$content);
            break;
        case '##resource_plugin_resources_roles_id##' :
            $content = str_replace($key, Role::getFriendlyNameById($resource->fields['plugin_resources_roles_id']),$content);
            break;
        default:
            $field = str_replace("##resource_", "", $key);
            $field = str_replace("##", "", $field);
            $content = str_replace($key, $resource->fields[$field], $content);
            break;
    }
}

if (substr_count($content, 'r<br>') > 1) {
    $content = str_replace('r<br>', '<br>', $content);
}
$ticket->fields["content"] = addslashes($content);

$ticket->fields['users_id_recipient']  = Session::getLoginUserID();
$ticket->fields['_users_id_requester'] = Session::getLoginUserID();
$ticket->fields["type"] = Ticket::DEMAND_TYPE;
$ticket->fields["entities_id"] = $_SESSION['glpiactive_entity'];
$ticket->fields['items_id'] = [Resource::class => [$resource->fields['id']]];
unset($ticket->fields["id"]);
$ticket_id = $ticket->add($ticket->fields);

if ($config->fields['use_module_duplicata_ticket'] && $config->fields['use_module_validation'] && $config->fields["send_second_ticket_validation"] && $config->fields["assignment_group_second_ticket"]) {
    $ticket->fields['users_id_recipient']  = Session::getLoginUserID();
    $ticket->fields['_users_id_requester'] = Session::getLoginUserID();
    $ticket->fields["type"] = Ticket::DEMAND_TYPE;
    $ticket->fields["entities_id"] = $_SESSION['glpiactive_entity'];
    $ticket->fields['items_id'] = [Resource::class => [$resource->fields['id']]];
    unset($ticket->fields["id"]);
    $ticket_id = $ticket->add($ticket->fields);
    $groupticket = new Group_Ticket();
    $groupticket->fields['tickets_id'] = $ticket_id;
    $groupticket->fields['groups_id'] = $config->fields["assignment_group_second_ticket"];
    $groupticket->fields['type'] = CommonITILActor::ASSIGN;
    unset($groupticket->fields["id"]);
    $groupticket->add($groupticket->fields);
}