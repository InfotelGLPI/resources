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

include ('../../../inc/includes.php');

//central or helpdesk access
if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
   Html::header(PluginResourcesResource::getTypeName(2), '', "admin", "pluginresourcesresource");
} else {
   Html::helpHeader(PluginResourcesResource::getTypeName(2));
}

$resource = new PluginResourcesResource();

if ($resource->canView() || Session::haveRight("config", UPDATE)) {
   if (Session::haveRight("plugin_resources_all", UPDATE)) {

      //Have right to see all resources
      //Have not right to see all resources
      echo "<div align='center'>";
//      echo "var account_window=new Ext.Window({
//               layout:'fit',
//               width:800,
//               height:400,
//               closeAction:'hide',
//               modal: true,
//               autoScroll: true,
//               title: \"".__('View by contract type', 'resources')."\",
//               autoLoad: '".$CFG_GLPI['root_doc']."/plugins/resources/ajax/resourcetree.php'
//            });";
//      echo "</script>";
      
      $script =  "cleanhide('modal_resource_content');";
      $script .= "var account_window=$('<div />').dialog({
               width:800,
               autoOpen: false,
               height:400,
               modal: true,
               title: \"".__('View by contract type', 'resources')."\",
               open: function (){
                  $(this).load('".$CFG_GLPI['root_doc']."/plugins/resources/ajax/resourcetree.php');
               }
            });";
      echo Html::scriptBlock($script);
      
      echo "<a onclick=\"account_window.dialog('open');\" href=\"#modal_resource_content\"title=\"".
      __('View by contract type', 'resources')."\">".
      __('View by contract type', 'resources')."</a>";
      echo "</div>";
   }

   Search::show("PluginResourcesResource", $_GET);
   
} else {
   Html::displayRightError();
}

if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
   Html::footer();
} else {
   Html::helpFooter();
}
?>