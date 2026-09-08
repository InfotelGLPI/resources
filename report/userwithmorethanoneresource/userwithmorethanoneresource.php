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

use Glpi\DBAL\QueryExpression;
use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\ColumnLink;

//Options for GLPI 0.71 and newer : need slave db to access the report

$USEDBREPLICATE = 1;
$DBCONNECTION_REQUIRED = 0;

global $HEADER_LOADED, $DB;

//"Rapport listant les ressources sans utilisateurs";
//"Report listing resource without user";
// Instantiate Report with Name
// Authorization guard: this report script is directly addressable and bypasses the
// reports plugin dispatcher, so enforce the plugin business right — like every other
// resources endpoint — before running any query or emitting output.
Session::checkRight('plugin_resources', READ);

$report = new AutoReport(__("Report listing users linked to more than one resource", "resources"));

// Columns title (optional)
$report->setColumns([
    new ColumnLink(
        'items_id',
        __('User'),
        'User',
        ['sorton' => 'items_id'],
    ),
]);

$dbu = new DbUtils();

// SQL statement
// Only the users linked to more than one resource.
$criteria = [
    'SELECT'    => ['glpi_plugin_resources_resources_items.items_id AS items_id'],
    'FROM'      => 'glpi_plugin_resources_resources_items',
    'LEFT JOIN' => [
        'glpi_plugin_resources_resources' => [
            'ON' => [
                'glpi_plugin_resources_resources'       => 'id',
                'glpi_plugin_resources_resources_items' => 'plugin_resources_resources_id',
            ],
        ],
        'glpi_users'                      => [
            'ON' => [
                'glpi_users'                            => 'id',
                'glpi_plugin_resources_resources_items' => 'items_id',
            ],
        ],
    ],
    'WHERE'     => [
        'glpi_plugin_resources_resources_items.itemtype' => 'User',
        'glpi_users.is_active'                           => 1,
        'glpi_users.is_deleted'                          => 0,
        'glpi_plugin_resources_resources.is_deleted'     => 0,
    ],
    'GROUPBY'   => 'items_id',
    'HAVING'    => [new QueryExpression('COUNT(' . $DB->quoteName('items_id') . ') > 1')],
];

// The result set is scoped to the entities of the session, the way every other report of
// the plugin does it: without this clause the report listed the users of the whole
// instance, whatever the active entity.
// Nested rather than merged with "+": getEntitiesRestrictCriteria() can return an "OR" key
// (recursive entities) or a bare QueryExpression under key 0, which a union would drop.
$criteria['WHERE'] = [
    $criteria['WHERE'],
    $dbu->getEntitiesRestrictCriteria('glpi_plugin_resources_resources', '', '', true),
];

$report->setSqlRequest($criteria);
$report->execute();

$report->footer();
