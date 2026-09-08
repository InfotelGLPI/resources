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

// Direct access to file
use Glpi\DBAL\QueryExpression;
use GlpiPlugin\Resources\Resource;

global $DB;

if (strpos($_SERVER['PHP_SELF'], "dropdownResources.php")) {
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

if (!defined('GLPI_ROOT')) {
    die("Can not acces directly to this file");
}

if (empty($_GET)) {
    $_GET = $_POST;
}

Session::checkRight('plugin_resources', READ);
// Default view : Nobody
if (!isset($_GET['all'])) {
    $_GET['all'] = 0;
}

$used = [];

if (isset($_GET['used'])) {
    if (is_array($_GET['used'])) {
        $used = $_GET['used'];
    } else {
        $used = Toolbox::decodeArrayFromInput($_GET['used']);
    }
}

if (!isset($_GET['searchText'])) {
    $_GET['searchText'] = '';
}

$plugin_resources_contracttypes_id = 0;
if (isset($_GET["plugin_resources_contracttypes_id"]) &&
    $_GET["plugin_resources_contracttypes_id"] > 0) {
    $plugin_resources_contracttypes_id = $_GET["plugin_resources_contracttypes_id"];
}

$isNotLeavingOnly = false;
if (isset($_GET['condition']) && isset($_GET['condition']['is_not_leaving_only'])) {
    $isNotLeavingOnly = true;
}

// Entity scoping: $_GET['entity'] is a client-controlled list of entity ids reused both
// as the getSqlSearchResult()/getEntitiesRestrictCriteria() filter and in the raw
// "entities_id IN (...)" clause below. getEntitiesRestrictCriteria() trusts the ids as
// given (GLPI does not confine queries automatically), so without this intersection a
// user holding plugin_resources READ in one entity could force entity[]=<other entities>
// and enumerate the personnel/user directory across the whole entity tree. Confine the
// requested entities to the active session scope; fall back to the full active scope
// when the caller sends nothing valid.
$requested_entities = isset($_GET['entity']) ? (array) $_GET['entity'] : [];
$requested_entities = array_map('intval', $requested_entities);
$allowed_entities   = array_values(array_intersect($requested_entities, $_SESSION['glpiactiveentities'] ?? []));
if (empty($allowed_entities)) {
    $allowed_entities = $_SESSION['glpiactiveentities'] ?? [];
}
$_GET['entity'] = $allowed_entities;

$result = Resource::getSqlSearchResult(
    false,
    $_GET["entity"],
    $_GET['value2'],
    $used,
    $_GET['searchText'],
    false,
    $isNotLeavingOnly,
);

$users = [];
$logins = [];
$linkedUsers = [];

$dbu = new DbUtils();

// Add linked resource users
if (count($result)) {
    foreach ($result as $data) {
        $users[] = [
            'id' => $data["id"],
            'text' => $dbu->formatUserName(
                $data["id"],
                $data["username"],
                $data["name"],
                $data["firstname"],
            ),
        ];
        //      $logins[$data["id"]] = $data["name"];
        $linkedUsers[] = $data["userid"];
    }
}

// Add unlinked users
if ($_GET['addUnlinkedUsers'] ?? false) {
    // The entity list has been confined to the session scope above. An empty list therefore
    // means the session sees no entity at all and no user can match, which is also why the
    // query is skipped instead of being run: the builder rejects an empty IN(), and the string
    // concatenation it replaces used to emit "IN ()", a syntax error.
    if (!empty($_GET["entity"])) {
        // makeTextSearchValue() returns the LIKE pattern. The loose comparison below mirrors
        // makeTextSearch(), which also falls back to IS NULL on an empty pattern, so the
        // "NULL" keyword and an empty search string keep behaving as they did. A null
        // criterion is turned into IS NULL by the builder. Only the concatenation still needs
        // the raw fragment, see below.
        $search_value = Search::makeTextSearchValue($_GET['searchText']);
        $search_crit  = $search_value == null ? null : ['LIKE', $search_value];

        $criteria = [
            'FROM'  => 'glpi_users',
            'WHERE' => [
                'glpi_users.entities_id' => $_GET["entity"],
                'glpi_users.is_deleted'  => 0,
                [
                    'OR' => [
                        'glpi_users.name'                => $search_crit,
                        'glpi_users.firstname'           => $search_crit,
                        'glpi_users.realname'            => $search_crit,
                        'glpi_users.registration_number' => $search_crit,
                        // A function call cannot be a criteria key, so this last comparison
                        // stays an expression, built the way Resource::getSqlSearchResult()
                        // builds its own.
                        new QueryExpression(
                            'CONCAT(' . $DB->quoteName('glpi_users.name') . ', '
                            . $DB->quoteValue(' ') . ', '
                            . $DB->quoteName('glpi_users.firstname') . ', '
                            . $DB->quoteValue(' ') . ', '
                            . $DB->quoteName('glpi_users.registration_number') . ', '
                            . $DB->quoteValue(' ') . ', '
                            . $DB->quoteName('glpi_users.name') . ') '
                            . Search::makeTextSearch($_GET['searchText']),
                        ),
                    ],
                ],
            ],
        ];

        // Same reasoning for the exclusion list: no linked user means there is nothing to
        // exclude, not an empty NOT IN().
        $linked_users = array_values(array_unique(array_filter(array_map('intval', $linkedUsers))));
        if (!empty($linked_users)) {
            $criteria['WHERE']['glpi_users.id'] = ['NOT IN', $linked_users];
        }

        foreach ($DB->request($criteria) as $data) {
            $users[] = [
                'id' => 'users-' . $data["id"],
                'text' => $dbu->formatUserName(
                    $data["id"],
                    $data["name"],
                    $data["realname"],
                    $data["firstname"],
                ),
            ];
        }
    }
}

if (!function_exists('dpuser_cmp')) {
    /**
     * @param $a
     * @param $b
     *
     * @return int
     */
    function dpuser_cmp($a, $b)
    {
        return strcasecmp($a['text'], $b['text']);
    }
}

// Sort non case sensitive
usort($users, 'dpuser_cmp');

$ret['results'] = $users;
$ret['count'] = count($users);

echo json_encode($ret);
