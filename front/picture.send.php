<?php

/*
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2015-2026 by the resources Development Team.

 https://github.com/InfotelGLPI/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of resources.

 resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Resources\Resource;

if (isset($_GET["file"])) {
    $base_dir = realpath(GLPI_PLUGIN_DOC_DIR . "/resources/pictures");
    $file     = realpath($base_dir . "/" . $_GET["file"]);
    if ($file === false || strpos($file, $base_dir . DIRECTORY_SEPARATOR) !== 0) {
        throw new AccessDeniedHttpException();
    }

    // Directory traversal is blocked above, but the file name is derived
    // deterministically from the employee identity (name_firstname.jpg), so it is
    // trivially enumerable. Authorization must match the rest of the plugin:
    // resolve the resource that owns this picture and enforce the business right
    // AND the entity scope through ->can(id, READ). Several resources may share the
    // same picture (homonyms overwrite each other), so allow access as soon as the
    // current user is entitled to at least one of them.
    $resource = new Resource();
    $granted  = false;
    foreach ($resource->find(['picture' => basename($file)]) as $row) {
        if ($resource->can($row['id'], READ)) {
            $granted = true;
            break;
        }
    }
    if (!$granted) {
        throw new AccessDeniedHttpException();
    }

    Resource::sendFile($file, basename($file));
} else {
    throw new AccessDeniedHttpException();
}

