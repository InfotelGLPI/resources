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

class PluginResourcesEmployment extends CommonDBTM {

   static $rightname = 'plugin_resources_employment';

   static public $itemtype = 'PluginResourcesResource';
   static public $items_id = 'plugin_resources_resources_id';

   // From CommonDBTM
   public $dohistory = true;

   static function getTypeName($nb = 0) {

      return _n('Employment', 'Employments', $nb, 'resources');
   }

   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, DELETE));
   }

   /**
    * Display tab for each emplyment
    **/
   function defineTabs($options = array()) {

      $ong = array();

      $this->addDefaultFormTab($ong);
      $this->addStandardTab('Document', $ong, $options);
      $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }

   /**
    * Display employment's tab for each resource except template
    **/
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == 'PluginResourcesResource' && $this->canView() && $withtemplate == 0) {
         if ($_SESSION['glpishow_count_on_tabs']) {
            return self::createTabEntry(self::getTypeName(2),
                                        countElementsInTable($this->getTable(),
                                                             "`plugin_resources_resources_id` = '" . $item->getID() . "'"));
         }
         return self::getTypeName(2);

      }
      return '';
   }

   /**
    * display tab's content for each resource
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType() == 'PluginResourcesResource') {
         if (Session::haveRight('plugin_resources_employment', UPDATE)) {
            self::addNewEmployments($item);
         }
         if (Session::haveRight('plugin_resources_employment', READ)) {
            self::showMinimalList($item);
         }
      }
      return true;
   }

   /**
    * Actions done when an employment is deleted from the database
    *
    * @return nothing
    **/
   function cleanDBonPurge() {

   }

   /**
    * allow search management
    */
   function getSearchOptions() {

      $tab           = array();
      $tab['common'] = self::getTypeName(2);

      $tab[1]['table']         = $this->getTable();
      $tab[1]['field']         = 'name';
      $tab[1]['name']          = __('Name');
      $tab[1]['datatype']      = 'itemlink';
      $tab[1]['itemlink_type'] = $this->getType();
      $tab[1]['massiveaction'] = false;

      $tab[2]['table']         = $this->getTable();
      $tab[2]['field']         = 'id';
      $tab[2]['name']          = __('ID');
      $tab[2]['datatype']      = 'number';
      $tab[2]['massiveaction'] = false;

      $tab[3]['table']         = 'glpi_plugin_resources_resources';
      $tab[3]['field']         = 'name';
      $tab[3]['name']          = __('Human resource', 'resources');
      $tab[3]['massiveaction'] = false;
      $tab[3]['datatype']      = 'dropdown';

      $tab[4]['table']         = 'glpi_plugin_resources_ranks';
      $tab[4]['field']         = 'name';
      $tab[4]['name']          = __('Rank', 'resources');
      $tab[4]['massiveaction'] = false;
      $tab[4]['datatype']      = 'dropdown';

      $tab[5]['table']         = 'glpi_plugin_resources_professions';
      $tab[5]['field']         = 'name';
      $tab[5]['name']          = __('Profession', 'resources');
      $tab[5]['massiveaction'] = false;
      $tab[5]['datatype']      = 'dropdown';

      $tab[6]['table']    = $this->getTable();
      $tab[6]['field']    = 'begin_date';
      $tab[6]['name']     = __('Begin date');
      $tab[6]['datatype'] = 'date';

      $tab[7]['table']    = $this->getTable();
      $tab[7]['field']    = 'end_date';
      $tab[7]['name']     = __('End date');
      $tab[7]['datatype'] = 'date';

      $tab[8]['table']    = 'glpi_plugin_resources_employmentstates';
      $tab[8]['field']    = 'name';
      $tab[8]['name']     = __('Employment state', 'resources');
      $tab[8]['datatype'] = 'dropdown';

      $tab[9]['table']    = 'glpi_plugin_resources_employers';
      $tab[9]['field']    = 'completename';
      $tab[9]['name']     = __('Employer', 'resources');
      $tab[9]['datatype'] = 'dropdown';

      $tab[10]['table']    = $this->getTable();
      $tab[10]['field']    = 'ratio_employment_budget';
      $tab[10]['name']     = __('Ratio Employment / Budget', 'resources');
      $tab[10]['datatype'] = 'decimal';

      $tab[13]['table']         = 'glpi_plugin_resources_resources';
      $tab[13]['field']         = 'id';
      $tab[13]['name']          = __('Human resource', 'resources') . __('ID');
      $tab[13]['massiveaction'] = false;

      $tab[14]['table']         = $this->getTable();
      $tab[14]['field']         = 'date_mod';
      $tab[14]['name']          = __('Last update');
      $tab[14]['datatype']      = 'datetime';
      $tab[14]['massiveaction'] = false;

      $tab[80]['table']    = 'glpi_entities';
      $tab[80]['field']    = 'completename';
      $tab[80]['name']     = __('Entity');
      $tab[80]['datatype'] = 'dropdown';

      return $tab;
   }

   /**
    * Display the employment form
    *
    * @param $ID integer ID of the item
    * @param $options array
    *     - target filename : where to go when done.
    *     - withtemplate boolean : template or basic item
    *
    * @return boolean item found
    **/
   function showForm($ID, $options = array("")) {
      global $CFG_GLPI;

      //validation des droits
      if (!$this->canView()) return false;

      $plugin_resources_resources_id = 0;
      if (isset($options['plugin_resources_resources_id'])) {
         $plugin_resources_resources_id = $options['plugin_resources_resources_id'];
      }

      if ($ID > 0) {
         $this->check($ID, READ);
      } else {
         // Create item
         $input = array('plugin_resources_resources_id' => $plugin_resources_resources_id);
         $this->check(-1, UPDATE, $input);
      }

      $this->showFormHeader($options);

      if ($ID > 0) {
         $resource = $this->fields["plugin_resources_resources_id"];
      } else {
         $resource = $plugin_resources_resources_id;
      }

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Name') . "</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "name", array('value' => $this->fields["name"]));
      echo "</td>";

      echo "<td>" . __('Employer', 'resources') . "</td>";
      echo "<td>";
      Dropdown::show('PluginResourcesEmployer',
                     array('value'  => $this->fields["plugin_resources_employers_id"],
                           'entity' => $this->fields["entities_id"]));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Profession', 'resources') . "</td>";
      echo "<td>";
      $params = array('name'   => 'plugin_resources_professions_id',
                      'value'  => $this->fields['plugin_resources_professions_id'],
                      'entity' => $this->fields["entities_id"],
                      'action' => $CFG_GLPI["root_doc"] . "/plugins/resources/ajax/dropdownRank.php",
                      'span'   => 'span_rank',
                      'sort'   => true
      );
      PluginResourcesResource::showGenericDropdown('PluginResourcesProfession', $params);
      echo "</td>";
      echo "<td>" . __('Rank', 'resources') . "</td><td>";
      echo "<span id='span_rank' name='span_rank'>";
      if ($this->fields["plugin_resources_ranks_id"] > 0) {
         echo Dropdown::getDropdownName('glpi_plugin_resources_ranks',
                                        $this->fields["plugin_resources_ranks_id"]);
      } else {
         echo __('None');
      }
      echo "</span></td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Employment state', 'resources') . "</td>";
      echo "<td>";
      Dropdown::show('PluginResourcesEmploymentState',
                     array('value'  => $this->fields["plugin_resources_employmentstates_id"],
                           'entity' => $this->fields["entities_id"]));
      echo "</td>";
      echo "<td>" . __('Ratio Employment / Budget', 'resources') . "</td><td>";
      echo "<input type='text' name='ratio_employment_budget' value='" .
           Html::formatNumber($this->fields["ratio_employment_budget"], true) .
           "' size='14'></td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Begin date') . "</td>";
      echo "<td>";
      Html::showDateField("begin_date", ['value' => $this->fields["begin_date"]]);
      echo "</td>";
      echo "<td>" . __('End date') . "</td>";
      echo "<td>";
      Html::showDateField("end_date", ['value' => $this->fields["end_date"]]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __('Human resource', 'resources') . "</td>";
      echo "<td>";

      PluginResourcesResource::dropdown(array('name'   => 'plugin_resources_resources_id',
                                              'value'  => $resource,
                                              'entity' => $this->fields["entities_id"]));

      echo "</td>";
      echo "<td>" . __('Comments') . "</td>";
      echo "<td><textarea cols='45' rows='5' name='comment' >" . $this->fields["comment"] . "</textarea>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='center' colspan='6'>";
      printf(__('Last update on %s'), Html::convDateTime($this->fields["date_mod"]));
      echo "</td>";
      echo "</tr>";


      if ($_SESSION['glpiactiveprofile']['interface'] != 'central') {
         $options['candel'] = false;
      }
      $this->showFormButtons($options);

      return true;

   }

   /**
    * adding of an employment in resource side
    *
    * @static
    *
    * @param CommonGLPI $item
    */
   static function addNewEmployments(CommonGLPI $item) {
      global $CFG_GLPI;

      $ID = $item->getField('id');

      $canedit = $item->can($ID, UPDATE);
      if (Session::haveRight('employment', UPDATE) && $canedit) {

         echo "<div align='center'>";
         echo "<a href='" . $CFG_GLPI["root_doc"] . "/plugins/resources/front/employment.form.php?plugin_resources_resources_id=" .
              $ID . "' >" . __('Declare a new employment', 'resources') . "</a></div>";
         echo "</div>";
      }

      echo "<div align='center'>";
      echo "<form method='post' name='addemployment' id='addemployment' action='" .
           $CFG_GLPI["root_doc"] . "/plugins/resources/front/employment.form.php'>";

      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<th colspan='2'>";
      echo __('To affect an employment', 'resources') . "</th>";
      echo "</tr>";
      echo "<tr class='tab_bg_1'>";
      echo "<input type='hidden' name='items_id' value='" . $ID . "'>";
      echo "<input type='hidden' name='itemtype' value='" . $item->getType() . "'>";
      echo "<td class='center' class='tab_bg_2'>";
      echo self::getTypeName(1);
      $restrict = " `plugin_resources_resources_id`='0'";
      Dropdown::show('PluginResourcesEmployment',
                     array('condition' => $restrict,
                           'entity'    => $item->getField("entities_id")));
      echo "</td><td class='center' class='tab_bg_2'>";
      echo "<input type='submit' name='add_item' value=\"" .
           _sx('button', 'Add') . "\" class='submit'></td></tr></table>";

      Html::closeForm();
      echo "</div>";

   }

   /**
    * Display the employments list of a resource
    *
    * @static
    *
    * @param CommonGLPI $item
    */
   static function showMinimalList(PluginResourcesResource $item) {
      $employemnt = new PluginResourcesEmployment();

      // Set search params
      $params = array(
         'start'      => 0,
         'order'      => 'DESC',
         'is_deleted' => 0
      );

      $toview = null;
      foreach ($employemnt->getSearchOptions() as $key => $option) {
         if (isset($option['table'])) {
            if ($option['table'] == "glpi_plugin_resources_resources" && $option['field'] == "id") {
               $params['criteria'][] = array('field'      => $key,
                                             'searchtype' => 'contains',
                                             'value'      => $item->fields['id']);
               $toview               = $key;
            }
            if ($option['table'] == $employemnt->getTable() && $option['field'] == "name") {
               $params['sort'] = $key;
            }
         }
      }

      $data = Search::prepareDatasForSearch(self::getType(), $params);
      // Force to view resource id  
      if ($toview != null && !in_array($toview, $data['toview'])) {
         array_push($data['toview'], $toview);
      }
      Search::constructSQL($data);
      Search::constructDatas($data);
      Search::displayDatas($data);
   }

   ////// CRON FUNCTIONS ///////
   //Cron action
   static function cronInfo($name) {

      switch ($name) {
         case 'ResourcesLeaving':
            return array(
               'description' => __('Updating leaving resources (declaring leaving, state of employment)', 'resources'));   // Optional
            break;
      }
      return array();
   }

   function queryLeavingResources() {

      $date  = date("Y-m-d H:i:s");
      $query = "SELECT *
            FROM `glpi_plugin_resources_resources`
            WHERE `date_end` IS NOT NULL
            AND `date_end` < '" . $date . "'
            AND `is_leaving` = 0
            AND `is_template` = 0
            AND `is_deleted` = 0";

      return $query;

   }

   /**
    * Cron action on tasks : LeavingResources
    *
    * @param $task for log, if NULL display
    *
    **/
   static function cronResourcesLeaving($task = NULL) {
      global $DB;


      $cron_status = 0;
      $message     = array();

      $PluginResourcesEmployment = new PluginResourcesEmployment();
      $query_expired             = $PluginResourcesEmployment->queryLeavingResources();

      $querys = array(Alert::END => $query_expired);

      $task_infos    = array();
      $task_messages = array();

      foreach ($querys as $type => $query) {
         $task_infos[$type] = array();
         foreach ($DB->request($query) as $data) {

            //when a resource is leaving, current employment get default state
            $default = PluginResourcesEmploymentState::getDefault();
            // only current employment
            $restrict    = "`plugin_resources_resources_id` = '" . $data["id"] . "'
                     AND ((`begin_date` < '" . $data['date_end'] . "'
                           OR `begin_date` IS NULL)
                           AND (`end_date` > '" . $data['date_end'] . "'
                                 OR `end_date` IS NULL)) ";
            $dbu         = new DbUtils();
            $employments = $dbu->getAllDataFromTable("glpi_plugin_resources_employments", $restrict);
            if (!empty($employments)) {
               foreach ($employments as $employment) {
                  $values = array('plugin_resources_employmentstates_id' => $default,
                                  'end_date'                             => $data['date_end'],
                                  'id'                                   => $employment['id']
                  );
                  $PluginResourcesEmployment->update($values);
               }
            }
            $resource = new PluginResourcesResource();
            $resource->getFromDB($data["id"]);
            $resource->update(array('is_leaving'                 => 1,
                                    'id'                         => $data["id"],
                                    'date_declaration_departure' => date('Y-m-d H:i:s'),
                                    'date_end'                   => $data['date_end']));
            $entity = $data['entities_id'];
            if (!isset($message[$entity])) {
               $message = array($entity => '');
            }
            $message[$entity] .= $data["name"] . " " . $data["firstname"] . " : " .
                                 Html::convDate($data["date_end"]) . "<br>\n";
            $task_infos[$type][$entity][] = $data;

            if (!isset($task_messages[$type][$entity])) {
               $task_messages[$type][$entity] = __('These resources left the company, linked current employment have been updated', 'resources') . "<br />";
            }
            $task_messages[$type][$entity] .= $message[$entity];

         }
      }

      foreach ($querys as $type => $query) {

         foreach ($task_infos[$type] as $entity => $resources) {
            Plugin::loadLang('resources');

            $message     = $task_messages[$type][$entity];
            $cron_status = 1;
            if ($task) {
               $task->log(Dropdown::getDropdownName("glpi_entities",
                                                    $entity) . ":  $message\n");
               $task->addVolume(count($resources));
            } else {
               Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities",
                                                                          $entity) . ":  $message");
            }
         }
      }

      return $cron_status;
   }

   //Massive action
   function getSpecificMassiveActions($checkitem = NULL) {
      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);

      if ($isadmin
          && $_SESSION['glpiactiveprofile']['interface'] == 'central'
      ) {
         if (strpos($_SERVER['PHP_SELF'], "employment.php")) {
            $actions['Delete'] = __('Delete permanently');
         }
         if (Session::haveRight('transfer', READ)
             && Session::isMultiEntitiesMode()
         ) {
            $actions['Transfert'] = __('Transfer');
         }
      } elseif ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         if (strpos($_SERVER['PHP_SELF'], "employment.php")) {
            $actions['Delete_Item'] = __('Delete permanently');
         }
      }
      return $actions;
   }

   function showSpecificMassiveActionsParameters($input = array()) {

      switch ($input['action']) {
         case "Delete" :
         case "Delete_Item" :
            echo "<input type=\"submit\" name=\"massiveaction\" class=\"submit\" value='" . _sx('button', 'Post') . "'>";
            return true;
            break;
         case "Transfert" :
            Dropdown::show('Entity');
            echo "&nbsp;<input type=\"submit\" name=\"massiveaction\" class=\"submit\" value='" . _sx('button', 'Post') . "'>";
            return true;
            break;

         default :
            return parent::showSpecificMassiveActionsParameters($input);
            break;
      }
      return false;
   }

   function doSpecificMassiveActions($input = array()) {

      $res = array('ok'      => 0,
                   'ko'      => 0,
                   'noright' => 0);

      $task_item = new PluginResourcesTask_Item();

      switch ($input['action']) {
         case "Transfert" :
            if ($input['itemtype'] == 'PluginResourcesEmployment') {
               foreach ($input["item"] as $key => $val) {
                  if ($val == 1) {
                     $this->getFromDB($key);
                     $rank = PluginResourcesRank::transfer($PluginResourcesEmployment->fields["plugin_resources_ranks_id"], $data['entities_id']);
                     if ($rank > 0) {
                        $values["id"]                        = $key;
                        $values["plugin_resources_ranks_id"] = $rank;
                        $this->update($values);
                     }

                     $profession = PluginResourcesProfession::transfer($PluginResourcesEmployment->fields["plugin_resources_professions_id"],
                                                                       $data['entities_id']);
                     if ($profession > 0) {
                        $values["id"]                              = $key;
                        $values["plugin_resources_professions_id"] = $profession;
                        $this->update($values);
                     }

                     unset($values);
                     $values["id"]          = $key;
                     $values["entities_id"] = $input['entities_id'];

                     if ($this->update($values)) {
                        $res['ok']++;
                     } else {
                        $res['ko']++;
                     }
                  }
               }
            }
            break;
         case "Delete" :
            if ($input['itemtype'] == 'PluginResourcesEmployment') {
               foreach ($input["item"] as $key => $val) {
                  if ($val == 1) {
                     if ($this->can(-1, UPDATE, $input)) {
                        if ($this->delete(array("id" => $key))) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                     } else {
                        $nbnoright++;
                     }
                  }
               }
            }
            break;
         case "Delete_Item" :
            if ($input['itemtype'] == 'PluginResourcesEmployment') {
               foreach ($input["item"] as $key => $val) {
                  if ($val == 1) {
                     $values = array('id'                            => $key,
                                     'plugin_resources_resources_id' => 0);
                     if ($this->can($values["id"], UPDATE, $values)) {
                        if ($this->update($values)) {
                           $nbok++;
                        } else {
                           $nbko++;
                        }
                     } else {
                        $nbnoright++;
                     }
                  }
               }
            }
            break;
         default :
            return parent::doSpecificMassiveActions($input);
            break;
      }
      return $res;
   }

   static function getMenuOptions($menu) {

      $plugin_page = '/plugins/resources/front/employment.php';
      $itemtype    = strtolower(self::getType());

      //Menu entry in admin
      $menu['options'][$itemtype]['title']           = self::getTypeName();
      $menu['options'][$itemtype]['page']            = $plugin_page;
      $menu['options'][$itemtype]['links']['search'] = $plugin_page;

      if (Session::haveright(self::$rightname, UPDATE)) {
         $menu['options'][$itemtype]['links']['add'] = '/plugins/resources/front/employment.form.php';
      }

      return $menu;
   }

}

?>