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
        case "glpi_plugin_resources_resources" :
            if ($_REQUEST['value'] == 0) {
                $tmpname['link'] = PLUGIN_RESOURCES_WEBDIR . "/front/resource.php";
                $tmpname['comment'] = "";
            } else {
                $tmpname = Resource::getResourceName($_REQUEST["value"], 2);
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

        default :
            if ($_REQUEST["value"] > 0) {
                $tmpname = Dropdown::getDropdownName($_REQUEST["table"], $_REQUEST["value"], 1);
                echo htmlspecialchars((string) $tmpname["comment"], ENT_QUOTES, 'UTF-8');
            }
    }
}
