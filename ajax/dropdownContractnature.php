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

use GlpiPlugin\Resources\ContractNature;

if (strpos($_SERVER['PHP_SELF'], "dropdownContractnature.php")) {
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}

Session::checkRight('plugin_resources', READ);

// entity_restrict is client supplied and is the only entity scope of the dropdown query
// below: intersect it with the session scope, the way ajax/dropdownRole.php ignores the posted
// value altogether. An empty intersection means the caller asked for entities it cannot reach,
// so fall back to its own scope rather than to no restriction at all.
$entity_restrict = array_values(array_intersect(
    array_map('intval', (array) ($_POST['entity_restrict'] ?? [])),
    $_SESSION['glpiactiveentities'],
));
if ($entity_restrict === []) {
    $entity_restrict = $_SESSION['glpiactiveentities'];
}

//allow ContractNature's display depending on resource situation
$options = [
    'plugin_resources_resourcesituations_id' => $_POST['plugin_resources_resourcesituations_id'],
    'entity' => $entity_restrict,
    'rand' => $_POST['rand'],
];

ContractNature::showContractnature($options);
