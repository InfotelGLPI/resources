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

//Options for GLPI 0.71 and newer : need slave db to access the report
use GlpiPlugin\Reports\ArrayCriteria;
use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\GroupCriteria;
use GlpiPlugin\Resources\Resource;

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

// GLPI 11 routes every request through the Symfony front controller, so $_SERVER['PHP_SELF']
// resolves to the router entry point instead of this script: the filter form and the pagination
// links used to send the user back to the GLPI root, losing the criteria. Build the target from
// the plugin web path, which is a server side value and reflects nothing from the request.
$report_target = PLUGIN_RESOURCES_WEBDIR . '/report/checkgroup/checkgroup.php';

$report = new AutoReport(__("Report listing the groups not included in the resource's permissions", "resources"));

//Report's search criterias
$tab = [
    0 => __('No'),
    1 => __('Yes'),
];
$filter1 = new ArrayCriteria($report, 'groupsN0', __('Display N0 Groups'), $tab);
$tab = [
    0 => __('No'),
    1 => __('Yes'),
];
$filter2 = new ArrayCriteria($report, 'groupsN1', __('Display N1 Groups'), $tab);
$tab = [
    0 => __('No'),
    1 => __('Yes'),
];
$filter3 = new ArrayCriteria($report, 'groupsN2', __('Display N2 Groups'), $tab);

$filter4 = new GroupCriteria($report, 'groups_id', __('Filter by Groups'));

//Display criterias form is needed
$report->displayCriteriasForm();

//colname with sort allowed
$columns = [
    'entity' => ['sorton' => 'entity'],
    'name' => ['sorton' => 'name'],
    'firstname' => ['sorton' => 'firstname'],
    'registration_number' => ['sorton' => 'registration_number'],
    'rank' => ['sorton' => 'rank'],
    'date_begin' => ['sorton' => 'date_begin'],
    'date_end' => ['sorton' => 'date_end'],
    'begin_date' => ['sorton' => 'begin_date'],
    'end_date' => ['sorton' => 'end_date'],
];

$output_type = Search::HTML_OUTPUT;

// Form validate
if ($report->criteriasValidated()) {
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
    // Cast to int: this criterion value comes from the submitted report form and is used
    // below as a group id, both as a filter flag and as a query criterion.
    $group = (int) $filter4->getParameterValue();
    $criteriaResourceUser = [
        'SELECT'    => [
            'glpi_plugin_resources_resources.*',
            'glpi_users.id AS glpi_users_id',
        ],
        'FROM'      => 'glpi_plugin_resources_resources',
        'LEFT JOIN' => [
            'glpi_plugin_resources_resources_items' => [
                'ON' => [
                    'glpi_plugin_resources_resources_items' => 'plugin_resources_resources_id',
                    'glpi_plugin_resources_resources'       => 'id',
                    [
                        'AND' => ['glpi_plugin_resources_resources_items.itemtype' => 'User'],
                    ],
                ],
            ],
            'glpi_users'                            => [
                'ON' => [
                    'glpi_plugin_resources_resources_items' => 'items_id',
                    'glpi_users'                            => 'id',
                    [
                        'AND' => ['glpi_plugin_resources_resources_items.itemtype' => 'User'],
                    ],
                ],
            ],
        ],
        'WHERE'     => [
            'glpi_plugin_resources_resources.is_deleted'  => 0,
            'glpi_plugin_resources_resources.is_template' => 0,
            'glpi_plugin_resources_resources.is_leaving'  => 0,
            // Nested rather than merged with "+": getEntitiesRestrictCriteria() can return an
            // "OR" key (recursive entities) or a bare QueryExpression under key 0, which a
            // union would drop.
            $dbu->getEntitiesRestrictCriteria('glpi_plugin_resources_resources', '', '', true),
        ],
        'ORDERBY'   => 'glpi_plugin_resources_resources.id ASC',
    ];

    // The group filter is optional: only then is the membership table joined.
    if ($group != 0) {
        $criteriaResourceUser['LEFT JOIN']['glpi_groups_users'] = [
            'ON' => [
                'glpi_users'        => 'id',
                'glpi_groups_users' => 'users_id',
            ],
        ];
        $criteriaResourceUser['WHERE']['glpi_groups_users.groups_id'] = $group;
    }

    $dataAll = [];

    $display_habilitation = [];

    foreach ($DB->request($criteriaResourceUser) as $data) {
        $habilitations = [];
        $groups = [];
        if (!empty($data['glpi_users_id'])) {
            $users_id = $data['glpi_users_id'];
            $resources_id = $data['id'];

            $resourceIterator = $DB->request([
                'SELECT' => 'date_end',
                'FROM'   => 'glpi_plugin_resources_resources',
                'WHERE'  => ['id' => $resources_id],
            ]);
            $date_end = $resourceIterator->current()['date_end'] ?? null;

            $habilitationIterator = $DB->request([
                'SELECT'    => 'glpi_plugin_resources_habilitations.*',
                'FROM'      => 'glpi_plugin_resources_resourcehabilitations',
                'LEFT JOIN' => [
                    'glpi_plugin_resources_habilitations' => [
                        'ON' => [
                            'glpi_plugin_resources_habilitations'         => 'id',
                            'glpi_plugin_resources_resourcehabilitations' => 'plugin_resources_habilitations_id',
                        ],
                    ],
                ],
                'WHERE'     => [
                    'glpi_plugin_resources_resourcehabilitations.plugin_resources_resources_id' => $resources_id,
                ],
            ]);

            foreach ($habilitationIterator as $data_habilitation) {
                $test_hab = explode("-", $data_habilitation['name']);
                if ($filter1->getParameterValue() && (isset($test_hab[1]) && $test_hab[1] == "N0")) {
                    $habilitations[$data_habilitation['id']] = $data_habilitation['name'];
                }
                if ($filter2->getParameterValue() && (isset($test_hab[1]) && $test_hab[1] == "N1")) {
                    $habilitations[$data_habilitation['id']] = $data_habilitation['name'];
                }
                if ($filter3->getParameterValue() && (isset($test_hab[1]) && $test_hab[1] == "N2")) {
                    $habilitations[$data_habilitation['id']] = $data_habilitation['name'];
                }
            }

            $groupIterator = $DB->request([
                'SELECT'    => 'glpi_groups.*',
                'FROM'      => 'glpi_groups_users',
                'LEFT JOIN' => [
                    'glpi_groups' => [
                        'ON' => [
                            'glpi_groups'       => 'id',
                            'glpi_groups_users' => 'groups_id',
                        ],
                    ],
                ],
                'WHERE'     => ['glpi_groups_users.users_id' => $users_id],
            ]);
            foreach ($groupIterator as $data_group) {
                $test_group_level = explode("-", $data_group['name']);
                if ($filter1->getParameterValue() && (isset($test_group_level[1]) && $test_group_level[1] == "N0")) {
                    $groups[$data_group['id']] = $data_group['name'];
                }
                if ($filter2->getParameterValue() && (isset($test_group_level[1]) && $test_group_level[1] == "N1")) {
                    $groups[$data_group['id']] = $data_group['name'];
                }
                if ($filter3->getParameterValue() && (isset($test_group_level[1]) && $test_group_level[1] == "N2")) {
                    $groups[$data_group['id']] = $data_group['name'];
                }
            }

            $display_habilitation = array_diff($groups, $habilitations);

            if (count($display_habilitation) > 0) {
                $dataAll[] = [
                    'resources_id' => $resources_id,
                    'resources_date_end' => $date_end,
                    'users_id' => $users_id,
                    'groups' => $groups,
                    'diff' => $display_habilitation,
                ];
            }
        }
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
            Html::header($title, $report_target, "utils", "report");
            Report::title();
        }
        echo "<div class='alert alert-danger center'><span style='color : red;font-weight:bold;'>" . __(
            'No results found',
        ) . "</span></div>";
        Html::footer();
    } elseif ($output_type == Search::PDF_OUTPUT_PORTRAIT || $output_type == Search::PDF_OUTPUT_LANDSCAPE) {
        include(GLPI_ROOT . "/vendor/tecnickcom/tcpdf/examples/tcpdf_include.php");
    } elseif ($output_type == Search::HTML_OUTPUT) {
        if (!$HEADER_LOADED) {
            Html::header($title, $report_target, "utils", "report");
            Report::title();
        }
        echo "<div class='center'><table class='tab_cadre_fixe'>";
        echo "<tr><th>$title</th></tr>\n";
        echo "<tr class='tab_bg_2 center'><td class='center'>";
        echo "<form method='POST' action='" . htmlescape($report_target) . "?start=$start'>\n";

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

        Html::printPager($start, $nbtot, $report_target, $param);
    }

    if ($nbtot > 0) {
        $nbcols = 4;
        $nbrows = count($dataAll);
        $num = 1;
        $link = $report_target;
        $order = 'ASC';
        $issort = false;

        echo Search::showHeader($output_type, $nbrows, $nbcols, true);

        echo Search::showNewLine($output_type);

        echo Search::showHeaderItem($output_type, Resource::getTypeName(1), $num);
        echo Search::showHeaderItem($output_type, Location::getTypeName(1), $num);
        echo Search::showHeaderItem($output_type, __('Departure date', 'resources'), $num);
        echo Search::showHeaderItem($output_type, __('Group'), $num);
        echo Search::showHeaderItem($output_type, User::getTypeName(1), $num);
        echo Search::showHeaderItem($output_type, __('Login'), $num);
        echo Search::showHeaderItem($output_type, __('Missing habilitation', 'resources'), $num);

        echo Search::showEndLine($output_type);

        if ($limit) {
            $dataAll = array_slice($dataAll, $start, $limit);
        }

        foreach ($dataAll as $key => $data) {
            if (!empty($data['diff'])) {
                echo Search::showNewLine($output_type);
                $resource = new Resource();
                $resource->getFromDB($data['resources_id']);

                echo Search::showItem($output_type, $resource->getLink(), $num, $key);
                echo Search::showItem(
                    $output_type,
                    Dropdown::getDropdownName(
                        'glpi_locations',
                        $resource->getField('locations_id'),
                    ),
                    $num,
                    $key,
                );
                echo Search::showItem($output_type, Html::convDate($data["resources_date_end"]), $num, $key);
                // Escape raw DB values (group/habilitation labels, user login) before output:
                // GLPI 10+ stores them unencoded, so a crafted label would otherwise run as HTML.
                $escape = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
                echo Search::showItem($output_type, implode('<br>', array_map($escape, $data['groups'])), $num, $key);
                $user = new User();
                $user->getFromDB($data['users_id']);
                echo Search::showItem($output_type, $user->getLink(), $num, $key);
                echo Search::showItem($output_type, $escape($user->getField('name')), $num, $key);
                echo Search::showItem($output_type, implode('<br>', array_map($escape, $data['diff'])), $num, $key);

                echo Search::showEndLine($output_type);
            }
        }

        echo Search::showFooter($output_type, $title);
    }
}
if ($output_type == Search::HTML_OUTPUT) {
    Html::footer();
}
