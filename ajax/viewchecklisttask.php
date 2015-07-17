<?php

/*
 * @version $Id: viewchecklisttask.php 480 2012-11-09 tsmr $
  -------------------------------------------------------------------------
  Resources plugin for GLPI
  Copyright (C) 2006-2012 by the Resources Development Team.

  https://forge.indepnet.net/projects/resources
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

include ('../../../inc/includes.php');
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

$item = new PluginResourcesChecklist();

if (isset($_POST["plugin_resources_contracttypes_id"]) && isset($_POST["checklist_type"])) {
   $options = array('id'                                => $_POST["id"],
                    'target'                            => $_POST["target"],
                    'plugin_resources_contracttypes_id' => $_POST["plugin_resources_contracttypes_id"],
                    'checklist_type'                    => $_POST["checklist_type"], 
                    'plugin_resources_resources_id'     => $_POST["plugin_resources_resources_id"]);
   
   echo "<table class='tab_cadre'>";
   echo "<tr class='tab_bg_1'>";
   echo "<td>";
   $item->showForm($_POST["id"], $options);
   echo "</td>";
   echo "</tr>";
   echo "</table>";
   
} else {
   _e("You don't have permission to perform this action.");
}

Html::ajaxFooter();
?>