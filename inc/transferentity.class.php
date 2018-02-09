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

class PluginResourcesTransferEntity extends CommonDBTM {

   static $rightname = 'plugin_resources';

   /**
    * functions mandatory
    * getTypeName(), canCreate(), canView()
    * */
   static function getTypeName($nb = 0) {
      return __('Transfer entities', 'resources');
   }

   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
   }



   function showForm($target) {
      global $CFG_GLPI;

      if (!$this->canView()) {
         return false;
      }
      if (!$this->canCreate()) {
         return false;
      }

      $used_entities = [];

      $dataEntity = $this->find();

      $canedit = true;

      if ($dataEntity) {
         foreach ($dataEntity as $field) {
            $used_entities[] = $field['entities_id'];
         }
      }

      if ($canedit) {
         echo "<form name='form' method='post' action='$target'>";

         echo "<div align='center'><table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='2'>".self::getTypeName()."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         // Dropdown group
         echo "<td class='center'>";
         echo __('Entity').'&nbsp;';
         $rand = Dropdown::show("Entity", ['name' => 'entities_id', 'used' => $used_entities, 'on_change' => 'entity_group()']);
         echo "<script type='text/javascript'>";
         echo "function entity_group(){";
         $params = ['action' => 'groupEntity', 'entities_id' => '__VALUE__'];
         Ajax::updateItemJsCode('entity_group', $CFG_GLPI['root_doc'].'/plugins/resources/ajax/resourceinfo.php', $params, 'dropdown_entities_id'.$rand);
         echo "}";
         echo "</script>";
         echo "</td>";
         echo "<td class='center'>";
         echo "<span id='entity_group'></span>";
         echo "</td>";
         echo "</tr>";

         echo "<tr>";
         echo "<td class='tab_bg_2 center' colspan='2'>";
         echo "<input type='submit' name='add_transferentity' class='submit' value='"._sx('button', 'Add')."' >";
         echo "</td>";
         echo "</tr>";
         echo "</table></div>";
         Html::closeForm();
      }
      if ($dataEntity) {
         $this->listItems($dataEntity, $canedit);
      }

   }

   private function listItems($fields, $canedit) {

      $rand = mt_rand();

      echo "<div class='center'>";
      if ($canedit) {
         Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
         $massiveactionparams = ['item' => __CLASS__, 'container' => 'mass'.__CLASS__.$rand];
         Html::showMassiveActions($massiveactionparams);
      }
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<th colspan='3'>".__('Entities allowed to transfer a resource', 'resources')."</th>";
      echo "</tr>";
      echo "<tr>";
      echo "<th width='10'>";
      if ($canedit) {
         echo Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
      }
      echo "</th>";
      echo "<th>".__('Entity')."</th>";
      echo "<th>".__('Group')."</th>";
      echo "</tr>";
      foreach ($fields as $field) {
         echo "<tr class='tab_bg_1'>";
         echo "<td width='10'>";
         if ($canedit) {
            Html::showMassiveActionCheckBox(__CLASS__, $field['id']);
         }
         echo "</td>";
         //DATA LINE
         echo "<td>".Dropdown::getDropdownName('glpi_entities', $field['entities_id'])."</td>";
         echo "<td>".Dropdown::getDropdownName('glpi_groups', $field['groups_id'])."</td>";
         echo "</tr>";
      }

      if ($canedit) {
         $massiveactionparams['ontop'] = false;
         Html::showMassiveActions($massiveactionparams);
         Html::closeForm();
      }
      echo "</table>";
      echo "</div>";
   }

   function getSearchOptions() {

      $tab = [];
      $tab['common'] = self::getTypeName(1);

      $tab[1]['table']           = $this->getTable();
      $tab[1]['field']           = 'name';
      $tab[1]['name']            = __('Name');
      $tab[1]['datatype']        = 'itemlink';
      $tab[1]['itemlink_type']   = $this->getType();
      $tab[1]['massiveaction']   = false;

      $tab[2]['table']           = $this->getTable();
      $tab[2]['field']           = 'id';
      $tab[2]['name']            = __('ID');
      $tab[2]['massiveaction']   = false;
      $tab[2]['datatype']        = 'number';

      $tab[92]['table']           = 'glpi_entities';
      $tab[92]['field']           = 'name';
      $tab[92]['name']            = __('Entity');
      $tab[92]['massiveaction']   = true;
      $tab[92]['datatype']        = 'dropdown';

      $tab[93]['table']           = 'glpi_groups';
      $tab[93]['field']           = 'name';
      $tab[93]['name']            = __('Group');
      $tab[93]['massiveaction']   = true;
      $tab[93]['datatype']        = 'dropdown';

      return $tab;
   }

   function prepareInputForAdd($input) {
      if (!$this->checkMandatoryFields($input)) {
         return false;
      }

      return $input;
   }

   function prepareInputForUpdate($input) {
      if (!$this->checkMandatoryFields($input)) {
         return false;
      }

      return $input;
   }

   function checkMandatoryFields($input) {
      $msg     = [];
      $checkKo = false;

      $mandatory_fields = ['entities_id'  => __('Entity')];

      foreach ($input as $key => $value) {
         if (array_key_exists($key, $mandatory_fields)) {
            if (empty($value)) {
               $msg[] = $mandatory_fields[$key];
               $checkKo = true;
            }
         }
      }

      if ($checkKo) {
         Session::addMessageAfterRedirect(sprintf(__("Mandatory fields are not filled. Please correct: %s"), implode(', ', $msg)), false, ERROR);
         return false;
      }
      return true;
   }

}
