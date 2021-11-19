<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2020 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

use Glpi\Event;

include ('../../../inc/includes.php');


$user      = new User();
$groupuser = new Group_User();

if (empty($_GET["id"]) && isset($_GET["name"])) {

   $user->getFromDBbyName($_GET["name"]);
   Html::redirect($user->getFormURLWithID($user->fields['id']));
}

if (empty($_GET["name"])) {
   $_GET["name"] = "";
}

if (isset($_POST["update"])) {
   $user->check($_POST['id'], UPDATE);
   $user->update($_POST);
   Event::log($_POST['id'], "users", 5, "setup",
              //TRANS: %s is the user login
              sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
   $resource        = new PluginResourcesResource();
   $resource->getFromDB($_POST['idResources']);
   NotificationEvent::raiseEvent('other', $resource);
   Html::back();

}  else {
   Html::back();
}
