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

// A JSON document on every path, the script being reached directly or included. Declared as
// text/html, a browser opening the URL would render the names it carries as markup.
if (!headers_sent()) {
    header("Content-Type: application/json; charset=UTF-8");
    Html::header_nocache();
}

Session::checkRight(Resource::$rightname, READ);

// Its only caller, the select2 of Resource::dropdown() (Html::jsAjaxDropdown()), posts its
// parameters, as the core dropdown endpoint expects too: a plain link opened by navigation
// gets nothing.
$params = $_POST;

$used = [];

if (isset($params['used'])) {
    if (is_array($params['used'])) {
        $used = $params['used'];
    } else {
        $used = Toolbox::decodeArrayFromInput($params['used']);
    }
}

$search_text = is_string($params['searchText'] ?? null) ? $params['searchText'] : '';

$isNotLeavingOnly = false;
if (isset($params['condition']) && is_array($params['condition']) && isset($params['condition']['is_not_leaving_only'])) {
    $isNotLeavingOnly = true;
}

// Entity scoping: $params['entity'] is a client-controlled list of entity ids reused as the
// getSqlSearchResult()/getEntitiesRestrictCriteria() filter. getEntitiesRestrictCriteria()
// trusts the ids as given (GLPI does not confine queries automatically), so without this
// intersection a user holding plugin_resources READ in one entity could force
// entity[]=<other entities> and enumerate the personnel directory across the whole entity
// tree. Confine the requested entities to the active session scope; fall back to the full
// active scope when the caller sends nothing valid.
$requested_entities = isset($params['entity']) ? (array) $params['entity'] : [];
$requested_entities = array_map('intval', $requested_entities);
$allowed_entities   = array_values(array_intersect($requested_entities, $_SESSION['glpiactiveentities'] ?? []));
if (empty($allowed_entities)) {
    $allowed_entities = $_SESSION['glpiactiveentities'] ?? [];
}

// Resources only. The former "addUnlinkedUsers" branch also listed glpi_users, matching on
// logins and registration numbers, on the resource right alone: no caller enabled it.
$result = Resource::getSqlSearchResult(
    false,
    $allowed_entities,
    $params['value2'] ?? 0,
    $used,
    $search_text,
    false,
    $isNotLeavingOnly,
);

$users = [];

$dbu = new DbUtils();

foreach ($result as $data) {
    // Raw text: the select2 templates of the core escape it on display
    $users[] = [
        'id' => $data["id"],
        'text' => $dbu->formatUserName(
            $data["id"],
            $data["username"],
            $data["name"],
            $data["firstname"],
        ),
    ];
}

// Sort non case sensitive
usort($users, static fn($a, $b) => strcasecmp($a['text'], $b['text']));

$ret = [
    'results' => $users,
    'count'   => count($users),
];

// The hex flags keep the body inert even if it were sniffed as HTML: <, >, &, ' and " are
// written as \u escapes, which JSON.parse() turns back into the same characters.
echo json_encode($ret, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
