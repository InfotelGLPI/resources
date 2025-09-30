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

use GlpiPlugin\Resources\Employee;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Servicecatalog\Main;

//from helpdesk
if (Plugin::isPluginActive('servicecatalog')) {
    Main::showDefaultHeaderHelpdesk(Menu::getTypeName(2));
} else {
    Html::helpHeader(Resource::getTypeName(2));
}

$employee = new Employee();

//add employee informations from helpdesk
//next step : show list needs of the new ressource
if (isset($_POST["add_helpdesk_employee"])) {
    $newID = $employee->add($_POST);
    Html::redirect("./resource_item.list.php?id=" . $_POST["plugin_resources_resources_id"] . "&exist=0");
} else {
    //show form employee informations from helpdesk
    $employee->showFormHelpdesk($_GET["id"], 0);
}

if (Session::getCurrentInterface() != 'central'
    && Plugin::isPluginActive('servicecatalog')) {
    Main::showNavBarFooter('resources');
}

Html::helpFooter();
