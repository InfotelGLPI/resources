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

//central or helpdesk access
if (Session::getCurrentInterface() == 'central') {
   Html::header(PluginResourcesMenu::getTypeName(2), '', "admin", PluginResourcesMenu::getType());
} else {
   if (Plugin::isPluginActive('servicecatalog')) {
      PluginServicecatalogMain::showDefaultHeaderHelpdesk(PluginResourcesMenu::getTypeName(2), false, "PluginResourcesResource");
   } else {
      Html::helpHeader(PluginResourcesMenu::getTypeName(2));
   }
}

$resource = new PluginResourcesResource();

if ($resource->canView() || Session::haveRight("config", UPDATE)) {
   if (Session::haveRight("plugin_resources_all", READ)) {

      global $CFG_GLPI;

      //Have right to see all resources
      //Have not right to see all resources
      echo "<div align='center'>";

      echo "<a href='#' data-bs-toggle='modal' data-bs-target='#seetypemodal' class='submit btn btn-primary' title='" . __('View by contract type', 'resources') . "' >";
      echo __('View by contract type', 'resources');
      echo "</a>";
      echo "</div><br>";
      echo Ajax::createIframeModalWindow('seetypemodal',
                                         PLUGIN_RESOURCES_WEBDIR. "/ajax/resourcetree.php",
                                         ['title'   => __('View by contract type', 'resources'),
                                          'display'       => false,
                                          'width'         => 600,
                                          'height'        => 500]);

   }

   Search::show(PluginResourcesResource::class);

} else {
   Html::displayRightError();
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
