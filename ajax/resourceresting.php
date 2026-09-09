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
use Glpi\Exception\Http\NotFoundHttpException;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\ResourceResting;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

// Every fragment below belongs to the non contract period wizard, which
// front/resourceresting.form.php gates on ResourceResting::canView(), that is on
// plugin_resources_resting: the plugin_resources right this file used to ask for is a
// different bit of a different profile right, and a session holding it alone had no business
// here. The config bypass is the one that controller grants alongside canView().
if (!ResourceResting::canView() && !Session::haveRight('config', UPDATE)) {
    throw new AccessDeniedHttpException();
}

if (isset($_POST['action'])) {
    $resting = new ResourceResting();
    switch ($_POST['action']) {
        case "loadResting":
            // Enforce per-record read (right + entity) before disclosing the
            // resource name and bench/resting periods; the global READ check
            // above does not restrict which resource id may be queried.
            $resources_id = (int) ($_POST['plugin_resources_resources_id'] ?? 0);
            $resource = new Resource();
            if ($resources_id <= 0 || !$resource->can($resources_id, READ)) {
                throw new NotFoundHttpException();
            }
            $resting->loadResting($resources_id);
            break;

        case "loadEndDateResting":
        case "loadButtonResting":
            // The id is echoed back into the hidden field that front/resourceresting.form.php
            // reads on addenddaterestingresources, and the global READ right posed above says
            // nothing about which period may be named here. ResourceResting carries no
            // entities_id of its own, so the owning Resource is what defines the boundary. A
            // zero id is the empty value of the dropdown and is left alone: the write branch
            // refuses it anyway.
            $resting_id = (int) ($_POST['plugin_resources_resting_id'] ?? 0);
            if ($resting_id > 0) {
                Resource::checkChildOwnership($resting, $resting_id, READ);
            }
            if ($_POST['action'] === 'loadEndDateResting') {
                $resting->loadEndDateResting($resting_id);
            } else {
                $resting->loadButtonResting($resting_id);
            }
            break;
    }
}
