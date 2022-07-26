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

include ('../../../inc/includes.php');

if (Session::getCurrentInterface() == 'central') {
   Html::header(PluginResourcesResource::getTypeName(2), '', "admin", PluginResourcesMenu::getType());
} else {
   if (Plugin::isPluginActive('servicecatalog')) {
      PluginServicecatalogMain::showDefaultHeaderHelpdesk(PluginResourcesMenu::getTypeName(2));
   } else {
      Html::helpHeader(PluginResourcesResource::getTypeName(2));
   }
}


$resource = new PluginResourcesResource();
$resource_change = new PluginResourcesResource_Change();

if (isset($_POST["change_action"]) && $_POST["change_action"] != 0 && $_POST["plugin_resources_resources_id"] != 0) {

   if ($_POST["change_action"] == PluginResourcesResource_Change::CHANGE_TRANSFER && isset($_POST['plugin_resources_resources_id'])) {
      Html::redirect(PLUGIN_RESOURCES_WEBDIR. "/front/resource.transfer.php?plugin_resources_resources_id=" . $_POST['plugin_resources_resources_id']);
   } else {
      $resource_change->startingChange($_POST['plugin_resources_resources_id'], $_POST["change_action"], $_POST);
      Html::back();
   }

} else if (isset($_POST["change_action"]) && $_POST["change_action"] == 0 && $_POST["plugin_resources_resources_id"] == 0) {

   if ($resource->canView() || Session::haveRight("config", UPDATE)) {
      //show remove resource form
      $resource->showResourcesToChange($_POST);
   }

} else {
   if ($resource->canView() || Session::haveRight("config", UPDATE)) {
      //show remove resource form
      $resource->showResourcesToChange($_POST);
   }
}

if (Session::getCurrentInterface() != 'central'
    && Plugin::isPluginActive('servicecatalog')) {

   PluginServicecatalogMain::showNavBarFooter('resources');
}

if (Session::getCurrentInterface() == 'central') {
   Html::footer();
} else {
   Html::helpFooter();
}
