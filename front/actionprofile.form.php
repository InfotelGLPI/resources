<?php
/*
 -------------------------------------------------------------------------
 Resources plugin for GLPI
 Copyright (C) 2015 by the Resources Development Team.
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

include('../../../inc/includes.php');

$actionprofile = new PluginResourcesActionprofile();
if (isset($_POST["addAction"])) {

      $actionprofile->check(-1, CREATE, $_POST);
      if(isset($_POST["actions_id"])){
         $_POST["actions_id"] = json_encode($_POST["actions_id"]);
      }else{
         $_POST["actions_id"] = "[]";
      }
      if($actionprofile->getFromDBByCrit(['profiles_id' => $_POST['profiles_id']])){
         $actionprofile->update(['id'   => $actionprofile->fields['id'],
                         'actions_id'   => $_POST['actions_id']]);
      } else{
         $actionprofile->add(['actions_id'   => $_POST['actions_id'],
                      'profiles_id' => $_POST['profiles_id']]);
      }

      Html::back();

} else {
   Html::back();
}