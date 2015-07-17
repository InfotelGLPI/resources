<?php
/*
 * @version $Id: rulechecklist.class.php 480 2012-11-09 tsmr $
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

if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access directly to this file");
}

/**
* Rule class store all informations about a GLPI rule :
*   - description
*   - criterias
*   - actions
*
**/
class PluginResourcesRuleChecklist extends Rule {

   static $rightname = 'plugin_resources';
   
   // From Rule
   static public $right='entity_rule_ticket';
   public $can_sort=true;

   function getTitle() {

      return PluginResourcesResource::getTypeName(2)." ".PluginResourcesChecklist::getTypeName(1);
   }
   
   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, DELETE));
   }
   
   function maybeRecursive() {
      return true;
   }


   function isEntityAssign() {
      return true;
   }

   function canUnrecurs() {
      return true;
   }
   
   function maxActionsCount() {
      return count($this->getActions());
   }
   
   function addSpecificParamsForPreview($params) {

      if (!isset($params["entities_id"])) {
         $params["entities_id"] = $_SESSION["glpiactive_entity"];
      }
      return $params;
   }
   
   function getCriterias() {

      $criterias = array();
      
      $criterias['plugin_resources_contracttypes_id']['name']  = PluginResourcesContractType::getTypeName(1);
      $criterias['plugin_resources_contracttypes_id']['type']  = 'dropdownContractType';
      
      $criterias['plugin_resources_contracttypes_id']['allow_condition'] = array(Rule::PATTERN_IS, Rule::PATTERN_IS_NOT);
      
      $criterias['checklist_type']['name']  = __('Checklist type', 'resources');
      $criterias['checklist_type']['type']  = 'dropdownChecklistType';
      
      $criterias['checklist_type']['allow_condition'] = array(Rule::PATTERN_IS, Rule::PATTERN_IS_NOT);

      return $criterias;
   }
   
   function displayCriteriaSelectPattern($name, $ID, $condition, $value="", $test=false) {
      
      $PluginResourcesChecklist = new PluginResourcesChecklist();
      $PluginResourcesContractType = new PluginResourcesContractType();
      
      $crit    = $this->getCriteria($ID);
      $display = false;
      if (isset($crit['type'])
          && ($test||$condition==Rule::PATTERN_IS || $condition==Rule::PATTERN_IS_NOT)) {

         switch ($crit['type']) {
            case "dropdownChecklistType" :
               $PluginResourcesChecklist->dropdownChecklistType($name);
               $display = true;
               break;
            case "dropdownContractType" :
               $PluginResourcesContractType->dropdownContractType($name);
               $display = true;
               break;
         }
      }
      
      if ($condition == Rule::PATTERN_EXISTS || $condition == Rule::PATTERN_DOES_NOT_EXISTS) {
         echo "<input type='hidden' name='$name' value='1'>";
         $display=true;
      }

      if (!$display) {
         $rc = new $this->rulecriteriaclass();
         Html::autocompletionTextField($rc, "pattern", array('name'  => $name,
                                                       'value' => $value,
                                                       'size'  => 70));
      }
   }
   
   /**
    * Return a value associated with a pattern associated to a criteria to display it
    *
    * @param $ID the given criteria
    * @param $condition condition used
    * @param $pattern the pattern
   **/
   function getCriteriaDisplayPattern($ID, $condition, $pattern) {

      if (($condition==Rule::PATTERN_IS || $condition==Rule::PATTERN_IS_NOT)) {
         $crit = $this->getCriteria($ID);
         if (isset($crit['type'])) {

            switch ($crit['type']) {
               case "dropdownChecklistType" :
                  $PluginResourcesChecklist = new PluginResourcesChecklist();
                  return $PluginResourcesChecklist->getChecklistType($pattern);
               case "dropdownContractType" :
                  $PluginResourcesContractType = new PluginResourcesContractType();
                  return $PluginResourcesContractType->getContractTypeName($pattern);
            }
         }
      }
      return $pattern;
   }

   function getActions() {

      $actions = array();
      
      $actions['checklists_id']['name']  = __('Checklist action', 'resources');
      $actions['checklists_id']['table'] = 'glpi_plugin_resources_checklistconfigs';
      $actions['checklists_id']['type'] = 'dropdown';
      $actions['checklists_id']['force_actions'] = array('assign');
      
      return $actions;
   }
}

?>