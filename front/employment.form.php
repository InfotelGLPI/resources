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

use GlpiPlugin\Resources\Employment;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}
if (!isset($_GET["plugin_resources_resources_id"])) {
    $_GET["plugin_resources_resources_id"] = 0;
}

$employment = new Employment();

if (isset($_POST["add"])) {
    $employment->check(-1, UPDATE);
    $newID = $employment->add($_POST);
    Html::back();
} elseif (isset($_POST["update"])) {
    $employment->check($_POST["id"], UPDATE);
    $employment->update($_POST);
    Html::back();
} elseif (isset($_POST["delete"])) {
    $employment->check($_POST["id"], UPDATE);
    $employment->delete($_POST);
    $employment->redirectToList();
} elseif (isset($_POST["purge"])) {
    $employment->check($_POST['id'], UPDATE);
    $employment->delete($_POST, 1);
    $employment->redirectToList();
} elseif (isset($_POST["restore"])) {
    $employment->check($_POST["id"], UPDATE);
    $employment->restore($_POST);
    $employment->redirectToList();
} elseif (isset($_POST["add_item"])) {
    // The employment dropdown offers an empty choice: skip the submission instead of
    // running check() on id 0, which would fail with an error page.
    $employments_id = (int) ($_POST['plugin_resources_employments_id'] ?? 0);
    if (!empty($_POST['itemtype']) && $employments_id > 0) {
        $input['id'] = $employments_id;
        $input['plugin_resources_resources_id'] = $_POST['items_id'];

        $employment->check($input["id"], UPDATE);
        // Employment has no entities_id of its own, so the check() above cannot enforce
        // any entity boundary on the target: re-check it on the owning Resource.
        Resource::checkOwnership($input['plugin_resources_resources_id']);
        $employment->update($input);
    }
    Html::back();
} else {
    $employment->checkGlobal(READ);
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class, Employment::class);
    $employment->display(
        ['id' => $_GET["id"], 'plugin_resources_resources_id' => $_GET["plugin_resources_resources_id"]],
    );
    Html::footer();
}
