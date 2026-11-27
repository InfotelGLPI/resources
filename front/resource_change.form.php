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

use GlpiPlugin\Resources\Config;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Resource_Change;

// Setup of the change actions: same right as the other setup controllers, on display too
// (the list names the entities and categories of the configured actions).
Session::checkRight(\Config::$rightname, UPDATE);

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}

$resource_change = new Resource_Change();

if (isset($_POST['add_entity_category'])) {
    // prepareInputForAdd() validates the action, the entity and the category posted
    $resource_change->check(-1, CREATE, $_POST);
    $resource_change->add($_POST);
    Html::back();
} else {
    $resource_change->checkGlobal(READ);
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class, Config::class);
    $resource_change->showFormActions();
    Html::footer();
}
