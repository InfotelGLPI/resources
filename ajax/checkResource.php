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

// The answer is a JSON number, read by response.json() in public/scripts/resourcechange.js:
// declared as such rather than as an HTML page.
header("Content-Type: application/json; charset=UTF-8");
Html::header_nocache();

Session::checkRight(Resource::$rightname, READ);

// Plain strings only: an array would turn each criterion into an IN() over several names
$name = is_string($_POST['name'] ?? null) ? $_POST['name'] : '';
$firstname = is_string($_POST['firstname'] ?? null) ? $_POST['firstname'] : '';

$resource = new Resource();
// find() applies no entity restriction of its own: without this the probe answered on the
// whole instance and turned into an oracle over the personnel of every entity.
$resources = $resource->find(
    ['name' => $name, 'firstname' => $firstname]
    + getEntitiesRestrictCriteria($resource->getTable(), '', '', true),
);

echo json_encode(count($resources), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
