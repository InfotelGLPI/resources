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

if (strpos($_SERVER['PHP_SELF'], "dropdownService.php")) {
   include ('../../../inc/includes.php');
   header("Content-Type: text/html; charset=UTF-8");
   Html::header_nocache();
}
Session::checkLoginUser();

if ($_POST['plugin_resources_departments_id']>0) {
   $rand = $_POST['rand'];
   $opt = ['name'   => "plugin_resources_services_id",
           'entity' => $_SESSION['glpiactiveentities'],
           'rand' => $rand,
           'display' => false];
   $params = ['plugin_resources_services_id' => '__VALUE__',
              'rand'  => $rand,
   ];
   Ajax::updateItemOnSelectEvent("dropdown_plugin_resources_services_id$rand", "show_roles", "../ajax/dropdownRole.php", $params);
   echo PluginResourcesService::dropdownFromDepart($_POST['plugin_resources_departments_id'],$opt);


}

