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

//Options for GLPI 0.71 and newer : need slave db to access the report
$USEDBREPLICATE        = 1;
$DBCONNECTION_REQUIRED = 0;

include("../../../../inc/includes.php");

//"Rapport listant les utilisateurs sans ressources";
//"Report listing user without resource";
// Instantiate Report with Name
$report = new PluginReportsAutoReport(__("User without resource", "resources"));

// Columns title (optional)
$report->setColumns(
   [new PluginReportsColumnLink('user_id', _n('User', 'Users', 1), 'User',
                                ['sorton' => 'user_name']),
    new PluginReportsColumn('realname', __('Surname'),
                            ['sorton' => 'realname']),
    new PluginReportsColumn('firstname', __('First name'),
                            ['sorton' => 'firstname']),
    new PluginReportsColumn('location', __('Location'),
                            ['sorton' => 'location'])]);

// SQL statement
//$dbu               = new DbUtils();
//$entities_user     = $dbu->getEntitiesRestrictRequest(' AND ', "glpi_users", '', '', false);
//$entities_resource = $dbu->getEntitiesRestrictRequest(' AND ', "glpi_plugin_resources_resources", '', '', false);
//

//display only resource without user linked
$query = "SELECT  `glpi_users`.`id` as user_id,
                  `glpi_users`.`name` as user_name,
                  `glpi_users`.`realname` as realname,
                  `glpi_users`.`firstname` as firstname,
                `glpi_locations`.`completename` as location
          FROM `glpi_users`
            LEFT JOIN `glpi_locations`
                ON (`glpi_locations`.`id` = `glpi_users`.`locations_id` )
          WHERE (`glpi_users`.`id` NOT IN (SELECT `glpi_plugin_resources_resources_items`.`items_id`
               FROM `glpi_plugin_resources_resources_items`
               WHERE `glpi_plugin_resources_resources_items`.`itemtype`= 'User')
                OR `glpi_users`.`id` IN (SELECT `glpi_plugin_resources_resources_items`.`items_id`
                FROM `glpi_plugin_resources_resources_items` 
                LEFT JOIN `glpi_plugin_resources_resources`
                ON (`glpi_plugin_resources_resources`.`id` = `glpi_plugin_resources_resources_items`.`plugin_resources_resources_id`)
                WHERE `glpi_plugin_resources_resources_items`.`itemtype`= 'User' 
                    AND `glpi_plugin_resources_resources`.`is_leaving` = 0 
                    AND `glpi_plugin_resources_resources`.`is_deleted` = 0
                    AND  `glpi_plugin_resources_resources`.`date_begin` IS NULL
                    ))
          AND `glpi_users`.`is_deleted` = 0
          AND `glpi_users`.`authtype`   = 3
          AND `glpi_users`.`is_active`  = 1";

$report->getOrderBy('user_id');


$report->setSqlRequest($query);
$report->execute();
