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

use GlpiPlugin\Resources\ResourceHoliday;
use GlpiPlugin\Servicecatalog\Main;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

if (Session::getCurrentInterface() == 'central') {
    //from central
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class);
} else {
    //from helpdesk
    if (Plugin::isPluginActive('servicecatalog')) {
        Main::showDefaultHeaderHelpdesk(Menu::getTypeName(2));
    } else {
        Html::helpHeader(Resource::getTypeName(2));
    }
}

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}

$holiday = new ResourceHoliday();

if (isset($_POST["addholidayresources"]) && $_POST["plugin_resources_resources_id"] != 0) {
    $holiday->add($_POST);
    Html::back();
} elseif (isset($_POST["updateholidayresources"]) && $_POST["plugin_resources_resources_id"] != 0) {
    $holiday->update($_POST);
    Html::back();
} elseif (isset($_POST["deleteholidayresources"]) && $_POST["plugin_resources_resources_id"] != 0) {
    $holiday->delete($_POST, 1);
    $holiday->redirectToList();
} elseif (isset($_GET['menu'])) {
    if ($holiday->canView() || Session::haveRight("config", UPDATE)) {
        $holiday->showMenu();
    }
} else {
    if ($holiday->canView() || Session::haveRight("config", UPDATE)) {
        $holiday->display($_GET);
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
