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
use GlpiPlugin\Servicecatalog\Main;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Resource_Change;
use GlpiPlugin\Resources\TransferEntity;

// The transfer is one of the change actions: front/resource.change.php only redirects here
// when the active profile may run it, and this page is reachable without going through it.
// Same test for the form and for its submission: the transfer is set up (TransferEntity) and
// not left out of the actions an administrator restricted the profile to (Actionprofile).
if (!Resource_Change::isAllowedAction(Resource_Change::CHANGE_TRANSFER)) {
    throw new AccessDeniedHttpException();
}

if (Session::getCurrentInterface() == 'central') {
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class);
} else {
    if (Plugin::isPluginActive('servicecatalog')) {
        Main::showDefaultHeaderHelpdesk(Menu::getTypeName(2));
    } else {
        Html::helpHeader(Resource::getTypeName(2));
    }
}

$resource = new Resource();

if (isset($_POST["transferresources"])) {
    // Enforce authorization before transferring: the current user must be allowed
    // to update the source resource and must have access to the target entity. The ids
    // checked are the ones handed to transferResource(), never the posted values.
    $resources_id = Resource::checkPostedItem($resource, $_POST["plugin_resources_resources_id"] ?? null, UPDATE);
    $entities_id = Resource::getPostedId($_POST['entities_id'] ?? null);
    if (!Session::haveAccessToEntity($entities_id)) {
        throw new AccessDeniedHttpException();
    }
    // The form only offers the entities opened to transfers by the administrator
    // (TransferEntity): enforce that list on the posted value too.
    if (!(new TransferEntity())->getFromDBByCrit(['entities_id' => $entities_id])) {
        throw new AccessDeniedHttpException();
    }
    if ($resource->checkTransferMandatoryFields($_POST)) {
        // Only the values validated above: the rest of the form is not an option of the
        // transfer (the user to link, in particular, is not part of it).
        $resource->transferResource($resources_id, $entities_id);
        Html::redirect(PLUGIN_RESOURCES_WEBDIR . "/front/resource.change.php");
    } else {
        Html::back();
    }
} else {
    if ($resource->canView() || Session::haveRight("config", UPDATE)) {
        //show remove resource form
        $resource->showResourcesToTransfer((int) ($_GET["plugin_resources_resources_id"] ?? 0));
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
