<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2022 by the resources Development Team.

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
 * Class PluginResourcesRole
 */
class PluginResourcesTeam extends CommonDropdown {

   static $rightname = 'plugin_resources_role';

   /**
    * @param $nb
    **@since 0.85
    *
    */
   static function getTypeName($nb = 0) {

      return _n('Team', 'Teams', $nb, 'resources');
   }

   /**
    * @return bool|\booleen
    */
   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   /**
    * @return bool|\booleen
    */
   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
   }

   /**
    * Return Additional Fields for this type
    *
    * @return array
    **/
   function getAdditionalFields() {

      return [
         ['name'  => 'users_id',
               'label' => __('Manager of the team', 'resources'),
               'type'  => 'UserDropdown',
               'right' => 'all'],
         ['name'  => 'users_id_substitute',
               'label' => __('Substitute manager of the team', 'resources'),
               'type'  => 'UserDropdown',
               'right' => 'all'],
         ['name'  => 'code',
               'label' => __('Team code', 'resources'),
               'type'  => 'text'],
      ];
   }

   /**
    * @return array
    */
   function rawSearchOptions() {

      $tab = parent::rawSearchOptions();


      return $tab;
   }



   /**
    * when a rank is deleted -> deletion of the linked specialities
    *
    * @return nothing|void
    */
   function cleanDBonPurge() {


   }

   /**
    * is_active = 1 during a creation
    *
    * @return nothing|void
    */
   function post_getEmpty() {

      $this->fields['is_active'] = 1;
   }

}
