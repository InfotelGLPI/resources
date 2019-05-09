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
 * Class PluginResourcesBudget
 */
class PluginResourcesBudget extends CommonDBTM {

   static $rightname = 'plugin_resources_budget';
   // From CommonDBTM
   public $dohistory = true;

   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @param integer $nb Number of items
    *
    * @return string
    **/
   static function getTypeName($nb = 0) {

      return _n('Budget', 'Budgets', $nb);
   }

   /**
    * Have I the global right to "view" the Object
    *
    * Default is true and check entity if the objet is entity assign
    *
    * May be overloaded if needed
    *
    * @return bool
    **/
   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   /**
    * Have I the global right to "create" the Object
    * May be overloaded if needed (ex KnowbaseItem)
    *
    * @return bool
    **/
   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
   }

   /**
    * Display Tab for each budget
    *
    * @param array $options
    *
    * @return array
    */
   function defineTabs($options = []) {

      $ong = [];

      $this->addDefaultFormTab($ong);
      $this->addStandardTab('Document', $ong, $options);
      $this->addStandardTab('Log', $ong, $options);

      return $ong;
   }

   /**
    * allow to control data before adding in bdd
    *
    * @param $input
    * @return array
    */
   function prepareInputForAdd($input) {

      if (!isset($input["plugin_resources_professions_id"]) || $input["plugin_resources_professions_id"] == '0') {
         Session::addMessageAfterRedirect(__('The profession for the budget must be filled', 'resources'), false, ERROR);
         return [];
      }

      return $input;
   }

   /**
    * allow to control data before updating in bdd
    *
    * @param $input
    * @return array
    */
   function prepareInputForUpdate($input) {

      if (!isset($input["plugin_resources_professions_id"]) || $input["plugin_resources_professions_id"] == '0') {
         Session::addMessageAfterRedirect(__('The profession for the budget must be filled', 'resources'), false, ERROR);
         return [];
      }

      return $input;
   }

   /**
    * allow search management
    */
   function getSearchOptions() {

      $tab           = [];
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

      $tab[3]['table']         = 'glpi_plugin_resources_ranks';
      $tab[3]['field']         = 'name';
      $tab[3]['name']          = __('Rank', 'resources');
      $tab[3]['massiveaction'] = false;
      $tab[3]['datatype']      = 'dropdown';

      $tab[4]['table']         = 'glpi_plugin_resources_professions';
      $tab[4]['field']         = 'name';
      $tab[4]['name']          = __('Profession', 'resources');
      $tab[4]['massiveaction'] = false;
      $tab[4]['datatype']      = 'dropdown';

      $tab[5]['table']    = 'glpi_plugin_resources_budgettypes';
      $tab[5]['field']    = 'name';
      $tab[5]['name']     = __('Budget type', 'resources');
      $tab[5]['datatype'] = 'dropdown';

      $tab[6]['table']    = $this->getTable();
      $tab[6]['field']    = 'begin_date';
      $tab[6]['name']     = __('Begin date');
      $tab[6]['datatype'] = 'date';

      $tab[7]['table']    = $this->getTable();
      $tab[7]['field']    = 'end_date';
      $tab[7]['name']     = __('End date');
      $tab[7]['datatype'] = 'date';

      $tab[8]['table'] = $this->getTable();
      $tab[8]['field'] = 'volume';
      $tab[8]['name']  = __('Budget volume', 'resources');

      $tab[9]['table']    = 'glpi_plugin_resources_budgetvolumes';
      $tab[9]['field']    = 'name';
      $tab[9]['name']     = __('Type of budget volume', 'resources');
      $tab[9]['datatype'] = 'dropdown';

      $tab[10]['table']         = $this->getTable();
      $tab[10]['field']         = 'date_mod';
      $tab[10]['name']          = __('Last update');
      $tab[10]['datatype']      = 'datetime';
      $tab[10]['massiveaction'] = false;

      $tab[80]['table']    = 'glpi_entities';
      $tab[80]['field']    = 'completename';
      $tab[80]['name']     = __('Entity');
      $tab[80]['datatype'] = 'dropdown';

      return $tab;
   }

   /**
    * Display the budget form
    *
    * @param $ID integer ID of the item
    * @param $options array
    *     - target filename : where to go when done.
    *     - withtemplate boolean : template or basic item
    *
    * @return boolean item found
    * */
   function showForm($ID, $options = [""]) {
      global $CFG_GLPI;

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Name')."</td>";
      echo "<td>";
      Html::autocompletionTextField($this, "name", ['value' => $this->fields["name"]]);
      echo "</td>";

      echo "<td>".__('Budget type', 'resources')."</td>";
      echo "<td>";
      Dropdown::show('PluginResourcesBudgetType', ['value'  => $this->fields["plugin_resources_budgettypes_id"],
          'entity' => $this->fields["entities_id"]]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Profession', 'resources')."</td>";
      echo "<td>";
      $params = ['name'    => 'plugin_resources_professions_id',
                      'value'   => $this->fields['plugin_resources_professions_id'],
                      'entityt' => $this->fields["entities_id"],
                      'action'  => $CFG_GLPI["root_doc"]."/plugins/resources/ajax/dropdownRank.php",
                      'span'    => 'span_rank',
                      'sort'    => true];
      PluginResourcesResource::showGenericDropdown('PluginResourcesProfession', $params);

      echo "</td>";
      echo "<td>".__('Rank', 'resources')."</td><td>";
      echo "<span id='span_rank' name='span_rank'>";
      if ($this->fields["plugin_resources_ranks_id"] > 0) {
         echo Dropdown::getDropdownName('glpi_plugin_resources_ranks', $this->fields["plugin_resources_ranks_id"]);
      } else {
         echo __('None');
      }
      echo "</span></td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Budget volume', 'resources')."</td>";
      echo "<td>";
      $options = ['value' => 0];
      Html::autocompletionTextField($this, 'volume', $options);
      echo "</td><td>".__('Type of budget volume', 'resources')."</td><td>";
      Dropdown::show('PluginResourcesBudgetVolume', ['value'  => $this->fields["plugin_resources_budgetvolumes_id"],
          'entity' => $this->fields["entities_id"]]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Begin date')."</td>";
      echo "<td>";
      Html::showDateField("begin_date", ['value' => $this->fields["begin_date"]]);
      echo "</td>";
      echo "<td>".__('End date')."</td>";
      echo "<td>";
      Html::showDateField("end_date", ['value' => $this->fields["end_date"]]);
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='center' colspan='6'>";
      printf(__('Last update on %s'), Html::convDateTime($this->fields["date_mod"]));
      echo "</td>";
      echo "</tr>";

      if (Session::getCurrentInterface() != 'central') {
         $options['candel'] = false;
      }
      $this->showFormButtons($options);

      return true;
   }

   /**
    * @param $menu
    *
    * @return mixed
    */
   static function getMenuOptions($menu) {

      $plugin_page                                   = '/plugins/resources/front/budget.php';
      $itemtype                                      = strtolower(self::getType());

      //Menu entry in admin
      $menu['options'][$itemtype]['title']           = self::getTypeName();
      $menu['options'][$itemtype]['page']            = $plugin_page;
      $menu['options'][$itemtype]['links']['search'] = $plugin_page;

      if (Session::haveright(self::$rightname, UPDATE)) {
         $menu['options'][$itemtype]['links']['add'] = '/plugins/resources/front/budget.form.php';
      }

      return $menu;
   }

}

