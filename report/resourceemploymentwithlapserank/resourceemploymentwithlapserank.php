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
use GlpiPlugin\Resources\Employment;
use GlpiPlugin\Resources\Rank;
use GlpiPlugin\Resources\Resource;

//Options for GLPI 0.71 and newer : need slave db to access the report
$USEDBREPLICATE = 1;
$DBCONNECTION_REQUIRED = 0;
global $HEADER_LOADED, $DB;

// Instantiate Report with Name
// Authorization guard: this report script is directly addressable and bypasses the
// reports plugin dispatcher, so enforce the plugin business right — like every other
// resources endpoint — before running any query or emitting output.
Session::checkRight('plugin_resources', READ);

$report = new AutoReport(__("Report listing resources or jobs with an obsolete grade", "resources"));

//"Rapport listant les ressources ou les emplois ayant un grade caduque"
//"Report listing resource or employment with lapse rank";

//colname with sort allowed
$columns = [
    'entity' => ['sorton' => 'entity'],
    'name' => ['sorton' => 'name'],
    'firstname' => ['sorton' => 'firstname'],
    'registration_number' => ['sorton' => 'registration_number'],
    'rank' => ['sorton' => 'rankName'],
    'date_begin' => ['sorton' => 'date_begin'],
    'date_end' => ['sorton' => 'date_end'],
    'begin_date' => ['sorton' => 'begin_date'],
    'end_date' => ['sorton' => 'end_date'],
];

$output_type = Search::HTML_OUTPUT;

if (isset($_POST['list_limit'])) {
    // Cast: this key is shared with the core, and the budget summary report reads it
    // back straight into a SQL LIMIT clause.
    $_SESSION['glpilist_limit'] = (int) $_POST['list_limit'];
    unset($_POST['list_limit']);
}
if (!isset($_REQUEST['sort'])) {
    $_REQUEST['sort'] = "entity";
    $_REQUEST['order'] = "ASC";
}

$limit = (int) $_SESSION['glpilist_limit'];

if (isset($_POST["display_type"])) {
    $output_type = $_POST["display_type"];
    if ($output_type < 0) {
        $output_type = -$output_type;
        $limit = 0;
    }
} else {
    $output_type = Search::HTML_OUTPUT;
}

$title = $report->getFullTitle();
$dbu = new DbUtils();

//to verify if resources exist
// SQL statement
$date = date("Y-m-d");
$dataAll = [];

// Both queries share the same "out of the rank validity range" test. The two first branches
// compare a column to a column: no criteria form expresses that, so they stay QueryExpression
// built from quoted identifiers.
$lapseOnRank = static fn(string $begin, string $end): array => [
    'OR' => [
        new QueryExpression(
            $DB->quoteName($begin)
            . ' > ' . $DB->quoteName('glpi_plugin_resources_ranks.end_date'),
        ),
        new QueryExpression(
            $DB->quoteName($end)
            . ' < ' . $DB->quoteName('glpi_plugin_resources_ranks.begin_date'),
        ),
        ['glpi_plugin_resources_ranks.end_date' => ['<', $date]],
        ['glpi_plugin_resources_ranks.begin_date' => ['>', $date]],
    ],
];

//case resource
$criteria = [
    'SELECT'    => [
        'glpi_plugin_resources_resources.entities_id AS entity',
        new QueryExpression($DB->quoteValue('Resource') . ' AS ' . $DB->quoteName('typeName')),
        'glpi_plugin_resources_resources.name AS name',
        'glpi_plugin_resources_resources.id AS ID',
        'glpi_plugin_resources_resources.firstname AS firstname',
        'glpi_users.registration_number AS registration_number',
        'glpi_plugin_resources_ranks.id AS rankID',
        'glpi_plugin_resources_ranks.name AS rankName',
        'glpi_plugin_resources_resources.date_begin',
        'glpi_plugin_resources_resources.date_end',
        'glpi_plugin_resources_ranks.begin_date',
        'glpi_plugin_resources_ranks.end_date',
    ],
    'FROM'      => 'glpi_users',
    'LEFT JOIN' => [
        'glpi_plugin_resources_resources_items' => [
            'ON' => [
                'glpi_users'                            => 'id',
                'glpi_plugin_resources_resources_items' => 'items_id',
                [
                    'AND' => ['glpi_plugin_resources_resources_items.itemtype' => 'User'],
                ],
            ],
        ],
        'glpi_plugin_resources_resources'       => [
            'ON' => [
                'glpi_plugin_resources_resources'       => 'id',
                'glpi_plugin_resources_resources_items' => 'plugin_resources_resources_id',
            ],
        ],
        'glpi_plugin_resources_ranks'           => [
            'ON' => [
                'glpi_plugin_resources_resources' => 'plugin_resources_ranks_id',
                'glpi_plugin_resources_ranks'     => 'id',
            ],
        ],
    ],
    'WHERE'     => [
        [
            'glpi_plugin_resources_resources.date_begin'  => ['<', $date],
            [
                'OR' => [
                    ['glpi_plugin_resources_resources.date_end' => null],
                    ['glpi_plugin_resources_resources.date_end' => ['>', $date]],
                ],
            ],
            'NOT'                                         => [
                'glpi_plugin_resources_ranks.id' => null,
            ],
            'glpi_plugin_resources_resources.is_leaving'  => 0,
            'glpi_plugin_resources_resources.is_deleted'  => 0,
            'glpi_plugin_resources_resources.is_template' => 0,
        ],
        $lapseOnRank(
            'glpi_plugin_resources_resources.date_begin',
            'glpi_plugin_resources_resources.date_end',
        ),
        // Nested rather than merged with "+": getEntitiesRestrictCriteria() can return an "OR"
        // key (recursive entities) or a bare QueryExpression under key 0, which a union would
        // drop.
        $dbu->getEntitiesRestrictCriteria('glpi_plugin_resources_resources', '', '', true),
    ],
];

$criteria = $criteria + getNewOrderBy('entity', $columns);

$row_num = 0;
foreach ($DB->request($criteria) as $data) {
    $dataAll[$row_num] = $data;
    $row_num++;
}

//case employment
$criteriaEmploy = [
    'SELECT'    => [
        'glpi_plugin_resources_employments.entities_id AS entity',
        new QueryExpression($DB->quoteValue('Employment') . ' AS ' . $DB->quoteName('typeName')),
        'glpi_plugin_resources_employments.name AS name',
        'glpi_plugin_resources_employments.id AS ID',
        new QueryExpression('NULL AS ' . $DB->quoteName('firstname')),
        new QueryExpression('NULL AS ' . $DB->quoteName('registration_number')),
        'glpi_plugin_resources_ranks.id AS rankID',
        'glpi_plugin_resources_ranks.name AS rankName',
        'glpi_plugin_resources_employments.begin_date AS date_begin',
        'glpi_plugin_resources_employments.end_date AS date_end',
        'glpi_plugin_resources_ranks.begin_date',
        'glpi_plugin_resources_ranks.end_date',
    ],
    'FROM'      => 'glpi_plugin_resources_employments',
    'LEFT JOIN' => [
        'glpi_plugin_resources_ranks' => [
            'ON' => [
                'glpi_plugin_resources_employments' => 'plugin_resources_ranks_id',
                'glpi_plugin_resources_ranks'       => 'id',
            ],
        ],
    ],
    'WHERE'     => [
        [
            'glpi_plugin_resources_employments.begin_date' => ['<', $date],
            [
                'OR' => [
                    ['glpi_plugin_resources_employments.end_date' => null],
                    ['glpi_plugin_resources_employments.end_date' => ['>', $date]],
                ],
            ],
            'NOT'                                          => [
                'glpi_plugin_resources_ranks.id' => null,
            ],
        ],
        $lapseOnRank(
            'glpi_plugin_resources_employments.begin_date',
            'glpi_plugin_resources_employments.end_date',
        ),
        $dbu->getEntitiesRestrictCriteria('glpi_plugin_resources_employments', '', '', true),
    ],
];

$criteriaEmploy = $criteriaEmploy + getNewOrderBy('entity', $columns);

foreach ($DB->request($criteriaEmploy) as $dataEmploy) {
    $dataAll[$row_num] = $dataEmploy;
    $row_num++;
}

$nbtot = count($dataAll);
if ($limit) {
    $start = (int) ($_GET["start"] ?? 0);
    if ($start >= $nbtot) {
        $start = 0;
    }
} else {
    $start = 0;
}

if ($nbtot == 0) {
    if (!$HEADER_LOADED) {
        Html::header($title, $_SERVER['PHP_SELF'], "utils", "report");
        Report::title();
    }
    echo "<div class='alert alert-danger center'>" . __('No results found') . "</div>";
    Html::footer();
} elseif ($output_type == Search::PDF_OUTPUT_PORTRAIT
    || $output_type == Search::PDF_OUTPUT_LANDSCAPE) {
    include(GLPI_ROOT . "/vendor/tecnickcom/tcpdf/examples/tcpdf_include.php");
} elseif ($output_type == Search::HTML_OUTPUT) {
    if (!$HEADER_LOADED) {
        Html::header($title, $_SERVER['PHP_SELF'], "utils", "report");
        Report::title();
    }
    echo "<div class='center'><table class='tab_cadre_fixe'>";
    echo "<tr><th>$title</th></tr>\n";
    echo "<tr class='tab_bg_2 center'><td class='center'>";
    echo "<form method='POST' action='" . htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8') . "?start=$start'>\n";

    $param = "";
    foreach ($_POST as $key => $val) {
        if (is_array($val)) {
            foreach ($val as $k => $v) {
                $name = $key . "[$k]";
                echo Html::hidden($name, ['value' => $v]);
                if (!empty($param)) {
                    $param .= "&";
                }
                $param .= $key . "[" . $k . "]=" . urlencode($v);
            }
        } else {
            echo Html::hidden($key, ['value' => $val]);
            if (!empty($param)) {
                $param .= "&";
            }
            $param .= "$key=" . urlencode($val);
        }
    }
    Dropdown::showOutputFormat();
    Html::closeForm();
    echo "</td></tr>";
    echo "</table></div>";

    Html::printPager($start, $nbtot, $_SERVER['PHP_SELF'], $param);
}

if ($nbtot > 0) {
    // The report merges two queries into $dataAll, so neither of them describes the table
    // printed below: the sizes come from the columns actually rendered and from the merged
    // row set.
    $nbcols = 10;
    $nbrows = $nbtot;
    $num = 1;
    $link = $_SERVER['PHP_SELF'];
    $order = 'ASC';
    $issort = false;

    echo Search::showHeader($output_type, $nbrows, $nbcols, true);

    echo Search::showNewLine($output_type);

    showTitle($output_type, $num, __('Entity'), 'entity', true);
    showTitle($output_type, $num, __('Type'), 'type');
    showTitle($output_type, $num, __('Surname'), 'name', true);
    showTitle($output_type, $num, __('First name'), 'firstname', true);
    showTitle($output_type, $num, _x('user', 'Administrative number'), 'registration_number', true);
    showTitle($output_type, $num, Rank::getTypeName(1), 'rankName', true);
    showTitle($output_type, $num, __('Arrival date', 'resources'), 'date_begin', true);
    showTitle($output_type, $num, __('Departure date', 'resources'), 'date_end', true);
    showTitle($output_type, $num, Rank::getTypeName(1) . " - " . __('Begin date'), 'begin_date', true);
    showTitle($output_type, $num, Rank::getTypeName(1) . " - " . __('End date'), 'end_date', true);

    echo Search::showEndLine($output_type);

    if ($limit) {
        $dataAll = array_slice($dataAll, $start, $limit);
    }

    // Escape raw DB values (resource identity, rank/profession labels) before output:
    // Search::showItem() concatenates its value straight into the <td>, and GLPI 10+
    // stores these fields unencoded, so a crafted value would otherwise run as HTML.
    $escape = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

    foreach ($dataAll as $key => $data) {
        $num = 1;
        echo Search::showNewLine($output_type);
        echo Search::showItem(
            $output_type,
            $escape(Dropdown::getDropdownName('glpi_entities', $data['entity'])),
            $num,
            $key,
        );
        if ($data['typeName'] == 'Resource') {
            $type = Resource::getTypeName(0);
            $link = Toolbox::getItemTypeFormURL(Resource::class);
        } elseif ($data['typeName'] == Employment::class) {
            $type = Employment::getTypeName(0);
            $link = Toolbox::getItemTypeFormURL(Employment::class);
        }

        echo Search::showItem($output_type, $type, $num, $key);

        $name = "<a href='" . $link . "?id=" . (int) $data["ID"] . "' target='_blank'>";
        if ($data["name"] == null) {
            $name .= "(" . (int) $data["ID"] . ")";
        } else {
            $name .= $escape($data["name"]);
        }
        $name .= "</a>";
        echo Search::showItem($output_type, $name, $num, $key);

        echo Search::showItem($output_type, $escape($data['firstname']), $num, $key);
        echo Search::showItem($output_type, $escape($data['registration_number']), $num, $key);

        $link1 = Toolbox::getItemTypeFormURL(Rank::class);
        $rankName = "<a href='" . $link1 . "?id=" . (int) $data["rankID"] . "' target='_blank'>"
            . $escape($data["rankName"]) . "</a>";
        echo Search::showItem($output_type, $rankName, $num, $key);

        echo Search::showItem($output_type, Html::convDate($data['date_begin']), $num, $key);
        echo Search::showItem($output_type, Html::convDate($data['date_end']), $num, $key);
        echo Search::showItem($output_type, Html::convDate($data['begin_date']), $num, $key);
        echo Search::showItem($output_type, Html::convDate($data['end_date']), $num, $key);
        echo Search::showEndLine($output_type);
    }

    echo Search::showFooter($output_type, $title);
}

if ($output_type == Search::HTML_OUTPUT) {
    Html::footer();
}

/**
 * Display the column title and allow the sort
 *
 * @param $output_type
 * @param $num
 * @param $title
 * @param $columnname
 * @param bool $sort
 * @return mixed
 */
function showTitle($output_type, &$num, $title, $columnname, $sort = false)
{
    if ($output_type != Search::HTML_OUTPUT || $sort == false) {
        echo Search::showHeaderItem($output_type, $title, $num);
        return;
    }
    $order = 'ASC';
    $issort = false;
    if (isset($_REQUEST['sort']) && $_REQUEST['sort'] == $columnname) {
        $issort = true;
        if (isset($_REQUEST['order']) && $_REQUEST['order'] == 'ASC') {
            $order = 'DESC';
        }
    }
    $link = $_SERVER['PHP_SELF'];
    $first = true;
    foreach ($_REQUEST as $name => $value) {
        if (!in_array($name, ['sort', 'order', 'PHPSESSID'])) {
            $link .= ($first ? '?' : '&amp;');
            $link .= $name . '=' . urlencode($value);
            $first = false;
        }
    }
    $link .= ($first ? '?' : '&amp;') . 'sort=' . urlencode($columnname);
    $link .= '&amp;order=' . $order;
    echo Search::showHeaderItem(
        $output_type,
        $title,
        $num,
        $link,
        $issort,
        ($order == 'ASC' ? 'DESC' : 'ASC'),
    );
}

/**
 * Build the "ORDER BY" criteria
 *
 * @param $default string, name of the column used by default
 * @param $columns array, the sortable columns of the report
 * @return array
 */
function getNewOrderBy($default, $columns)
{
    if (!isset($_REQUEST['order']) || $_REQUEST['order'] != 'DESC') {
        $_REQUEST['order'] = 'ASC';
    }
    $order = $_REQUEST['order'];

    $field = getOrderByFields($default, $columns);
    if (is_string($field) && $field !== '') {
        return ['ORDERBY' => $field . ' ' . $order];
    }
    return [];
}

/**
 * Get the fields used for order
 *
 * @param $default string, name of the column used by default
 *
 * @return array of column names
 */
function getOrderByFields($default, $columns)
{
    if (!isset($_REQUEST['sort'])) {
        $_REQUEST['sort'] = $default;
    }
    $colsort = $_REQUEST['sort'];

    foreach ($columns as $colname => $column) {
        if ($colname == $colsort) {
            return $column['sorton'];
        }
    }
    return [];
}
