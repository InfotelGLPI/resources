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

use GlpiPlugin\Resources\Budget;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}

$budget = new Budget();

if (isset($_POST["add"])) {
    // The posted values have to reach the guard. CommonDBTM::can() only copies them into the
    // object under "if (is_array($input))"; without them the object stays the one getEmpty()
    // built, whose entities_id is the active entity of the session, and canCreateItem() then
    // validates that entity instead of the submitted one. add() does not reimpose it either,
    // so a row could be created in an entity the user has no access to.
    $budget->check(-1, CREATE, $_POST);
    $newID = $budget->add($_POST);

    Html::back();
} elseif (isset($_POST["update"])) {
    $budget->check($_POST["id"], UPDATE);
    $budget->update($_POST);

    Html::back();
} elseif (isset($_POST["delete"])) {
    // glpi_plugin_resources_budgets carries no is_deleted column, so maybeDeleted() is
    // false: CommonDBTM::getRights() never publishes a DELETE bit for this itemtype and
    // delete() is a hard delete. PURGE is the bit that matches what happens here, and it
    // is the one the profile form actually offers; UPDATE let anyone who could merely
    // edit a budget destroy it.
    $budget->check($_POST["id"], PURGE);
    $budget->delete($_POST);

    $budget->redirectToList();
} elseif (isset($_POST["purge"])) {
    $budget->check($_POST['id'], PURGE);
    $budget->delete($_POST, 1);

    $budget->redirectToList();
} elseif (isset($_POST["restore"])) {
    $budget->check($_POST["id"], UPDATE);
    $budget->restore($_POST);

    $budget->redirectToList();
} else {
    $budget->checkGlobal(READ);
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class, strtolower(Budget::getType()));
    $budget->display(['id' => $_GET["id"]]);
    Html::footer();
}
