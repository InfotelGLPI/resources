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

class PluginResourcesTicketCategory extends CommonDBTM {

   function getFromDBbyCategory($category) {
      global $DB;

      $query = "SELECT * FROM `".$this->getTable()."` ".
              "WHERE `ticketcategories_id` = '".$category."' ";
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result) != 1) {
            return false;
         }
         $this->fields = $DB->fetch_assoc($result);
         if (is_array($this->fields) && count($this->fields)) {
            return true;
         } else {
            return false;
         }
      }
      return false;
   }

   function addTicketCategory($category) {

      if ($this->getFromDBbyCategory($category)) {

         $this->update(array(
             'id' => $this->fields['id'],
             'ticketcategories_id' => $category));
      } else {

         $this->add(array(
             'id' => 1,
             'ticketcategories_id' => $category));
      }
   }

   function showForm($target) {
      global $CFG_GLPI;

      $categories = getAllDatasFromTable($this->getTable());
      if (!empty($categories)) {
         echo "<div align='center'>";
         $rand = mt_rand();
         echo "<form method='post' name='massiveaction_form_ticket$rand' 
                                    id='massiveaction_form_ticket$rand' action='".$target."'>";
         echo "<table class='tab_cadre_fixe' cellpadding='5'>";
         echo "<tr>";
         echo "<th></th><th>".__('Category of created tickets', 'resources')."</th>";
         echo "</tr>";
         foreach ($categories as $categorie) {
            $ID = $categorie["id"];
            echo "<tr class='tab_bg_1'>";
            echo "<td class='center' width='10'>";
            echo "<input type='hidden' name='id' value='$ID'>";
            echo "<input type='checkbox' name='item[$ID]' value='1'>";
            echo "</td>";
            echo "<td>".Dropdown::getDropdownName("glpi_itilcategories", $categorie["ticketcategories_id"])."</td>";
            echo "</tr>";
         }

         Html::openArrowMassives("massiveaction_form_ticket$rand", true);
         Html::closeArrowMassives(array('delete_ticket' => __s('Delete permanently')));

         echo "</table>";
         Html::closeForm();
         echo "</div>";
      } else {
         echo "<div align='center'><form method='post'  action='".$target."'>";
         echo "<table class='tab_cadre_fixe' cellpadding='5'><tr ><th colspan='2'>";
         echo __('Category of created tickets', 'resources')."</th></tr>";
         echo "<tr class='tab_bg_1'><td>";
         Dropdown::show('ITILCategory', array('name' => "ticketcategories_id"));
         echo "</td>";
         echo "<td>";
         echo "<div align='center'>";
         echo "<input type='submit' name='add_ticket' value=\""._sx('button', 'Add')."\" 
                                                                                 class='submit'>";
         echo "</div></td></tr>";
         echo "</table>";
         Html::closeForm();
         echo "</div>";
      }
   }

}

?>