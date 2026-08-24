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

use GlpiPlugin\Resources\Choice;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Servicecatalog\Main;

//from helpdesk
if (Plugin::isPluginActive('servicecatalog')) {
    Main::showDefaultHeaderHelpdesk(Menu::getTypeName(2));
} else {
    Html::helpHeader(Resource::getTypeName(2));
}

$choice = new Choice();
$resource = new Resource();

//add items needs from helpdesk
if (isset($_POST["addhelpdeskitem"])) {
    if ($_POST['plugin_resources_choiceitems_id'] > 0
        && $_POST['plugin_resources_resources_id'] > 0) {
        // check($id, UPDATE) instead of the global canCreate(): these branches mutate a
        // specific Resource's data (directly, or its Choice rows), so the right must be
        // re-checked on that instance, not just globally (IDOR otherwise).
        $resource->check($_POST['plugin_resources_resources_id'], UPDATE);
        $choice->addHelpdeskItem($_POST);
    }
    Html::back();
} //delete items needs from helpdesk
elseif (isset($_POST["deletehelpdeskitem"])) {
    $choice->getFromDB($_POST["id"]);
    $resource->check($choice->fields['plugin_resources_resources_id'], UPDATE);
    $choice->delete(['id' => $_POST["id"]]);
    Html::back();
    //next step : email and finish resource creation
} elseif (isset($_POST["finish"])) {
    $resource->redirectToList();
} elseif (isset($_POST["updateneedcomment"])) {
    $resource->check($_POST['plugin_resources_resources_id'], UPDATE);
    foreach ($_POST["updateneedcomment"] as $key => $val) {
        $varcomment = "commentneed" . $key;
        $values['id'] = $key;
        $values['commentneed'] = $_POST[$varcomment];
        $choice->addNeedComment($values);
    }
    Html::back();
} elseif (isset($_POST['updateSpecialRequirement'])) {
    $resource->check($_POST['plugin_resources_resources_id'], UPDATE);
    $_POST['id'] = $_POST['plugin_resources_resources_id'];
    $resource->update($_POST);
    Html::back();
} else {
    //show form items needs from helpdesk
    if ($resource->canView() || Session::haveRight("config", UPDATE)) {
        $choice->showItemHelpdesk($_GET["id"], $_GET["exist"]);
    }
}

if (Session::getCurrentInterface() != 'central'
    && Plugin::isPluginActive('servicecatalog')) {
    Main::showNavBarFooter('resources');
}

Html::helpFooter();
