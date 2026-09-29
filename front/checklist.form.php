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
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

$checklist = new Checklist();

//from central
//update checklist
if (isset($_POST["add"])) {
    // Enforce the create right before the mutation, mirroring the update branch below:
    // CommonDBTM::add() performs no authorization on its own.
    $checklist->check(-1, CREATE, $_POST);
    // check(-1, CREATE) only covers the posted entity, which the client chooses: the parent
    // resource has to be authorised too, and it fixes the entity of the new row, as the
    // add_checklist branch of front/resource.form.php does.
    $resources_id = (int) ($_POST['plugin_resources_resources_id'] ?? 0);
    if ($resources_id > 0) {
        $resource = new Resource();
        $resource->check($resources_id, UPDATE);
        $_POST['entities_id'] = $resource->fields['entities_id'];
    }
    $checklist->add($_POST);
    Html::back();
} elseif (isset($_POST["update"])) {
    // check($id, UPDATE) instead of the global canCreate(): CommonDBTM::update() performs
    // no per-item authorization, so without this any authenticated user holding the create
    // right could update an arbitrary checklist by id (IDOR), regardless of its entity.
    $checklist->check($_POST["id"], UPDATE, $_POST);
    // The check above authorises the existing row, not the values it is updated with: a
    // checklist stays attached to the resource and entity it was created under.
    $input = $_POST;
    unset($input['plugin_resources_resources_id'], $input['entities_id']);
    $checklist->update($input);
    Html::back();
} else {
    $checklist->checkGlobal(READ);
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class, Checklist::class);
    $options = [
        'id' => (int) ($_GET['id'] ?? 0),
        'checklist_type' => (int) ($_GET['checklist_type'] ?? 0),
        'plugin_resources_contracttypes_id' => (int) ($_GET['plugin_resources_contracttypes_id'] ?? 0),
        'plugin_resources_resources_id' => (int) ($_GET['plugin_resources_resources_id'] ?? -1),
    ];
    $checklist->display($options);
    Html::footer();
}
