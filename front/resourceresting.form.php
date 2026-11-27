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

use GlpiPlugin\Resources\ResourceResting;
use GlpiPlugin\Servicecatalog\Main;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

if (Session::getCurrentInterface() == 'central') {
    //from central
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class, ResourceResting::class);
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

$resting = new ResourceResting();

// The writes below use the ids checked, as integers, and never the posted values: see
// Resource::getPostedId().
if (isset($_POST["addrestingresources"]) && $_POST["plugin_resources_resources_id"] != 0) {
    $resting->check(-1, CREATE, $_POST);
    // ResourceResting has no entities_id of its own, so the check() above cannot enforce
    // any entity boundary: re-check it on the owning Resource.
    $resources_id = Resource::checkOwnership($_POST["plugin_resources_resources_id"]);
    $resting->add(['plugin_resources_resources_id' => $resources_id] + $_POST);
    Html::back();
} elseif (isset($_POST["updaterestingresources"]) && $_POST["plugin_resources_resources_id"] != 0) {
    $restings_id = Resource::checkPostedItem($resting, $_POST['id'] ?? null, UPDATE);
    // Check the currently owning Resource...
    Resource::checkChildOwnership($resting, $restings_id);
    // ...and the posted one too, as the form allows reassigning the period to
    // another Resource: checkChildOwnership() only validates the stored owner.
    $resources_id = Resource::checkOwnership($_POST["plugin_resources_resources_id"]);
    $resting->update(['id' => $restings_id, 'plugin_resources_resources_id' => $resources_id] + $_POST);
    Html::back();
} elseif (isset($_POST["addenddaterestingresources"]) && isset($_POST["date_end"])) {
    $restings_id = Resource::checkPostedItem($resting, $_POST['id'] ?? null, UPDATE);
    // Resolve the owner before $resting->fields is overwritten below.
    Resource::checkChildOwnership($resting, $restings_id);
    $resting->fields = ['id' => $restings_id, 'date_end' => $_POST['date_end']];
    $resting->updateInDB(['date_end']);
    Html::back();
} elseif (isset($_POST["deleterestingresources"]) && $_POST["plugin_resources_resources_id"] != 0) {
    $restings_id = Resource::checkPostedItem($resting, $_POST['id'] ?? null, PURGE);
    Resource::checkChildOwnership($resting, $restings_id);
    $resting->delete(['id' => $restings_id] + $_POST, 1);
    $resting->redirectToList();
} elseif (isset($_GET['menu'])) {
    if ($resting->canView() || Session::haveRight("config", UPDATE)) {
        $resting->showMenu();
    }
} elseif (isset($_GET['end'])) {
    if ($resting->canView() || Session::haveRight("config", UPDATE)) {
        $resting->showFormEnd($_GET["id"], []);
    }
} else {
    if ($resting->canView() || Session::haveRight("config", UPDATE)) {
        $resting->showForm($_GET["id"], []);
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
