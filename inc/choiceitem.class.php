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

class PluginResourcesChoiceItem extends CommonTreeDropdown {

   static function getTypeName($nb = 0) {

      return _n('Type of need', 'Types of need', $nb, 'resources');
   }

   static function canView() {
      return Session::haveRight('plugin_resources', READ);
   }

   static function canCreate() {
      return Session::haveRightsOr('dropdown', [CREATE, UPDATE, DELETE]);
   }

   function getAdditionalFields() {

      return [['name'  => $this->getForeignKeyField(),
               'label' => __('As child of'),
               'type'  => 'parent',
               'list'  => false],
              ['name'  => 'is_helpdesk_visible',
               'label' => __('Visible in the simplified interface'),
               'type'  => 'bool',
               'list'  => true]];
   }

   function getSearchOptions () {

      $tab = parent::getSearchOptions();

      $tab[11]['table']    = $this->getTable();
      $tab[11]['field']    = 'is_helpdesk_visible';
      $tab[11]['name']     = __('Visible in the simplified interface');
      $tab[11]['datatype'] = 'bool';

      return $tab;
   }
}

