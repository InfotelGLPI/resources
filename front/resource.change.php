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

use GlpiPlugin\Resources\Resource_Change;
use GlpiPlugin\Servicecatalog\Main;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

if (Session::getCurrentInterface() == 'central') {
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class);
} else {
    if (Plugin::isPluginActive('servicecatalog')) {
        Main::showDefaultHeaderHelpdesk(Menu::getTypeName(2));
    } else {
        Html::helpHeader(Resource::getTypeName(2));
    }
}

$resource = new Resource();
$resource_change = new Resource_Change();

if (isset($_POST["change_action"]) && $_POST["change_action"] != 0 && $_POST["plugin_resources_resources_id"] != 0) {
    if ($_POST["change_action"] == Resource_Change::CHANGE_TRANSFER && isset($_POST['plugin_resources_resources_id'])) {
        Html::redirect(
            PLUGIN_RESOURCES_WEBDIR . "/front/resource.transfer.php?plugin_resources_resources_id=" . $_POST['plugin_resources_resources_id']
        );
    } else {
        $resource_change->startingChange($_POST['plugin_resources_resources_id'], $_POST["change_action"], $_POST);
        Html::back();
    }
} elseif (isset($_POST["change_action"]) && $_POST["change_action"] == 0 && $_POST["plugin_resources_resources_id"] == 0) {
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
    Main::showNavBarFooter('resources');
}

if (Session::getCurrentInterface() == 'central') {
    Html::footer();
} else {
    Html::helpFooter();
}
