<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2022 by the resources Development Team.

 https://github.com/InfotelGLPI/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of resources.

 resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

use Glpi\Exception\Http\AccessDeniedHttpException;

include('../../../inc/includes.php');

Session::checkLoginUser();
if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}
$import = new PluginResourcesImport();

if (isset($_POST["add"])) {
   $import->check(-1, CREATE, $_POST);
   $import->add($_POST);
   Html::back();

} else if (isset($_POST["purge"])) {
   $import->check($_POST['id'], PURGE);
   $import->delete($_POST);
   $import->redirectToList();

} else if (isset($_POST["update"])) {
   $import->check($_POST['id'], UPDATE);
   $import->update($_POST);
   Html::back();

} else {

   $import->checkGlobal(READ);

   Html::header(PluginResourcesMenu::getTypeName(2), '', "admin", "pluginresourcesmenu");

   if ($import->canView()) {
      $import->showTitle(false);
      $import->display(['id' => $_GET['id']]);

   } else {
       throw new AccessDeniedHttpException();
   }
   Html::footer();
}
