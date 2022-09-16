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
 * Class PluginResourcesResourceResting
 */
class PluginResourcesResourceResting extends CommonDBTM {

   static $rightname = 'plugin_resources_resting';
   public $dohistory = true;

   /**
    * Return the localized name of the current Type
    * Should be overloaded in each new class
    *
    * @return string
    **/
   static function getTypeName($nb = 0) {

      return _n('Non contract period', 'Non contract periods', $nb, 'resources');
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
      return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
   }

   /**
    * Prepare input datas for adding the item
    *
    * @param $input datas used to add the item
    *
    * @return the modified $input array
    **/
   function prepareInputForAdd($input) {

      if (!isset($input["date_begin"]) || $input["date_begin"] == 'NULL') {
         Session::addMessageAfterRedirect(__('The begin date of the non contract period must be filled', 'resources'), false, ERROR);
         return [];
      }

      return $input;
   }

   /**
    * Actions done after the ADD of the item in the database
    *
    * @return nothing
    **/
   function post_addItem() {
      global $CFG_GLPI;

      Session::addMessageAfterRedirect(__('Non contract period declaration of a resource performed', 'resources'));

      $PluginResourcesResource = new PluginResourcesResource();
      if ($CFG_GLPI["notifications_mailing"]) {
         $options = ['resting_id' => $this->fields["id"]];
         if ($PluginResourcesResource->getFromDB($this->fields["plugin_resources_resources_id"])) {
            NotificationEvent::raiseEvent("newresting", $PluginResourcesResource, $options);
         }
      }
   }

   /**
    * Prepare input datas for updating the item
    *
    * @param $input datas used to update the item
    *
    * @return the modified $input array
    **/
   function prepareInputForUpdate($input) {

      if (!isset($input["date_begin"]) || $input["date_begin"] == 'NULL') {
         Session::addMessageAfterRedirect(__('The begin date of the non contract period must be filled', 'resources'), false, ERROR);
         return [];
      }
      if (isset($input['date_end']) && empty($input['date_end'])) {
         $input['date_end'] = 'NULL';
      }

      //unset($input['picture']);
      $this->getFromDB($input["id"]);

      $input["_old_date_begin"] = $this->fields["date_begin"];
      $input["_old_date_end"] = $this->fields["date_end"];
      $input["_old_locations_id"] = $this->fields["locations_id"];
      $input["_old_at_home"] = $this->fields["at_home"];
      $input["_old_comment"] = $this->fields["comment"];

      return $input;
   }

   /**
    * Actions done after the UPDATE of the item in the database
    *
    * @param $history store changes history ? (default 1)
    *
    * @return nothing
    **/
   function post_updateItem($history = 1) {
      global $CFG_GLPI;

      if ($CFG_GLPI["notifications_mailing"] && count($this->updates)) {
         $options = ['resting_id' => $this->fields["id"],
             'oldvalues' => $this->oldvalues];
         $PluginResourcesResource = new PluginResourcesResource();
         if ($PluginResourcesResource->getFromDB($this->fields["plugin_resources_resources_id"])) {
            NotificationEvent::raiseEvent("updateresting", $PluginResourcesResource, $options);
         }
      }
   }

   /**
    * Actions done before the DELETE of the item in the database /
    * Maybe used to add another check for deletion
    *
    * @return bool : true if item need to be deleted else false
    **/
   function pre_deleteItem() {
      global $CFG_GLPI;

      if ($CFG_GLPI["notifications_mailing"]) {
         $PluginResourcesResource = new PluginResourcesResource();
         $options = ['resting_id' => $this->fields["id"]];
         if ($PluginResourcesResource->getFromDB($this->fields["plugin_resources_resources_id"])) {
            NotificationEvent::raiseEvent("deleteresting", $PluginResourcesResource, $options);
         }
      }
      return true;
   }

   /**
    * Get the Search options for the given Type
    *
    * This should be overloaded in Class
    *
    * @return an array of search options
    * More information on https://forge.indepnet.net/wiki/glpi/SearchEngine
    **/
   function rawSearchOptions() {

      $tab[] = [
         'id'                 => 'common',
         'name'               => self::GetTypeName()
      ];

      $tab[] = [
         'id'            => '1',
         'table'         => 'glpi_plugin_resources_resources',
         'field'         => 'name',
         'name'          => __('Surname'),
         'datatype'      => 'itemlink',
         'itemlink_type' => $this->getType()
      ];
      if (!Session::haveRight("plugin_resources_all", READ)) {
         $tab[] = [
            'id'         => '1',
            'searchtype' => 'contains'
         ];
      }

      $tab[] = [
         'id'    => '2',
         'table' => 'glpi_plugin_resources_resources',
         'field' => 'firstname',
         'name'  => __('First name')
      ];

      $tab[] = [
         'id'       => '5',
         'table'    => $this->getTable(),
         'field'    => 'date_begin',
         'name'     => __('Begin date'),
         'datatype' => 'date'
      ];

      $tab[] = [
         'id'       => '4',
         'table'    => $this->getTable(),
         'field'    => 'date_end',
         'name'     => __('End date'),
         'datatype' => 'date'
      ];
      $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

      $tab[] = [
         'id'       => '6',
         'table'    => $this->getTable(),
         'field'    => 'at_home',
         'name'     => __('At home', 'resources'),
         'datatype' => 'bool'
      ];

      $tab[] = [
         'id'       => '7',
         'table'    => $this->getTable(),
         'field'    => 'comment',
         'name'     => __('Comments'),
         'datatype' => 'text'
      ];

      $tab[] = [
         'id'            => '30',
         'table'         => $this->getTable(),
         'field'         => 'id',
         'name'          => __('ID'),
         'datatype'      => 'number',
         'massiveaction' => false
      ];

      return $tab;
   }

   /**
    *Menu
    */
   function showMenu() {
      global $CFG_GLPI;
      echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR."/css/style_bootstrap_main.css");
      echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR."/css/style_bootstrap_ticket.css");

      echo "<h3><div class='alert alert-secondary' role='alert'>";
      echo "<i class='fas fa-user-friends'></i>&nbsp;";
      echo _n('Non contract period management', 'Non contract periods management', 2, 'resources');
      echo "</div></h3>";

      echo "<div align='center'><table class='tab_menu' width='30%' cellpadding='5'>";

      $canresting = Session::haveright('plugin_resources_resting', UPDATE);

      echo "<tr class=''>";
      if ($canresting) {
         //Add resting resource
         echo "<td class='tab_td_menu center'>";
         echo "<a href=\"./resourceresting.form.php\">";
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/newresting.png' alt='" . __('Declare a non contract period', 'resources') . "'>";
         echo "<br>" . __('Declare a non contract period', 'resources') . "</a>";
         echo "</td>";

         //delete resting resource
         echo "<td class='tab_td_menu center'>";
         echo "<a href=\"./resourceresting.form.php?end\">";
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/closeresting.png' alt='" . __('Declaring the end of non contract periods', 'resources') . "'>";
         echo "<br>" . __('Declaring the end of non contract periods', 'resources') . "</a>";
         echo "</td>";

         //List resting resource
         echo "<td class='tab_td_menu center'>";
         echo "<a href=\"./resourceresting.php\">";
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/restinglist.png' alt='" . __('List of non contract periods', 'resources') . "'>";
         echo "<br>" . __('List of non contract periods', 'resources') . "</a>";
         echo "</td>";
      }
      echo "</tr></table>";

      echo "</div>";

   }

   /**
    * Show form from helpdesk to add resting of a resource
    *
    * @param $ID
    * @param array $options
    */
   function showForm($ID, $options = []) {
      global $CFG_GLPI;

      $this->initForm($ID, $options);

      echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR."/css/bootstrap_main.css");
      echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR."/css/style_bootstrap_ticket.css");

      echo "<h3><div class='alert alert-secondary' role='alert' >";
      echo "<i class='fas fa-user-friends'></i>&nbsp;";
      echo __('Resources management', 'resources');
      echo "</div></h3>";

      echo "<div id ='content'>";
      echo "<div class='bt-container resources_wizard_resp'> ";
      echo "<div class='bt-block bt-features' > ";

      echo "<form method='post' action=\"".PLUGIN_RESOURCES_WEBDIR."/front/resourceresting.form.php\">";

      echo "<div class=\"form-row plugin_resources_wizard_margin\">";
      echo "<div class=\"bt-feature col-md-12 \">";
      echo "<h4 class=\"bt-title-divider\">";
      echo "<img class='resources_wizard_resp_img' src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/newresting.png' alt='newresting'/>&nbsp;";
      $title = __('Declare a non contract period', 'resources');
      if ($ID > 0) {
         $title = __('Detail of non contract period', 'resources');
      }
      echo $title;
      echo "</h4></div></div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-4 \">";
      echo PluginResourcesResource::getTypeName(1);

      echo "</div>";
      echo "<div class=\"bt-feature col-md-4 \">";
      PluginResourcesResource::dropdown(['name'   => 'plugin_resources_resources_id',
                                         'display'   => true,
                                              'value'  => $this->fields["plugin_resources_resources_id"],
                                              'entity' => $_SESSION['glpiactiveentities']]);

      echo "</div>";
      echo "</div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-4 \">";
      echo __('Begin date');
      echo "</div>";
      echo "<div class=\"bt-feature col-md-4 \">";
      Html::showDateField("date_begin", ['value' => $this->fields["date_begin"]]);
      echo "</div>";
      echo "</div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-4 \">";
      echo __('End date');
      echo "</div>";
      echo "<div class=\"bt-feature col-md-4 \">";
      Html::showDateField("date_end", ['value' => $this->fields["date_end"]]);
      echo "</div>";
      echo "</div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-4 \">";
      echo __('Agency concerned', 'resources');
      echo "</div>";
      echo "<div class=\"bt-feature col-md-4 \">";
      Dropdown::show('Location', ['value' => $this->fields["locations_id"]]);
      echo "</div>";
      echo "</div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-4 \">";
      echo __('At home', 'resources');
      echo "</div>";
      echo "<div class=\"bt-feature col-md-4 \">";
      Dropdown::showYesNo('at_home', $this->fields['at_home']);
      echo "</div>";
      echo "</div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-4 \">";
      echo __('Comments');
      echo "</div>";
      echo "<div class=\"bt-feature col-md-4 \">";
      echo Html::textarea([
                             'name'    => 'comment',
                             'value' => $this->fields["comment"],
                             'cols'    => '70',
                             'rows'    => '4',
                             'display' => false,
                          ]);
      echo "</div>";
      echo "</div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-12 \">";
      echo "<div class='preview'>";
      echo "<a href=\"./resourceresting.form.php\">";
      echo __('Declare a non contract period', 'resources');
      echo "</a>";
      echo "&nbsp;/&nbsp;<a href=\"./resourceresting.php\">";
      echo __('List of non contract periods', 'resources');
      echo "</a>";
      echo "</div>";
      echo "</div></div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-12 \">";
      echo "<div class='next'>";
      if ($ID > 0) {
         echo Html::hidden('id', ['value' => $ID]);
         echo Html::hidden('plugin_resources_resources_id', ['value' => $this->fields["plugin_resources_resources_id"]]);
         echo Html::submit(_sx('button', 'Update'), ['name' => 'updaterestingresources', 'class' => 'btn btn-primary']);
         echo "&nbsp;&nbsp;";
         echo Html::submit(_sx('button', 'Delete permanently'), ['name' => 'deleterestingresources', 'class' => 'btn btn-primary']);

      } else {
         echo Html::submit(_sx('button', 'Add'), ['name' => 'addrestingresources', 'class' => 'btn btn-primary']);
      }
      echo "</div>";
      echo "</div></div>";

      Html::closeForm();

      echo "</div>";
      echo "</div>";
      echo "</div>";

   }

   /**
    * Show form from helpdesk to add resting of a resource
    *
    * @param $ID
    * @param array $options
    */
   function showFormEnd($ID, $options = []) {
      global $CFG_GLPI;

      $this->initForm($ID, $options);
      echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR."/css/style_bootstrap_main.css");
      echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR."/css/style_bootstrap_ticket.css");

      echo "<h3><div class='alert alert-secondary' role='alert' >";
      echo "<i class='fas fa-user-friends'></i>&nbsp;";
      echo __('Resources management', 'resources');
      echo "</div></h3>";
      
      echo "<div id ='content'>";
      echo "<div class='bt-container resources_wizard_resp'> ";
      echo "<div class='bt-block bt-features' > ";

      echo "<form method='post' action=\"".PLUGIN_RESOURCES_WEBDIR."/front/resourceresting.form.php\">";

      echo "<div class=\"form-row plugin_resources_wizard_margin\">";
      echo "<div class=\"bt-feature col-md-12 \">";
      echo "<h4 class=\"bt-title-divider\">";
      echo "<img class='resources_wizard_resp_img' src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/newresting.png' alt='newresting'/>&nbsp;";
      echo __('Declaring the end of non contract periods', 'resources');
      echo "</h4></div></div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-4 \">";
      echo PluginResourcesResource::getTypeName(1);
      echo "</div>";
      echo "<div class=\"bt-feature col-md-4 \">";
      $rand = PluginResourcesResource::dropdown([
         'name' => 'plugin_resources_resources_id',
         'on_change' => 'plugin_resources_load_user_resting()',
         'entity' => $_SESSION['glpiactiveentities'],
         'display' => true
      ]);

      echo "<script type='text/javascript'>";
      echo "function plugin_resources_load_user_resting(){";
      $params = [
         'action' => 'loadResting',
         'plugin_resources_resources_id' => '__VALUE__'
      ];
      Ajax::updateItemJsCode(
         'plugin_resources_resting',
         PLUGIN_RESOURCES_WEBDIR. '/ajax/resourceresting.php',
         $params,
         'dropdown_plugin_resources_resources_id'.$rand);
      echo "}";

      echo "</script>";
      echo "</div>";
      echo "</div>";

      echo "<div id='plugin_resources_resting'>";
      echo "</div>";

      echo "<div id='plugin_resources_endate_resting'>";
      echo "</div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-12 \">";
      echo "<div class='preview'>";
      echo "<a href=\"./resourceresting.php\">";
      echo __('List of non contract periods', 'resources');
      echo "</a>";
      echo "</div>";
      echo "<div class='next' id='plugin_resources_button_resting'>";
      //      echo "<input type='submit' name='addenddaterestingresources' value='" . _sx('button', 'Save') . "' class='submit' />";
      echo "</div>";
      echo "</div></div>";

      Html::closeForm();

      echo "</div>";
      echo "</div>";
      echo "</div>";
   }

   /**
    * Display of the choice of the intercontrat
    *
    * @param $plugin_resources_resources_id
    */
   function loadResting($plugin_resources_resources_id) {
      global $CFG_GLPI;

      $resting  = new PluginResourcesResourceResting();
      $restrict = ['plugin_resources_resources_id' => $plugin_resources_resources_id,
                   [
                      'OR' => [
                         ['date_end' => NULL],
                         ['date_end' => '0000-00-00']
                      ]
                   ]];

      $restings = $resting->find($restrict);

      //array of resting
      $elements = [];
      $elements[0] = Dropdown::EMPTY_VALUE;
      foreach ($restings as $data) {
         $elements[$data['id']] = PluginResourcesResource::getResourceName($plugin_resources_resources_id)." - ".Html::convDate($data['date_begin']);
      }

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-4 \">";
      echo __('Choosing the intercontrat', 'resources');
      echo "</div>";
      echo "<div class=\"bt-feature col-md-4 \">";
      $rand = Dropdown::showFromArray('plugin_resources_resting_id', $elements, ['on_change' => "plugin_resources_load_end_date_resting()"]);
      echo "</div>";
      echo "</div>";

      //script for display of end date
      echo "<script type='text/javascript'>";
      echo "function plugin_resources_load_end_date_resting(){";
      $params = ['action' => 'loadEndDateResting', 'plugin_resources_resting_id' => '__VALUE__'];
      Ajax::updateItemJsCode('plugin_resources_endate_resting', PLUGIN_RESOURCES_WEBDIR. '/ajax/resourceresting.php', $params, 'dropdown_plugin_resources_resting_id'.$rand);
      $params = ['action' => 'loadButtonResting', 'plugin_resources_resting_id' => '__VALUE__'];
      Ajax::updateItemJsCode('plugin_resources_button_resting', PLUGIN_RESOURCES_WEBDIR. '/ajax/resourceresting.php', $params, 'dropdown_plugin_resources_resting_id'.$rand);
      echo "}";

      echo "</script>";
   }

   /**
    * Display of end date
    *
    * @param $plugin_resources_resting_id
    */
   function loadEndDateResting($plugin_resources_resting_id) {

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-4 \">";
      echo __('End date');
      echo "</div>";
      echo "<div class=\"bt-feature col-md-4 \">";
      Html::showDateField("date_end");
      echo Html::hidden('id', ['value' => $plugin_resources_resting_id]);
      echo "</div>";
      echo "</div>";
   }

   /**
    * Display of end date
    *
    * @param $plugin_resources_resting_id
    */
   function loadButtonResting($plugin_resources_resting_id) {

      echo Html::submit(_sx('button', 'Save'), ['name' => 'addenddaterestingresources', 'class' => 'btn btn-primary']);
   }
}
