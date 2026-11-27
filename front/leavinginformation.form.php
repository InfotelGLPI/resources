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

use GlpiPlugin\Resources\LeavingInformation;
use GlpiPlugin\Resources\Profile;
use GlpiPlugin\Resources\Resource;

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}
$leavingReason = new LeavingInformation();

// The leaving information tab is only shown with this right: the form it posts to is not
// meant to be reachable without it.
Session::checkRight(Profile::RIGHT_LEAVINGINFORMATION, READ);

// The rights below are answered by the resource the row belongs to (see
// ResourceChildItemRightTrait), and the writes use the id checked, never the posted value.
if (isset($_POST["add"])) {
    $leavingReason->check(-1, CREATE, $_POST);
    $leavingReason->add($_POST);
    Html::back();
} elseif (isset($_POST["purge"])) {
    $leavinginformations_id = Resource::checkPostedItem($leavingReason, $_POST['id'] ?? null, PURGE);
    $leavingReason->delete(['id' => $leavinginformations_id]);
    Html::back();
} elseif (isset($_POST["update"])) {
    $leavinginformations_id = Resource::checkPostedItem($leavingReason, $_POST['id'] ?? null, UPDATE);
    $leavingReason->update(['id' => $leavinginformations_id] + $_POST);
    Html::back();
}
