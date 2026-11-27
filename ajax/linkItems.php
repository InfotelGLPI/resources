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

use GlpiPlugin\Resources\Checklistconfig;
use GlpiPlugin\Resources\Resource;

if (strpos($_SERVER['PHP_SELF'], "linkItems.php")) {
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkRight(Resource::$rightname, READ);
// The only caller is the checklist setup form (Checklistconfig::showForm()): the right of
// that screen is required, not only the generic plugin one.
Session::checkRight(Checklistconfig::$rightname, READ);

$type         = $_POST["type"] ?? null;
$current_type = $_POST["current_type"] ?? null;

if (is_string($type) && is_string($current_type)) {
    if ($type != "0" && $type != "" && $type != "ALL") {
        // Restrict the dynamic ::dropdown() call to the itemtypes the plugin exposes and the
        // session may view, the list the form itself offers: an arbitrary class would have
        // its static dropdown() invoked, and a type without the right would be listed.
        if (!in_array($type, Resource::getTypes(), true)
            || !is_a($type, CommonDBTM::class, true)) {
            throw new \Glpi\Exception\Http\BadRequestHttpException();
        }

        // The current value is resolved to a name and a comment by the dropdown: kept only
        // when the session may read that very object, entity included.
        $values = 0;
        if ($type === $current_type && is_numeric($_POST["values"] ?? null)) {
            $posted = (int) $_POST["values"];
            $item   = new $type();
            if ($posted > 0 && $item->can($posted, READ)) {
                $values = $posted;
            }
        }

        $type::dropdown([
            'name'  => 'items',
            'value' => $values,
        ]);
    }
}
