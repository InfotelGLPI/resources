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

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Resources\Employer;

if (strpos($_SERVER['PHP_SELF'], "dropdownLocation.php")) {
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}
Session::checkRight('plugin_resources', READ);

$employers_id = (int) ($_POST['plugin_resources_employers_id'] ?? 0);
if ($employers_id > 0) {
    $employer = new Employer();
    // Employer is entity assigned and the id is posted: resolve the record and confine it to
    // the session scope, instead of reading fields off an object getFromDB() may have left
    // empty.
    if (!$employer->getFromDB($employers_id)
        || !Session::haveAccessToEntity(
            $employer->fields['entities_id'],
            $employer->fields['is_recursive'],
        )) {
        throw new AccessDeniedHttpException();
    }

    $locationId = (int) $employer->fields["locations_id"];
    if ($locationId > 0) {
        // GLPI 11 stores dropdown names raw, and getDropdownName() delegates to
        // getTreeValueCompleteName(), which concatenates completename, alias and code without
        // escaping anything. This response is served as text/html and injected into the
        // resource form as is, so whatever a location name carries would run in the session
        // that opens that form: escape at the sink.
        echo htmlescape(Dropdown::getDropdownName('glpi_locations', $locationId));
    } else {
        echo htmlescape(_x('periodicity', 'None'));
    }
}
