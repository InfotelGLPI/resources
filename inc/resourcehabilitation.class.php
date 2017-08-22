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
 * Class PluginResourcesResourceBadge
 */
class PluginResourcesResourceHabilitation extends CommonDBTM {

   static $rightname = 'plugin_resources_habilitation';
   public $dohistory = true;
   
   const ACTION_ADD    = 1;
   const ACTION_DELETE = 2;

   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @param int $nb
    * @return string
    */
   static function getTypeName($nb = 0) {

      return _n('Additional habilitation', 'Additional habilitations', $nb, 'resources');
   }

   /**
    * Have I the global right to "view" the Object
    *
    * Default is true and check entity if the objet is entity assign
    *
    * May be overloaded if needed
    *
    * @return booleen
    **/
   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   /**
    * Have I the global right to "create" the Object
    * May be overloaded if needed (ex KnowbaseItem)
    *
    * @return booleen
    **/
   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, DELETE));
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->getType()=='PluginResourcesResource'
          && $this->canView()) {
         if ($_SESSION['glpishow_count_on_tabs']) {
            return self::createTabEntry(self::getTypeName(2), self::countForResource($item));
         }
         return self::getTypeName(2);
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType()=='PluginResourcesResource') {

         $self = new self();
         $self->showItem($item);
      }
      return true;
   }

   static function countForResource(PluginResourcesResource $item) {

      $restrict = "`plugin_resources_resources_id` = '".$item->getField('id')."' ";
      $nb = countElementsInTable(array('glpi_plugin_resources_resourcehabilitations'), $restrict);

      return $nb ;
   }

   function showItem($item) {
      if (!$this->canView())   return false;

      $canedit = $this->canCreate();

      //list metademands
      $data = $this->find("`plugin_resources_resources_id` = ".$item->getField('id'));

      if ($canedit) {
         $used = array();
         foreach ($data as $habilitation) {
            $used[] = $habilitation['plugin_resources_habilitations_id'];
         }
         //form to choose the metademand
         echo "<form name='form' method='post' action='" .
              Toolbox::getItemTypeFormURL('PluginResourcesResourceHabilitation') . "'>";

         echo "<div align='center'><table class='tab_cadre_fixe'>";
         echo "<tr class='tab_bg_1'><th colspan='2'>" . __('Add additional habilitation', 'resources') . "</th></tr>";
         echo "<tr class='tab_bg_1'><td class='center'>";
         echo self::getTypeName(1) . "</td>";
         echo "<td class='center'>";
         Dropdown::show('PluginResourcesHabilitation', array('used'   => $used,
                                                             'entity' => $item->getField("entities_id")));
         echo "</td></tr>";

         echo "<tr class='tab_bg_1'><td colspan='2' class='tab_bg_2 center'><input type=\"submit\" name=\"add\" 
                    class=\"submit\" value=\"" . _sx('button', 'Add') . "\" >";
         echo "<input type='hidden' name='plugin_resources_resources_id' value='".$item->getField('id')."'>";

         echo "</td></tr>";
         echo "</table></div>";
         Html::closeForm();
      }
      $this->listItems($data, $canedit);
   }

   /**
    * List of metademands
    *
    * @param $fields
    * @param $canedit
    */
   private function listItems($fields, $canedit){

      if(!empty($fields)){
         $rand = mt_rand();
         echo "<div class='center'>";
         if ($canedit) {
            Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
            $massiveactionparams = array('item' => __CLASS__, 'container' => 'mass'.__CLASS__.$rand);
            Html::showMassiveActions($massiveactionparams);
         }
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr>";
         echo "<th colspan='2'>".self::getTypeName()."</th>";
         echo "</tr>";
         echo "<tr>";
         if ($canedit) {
            echo "<th width='10'>".Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand)."</th>";
         }
         echo "<th>".__('Name')."</th>";
         foreach($fields as $field){
            echo "<tr class='tab_bg_1'>";
            if ($canedit) {
               echo "<td width='10'>";
               Html::showMassiveActionCheckBox(__CLASS__, $field['id']);
               echo "</td>";
            }
            //DATA LINE
            echo "<td class='center'>".Dropdown::getDropdownName('glpi_plugin_resources_habilitations', $field['plugin_resources_habilitations_id'])."</td>";
            echo "</tr></table>";
         }

         if ($canedit) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();
         }
         echo "</div>";
      }
   }

}