<?php
/*
 *
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

class PluginResourcesConfig extends CommonDBTM {

   static $rightname = 'plugin_resources';

   /**
    * functions mandatory
    * getTypeName(), canCreate(), canView()
    * */
   static function getTypeName($nb = 0) {
      return __('Setup');
   }

   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
   }

   /**
    * PluginResourcesConfig constructor.
    */
   function __construct() {
      global $DB;

      if ($DB->tableExists($this->getTable())) {
         $this->getFromDB(1);
      }
   }

   function showForm() {

      if (!$this->canView()) {
         return false;
      }
      if (!$this->canCreate()) {
         return false;
      }

      $canedit = true;

      if ($canedit) {
         $this->getFromDB(1);
         echo "<form name='form' method='post' action='" . $this->getFormURL() . "'>";

         echo "<div align='center'><table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='2'>".self::getTypeName()."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Displaying the security block on the resource', 'resources');
         echo "</td>";
         echo "<td>";
         Dropdown::showYesNo('security_display', $this->fields['security_display']);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Security compliance management', 'resources');
         echo "<br><span class='red'>".sprintf(__('%1$s <br> %2$s'), __('Display of four additional security fields in the clients', 'resources'),
                                               __('(If all four fields are enabled, the client is compliant with security)', 'resources'))."</span>";
         echo "</td>";
         echo "<td>";
         Dropdown::showYesNo('security_compliance', $this->fields['security_compliance']);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>";
         echo __('Import données SYGES', 'resources');
         echo "</td>";
         echo "<td>";
         Dropdown::showYesNo('import_external_datas', $this->fields['import_external_datas']);
         echo "</td>";
         echo "</tr>";

         echo "<tr>";
         echo "<td class='tab_bg_2 center' colspan='2'>";
         echo "<input type='hidden' name='id' value='1' >";
         echo "<input type='submit' name='update_setup' class='submit' value='"._sx('button', 'Update')."' >";
         echo "</td>";
         echo "</tr>";
         echo "</table></div>";
         Html::closeForm();
      }

   }

   /**
    * @return mixed
    */
   function useSecurity() {
      return $this->fields['security_display'];
   }

   /**
    * @return mixed
    */
   function useSecurityCompliance() {
      return $this->fields['security_compliance'];
   }

   /**
    * @return mixed
    */
   function useImportExternalDatas() {
      return $this->fields['import_external_datas'];
   }

}
