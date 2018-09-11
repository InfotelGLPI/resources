<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2016 by the resources Development Team.

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

include('../../../inc/includes.php');

//show list of imports
if (Session::getCurrentInterface() == 'central' &&
    (!isset($_POST['exportCSV']) && !isset($_POST['exportPDF']))) {
   Html::header(PluginResourcesResource::getTypeName(2), '', "admin", "pluginresourcesresource");
} else if (!isset($_POST['exportCSV']) && !isset($_POST['exportPDF'])) {
   Html::helpHeader(PluginResourcesResource::getTypeName(2));
}

$import = new PluginResourcesImport();
if ($import->canView() || Session::haveRight("config", UPDATE)) {
   if (isset($_GET["actionImport"]) && !empty($_GET["actionImport"])) {
      $_SESSION["actionImport"] = $_GET["actionImport"];
      $action                   = $_SESSION["actionImport"];
   }
   if (isset($_POST['import'])) {
      if (isset($_POST['resource']['import'])) {
         foreach ($_POST['resource']['import'] as $idResourceImport => $numRow) {
            $import->processResources($_POST['resource']['values'][$idResourceImport], $_GET['actionImport']);
         }
         Html::back();
      } else {
         Session::addMessageAfterRedirect(__('No item selected', 'resources'), true, ERROR);
         Html::back();
      }
   } else if (isset($_POST['exportCSV'])) {
      if (isset($_POST['resource']['import'])) {
         foreach ($_POST['resource']['import'] as $idResourceImport => $numRow) {
            $datas[$idResourceImport] = $import->processResources($_POST['resource']['values'][$idResourceImport], "importIncoherencesCSV");
         }
         $import->array_download($datas, ";");
      } else {
         Session::addMessageAfterRedirect(__('No item selected', 'resources'), true, ERROR);
         Html::back();
      }
   } else if (isset($_POST['exportPDF'])) {
      if (isset($_POST['resource']['import'])) {
         foreach ($_POST['resource']['import'] as $idResourceImport => $numRow) {
            $datas[$idResourceImport] = $import->processResources($_POST['resource']['values'][$idResourceImport], "importIncoherencesPDF");
         }
         $import->array_download($datas, "");
      } else {
         Session::addMessageAfterRedirect(__('No item selected', 'resources'), true, ERROR);
         Html::back();
      }
   }
   $import->showListDatas();
} else {
   Html::displayRightError();
}

if (Session::getCurrentInterface() == 'central') {
   Html::footer();
} else {
   Html::helpFooter();
}
