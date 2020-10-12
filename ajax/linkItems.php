<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 databases plugin for GLPI
 Copyright (C) 2009-2016 by the databases Development Team.

 https://github.com/InfotelGLPI/databases
 -------------------------------------------------------------------------

 LICENSE

 This file is part of databases.

 databases is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 databases is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with databases. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (strpos($_SERVER['PHP_SELF'], "linkItems.php")) {
   $AJAX_INCLUDE = 1;
   include('../../../inc/includes.php');
   header("Content-Type: text/html; charset=UTF-8");
   Html::header_nocache();
}

Session::checkCentralAccess();

if (isset($_POST["type"]) && isset($_POST["current_type"])) {
   $values = [];
   $data   = [];
   if ($_POST["type"] != "0" && $_POST["type"] != "" && $_POST["type"] != "ALL") {
      if ($_POST['type'] == $_POST['current_type'] && isset($_POST["values"])) {
         $values = $_POST['values'];
      }
      $dbu = new DbUtils();


      $item      = new $_POST["type"]();
      $condition = $dbu->getEntitiesRestrictCriteria($item->getTable());
      $items     = $item->find();


      foreach ($items as $vals) {
         if ($_POST["type"] == User::getType()) {
            $item->getFromDB($vals["id"]);
            $data[$vals["id"]] = $item->getFriendlyName();
         } else {
            $data[$vals["id"]] = $vals["name"];
         }
      }
   }

   Dropdown::showFromArray("items", $data, ['id'       => 'target',
                                             'multiple' => true,
                                             'values'   => (is_array($values) ? $values : []),
                                             "display"  => true]);
}