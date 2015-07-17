<?php

/*
 * @version $Id: resource_item.list.php 480 2012-11-09 tsmr $
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

//from helpdesk
Html::helpHeader(PluginResourcesResource::getTypeName(2));

$choice = new PluginResourcesChoice();
$resource = new PluginResourcesResource();

//add items needs from helpdesk
if (isset($_POST["addhelpdeskitem"])) {
   if ($_POST['plugin_resources_choiceitems_id'] > 0
           && $_POST['plugin_resources_resources_id'] > 0) {
      if ($resource->canCreate())
         $choice->addHelpdeskItem($_POST);
   }
   Html::back();
}
//delete items needs from helpdesk
else if (isset($_POST["deletehelpdeskitem"])) {
   if ($resource->canCreate())
      $choice->delete(array('id' => $_POST["id"]));
   Html::back();

//next step : email and finish resource creation
} else if (isset($_POST["finish"])) {
   $resource->redirectToList();
   
} else if (isset($_POST["updateneedcomment"])) {
   if ($resource->canCreate())
      foreach ($_POST["updateneedcomment"] as $key => $val) {
         $varcomment = "commentneed".$key;
         $values['id'] = $key;
         $values['commentneed'] = $_POST[$varcomment];
         $choice->addNeedComment($values);
      }
   Html::back();
   
} else {
   //show form items needs from helpdesk
   if ($resource->canView() || Session::haveRight("config", UPDATE)) {
      $choice->showItemHelpdesk($_GET["id"], $_GET["exist"]);
   }
}

Html::helpFooter();
?>