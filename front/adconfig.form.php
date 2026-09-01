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
use GlpiPlugin\Resources\Adconfig;

Session::checkRight("config", UPDATE);

if (Plugin::isPluginActive("resources")) {
    $config = new Adconfig();


    if (isset($_POST["update_setup"])) {
        $config->check(-1, UPDATE, $_POST);
        $config->update($_POST);
        Html::back();
    }
} else {
    Html::header(__s('Setup'), '', "config", "plugin");
    TemplateRenderer::getInstance()->display('@resources/alert_warning.html.twig', [
        'message' => __('Please activate the plugin', 'resources'),
    ]);
    Html::footer();
}

Html::footer();
