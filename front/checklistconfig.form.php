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

use GlpiPlugin\Resources\Checklist;
use GlpiPlugin\Resources\Checklistconfig;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}

$checklistconfig = new Checklistconfig();

if (isset($_POST["add"])) {
    $checklistconfig->check(-1, UPDATE, $_POST);
    $newID = $checklistconfig->add($_POST);
    Html::back();
} elseif (isset($_POST["purge"])) {
    $checklistconfig->check($_POST['id'], UPDATE);
    $checklistconfig->delete($_POST, 1);
    $checklistconfig->redirectToList();
} elseif (isset($_POST["update"])) {
    $checklistconfig->check($_POST['id'], UPDATE);
    $checklistconfig->update($_POST);
    Html::back();
} else {
    $checklistconfig->checkGlobal(READ);
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class, Checklist::class);
    $checklistconfig->display(['id' => $_GET["id"]]);
    Html::footer();
}
