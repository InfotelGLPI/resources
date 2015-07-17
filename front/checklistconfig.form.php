<?php

/*
 * @version $Id: checklistconfig.form.php 480 2012-11-09 tsmr $
  -------------------------------------------------------------------------
  Resources plugin for GLPI
  Copyright (C) 2006-2012 by the Resources Development Team.

  https://forge.indepnet.net/projects/resources
  -------------------------------------------------------------------------

  LICENSE

  This file is part of Resources.

  Resources is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  Resources is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with Resources. If not, see <http://www.gnu.org/licenses/>.
  --------------------------------------------------------------------------
 */

include ('../../../inc/includes.php');

if (!isset($_GET["id"]))
   $_GET["id"] = "";

$checklistconfig = new PluginResourcesChecklistconfig();

if (isset($_POST["add"])) {
   $checklistconfig->check(-1, UPDATE, $_POST);
   $newID = $checklistconfig->add($_POST);
   Html::back();
   
} else if (isset($_POST["delete"])) {
   $checklistconfig->check($_POST['id'], UPDATE);
   $checklistconfig->delete($_POST);
   $checklistconfig->redirectToList();
   
} else if (isset($_POST["restore"])) {
   $checklistconfig->check($_POST['id'], UPDATE);
   $checklistconfig->restore($_POST);
   $checklistconfig->redirectToList();
   
} else if (isset($_POST["purge"])) {
   $checklistconfig->check($_POST['id'], UPDATE);
   $checklistconfig->delete($_POST, 1);
   $checklistconfig->redirectToList();
   
} else if (isset($_POST["update"])) {
   $checklistconfig->check($_POST['id'], UPDATE);
   $checklistconfig->update($_POST);
   Html::back();
   
} else {
   $checklistconfig->checkGlobal(READ);
   Html::header(PluginResourcesResource::getTypeName(2), '', "admin", "pluginresourcesresource", "pluginresourceschecklist");
   $options = array();
   $checklistconfig->showForm($_GET["id"], $options);
   Html::footer();
}
?>