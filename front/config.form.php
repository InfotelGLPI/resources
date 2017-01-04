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

Session::checkRight("config", UPDATE);
$plugin = new Plugin();
if ($plugin->isActivated("resources")) {
   $cat = new PluginResourcesTicketCategory();
   $transferEntity = new PluginResourcesTransferEntity();
   $resourceChange = new PluginResourcesResource_Change();
   $resourceBadge = new PluginResourcesResourceBadge();

   if (isset($_POST["add_ticket"])) {
      $cat->addTicketCategory($_POST['ticketcategories_id']);
      Html::back();
      
   } else if (isset($_POST["delete_ticket"])) {
      foreach ($_POST["item"] as $key => $val) {
         if ($val == 1) {
            $cat->delete(array('id' => $key));
         }
      }
      Html::back();
      
   } else if (isset($_POST["add_transferentity"])) {
      $transferEntity->check(-1, UPDATE, $_POST);
      $transferEntity->add($_POST);
      Html::back();
      
   } else {
      Html::header(PluginResourcesResource::getTypeName(2), '', "admin", "pluginresourcesresource");
      $resourceChange->showForm($_SERVER['PHP_SELF']);
      $plugin = new Plugin();
      if ($plugin->isActivated("badges") && $plugin->isActivated("metademands")) {
         $resourceBadge->showFormConfig($_SERVER['PHP_SELF']);
      }

      $cat->showForm($_SERVER['PHP_SELF']);
      $transferEntity->showForm($_SERVER['PHP_SELF']);
   }
   
} else {
   Html::header(__('Setup'), '', "config", "plugins");
   echo "<div align='center'>";
   echo "<br><br><img src=\"".$CFG_GLPI["root_doc"]."/pics/warning.png\" alt=\"warning\"><br><br>";
   echo "<b>".__('Please activate the plugin', 'resources')."</b></div>";
}

Html::footer();
?>