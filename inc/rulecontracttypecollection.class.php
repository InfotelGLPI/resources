<?php
/*
 * @version $Id: rulecontracttypecollection.class.php 480 2012-11-09 tsmr $
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


class PluginResourcesRuleContracttypeCollection extends RuleCollection {

   // From RuleCollection
   public $stop_on_first_match=true;
   static public $right='entity_rule_ticket';
   public $menu_option='contracttypes';
   
   function getTitle() {

      return __('Assignment rule of fields to a contract type', 'resources');
   }
   
   function __construct($entity=0) {
      $this->entity = $entity;
   }
   
   function showInheritedTab() {
      return Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, DELETE)) && ($this->entity);
   }

   function showChildrensTab() {
      return Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, DELETE)) && (count($_SESSION['glpiactiveentities']) > 1);
   }
}

?>