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

use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use GlpiPlugin\Resources\Resource;

$AJAX_INCLUDE = 1;

// Send UTF8 Headers
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkRight('plugin_resources', READ);
global $DB;

if (isset($_REQUEST["table"]) && isset($_REQUEST["value"])) {
    // Security
    if (!$DB->tableExists($_REQUEST['table'])) {
        throw new NotFoundHttpException();
    }

    switch ($_REQUEST["table"]) {
        case "glpi_plugin_resources_resources":
            if ($_REQUEST['value'] == 0) {
                $tmpname['link'] = PLUGIN_RESOURCES_WEBDIR . "/front/resource.php";
                $tmpname['comment'] = "";
            } else {
                // Anti-IDOR: getResourceName() builds a WHERE on the resource id with no
                // entity restriction, so the global plugin_resources READ alone would let a
                // user read the free-text comment of a resource in another entity. Replay the
                // per-record guard (right + entity scope) used by picture.send.php /
                // showHabilitations.php before disclosing it.
                $resources_id = (int) $_REQUEST["value"];
                $resource = new Resource();
                if ($resources_id <= 0 || !$resource->can($resources_id, READ)) {
                    throw new NotFoundHttpException();
                }
                $tmpname = Resource::getResourceName($resources_id, 2);
            }
            echo htmlspecialchars((string) $tmpname["comment"], ENT_QUOTES, 'UTF-8');

            if (isset($_REQUEST['withlink'])) {
                // withlink is reflected into a jQuery selector ($('#...')): restrict it to
                // a safe id pattern so a crafted GET value cannot break out and inject JS.
                // json_encode() emits the link as a properly-quoted JS string literal.
                $withlink = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $_REQUEST['withlink']);
                echo "<script type='text/javascript' >\n";
                echo Resource::jsGetElementbyID($withlink) . ".attr('href', " . json_encode($tmpname['link']) . ");";
                echo "</script>\n";
            }
            break;

        default:
            // The only table this plugin endpoint legitimately serves is the resources
            // table handled above (see Resource::dropdown(), which passes getTable()).
            // Refuse any other client-supplied table so it cannot be used to read the
            // "comment" column of an arbitrary GLPI table (horizontal info disclosure).
            throw new BadRequestHttpException();
    }
}
