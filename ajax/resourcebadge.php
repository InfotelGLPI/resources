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

use Glpi\Exception\Http\NotFoundHttpException;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\ResourceBadge;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkRight('plugin_resources', READ);

if (isset($_POST['action'])) {
    $badge = new ResourceBadge();
    switch ($_POST['action']) {
        case "loadBadge":
            // Anti-IDOR: loadBadge() lists the badges/users linked to a resource id with no
            // entity restriction. Confirm the caller may read that specific resource (right +
            // entity scope) before disclosing them, like picture.send.php / showHabilitations.php.
            $resources_id = (int) ($_POST['plugin_resources_resources_id'] ?? 0);
            $resource = new Resource();
            if ($resources_id <= 0 || !$resource->can($resources_id, READ)) {
                throw new NotFoundHttpException();
            }
            $badge->loadBadge($resources_id);
            break;
        case "loadBadgeRestitution":
            $badge->loadBadgeRestitution();
            break;
        case "cleanButtonRestitution":
            echo "";
            break;
    }
}
