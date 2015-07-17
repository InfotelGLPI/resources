<?php
/*
 * @version $Id: addneedcomment.php 480 2012-11-09 tsmr $
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
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

if (isset($_POST["id"])) {
   $items_id = $_POST["id"];
   $rand = $_POST["rand"];
   echo "<div id='addcommentneed$items_id$rand'class='center'>";
   echo "<textarea cols='30' rows='3' name='commentneed$items_id'></textarea>";
   echo "<input type='hidden' name='id' value='".$items_id ."'>";
   echo "</div>";
   echo "<div id='viewaccept$items_id'class='center'>";
   echo "<p><input type='submit' name='updateneedcomment[".$items_id."]' value=\"".
         _sx('button','Add')."\" class='submit'>";
   echo "&nbsp;<input type='button' onclick=\"hideAddForm$items_id();\" value=\"".
         _sx('button','Cancel')."\" class='submit'></p>";
   echo "</div>";
      
} else {
   _e("You don't have permission to perform this action.");
}


Html::ajaxFooter();

?>