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
use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Resources\Checklist;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkRight('plugin_resources', READ);

$item = new Checklist();

if (isset($_POST["plugin_resources_contracttypes_id"]) && isset($_POST["checklist_type"])) {
    $checklist_type = (int) $_POST["checklist_type"];
    if (
        !in_array(
            $checklist_type,
            [
                Checklist::RESOURCES_CHECKLIST_IN,
                Checklist::RESOURCES_CHECKLIST_OUT,
                Checklist::RESOURCES_CHECKLIST_TRANSFER,
            ],
            true,
        )
    ) {
        throw new BadRequestHttpException();
    }

    $id = (int) ($_POST["id"] ?? -1);
    $options = [
        'id' => $id,
        // The target is decided here and never read from the request. Posted straight through,
        // it became the action attribute of the checklist form through generic_show_form, so an
        // absolute URL would have sent the typed fields and the CSRF token of the session to a
        // third party host. The value below is the one the caller always sent anyway.
        'target' => PLUGIN_RESOURCES_WEBDIR . '/front/checklist.form.php',
        'plugin_resources_contracttypes_id' => (int) $_POST["plugin_resources_contracttypes_id"],
        'checklist_type' => $checklist_type,
        'plugin_resources_resources_id' => (int) ($_POST["plugin_resources_resources_id"] ?? 0),
    ];

    // Checklist::showForm() renders generic_show_form.html.twig, which already provides
    // its own card: the legacy tab_cadre wrapper this endpoint used to echo around it
    // only nested a table inside that card.
    $item->showForm($id, $options);
} else {
    throw new AccessDeniedHttpException();
}
