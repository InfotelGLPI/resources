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
use GlpiPlugin\Resources\Resource_Change;
use GlpiPlugin\Servicecatalog\Main;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

$resource = new Resource();

// The ids are checked and used as the same integers, see Resource::getPostedId()
$change_action = Resource::getPostedId($_POST["change_action"] ?? null);
$resources_id  = Resource::getPostedId($_POST["plugin_resources_resources_id"] ?? null);

if ($change_action > 0 && $resources_id > 0) {
    // The dropdown of the form only offers the actions set up and granted to the active
    // profile: the posted id is held to the same list, before anything runs.
    if (!Resource_Change::isAllowedAction($change_action)) {
        throw new AccessDeniedHttpException();
    }
    if ($change_action == Resource_Change::CHANGE_TRANSFER) {
        Html::redirect(
            PLUGIN_RESOURCES_WEBDIR . "/front/resource.transfer.php?plugin_resources_resources_id=" . $resources_id,
        );
    } else {
        // Enforce authorization before mutating: the current user must hold the plugin
        // UPDATE right and have access to the target resource's entity. Without this any
        // authenticated user could change an arbitrary resource across entities (IDOR).
        // Mirrors the guard already present in resource.transfer.php / resource.remove.php.
        $resource->check($resources_id, UPDATE);
        Resource_Change::startingChange($resources_id, $change_action, $_POST);
        Html::back();
    }
} else {
    if (Session::getCurrentInterface() == 'central') {
        Html::header(Resource::getTypeName(2), '', "admin", Menu::class);
    } else {
        if (Plugin::isPluginActive('servicecatalog')) {
            Main::showDefaultHeaderHelpdesk(Menu::getTypeName(2));
        } else {
            Html::helpHeader(Resource::getTypeName(2));
        }
    }
    if ($resource->canView() || Session::haveRight(\Config::$rightname, UPDATE)) {
        //show change resource form
        $resource->showResourcesToChange($_POST);
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
}
