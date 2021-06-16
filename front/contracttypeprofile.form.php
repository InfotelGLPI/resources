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

$contracttype = new PluginResourcesContracttypeprofile();
if (isset($_POST["addContracttype"])) {

      $contracttype->check(-1, CREATE, $_POST);
      if(isset($_POST["plugin_resources_contracttypes_id"])){
         $_POST["plugin_resources_contracttypes_id"] = json_encode($_POST["plugin_resources_contracttypes_id"]);
      }else{
         $_POST["plugin_resources_contracttypes_id"] = "[]";
      }
      if($contracttype->getFromDBByCrit(['profiles_id' => $_POST['profiles_id']])){
         $contracttype->update(['id'   => $contracttype->fields['id'],
                         'plugin_resources_contracttypes_id'   => $_POST['plugin_resources_contracttypes_id']]);
      } else{
         $contracttype->add(['plugin_resources_contracttypes_id'   => $_POST['plugin_resources_contracttypes_id'],
                      'profiles_id' => $_POST['profiles_id']]);
      }

      Html::back();

} else {
   Html::back();
}