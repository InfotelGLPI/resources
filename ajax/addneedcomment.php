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

include ('../../../inc/includes.php');
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

if (isset($_POST["id"])) {
   $items_id = $_POST["id"];
   $rand = $_POST["rand"];
   echo "<div id='addcommentneed$items_id$rand'class='center'>";
   echo Html::textarea([
                          'name'    => 'commentneed'.$items_id,
                          'cols'    => '30',
                          'rows'    => '3',
                          'display' => false,
                       ]);
   echo Html::hidden('id', ['value' => $items_id]);
   echo "</div>";
   echo "<div id='viewaccept$items_id'class='center'>";
   echo "<p>";
   $name = "updateneedcomment[".$items_id."]";
   echo Html::submit(_sx('button', 'Add'), ['name' => $name, 'class' => 'btn btn-primary']);
   echo "&nbsp;";
   echo Html::submit(_sx('button', 'Cancel'), ['name' => 'cancel', 'class' => 'btn btn-primary', 'onclick' => "hideAddForm$items_id();"]);
   echo "</div>";

} else {
   echo __("You don't have permission to perform this action.");
}


Html::ajaxFooter();

