<?php
/*
 *
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
include ('../../../inc/includes.php');

Session::checkRight("config", UPDATE);

if (Plugin::isPluginActive("resources")) {
   $cat = new PluginResourcesTicketCategory();
   $transferEntity = new PluginResourcesTransferEntity();
   $resourceChange = new PluginResourcesResource_Change();
   $resourceAdConfig = new PluginResourcesAdconfig();
   $resourceBadge = new PluginResourcesResourceBadge();
   $config = new PluginResourcesConfig();

   if (isset($_POST["add_ticket"])) {
      $cat->addTicketCategory($_POST['ticketcategories_id']);
      Html::back();

   } else if (isset($_POST["delete_ticket"])) {
      if (isset($_POST['id'])) {
         $cat->delete(['id' => $_POST['id']]);
      }
      Html::back();

   } else if (isset($_POST["add_transferentity"])) {
      $transferEntity->check(-1, UPDATE, $_POST);
      $transferEntity->add($_POST);
      Html::back();

   } else if (isset($_POST["update_setup"])) {
      $config->check(-1, UPDATE, $_POST);
      $config->update($_POST);
      Html::back();

   } else {
      Html::header(PluginResourcesResource::getTypeName(2), '', "admin", PluginResourcesMenu::getType());
      //setup
      $config->showConfigForm();

      //changes
      $resourceChange->showConfigForm();

      $resourceAdConfig->showConfigForm();

      //badges
      if (Plugin::isPluginActive("badges") && Plugin::isPluginActive("metademands")) {
         $resourceBadge->showConfigForm();
      }

      //metademand
      if (Plugin::isPluginActive("metademands")) {
         $configHabilitation = new PluginResourcesConfigHabilitation();
         $configHabilitation->showConfigForm();
      }

      $cat->showConfigForm($_SERVER['PHP_SELF']);
      $transferEntity->showConfigForm($_SERVER['PHP_SELF']);
   }

} else {
   Html::header(__('Setup'), '', "config", "plugins");
   echo "<div class='alert alert-important alert-warning d-flex'>";
   echo "<b>".__('Please activate the plugin', 'resources')."</b></div>";
}

Html::footer();
