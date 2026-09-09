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

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Resource_Change;

Session::checkRight('plugin_resources', READ);

$resource_change = new Resource_Change();

if (isset($_POST['load_button_changeresources'])) {
    $resource_change->loadButtonChangeResources($_POST['action'], $_POST);
} elseif (isset($_POST['action'])) {
    // The three fragments below belong to the "Managing change actions" setup screen, which
    // Resource_Change::showFormActions() gates on canView() && canCreate(): the READ right
    // posed at the top of this file is not enough for them. The test stands before the switch
    // so no branch is left uncovered.
    if (in_array($_POST['action'], ['loadEntity', 'loadCategory', 'loadButtonAdd'], true)
        && !(Resource_Change::canView() && Resource_Change::canCreate())) {
        throw new AccessDeniedHttpException();
    }

    switch ($_POST['action']) {
        case "loadEntity":
            $resource_change->loadEntity((int) ($_POST['actions_id'] ?? 0));
            break;
        case "loadCategory":
            // The entity is posted by the client and is the only scope of the ITIL category
            // dropdown built from it: without this test the category list of any entity of the
            // instance was readable from this endpoint alone.
            $entities_id = (int) ($_POST['entities_id'] ?? 0);
            if (!Session::haveAccessToEntity($entities_id)) {
                throw new AccessDeniedHttpException();
            }
            $resource_change->displayCategory($entities_id);
            break;
        case "loadButtonAdd":
            $resource_change->displayButtonAdd((int) ($_POST['itilcategories_id'] ?? 0));
            break;
        case "clean":
            echo "";
            break;
    }
} else {
    // Enforce per-record read (right + entity) before setFieldByAction()
    // discloses the resource's current manager name for the given id.
    $resources_id = (int) ($_POST['plugin_resources_resources_id'] ?? 0);
    $resource = new Resource();
    if ($resources_id <= 0 || !$resource->can($resources_id, READ)) {
        throw new NotFoundHttpException();
    }
    $resource_change->setFieldByAction($_POST["id"], $resources_id);
}
