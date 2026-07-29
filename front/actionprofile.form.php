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

use GlpiPlugin\Resources\Actionprofile;

// Mapping a profile to plugin capabilities (which actions/buttons a profile is
// offered) is a configuration-level change: gate it on the administration right
// (config UPDATE), like config.form.php/adconfig.form.php, rather than on the
// ordinary plugin_resources CREATE right that any resource manager holds — the
// latter would let a non-admin grant capabilities to any profile (privilege
// escalation).
Session::checkRight('config', UPDATE);

$actionprofile = new Actionprofile();
if (isset($_POST["addAction"])) {
    $actionprofile->check(-1, CREATE, $_POST);
    // Validate that profiles_id references a real profile before writing the mapping.
    $profiles_id = (int) ($_POST['profiles_id'] ?? 0);
    if ($profiles_id <= 0 || !(new Profile())->getFromDB($profiles_id)) {
        Html::back();
    }
    $_POST['profiles_id'] = $profiles_id;
    if (isset($_POST["actions_id"])) {
        $_POST["actions_id"] = json_encode($_POST["actions_id"]);
    } else {
        $_POST["actions_id"] = "[]";
    }
    if ($actionprofile->getFromDBByCrit(['profiles_id' => $_POST['profiles_id']])) {
        $actionprofile->update([
            'id' => $actionprofile->fields['id'],
            'actions_id' => $_POST['actions_id']
        ]);
    } else {
        $actionprofile->add([
            'actions_id' => $_POST['actions_id'],
            'profiles_id' => $_POST['profiles_id']
        ]);
    }

    Html::back();
} else {
    Html::back();
}
