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

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Servicecatalog\Main;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

//central or helpdesk access
if (Session::getCurrentInterface() == 'central') {
    Html::header(Menu::getTypeName(2), '', "admin", Menu::class);
} else {
    if (Plugin::isPluginActive('servicecatalog')) {
        Main::showDefaultHeaderHelpdesk(Menu::getTypeName(2), false, Resource::class);
    } else {
        Html::helpHeader(Menu::getTypeName(2));
    }
}

$resource = new Resource();

if ($resource->canView() || Session::haveRight("config", UPDATE)) {
    if (Session::haveRight("plugin_resources_all", READ)
    && Session::getCurrentInterface() == 'central') {
        // The modal markup and the script that opens it come from the core helper: ask for
        // the string, so the template stays in charge of the layout.
        $modal = (string) Ajax::createIframeModalWindow(
            'seetypemodal',
            PLUGIN_RESOURCES_WEBDIR . "/ajax/resourcetree.php",
            [
                'title' => __('View by contract type', 'resources'),
                'display' => false,
                // createIframeModalWindow() accepts width/height but never emits them: the
                // iframe is hardcoded to height="400". Only the dialog class reaches the
                // markup, the rest of the sizing is done in css/resources.css.
                'dialog_class' => 'modal-xl modal-dialog-centered plugin_resources_tree_dialog',
            ],
        );

        TemplateRenderer::getInstance()->display('@resources/resource_tree_button.html.twig', [
            'label' => __('View by contract type', 'resources'),
            'modal' => $modal,
        ]);
    }

    Search::show(Resource::class);
} else {
    throw new AccessDeniedHttpException();
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
