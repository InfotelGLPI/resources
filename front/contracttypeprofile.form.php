<?php

/*
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2015-2026 by the resources Development Team.

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

use GlpiPlugin\Resources\Contracttypeprofile;

// Mapping a profile to the contract types it may see is a configuration-level
// change: gate it on the administration right (config UPDATE), like
// config.form.php/adconfig.form.php, rather than on the ordinary plugin_resources
// CREATE right that any resource manager holds — the latter would let a non-admin
// widen any profile's visible contract types (privilege escalation).
Session::checkRight('config', UPDATE);

$contracttype = new Contracttypeprofile();
if (isset($_POST["addContracttype"])) {
    $contracttype->check(-1, CREATE, $_POST);
    // Validate that profiles_id references a real profile before writing the mapping.
    $profiles_id = (int) ($_POST['profiles_id'] ?? 0);
    if ($profiles_id <= 0 || !(new Profile())->getFromDB($profiles_id)) {
        Html::back();
    }
    $_POST['profiles_id'] = $profiles_id;
    if (isset($_POST["plugin_resources_contracttypes_id"])) {
        $_POST["plugin_resources_contracttypes_id"] = json_encode($_POST["plugin_resources_contracttypes_id"]);
    } else {
        $_POST["plugin_resources_contracttypes_id"] = "[]";
    }
    if ($contracttype->getFromDBByCrit(['profiles_id' => $_POST['profiles_id']])) {
        $contracttype->update([
            'id' => $contracttype->fields['id'],
            'plugin_resources_contracttypes_id' => $_POST['plugin_resources_contracttypes_id']
        ]);
    } else {
        $contracttype->add([
            'plugin_resources_contracttypes_id' => $_POST['plugin_resources_contracttypes_id'],
            'profiles_id' => $_POST['profiles_id']
        ]);
    }

    Html::back();
} else {
    Html::back();
}
