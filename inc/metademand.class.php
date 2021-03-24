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


if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}


/**
 * Class PluginResourcesMetademand
 */
class PluginResourcesMetademand extends CommonGLPI {

   static $rightname = 'plugin_metademands';

   var $dohistory = false;

   /**
    * @return array
    */
   static function addFieldItems() {

      return ['PluginResourcesResource',
      ];
   }

   /**
    * @return array
    */
   static function addDropdownFieldItems() {

      return [PluginResourcesResource::getTypeName(2)=>['PluginResourcesResource'=>PluginResourcesResource::getTypeName()]];
//		return ['PluginResourcesResource',
//		];
   }

   /**
    * @return array
    */
   static function getFieldItemsName() {

      $prefix = _n('Human Resource', 'Human Resources', 2, 'resources') . " - ";
      return ['PluginResourcesResource' => $prefix . PluginResourcesResource::getTypeName(1),
      ];
   }

   /**
    * @return array
    */
   static function getFieldItemsType() {

      return ['PluginResourcesResource' => 'dropdown',
      ];
   }
   /**
    * @return array
    */
   static function getParamsOptions($p) {
      $params = [];
      $linkmeta = new PluginResourcesLinkmetademand();
      if(!$linkmeta->getFromDBByCrit(["plugin_metademands_fields_id"=>$p["plugin_metademands_fields_id"],"plugin_metademands_metademands_id"=>$p["plugin_metademands_metademands_id"]])){
         $linkmeta->getEmpty();
      }
      $params['checklist_in'] = PluginMetademandsField::_unserialize($linkmeta->fields["checklist_in"]);
      if (!isset($params['checklist_in'][$p["nbOpt"]])) {
         $params['checklist_in'] = "";
      } else {
         $params['checklist_in'] = $params['checklist_in'][$p["nbOpt"]];
      }

      $params['checklist_out'] = PluginMetademandsField::_unserialize($linkmeta->fields["checklist_out"]);
      if (!isset($params['checklist_out'][$p["nbOpt"]])) {
         $params['checklist_out'] = "";
      } else {
         $params['checklist_out'] = $params['checklist_out'][$p["nbOpt"]];
      }

      return $params;
   }


   /**
    * @return string
    */
   static function showOptions($p) {
      $res ="";
      if ($p["hidden"]) {
         $res .= "<tr><td>";
         $res .= __('Link a checklist in', 'resources');
         $res .= '</br><span class="metademands_wizard_comments">' . __('If the value selected equals the value to check, the checklist in will be add', 'resources') . '</span>';
         $res .= '</td>';
         $res .= "<td>";
         $res .= PluginResourcesLinkmetademand::showChecklistInDropdown($p["plugin_metademands_metademands_id"], $p['checklist_in'], $p["plugin_metademands_fields_id"], false);
         $res .= "</td></tr>";

         $res .= "<tr><td>";
         $res .= __('Link a checklist out', 'resources');
         $res .= '</br><span class="metademands_wizard_comments">' . __('If the value selected equals the value to check, the checklist out will be add', 'resources') . '</span>';
         $res .= '</td>';
         $res .= "<td>";
         $res .= PluginResourcesLinkmetademand::showChecklistOutDropdown($p["plugin_metademands_metademands_id"], $p['checklist_out'], $p["plugin_metademands_fields_id"], false);
         $res .= "</td></tr>";
      }
      return $res;
   }

   /**
    * @return string
    */
   static function saveOptions($p) {

      $input["check_value"] = $_POST["check_value"];
      $linkmeta             = new PluginResourcesLinkmetademand();
      if (isset($_POST["checklist_in"])) {
         $input["checklist_in"] = PluginMetademandsField::_serialize($_POST["checklist_in"]);
      }
      if (isset($_POST["checklist_out"])) {
         $input["checklist_out"] = PluginMetademandsField::_serialize($_POST["checklist_out"]);
      }
      if ($linkmeta->getFromDBByCrit(["plugin_metademands_fields_id" => $_POST["id"], "plugin_metademands_metademands_id" => $_POST["plugin_metademands_metademands_id"]])) {
         $input["id"] = $linkmeta->getID();
         $linkmeta->update($input);
      } else {
         $input["plugin_metademands_fields_id"]      = $_POST["id"];
         $input["plugin_metademands_metademands_id"] = $_POST["plugin_metademands_metademands_id"];
         $linkmeta->add($input);
      }

   }

   /**
    * @return string
    */
   static function afterCreateTicket($p){
      $options = $p["options"];
      $values = $p["values"];
      $line = $p["line"];
      if(plugin::isPluginActive('resources')){
         if(isset($options["resources_id"])){
            $checklistConfig = new PluginResourcesChecklistconfig();
            $resource = new PluginResourcesResource();
            $resource->getFromDB($options["resources_id"]);
            if(count($line["form"])){
               foreach ($line["form"] as $id => $v){
                  if(isset($values["fields"]) && is_array($values["fields"]) && array_key_exists ($v["id"],$values["fields"])){
                     $Pfield = new PluginResourcesLinkmetademand();
                     if($Pfield->getFromDBByCrit(["plugin_metademands_fields_id"=>$v["id"]])){
                        $checkvalues =  PluginMetademandsField::_unserialize($Pfield->fields["check_value"]);
                        $checklist_in =  PluginMetademandsField::_unserialize($Pfield->fields["checklist_in"]);
                        $checklist_out =  PluginMetademandsField::_unserialize($Pfield->fields["checklist_out"]);
                        if(isset($checkvalues) && is_array($checkvalues)){
                           foreach ($checkvalues as $k => $checkvalue){
                              if((!is_array($values["fields"][$v["id"]]) && $checkvalue == $values["fields"][$v["id"]]) ||(is_array($values["fields"][$v["id"]]) && in_array($checkvalue,$values["fields"][$v["id"]]))){
                                 if($checklist_in[$k] != 0){
                                    $c = $checklist_in[$k];
                                    $checklistConfig->addResourceChecklist($resource, $c, PluginResourcesChecklist::RESOURCES_CHECKLIST_IN);
                                 }
                                 if($checklist_out[$k] != 0){
                                    $c = $checklist_out[$k];
                                    $checklistConfig->addResourceChecklist($resource,$c , PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT);
                                 }

                              }
                           }
                        }
                     }
                  }
               }
            }
         }
      }
   }

}
