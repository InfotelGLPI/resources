<?php

/*
 * @version $Id: employee.form.php 480 2012-11-09 tsmr $
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

$employee = new PluginResourcesEmployee();

//add employee informations from helpdesk
//next step : show list needs of the new ressource
if (isset($_POST["add_helpdesk_employee"])) {
   $newID = $employee->add($_POST);
   Html::redirect("./resource_item.list.php?id=".$_POST["plugin_resources_resources_id"]."&exist=0");
   
} else {
   //show form employee informations from helpdesk
   $employee->showFormHelpdesk($_GET["id"], 0);
}

Html::helpFooter();
?>