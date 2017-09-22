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

class PluginResourcesChoice extends CommonDBTM {

   static $rightname = 'plugin_resources';

   static function getTypeName($nb = 0) {

      return _n('Need', 'Needs', $nb, 'resources');
   }

   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, DELETE));
   }

   /**
    * Clean object veryfing criteria (when a relation is deleted)
    *
    * @param $crit array of criteria (should be an index)
    */
   public function clean($crit) {
      global $DB;

      foreach ($DB->request($this->getTable(), $crit) as $data) {
         $this->delete($data);
      }
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      $wizard_need = PluginResourcesContractType::checkWizardSetup($item->getField('id'), "use_need_wizard");

      if ($item->getType() == 'PluginResourcesResource'
          && $this->canView()
          && $wizard_need
      ) {
         if ($_SESSION['glpishow_count_on_tabs']) {
            return self::createTabEntry(self::getTypeName(2), self::countForResource($item));
         }
         return self::getTypeName(2);
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType() == 'PluginResourcesResource') {

         $self = new self();
         $self->showItemHelpdesk($item->getField('id'), 0, $withtemplate);
      }
      return true;
   }

   static function countForResource(PluginResourcesResource $item) {

      $restrict = "`plugin_resources_resources_id` = '" . $item->getField('id') . "' ";
      $nb       = countElementsInTable(array('glpi_plugin_resources_choices'), $restrict);

      return $nb;
   }

   function addHelpdeskItem($values) {

      $this->add(array(
                    'plugin_resources_resources_id'   => $values["plugin_resources_resources_id"],
                    'plugin_resources_choiceitems_id' => $values["plugin_resources_choiceitems_id"],
                    'comment'                         => ''));
   }

   function addComment($values) {

      $resource = new PluginResourcesResource();
      $resource->getFromDB($values['plugin_resources_resources_id']);

      $comment = $values['comment'];

      if (!empty($resource->fields['comment'])) {
         $comment = $resource->fields['comment'] .
                    "\r\n\r" . __('Others needs', 'resources') . "\r\n\r" . $values['comment'];
      }

      $comment = Html::cleanPostForTextArea($comment);

      $resource->update(array(
                           'id'      => $values['plugin_resources_resources_id'],
                           'comment' => addslashes($comment)));

      $_SESSION['plugin_ressources_' . $values['plugin_resources_resources_id'] . '_comment'] = $comment;
   }

   function updateComment($values) {

      $resource = new PluginResourcesResource();
      $resource->getFromDB($values['plugin_resources_resources_id']);

      $comment = $values['comment'];

      $comment = Html::cleanPostForTextArea($comment);

      $resource->update(array(
                           'id'      => $values['plugin_resources_resources_id'],
                           'comment' => addslashes($comment)));

      $_SESSION['plugin_ressources_' . $values['plugin_resources_resources_id'] . '_comment'] = $comment;
   }

   function addNeedComment($values) {

      $this->update(array(
                       'id'      => $values['id'],
                       'comment' => $values['commentneed']));
   }

   function prepareInputForAdd($input) {

      $choice_item = new PluginResourcesChoiceItem();
      $choice_item->getfromDB($input['plugin_resources_choiceitems_id']);
      $childs = $choice_item->haveChildren();
      if ($childs) {
         return false;
      }

      return $input;
   }

   /**
    * Duplicate item resources from an item template to its clone
    *
    * @since version 0.84
    *
    * @param $itemtype     itemtype of the item
    * @param $oldid        ID of the item to clone
    * @param $newid        ID of the item cloned
    * @param $newitemtype  itemtype of the new item (= $itemtype if empty) (default '')
    **/
   static function cloneItem($oldid, $newid) {
      global $DB;

      $query = "SELECT *
                 FROM `glpi_plugin_resources_choices`
                 WHERE `plugin_resources_resources_id` = '$oldid';";

      foreach ($DB->request($query) as $data) {
         $choice = new self();
         $choice->add(array('plugin_resources_resources_id'   => $newid,
                            'plugin_resources_choiceitems_id' => $data["plugin_resources_choiceitems_id"],
                            'comment'                         => $data["comment"]));
      }
   }

   function wizardFourForm($plugin_resources_resources_id) {
      global $CFG_GLPI;

      if (!$this->canView()) return false;

      $employer_spotted = false;

      $resource = new PluginResourcesResource();
      $resource->getFromDB($plugin_resources_resources_id);

      $newrestrict = "`plugin_resources_resources_id` = '$plugin_resources_resources_id'";

      $dbu        = new DbUtils();
      $newchoices = $dbu->getAllDataFromTable($this->getTable(), $newrestrict);

      $ID = 0;
      if (!empty($newchoices)) {
         foreach ($newchoices as $newchoice)
            $ID = $newchoice["id"];
      }
      if (empty($ID)) {
         if ($this->getEmpty()) $spotted = true;
      } else {
         if ($this->getfromDB($ID)) $spotted = true;
      }

      if ($spotted && $plugin_resources_resources_id) {

         echo "<div align='center'>";

         echo "<form action='" . Toolbox::getItemTypeFormURL('PluginResourcesWizard') . "' name=\"choice\" method='post'>";
         echo "<table class='plugin_resources_wizard'>";
         echo "<tr>";
         echo "<td class='plugin_resources_wizard_left_area' valign='top'>";
         echo "</td>";

         echo "<td class='plugin_resources_wizard_right_area' valign='top'>";

         echo "<div class='plugin_resources_wizard_title'><p>";
         echo "<img class='plugin_resource_wizard_img' src='" . $CFG_GLPI['root_doc'] . "/plugins/resources/pics/newresource.png' alt='newresource'/>&nbsp;";
         echo __('Enter the computing needs of the resource', 'resources') . "</p></div>";

         echo "<div align='left'>";

         $restrict = "`plugin_resources_resources_id` = '$plugin_resources_resources_id' ";
         $choices  = $dbu->getAllDataFromTable($this->getTable(), $restrict);

         echo "<table class='plugin_resources_wizard_table'>";

         echo "<tr>";
         echo "<th colspan='4' class='plugin_resources_wizard_th1'>";
         echo __('IT needs identified', 'resources');
         echo "</th>";
         echo "</tr>";
         $used = array();

         if (!empty($choices)) {
            foreach ($choices as $choice) {
               $used[] = $choice["plugin_resources_choiceitems_id"];
               echo "<tr>";
               $items = Dropdown::getDropdownName("glpi_plugin_resources_choiceitems",
                                                  $choice["plugin_resources_choiceitems_id"], 1);
               echo "<td class='plugin_resources_wizard_choice_td'>" . $items["name"] . "</td>";
               echo "<td class='plugin_resources_wizard_choice_td'>" . nl2br($items["comment"]) . "</td>";
               echo "<td class='plugin_resources_wizard_choice_td'>";
               $items_id = $choice["id"];
               $rand     = mt_rand();
               if (!empty($choice["comment"])) {

                  self::showModifyCommentFrom($choice, $rand);

               } else {

                  self::showAddCommentForm($choice, $rand);

               }
               echo "</td>";
               if ($this->canCreate()) {
                  echo "<td class='plugin_resources_wizard_choice_td' class='tab_bg_2'>";
                  Html::showSimpleForm($CFG_GLPI['root_doc'] . '/plugins/resources/front/wizard.form.php',
                                       'deletechoice',
                                       _x('button', 'Delete permanently'),
                                       array('id' => $choice["id"], 'plugin_resources_resources_id' => $plugin_resources_resources_id));

                  echo "</td>";
               }
               echo "</tr>";
            }
         }
         echo "</table></br>";

         if ($this->canCreate()) {

            echo "<table class='plugin_resources_wizard_table'>";
            echo "<tr ><th>" . __('Add a need', 'resources') . "</th>";
            echo "<td class='center'>";
            echo "<input type='hidden' name='plugin_resources_resources_id' value='$plugin_resources_resources_id'>";
            Dropdown::show('PluginResourcesChoiceItem',
                           array('name'      => 'plugin_resources_choiceitems_id',
                                 'entity'    => $_SESSION['glpiactive_entity'],
                                 'condition' => '`is_helpdesk_visible` = 1',
                                 'used'      => $used));
            echo "</td>";
            echo "<td class='center'>";
            echo "<input type='submit' name='addchoice' value=\"" . _sx('button', 'Post') . "\" class='submit'>";
            echo "</td>";
            echo "</tr>";
            echo "</table>";

            $rand = mt_rand();
            echo "<br><table class='plugin_resources_wizard_table' >";
            echo "<tr ><th class='plugin_resources_wizard_th1'>";
            echo "<a href=\"javascript:showHideDiv('view_comment','commentimg$rand','" .
                 $CFG_GLPI["root_doc"] . "/pics/deplier_down.png','" .
                 $CFG_GLPI["root_doc"] . "/pics/deplier_up.png');\">";
            echo "<img alt='' name='commentimg$rand' src=\"" .
                 $CFG_GLPI["root_doc"] . "/pics/deplier_down.png\">&nbsp;";
            echo __('Others needs', 'resources') . "&nbsp;";
            Html::showToolTip(__('Will be added to the resource comment area', 'resources'), array());
            echo "</a>";
            echo "</th>";
            echo "</tr>";
            echo "</table>";

            echo "<div align='center' style='display:none;' id='view_comment'>";
            echo "<table class='plugin_resources_wizard_table'>";
            echo "<tr >";
            echo "<td class='center'>";
            $comment = "";
            if (isset($_SESSION['plugin_ressources_' . $plugin_resources_resources_id . '_comment'])) {

               if (!empty($resource->fields['comment'])) {
                  $comment = $resource->fields['comment'];
               } else {
                  $comment = $_SESSION['plugin_ressources_' . $plugin_resources_resources_id . '_comment'];
               }
            }

            echo "<textarea cols='65' rows='3' name='comment'>" . Html::clean($comment) . "</textarea>";
            echo "</td>";
            echo "</tr>";
            echo "<tr>";
            echo "<td class='center'>";
            if (isset($_SESSION['plugin_ressources_' . $plugin_resources_resources_id . '_comment'])) {
               echo "<input type='submit' name='updatecomment' value=\"" . _sx('button', 'Update') . "\" class='submit'>";
            } else {
               echo "<input type='submit' name='addcomment' value=\"" . _sx('button', 'Add') . "\" class='submit'>";
            }
            echo "</td>";
            echo "</tr>";
            echo "</table>";
            echo "</div>";
         }
         echo "</div>";
         echo "</td>";
         echo "</tr>";

         if ($this->canCreate()) {
            echo "<tr><td class='plugin_resources_wizard_button' colspan='2'>";
            echo "<div class='next'>";
            echo "<input type='hidden' name='plugin_resources_resources_id' value=\"" . $plugin_resources_resources_id . "\">";
            echo "<input type='submit' name='four_step' value='" . _sx('button', 'Next >', 'resources') . "' class='submit' />";
            echo "</div>";
            echo "</td></tr>";
         }

         echo "</table>";
         Html::closeForm();

         echo "</div>";
      }
   }

   static function showAddCommentForm($item, $rand) {
      global $CFG_GLPI;


      $items_id = $item['id'];
      echo "<div class='center' id='addneedcomment" . "$items_id$rand'></div>\n";
      echo "<script type='text/javascript' >\n";
      echo "function viewAddNeedComment" . "$items_id(){\n";
      $params = array('id'   => $items_id,
                      'rand' => $rand);
      Ajax::UpdateItemJsCode("addneedcomment" . "$items_id$rand",
                             $CFG_GLPI["root_doc"] . "/plugins/resources/ajax/addneedcomment.php", $params, false);
      echo "};";
      echo "</script>\n";
      echo "<p align='center'><a href='javascript:viewAddNeedComment" . "$items_id();'>";
      echo __('Add a comment', 'resources');
      echo "</a></p>\n";

      echo "<script type='text/javascript' >\n";
      echo "function hideAddForm$items_id() {\n";
      echo "$('#addcommentneed$items_id$rand').hide();";
      echo "$('#viewaccept$items_id').hide();";
      echo "}\n";
      echo "</script>\n";
   }

   static function showModifyCommentFrom($item, $rand) {
      global $CFG_GLPI;

      $items_id = $item['id'];
      echo "<script type='text/javascript' >\n";
      echo "function showComment$items_id () {\n";
      echo "$('#commentneed$items_id$rand').hide();";
      echo "$('#viewaccept$items_id$rand').show();";

      $params = array('name' => 'commentneed' . $items_id,
                      'data' => rawurlencode($item["comment"]));
      Ajax::UpdateItemJsCode("viewcommentneed$items_id$rand", $CFG_GLPI["root_doc"] . "/plugins/resources/ajax/inputtext.php",
                             $params, false);
      echo "}";
      echo "</script>\n";
      echo "<div id='commentneed$items_id$rand' class='center' onClick='showComment$items_id()'>\n";
      echo $item["comment"];
      echo "</div>\n";
      echo "<div id='viewcommentneed$items_id$rand'>\n";
      echo "</div>\n";
      echo "<div id='viewaccept$items_id$rand' style='display:none;' class='center'>";
      echo "<p><input type='submit' name='updateneedcomment[" . $items_id . "]' value=\"" .
           _sx('button', 'Update') . "\" class='submit'>";
      echo "&nbsp;<input type='button' onclick=\"hideForm$items_id();\" value=\"" .
           _sx('button', 'Cancel') . "\" class='submit'></p>";
      echo "</div>";
      echo "<script type='text/javascript' >\n";
      echo "function hideForm$items_id() {\n";
      echo "$('#viewcommentneed$items_id$rand textarea').remove();";
      echo "$('#commentneed$items_id$rand').show();";
      echo "$('#viewaccept$items_id$rand').hide();";
      echo "}\n";
      echo "</script>\n";

   }

   function showItemHelpdesk($plugin_resources_resources_id, $exist, $withtemplate = '') {
      global $CFG_GLPI;

      $restrict = "`plugin_resources_resources_id` = '$plugin_resources_resources_id'";
      $dbu      = new DbUtils();
      $choices  = $dbu->getAllDataFromTable($this->getTable(), $restrict);

      $resource = new PluginResourcesResource();
      $resource->getFromDB($plugin_resources_resources_id);

      $canedit = $resource->can($plugin_resources_resources_id, UPDATE)
                 && $withtemplate < 2
                 && $resource->fields["is_leaving"] != 1;
      if ($exist == 0)
         echo "<form method='post' action=\"" . $CFG_GLPI["root_doc"] . "/plugins/resources/front/resource_item.list.php\">";
      else if ($exist == 1)
         echo "<form method='post' action=\"" . $CFG_GLPI["root_doc"] . "/plugins/resources/front/resource.form.php\">";

      echo "<div align='center'><table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<th colspan='4'>" . __('Element(s) to be affected', 'resources') . "</th>";
      echo "</tr>";
      echo "<tr>";
      echo "<th>" . __('Type') . "</th>";
      echo "<th>" . __('Description') . "</th>";
      echo "<th>" . __('Comments') . "</th>";
      if ($canedit)
         echo "<th>&nbsp;</th>";
      echo "</tr>";

      $used = array();
      if (!empty($choices)) {
         foreach ($choices as $choice) {

            $used[] = $choice["plugin_resources_choiceitems_id"];
            echo "<tr class='tab_bg_1'>";

            $items = Dropdown::getDropdownName("glpi_plugin_resources_choiceitems",
                                               $choice["plugin_resources_choiceitems_id"], 1);
            echo "<td class='left'>";
            echo $items['name'];
            echo "</td>";
            echo "<td class='left'>";
            echo nl2br($items["comment"]);
            echo "</td>";
            echo "<td class='center'>";

            $rand = mt_rand();
            if (!empty($choice["comment"])) {

               self::showModifyCommentFrom($choice, $rand);

            } else {

               self::showAddCommentForm($choice, $rand);

            }
            echo "</td>";
            if ($canedit) {
               echo "<td class='center' class='tab_bg_2'>";
               Html::showSimpleForm($CFG_GLPI['root_doc'] . '/plugins/resources/front/resource_item.list.php',
                                    'deletehelpdeskitem',
                                    _x('button', 'Delete permanently'),
                                    array('id' => $choice["id"]));
               echo "</td>";
            }
            echo "</tr>";
         }
      }
      if ($canedit) {
         echo "<tr class='tab_bg_1'>";
         echo "<th colspan='4'>" . __('Add a need', 'resources') . " :</th>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan='4' class='center'>";
         echo "<input type='hidden' name='plugin_resources_resources_id' value='$plugin_resources_resources_id'>";

         $condition = "";
         if ($_SESSION['glpiactiveprofile']['interface'] != 'central')
            $condition = '`is_helpdesk_visible` = 1';
         Dropdown::show('PluginResourcesChoiceItem',
                        array('name'      => 'plugin_resources_choiceitems_id',
                              'entity'    => $_SESSION['glpiactive_entity'],
                              'condition' => $condition,
                              'used'      => $used,
                              'addicon'   => true));
         echo "</td></tr>";
         echo "<tr class='tab_bg_1'>";
         echo "<td class='center' colspan='4'>";
         echo "<input type='submit' name='addhelpdeskitem' value=\"" . _sx('button', 'Add') . "\" class='submit'>";
         echo "<input type='hidden' name='id' value=\"$plugin_resources_resources_id\">";
         echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
         if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
            if ($exist != 1) {
               echo "<input type='submit' name='finish' value=\"" . __('Terminate the declaration', 'resources') . "\" class='submit'>";
            } else {
               echo "<input type='submit' name='resend' value=\"" . __('Resend the declaration', 'resources') . "\" class='submit'>";
            }
         }
         echo "</td>";
         echo "</tr>";
      }
      echo "</table></div>";
      Html::closeForm();
      echo "<br>";

   }
}

?>