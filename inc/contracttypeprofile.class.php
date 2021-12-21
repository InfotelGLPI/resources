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

/**
 * Class PluginResourcesContracttypeprofile
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginResourcesContracttypeprofile extends CommonDBTM {

   static $rightname = 'plugin_resources';
   var    $dohistory = true;

   /**
    * Add a category to profile
    * @global type $CFG_GLPI
    *
    * @param type  $profiles_id
    * @param type  $canedit
    */
   static function addContracttype($profiles_id, $canedit) {
      global $CFG_GLPI;
      if ($canedit) {

         echo "<form method='post' action='" . PLUGIN_RESOURCES_WEBDIR. "/front/contracttypeprofile.form.php" . "'>";
         echo Html::hidden('profiles_id', ['value' => $profiles_id]);

         echo "<table class='tab_cadre_fixe'>";
         echo "<tr class='tab_bg_1'><th colspan='4'>";
         echo __('Contract type authorization', 'resources');
         echo "</th></tr>";

         echo "<tr class='tab_bg_1'><td>";

         echo "<td>";
         echo __('Available contract type', 'resources');
         echo "</td><td>";
         $contracttypeprofile = new self();
         $plugin_resources_contracttypes_id = [];
         if($contracttypeprofile->getFromDBByCrit(['profiles_id' => $profiles_id])){
            $plugin_resources_contracttypes_id =  json_decode($contracttypeprofile->fields['plugin_resources_contracttypes_id']);
         }
         //         Group::dropdown(['entity' => $_SESSION['glpiactive_entity'],
         //                          'name'   => 'groups_id',
         //                          'value'  => $groups_id]);

         $dbu    = new DbUtils();
         $result = $dbu->getAllDataFromTable(PluginResourcesContractType::getTable());
         //         $pref = json_decode($groupprofile->fields['prefered_group']);

         $temp                         = [];
         $temp[0] = __("Without contract",'resources');
         foreach ($result as $item) {
            $temp[$item['id']] = $item['name'];
         }

         $params = [
            "name"                => 'plugin_resources_contracttypes_id',
            'entity'    => $_SESSION['glpiactive_entity'],
            "display"             => false,
            "multiple"            => true,
            "width"               => '200px',
            'values'              => isset($plugin_resources_contracttypes_id) ? $plugin_resources_contracttypes_id : [],
            'display_emptychoice' => true
         ];



         $dropdown = Dropdown::showFromArray("plugin_resources_contracttypes_id", $temp, $params);

         echo $dropdown;

         echo "</td></tr>";

         echo "<tr class='tab_bg_2'><td colspan='4' style='text-align:center'>";
         echo Html::submit(_sx('button', 'Save'), ['name' => 'addContracttype', 'class' => 'btn btn-primary']);
         echo "</td></tr>";

         echo "</table></div>";
         Html::closeForm();
      }
   }


}
