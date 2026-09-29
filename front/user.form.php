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

use Glpi\Event;
use Glpi\Exception\Http\NotFoundHttpException;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\User as ResourceUser;

// Every branch of this controller reads or writes a core user, and the tab it backs is gated
// on \User::canView() (src/User.php:102): pose the same right here. Without it the login
// resolution below was reachable by any authenticated session, including a self service one.
Session::checkRight(User::$rightname, READ);

$user = new User();
$groupuser = new Group_User();

if (empty($_GET["id"]) && isset($_GET["name"])) {
    // getFromDBbyName() leaves $this->fields empty when no such login exists, so
    // $user->fields['id'] used to read an undefined key and redirect without an id: the shape
    // of the answer told the caller whether the login it guessed exists, and gave away its
    // internal id when it does.
    if (!$user->getFromDBbyName($_GET["name"])) {
        throw new NotFoundHttpException();
    }
    Html::redirect($user->getFormURLWithID($user->fields['id']));
}

if (empty($_GET["name"])) {
    $_GET["name"] = "";
}

if (isset($_POST["update"])) {
    $user->check($_POST['id'], UPDATE);
    // idResource is a second identifier, uncorrelated with the user authorised above.
    // It drives the enumeration of the linked tickets and the ITILSolution::add() of
    // Resource::solveOpenTickets() below,
    // and nothing downstream re-checks it: ITILSolution::prepareInputForAdd() only
    // validates that the parent exists and enforces no right on the ticket, so this
    // controller is the only gate on that path. Authorise it before anything is written.
    $resource = new Resource();
    $resource->check((int) ($_POST['idResource'] ?? 0), UPDATE);
    $user->update($_POST);
    Event::log(
        $_POST['id'],
        "users",
        5,
        "setup",
        //TRANS: %s is the user login
        sprintf(__('%s updates an item'), $_SESSION["glpiname"]),
    );
    $resource->solveOpenTickets(ResourceUser::buildSolutionContent($_POST));
    Html::back();
} else {
    Html::back();
}
