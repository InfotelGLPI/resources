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
use GlpiPlugin\Resources\Resource_Item;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkRight('plugin_resources', READ);

if (isset($_POST["computer_id"])) {
    /** @var \DBmysql $DB */
    global $DB;

    // The link table carries no entity of its own: the boundary lives on the owning
    // resource, so the probe has to join it instead of answering on the raw link rows.
    $link_table     = Resource_Item::getTable();
    $resource_table = Resource::getTable();
    $iterator       = $DB->request([
        'COUNT'      => 'cpt',
        'FROM'       => $link_table,
        'INNER JOIN' => [
            $resource_table => [
                'ON' => [
                    $link_table     => 'plugin_resources_resources_id',
                    $resource_table => 'id',
                ],
            ],
        ],
        'WHERE'      => [
            $link_table . '.itemtype' => Computer::class,
            $link_table . '.items_id' => (int) $_POST["computer_id"],
        ] + getEntitiesRestrictCriteria($resource_table, '', '', true),
    ]);

    $row = $iterator->current();
    if ((int) ($row['cpt'] ?? 0) > 0) {
        echo true;
    }
}
