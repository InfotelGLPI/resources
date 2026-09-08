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
use Glpi\Exception\Http\BadRequestHttpException;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkRight('plugin_resources', READ);

switch ($_POST['action'] ?? '') {
    case 'groupEntity':
        // The entity is posted by the client and "entity_sons" widens the lookup to the whole
        // sub tree, so an unchecked value would list the groups of an entity the user has no
        // access to. The global plugin right says nothing about the entity perimeter.
        $entities_id = (int) ($_POST["entities_id"] ?? -1);
        if (!Session::haveAccessToEntity($entities_id)) {
            throw new AccessDeniedHttpException();
        }
        echo htmlescape(__('Group')) . "&nbsp;";
        Dropdown::show('Group', ['entity' => $entities_id, 'entity_sons' => true]);
        break;

    default:
        throw new BadRequestHttpException();
}
