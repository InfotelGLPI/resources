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
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

$resource = new Resource();

// A guard clause rather than a wrapping if: without the right this used to answer 200 with an
// empty body, which reads as "nothing to show here" instead of "you may not look".
if (!$resource->canView() && !Session::haveRight("config", UPDATE)) {
    throw new AccessDeniedHttpException();
}

Html::header(Resource::getTypeName(2), '', "admin", Menu::class);

$resource->listOfTemplates(PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php", (int) ($_GET["add"] ?? 0));

Html::footer();
