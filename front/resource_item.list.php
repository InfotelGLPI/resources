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
// Each branch checks a right on an id and then writes with an id: both are the same integer,
// see Resource::getPostedId().
if (isset($_POST["addhelpdeskitem"])) {
    $resources_id   = Resource::getPostedId($_POST['plugin_resources_resources_id'] ?? null);
    $choiceitems_id = Resource::getPostedId($_POST['plugin_resources_choiceitems_id'] ?? null);
    if ($choiceitems_id > 0 && $resources_id > 0) {
        // check($id, UPDATE) instead of the global canCreate(): these branches mutate a
        // specific Resource's data (directly, or its Choice rows), so the right must be
        // re-checked on that instance, not just globally (IDOR otherwise).
        $resource->check($resources_id, UPDATE);
        $choice->addHelpdeskItem($resources_id, $choiceitems_id);
    }
    Html::back();
} //delete items needs from helpdesk
elseif (isset($_POST["deletehelpdeskitem"])) {
    $choices_id = Resource::checkChildOwnership($choice, $_POST["id"] ?? null);
    $choice->delete(['id' => $choices_id]);
    Html::back();
    //next step : email and finish resource creation
} elseif (isset($_POST["finish"])) {
    $resource->redirectToList();
} elseif (isset($_POST["updateneedcomment"])) {
    // updateneedcomment[] keys are Choice ids, not the checked Resource id: a caller could
    // pass a Resource they own in plugin_resources_resources_id while listing Choice ids
    // belonging to a different Resource, so each Choice's own parent must be re-checked.
    foreach (array_keys((array) $_POST["updateneedcomment"]) as $key) {
        $choices_id = Resource::checkChildOwnership($choice, $key);
        $choice->addNeedComment($choices_id, (string) ($_POST["commentneed" . $choices_id] ?? ''));
    }
    Html::back();
} elseif (isset($_POST['updateSpecialRequirement'])) {
    $resources_id = Resource::checkPostedItem($resource, $_POST['plugin_resources_resources_id'] ?? null, UPDATE);
    // The frozen form only hides its save button: refuse the same POST server-side.
    $resource->checkNotFrozen();
    // Whitelist to the fields actually exposed by this form (see the "Specials
    // requirements" section of Choice::showItemHelpdesk()) to avoid mass-assigning
    // unrelated Resource fields (entities_id, is_leaving, ...) via a crafted POST.
    $input = [
        'id' => $resources_id,
        'computer_phone_equipment' => $_POST['computer_phone_equipment'] ?? '',
        'softwares_requirements' => $_POST['softwares_requirements'] ?? '',
        'furnitures_needs' => $_POST['furnitures_needs'] ?? '',
        'other_needs' => $_POST['other_needs'] ?? '',
    ];
    $resource->update($input);
    Html::back();
} else {
    //show form items needs from helpdesk
    // No test here: the per record guard (right bit, config bypass and entity boundary alike)
    // is posed at the sink, in Choice::showItemHelpdesk(), so every call path goes through it
    // and a refusal comes back as a refusal rather than as an empty page.
    $choice->showItemHelpdesk((int) ($_GET["id"] ?? 0), $_GET["exist"] ?? 0);
}

if (Session::getCurrentInterface() != 'central'
    && Plugin::isPluginActive('servicecatalog')) {
    Main::showNavBarFooter('resources');
}

Html::helpFooter();
