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
$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 0;

include ("../../../../inc/includes.php");

//"Rapport listant les ressources sans utilisateurs";
//"Report listing resource without user";
// Instantiate Report with Name
$report = new PluginReportsAutoReport(__("userwithmorethanoneresource_report_title", "resources"));

// Columns title (optional)
$report->setColumns( [new PluginReportsColumnLink('items_id', __('User'), 'User',
                                                  ['sorton' => 'items_id']),]);


//display only resource without user linked
$query = "SELECT `glpi_plugin_resources_resources_items`.`items_id` as items_id
          FROM `glpi_plugin_resources_resources_items`
           LEFT JOIN `glpi_plugin_resources_resources`
               ON (`glpi_plugin_resources_resources`.`id` = `glpi_plugin_resources_resources_items`.`plugin_resources_resources_id`)
          LEFT JOIN `glpi_users`
               ON (`glpi_users`.`id` = `glpi_plugin_resources_resources_items`.`items_id`)
          WHERE `glpi_plugin_resources_resources_items`.`itemtype`= 'User' 
            AND `glpi_users`.`is_active` = 1 
            AND `glpi_users`.`is_deleted` = 0
          AND `glpi_plugin_resources_resources`.`is_deleted` = 0
          GROUP BY items_id HAVING COUNT(items_id) > 1 ";


$report->setSqlRequest($query);
$report->execute();
