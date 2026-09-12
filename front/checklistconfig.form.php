<?php

/**
 * -------------------------------------------------------------------------
 * resources plugin for GLPI
 * Copyright (C) 2015-2026 by the resources Development Team.
 *
 * https://github.com/InfotelGLPI/resources
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of resources.
 *
 * resources is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * resources is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with resources. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
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
    // CREATE, not UPDATE: canCreate() is widened here to
    // haveRightsOr([CREATE, UPDATE, DELETE]), so no profile loses the form.
    $checklistconfig->check(-1, CREATE, $_POST);
    $newID = $checklistconfig->add($_POST);
    Html::back();
} elseif (isset($_POST["purge"])) {
    // glpi_plugin_resources_checklistconfigs has no is_deleted column: this really is a
    // purge, and PURGE is the bit the profile form offers for it.
    $checklistconfig->check($_POST['id'], PURGE);
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
