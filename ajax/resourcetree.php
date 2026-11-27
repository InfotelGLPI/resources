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

use GlpiPlugin\Resources\Resource;

Html::header_nocache();

Session::checkRight('plugin_resources', READ);

// $_GET['target'] becomes the href of the "Show all" button rendered by showSelector(). A
// prefix test on the web root let through "//host" or "/\host" when GLPI is served from
// the web root, that is a link to any site from a genuine GLPI page. Only a known page of
// the plugin is accepted now, compared as a whole string; anything else falls back to the
// resource list, which is also what the only caller (front/resource.php) relies on.
$allowed_targets = [
    PLUGIN_RESOURCES_WEBDIR . "/front/resource.php",
];
$target = $_GET['target'] ?? null;
if (!is_string($target) || !in_array($target, $allowed_targets, true)) {
    $target = $allowed_targets[0];
}

// The page is loaded in the iframe of a modal: emit the standard modal document so the
// tree inherits the whole GLPI stylesheet (Tabler) and the core bundles carrying
// fancytree, instead of rendering a bare fragment in quirks mode.
Html::popHeader(__('View by contract type', 'resources'), '', true);

Resource::showSelector($target);

Html::popFooter();
