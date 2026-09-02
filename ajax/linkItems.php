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

if (strpos($_SERVER['PHP_SELF'], "linkItems.php")) {
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkRight('plugin_resources', READ);

if (isset($_POST["type"]) && isset($_POST["current_type"])) {
    $values = 0;
    if ($_POST["type"] != "0" && $_POST["type"] != "" && $_POST["type"] != "ALL") {
        // Restrict the dynamic ::dropdown() call to the itemtypes the plugin actually
        // exposes: prevents an attacker from invoking a static dropdown() on an arbitrary
        // class through the POSTed "type" value.
        if (!in_array($_POST["type"], Resource::getTypes(true), true)) {
            throw new \Glpi\Exception\Http\BadRequestHttpException();
        }

        if ($_POST['type'] == $_POST['current_type'] && isset($_POST["values"])) {
            $values = $_POST['values'];
        }


        $option["name"] = "items";
        // $values is always defined above (defaults to 0), so use it directly.
        $option["value"] = $values;


        $_POST["type"]::dropdown($option);
    }
}
