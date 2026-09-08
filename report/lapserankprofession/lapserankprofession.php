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
use GlpiPlugin\Reports\Column;
use GlpiPlugin\Reports\ColumnDate;
use GlpiPlugin\Reports\ColumnLink;
use GlpiPlugin\Resources\Profession;
use GlpiPlugin\Resources\Rank;

//Options for GLPI 0.71 and newer : need slave db to access the report
$USEDBREPLICATE = 1;
$DBCONNECTION_REQUIRED = 1;

global $HEADER_LOADED, $DB;

// Instantiate Report with Name
// Authorization guard: this report script is directly addressable and bypasses the
// reports plugin dispatcher, so enforce the plugin business right — like every other
// resources endpoint — before running any query or emitting output.
Session::checkRight('plugin_resources', READ);

$report = new AutoReport(__("Report listing obsolete corps and ranks", "resources"));

// Columns title (optional)
$report->setColumns([
    new ColumnLink(
        'rank_id',
        Rank::getTypeName(1),
        Rank::class,
        ['sorton' => 'rank_name'],
    ),
    new Column(
        'rank_code',
        Rank::getTypeName(1) . " - " . __('Code', 'resources'),
        ['sorton' => 'rank_code'],
    ),
    new ColumnDate(
        'rank_begin_date',
        Rank::getTypeName(1) . " - " . __('Begin date'),
        ['sorton' => 'rank_begin_date'],
    ),
    new ColumnDate(
        'rank_end_date',
        Rank::getTypeName(1) . " - " . __('End date'),
        ['sorton' => 'rank_end_date'],
    ),
    new ColumnLink(
        'prof_id',
        Profession::getTypeName(1),
        Profession::class,
        ['sorton' => 'prof_name'],
    ),
    new Column(
        'prof_code',
        Profession::getTypeName(1) . " - " . __('Code', 'resources'),
        ['sorton' => 'prof_code'],
    ),
    new ColumnDate(
        'prof_begin_date',
        Profession::getTypeName(1) . " - " . __('Begin date'),
        ['sorton' => 'prof_begin_date'],
    ),
    new ColumnDate(
        'prof_end_date',
        Profession::getTypeName(1) . " - " . __('End date'),
        ['sorton' => 'prof_end_date'],
    ),
]);

// SQL statement
$date = date("Y-m-d");

//display only leaving resource with active employment
$criteria = [
    'SELECT'    => [
        'glpi_plugin_resources_ranks.id AS rank_id',
        'glpi_plugin_resources_ranks.name AS rank_name',
        'glpi_plugin_resources_ranks.code AS rank_code',
        'glpi_plugin_resources_ranks.begin_date AS rank_begin_date',
        'glpi_plugin_resources_ranks.end_date AS rank_end_date',
        'glpi_plugin_resources_professions.id AS prof_id',
        'glpi_plugin_resources_professions.name AS prof_name',
        'glpi_plugin_resources_professions.code AS prof_code',
        'glpi_plugin_resources_professions.begin_date AS prof_begin_date',
        'glpi_plugin_resources_professions.end_date AS prof_end_date',
    ],
    'FROM'      => 'glpi_plugin_resources_ranks',
    'LEFT JOIN' => [
        'glpi_plugin_resources_professions' => [
            'ON' => [
                'glpi_plugin_resources_ranks'       => 'plugin_resources_professions_id',
                'glpi_plugin_resources_professions' => 'id',
                [
                    // Kept exactly as the original SQL grouped it: AND binds tighter than OR,
                    // so the middle branch is "begin_date < date AND end_date IS NULL".
                    'AND' => [
                        [
                            'OR' => [
                                ['glpi_plugin_resources_professions.begin_date' => null],
                                [
                                    'AND' => [
                                        ['glpi_plugin_resources_professions.begin_date' => ['<', $date]],
                                        ['glpi_plugin_resources_professions.end_date' => null],
                                    ],
                                ],
                                ['glpi_plugin_resources_professions.end_date' => ['>', $date]],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    // Column to column comparisons: no criteria form expresses those, so they stay
    // QueryExpression built from quoted identifiers.
    'WHERE'     => [
        'OR' => [
            new QueryExpression(
                $DB->quoteName('glpi_plugin_resources_ranks.is_active')
                . ' <> ' . $DB->quoteName('glpi_plugin_resources_professions.is_active'),
            ),
            new QueryExpression(
                $DB->quoteName('glpi_plugin_resources_ranks.begin_date')
                . ' > ' . $DB->quoteName('glpi_plugin_resources_professions.end_date'),
            ),
            new QueryExpression(
                $DB->quoteName('glpi_plugin_resources_ranks.end_date')
                . ' < ' . $DB->quoteName('glpi_plugin_resources_professions.begin_date'),
            ),
            new QueryExpression(
                $DB->quoteName('glpi_plugin_resources_ranks.end_date')
                . ' > ' . $DB->quoteName('glpi_plugin_resources_professions.end_date'),
            ),
            new QueryExpression(
                $DB->quoteName('glpi_plugin_resources_ranks.begin_date')
                . ' < ' . $DB->quoteName('glpi_plugin_resources_professions.begin_date'),
            ),
            [
                'glpi_plugin_resources_ranks.end_date' => null,
                'NOT'                                  => [
                    'glpi_plugin_resources_professions.end_date' => null,
                ],
            ],
            [
                'NOT'                                        => [
                    'glpi_plugin_resources_ranks.end_date' => null,
                ],
                'glpi_plugin_resources_professions.end_date' => null,
            ],
        ],
    ],
];

// The entity restriction now applies to the whole set of OR branches. It used to be
// concatenated after them, where AND binds tighter than OR: it only constrained the last
// branch and every other one returned rows from any entity.
// Nesting rather than merging: getEntitiesRestrictCriteria() can itself return an "OR"
// key (recursive entities) or a bare QueryExpression, either of which a "+" union would
// silently drop against the criteria already built here.
$criteria['WHERE'] = [
    $criteria['WHERE'],
    getEntitiesRestrictCriteria('glpi_plugin_resources_professions', '', '', true),
];

$criteria = $criteria + $report->getNewOrderBy('rank_id');

$report->setSqlRequest($criteria);

$report->execute();

$report->footer();
