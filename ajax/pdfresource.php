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
use GlpiPlugin\Resources\Resource_Item;

Session::checkRight('plugin_resources', READ);

global $CFG_GLPI, $DB;

if (Plugin::isPluginActive("useditemsexport")) {
    if (isset($_POST['plugin_resources_resources_id'])) {
        // Anti-IDOR: this endpoint discloses whether a resource's linked user owns equipment
        // (and that users_id) with no entity restriction. Validate the resource id and confirm
        // the caller may read that specific resource (right + entity scope) before proceeding,
        // like picture.send.php / showHabilitations.php.
        $resources_id = (int) $_POST['plugin_resources_resources_id'];
        $resource_obj = new Resource();
        if ($resources_id <= 0 || !$resource_obj->can($resources_id, READ)) {
            throw new NotFoundHttpException();
        }
        $resource_item = new Resource_Item();
        $resource = $resource_item->find(
            [
                'itemtype' => 'User',
                'plugin_resources_resources_id' => $resources_id,
            ],
            [],
            [1],
        );
        if (count($resource) == 1) {
            $resource = reset($resource);
            $users_id = (int) $resource['items_id'];

            $type_user = $CFG_GLPI['linkuser_types'];
            $field_user = 'users_id';

            $total_numrows = 0;
            $dbu = new DbUtils();

            foreach ($type_user as $itemtype) {
                if (!($item = $dbu->getItemForItemtype($itemtype))) {
                    continue;
                }

                $itemtable = $dbu->getTableForItemType($itemtype);
                // Use the query builder so users_id is bound rather than concatenated.
                $where = [$field_user => $users_id];
                if ($item->maybeTemplate()) {
                    $where['is_template'] = 0;
                }
                if ($item->maybeDeleted()) {
                    $where['is_deleted'] = 0;
                }
                $total_numrows += count($DB->request(['FROM' => $itemtable, 'WHERE' => $where]));
            }

            if ($total_numrows > 0) {
                $rand = mt_rand();

                $url = PLUGIN_RESOURCES_WEBDIR . "/front/export.pdf.php";
                echo __('Please ensure that the return form is signed by the employee', 'resources') . "<br><br>";
                echo "<span class='red'>" .
                    __(
                        "The sales manager is responsible for the complete return of the company's equipment held by the outgoing employee (badge, PC, smartphone, etc.)",
                        'resources',
                    ) .
                    "</span><br><br>";

                echo "<a class='submit btn btn-warning' style='color: white;' href='$url?generate_pdf&users_id=$users_id' target=\"_blank\">" . __(
                    'Download the restitution form',
                    'resources',
                ) . "</a>";

                Html::closeForm();
            }
        }
    }
}
