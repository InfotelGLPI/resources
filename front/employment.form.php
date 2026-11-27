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
    // Same as budget.form.php: without the posted values, can() validates the entity of the
    // session rather than the submitted entities_id.
    $employment->check(-1, CREATE, $_POST);

    // The employment carries the resource it belongs to. That resource is a separate record
    // with its own entity, which the guard above says nothing about, so an employment could be
    // attached to the file of a resource outside the perimeter of its author. An employment
    // may legitimately be created with no resource, hence the test rather than a check on
    // id 0. Employment::prepareInputForAdd() applies the same rule to every other entry point.
    // The row is attached to the id checked, not to the posted value.
    $resources_id = Resource::getPostedId($_POST['plugin_resources_resources_id'] ?? null);
    if ($resources_id > 0) {
        Resource::checkOwnership($resources_id);
    }

    $newID = $employment->add(['plugin_resources_resources_id' => $resources_id] + $_POST);
    Html::back();
} elseif (isset($_POST["update"])) {
    $employments_id = Resource::checkPostedItem($employment, $_POST["id"] ?? null, UPDATE);
    // The check above covers the employment only. The form also posts the resource it is
    // attached to: Employment::prepareInputForUpdate() refuses to move it to a resource the
    // session may not update.
    $employment->update(['id' => $employments_id] + $_POST);
    Html::back();
} elseif (isset($_POST["delete"])) {
    // Same as budget.form.php: glpi_plugin_resources_employments has no is_deleted column,
    // so delete() destroys the row for good and PURGE is the matching bit.
    $employments_id = Resource::checkPostedItem($employment, $_POST["id"] ?? null, PURGE);
    $employment->delete(['id' => $employments_id] + $_POST);
    $employment->redirectToList();
} elseif (isset($_POST["purge"])) {
    $employments_id = Resource::checkPostedItem($employment, $_POST["id"] ?? null, PURGE);
    $employment->delete(['id' => $employments_id] + $_POST, 1);
    $employment->redirectToList();
} elseif (isset($_POST["restore"])) {
    // Same as budget.form.php: unreachable without an is_deleted column, but kept
    // aligned with the sibling delete/purge branches rather than left on UPDATE.
    $employments_id = Resource::checkPostedItem($employment, $_POST["id"] ?? null, PURGE);
    $employment->restore(['id' => $employments_id] + $_POST);
    $employment->redirectToList();
} elseif (isset($_POST["add_item"])) {
    // The employment dropdown offers an empty choice: skip the submission instead of
    // running check() on id 0, which would fail with an error page.
    $employments_id = Resource::getPostedId($_POST['plugin_resources_employments_id'] ?? null);
    if (!empty($_POST['itemtype']) && $employments_id > 0) {
        $employment->check($employments_id, UPDATE);
        // The check() above covers the employment row, which does carry an entities_id. It
        // says nothing about the resource being linked here: that is another record, with an
        // entity of its own, so the boundary has to be checked on it too.
        $employment->update([
            'id'                            => $employments_id,
            'plugin_resources_resources_id' => Resource::checkOwnership($_POST['items_id'] ?? null),
        ]);
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
