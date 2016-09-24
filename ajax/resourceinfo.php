<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2016 by the resources Development Team.

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

include ('../../../inc/includes.php');
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

switch($_POST['action']){
   case 'resourceInfo':
      if (isset($_POST["plugin_resources_resources_id"])) {
         $resource = new PluginResourcesResource();
         if ($resource->getFromDB($_POST["plugin_resources_resources_id"])) {
            $resource_item = new PluginResourcesResource_Item();
            $linked        = $resource_item->find("`plugin_resources_resources_id` = ".$_POST["plugin_resources_resources_id"]);

            echo "<br><br>";
            echo "<table class='tab_cadre' style='text-align:left; margin:0px'>";
            echo "<tr class='tab_bg_1'>";
            echo "<th>".__('Current entity', 'resources')."</th>";
            echo "</tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo Dropdown::getDropdownName('glpi_entities', $resource->fields['entities_id']);
            echo "</td>";
            echo "</tr>";
            echo "</table>";
            
            if (!$linked) {
               echo "<br>";
               echo "<table class='tab_cadre' style='text-align:left; margin:0px'>";
               echo "<tr class='tab_bg_1'>";
               echo "<th>".__('Associate a user', 'resources')."</th>";
               echo "</tr>";
               echo "<tr class='tab_bg_1'>";
               echo "<td>";
               echo __('User')." ";
               User::dropdown(array('entity' => $_SESSION['glpiactiveentities']));
               echo "</td>";
               echo "</tr>";
               echo "</table>";
            }
            
         } else {
            echo "<br><br>";        
            Html::displayTitle($CFG_GLPI['root_doc']."/pics/warning.png", __('Resource does not exist', 'resources'), __('Resource does not exist', 'resources'));
            echo "<br>";        
            echo "<table class='tab_cadre' style='text-align:left; margin:0px'>";
            echo "<tr class='tab_bg_1'>";
            echo "<th>".__('Associate a resource', 'resources')."</th>";
            echo "</tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo PluginResourcesResource::getTypeName()." ";
            PluginResourcesResource::dropdown(array('name' => 'link_resources_id', 'entity' => $_SESSION['glpiactiveentities']));
            echo "</td>";
            echo "</tr>";
            echo "</table>";
            
            echo "<br>".__('Or', 'resources')."<br><br>";
            
            echo "<table class='tab_cadre' style='text-align:left; margin:0px'>";
            echo "<tr class='tab_bg_1'>";
            echo "<th width='80px'>".__('Generate a resource', 'resources')."</th>";
            echo "</tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            PluginResourcesResource::fastResourceAddForm();
            echo "</td>";
            echo "</tr>";
            echo "</table>";
         }
      }
      break;
   case 'groupEntity':
      if (isset($_POST["entities_id"])) {
         echo __('Group')."&nbsp;";
         Dropdown::show('Group', array('entity' => $_POST["entities_id"], 'entity_sons' => true));
      }
      break;
}

Html::ajaxFooter();

?>