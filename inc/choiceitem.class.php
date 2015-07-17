<?php
/*
 * @version $Id: choiceitem.class.php 480 2012-11-09 tsmr $
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

class PluginResourcesChoiceItem extends CommonTreeDropdown {
   
   static function getTypeName($nb=0) {

      return _n('Type of need', 'Types of need', $nb, 'resources');
   }
      
   static function canView() {
      return Session::haveRight('plugin_resources', READ);
   }

   static function canCreate() {
      return Session::haveRightsOr('dropdown', array(CREATE, UPDATE, DELETE));
   }
   
   function getAdditionalFields() {

      return array(array('name'  => $this->getForeignKeyField(),
                         'label' => __('As child of'),
                         'type'  => 'parent',
                         'list'  => false),
                     array('name'  => 'is_helpdesk_visible',
                         'label' => __('Last update'),
                         'type'  => 'bool',
                         'list'  => true));
   }
   
   function getSearchOptions () {
      
      $tab = parent::getSearchOptions();
      
      $tab[11]['table']    = $this->getTable();
      $tab[11]['field']    = 'is_helpdesk_visible';
      $tab[11]['name']     = __('Last update');
      $tab[11]['datatype'] = 'bool';

      return $tab;
   }
}

?>