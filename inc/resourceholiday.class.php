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
 * Class PluginResourcesResourceHoliday
 */
class PluginResourcesResourceHoliday extends CommonDBTM {

   static $rightname = 'plugin_resources_holiday';

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

      return _n('Holiday', 'Holidays', $nb, 'resources');
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
    * @param array $input datas used to add the item
    *
    * @return array the modified $input array
    **/
   function prepareInputForAdd($input) {

      if (!isset ($input["date_begin"]) || $input["date_begin"] == 'NULL') {
         Session::addMessageAfterRedirect(__('The begin date of the forced holiday period must be filled', 'resources'), false, ERROR);
         return [];
      }
      if (!isset ($input["date_end"]) || $input["date_end"] == 'NULL') {
         Session::addMessageAfterRedirect(__('The end date of the forced holiday period must be filled', 'resources'), false, ERROR);
         return [];
      }

      return $input;
   }

   function post_addItem() {
      global $CFG_GLPI;

      Session::addMessageAfterRedirect(__('Forced holiday declaration of a resource performed', 'resources'));

      $PluginResourcesResource = new PluginResourcesResource();
      if ($CFG_GLPI["notifications_mailing"]) {
         $options = ['holiday_id' => $this->fields["id"]];
         if ($PluginResourcesResource->getFromDB($this->fields["plugin_resources_resources_id"])) {
            NotificationEvent::raiseEvent("newholiday", $PluginResourcesResource, $options);
         }
      }
   }

   /**
    * Prepare input datas for updating the item
    *
    * @param array $input data used to update the item
    *
    * @return array the modified $input array
    **/
   function prepareInputForUpdate($input) {
      if (!isset ($input["date_begin"]) || $input["date_begin"] == 'NULL') {
         Session::addMessageAfterRedirect(__('The begin date of the forced holiday period must be filled', 'resources'), false, ERROR);
         return [];
      }
      if (!isset ($input["date_end"]) || $input["date_end"] == 'NULL') {
         Session::addMessageAfterRedirect(__('The end date of the forced holiday period must be filled', 'resources'), false, ERROR);
         return [];
      }

      //unset($input['picture']);
      $this->getFromDB($input["id"]);

      $input["_old_date_begin"] = $this->fields["date_begin"];
      $input["_old_date_end"]   = $this->fields["date_end"];
      $input["_old_comment"]    = $this->fields["comment"];

      return $input;
   }

   /**
    * Actions done after the UPDATE of the item in the database
    *
    * @param boolean $history store changes history ? (default 1)
    *
    * @return void
    **/
   function post_updateItem($history = 1) {
      global $CFG_GLPI;

      if ($CFG_GLPI["notifications_mailing"] && count($this->updates)) {
         $options                 = ['holiday_id' => $this->fields["id"],
                                     'oldvalues'  => $this->oldvalues];
         $PluginResourcesResource = new PluginResourcesResource();
         if ($PluginResourcesResource->getFromDB($this->fields["plugin_resources_resources_id"])) {
            NotificationEvent::raiseEvent("updateholiday", $PluginResourcesResource, $options);
         }
      }
   }

   /**
    * Actions done before the DELETE of the item in the database /
    * Maybe used to add another check for deletion
    *
    * @return boolean true if item need to be deleted else false
    **/
   function pre_deleteItem() {
      global $CFG_GLPI;

      if ($CFG_GLPI["notifications_mailing"]) {
         $PluginResourcesResource = new PluginResourcesResource();
         $options                 = ['holiday_id' => $this->fields["id"]];
         if ($PluginResourcesResource->getFromDB($this->fields["plugin_resources_resources_id"])) {
            NotificationEvent::raiseEvent("deleteholiday", $PluginResourcesResource, $options);
         }
      }
      return true;
   }

   /**
    * Provides search options configuration. Do not rely directly
    * on this, @see CommonDBTM::searchOptions instead.
    *
    * @since 9.3
    *
    * This should be overloaded in Class
    *
    * @return array a *not indexed* array of search options
    *
    * @see https://glpi-developer-documentation.rtfd.io/en/master/devapi/search.html
    **/
   function rawSearchOptions() {

      $tab = [];

      $tab[] = [
         'id'   => 'common',
         'name' => self::getTypeName(2)
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
         'id'       => '3',
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

      $tab[] = [
         'id'       => '5',
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
      echo __('Forced holiday management', 'resources');
      echo "</div></h3>";

      echo "<div align='center'><table class='tab_menu' width='30%' cellpadding='5'>";

      $canholiday = Session::haveright('plugin_resources_holiday', UPDATE);

      echo "<tr class=''>";
      if ($canholiday) {
         echo "<td class='tab_td_menu center'>";
         echo "<a href=\"./resourceholiday.form.php\">";
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/holidayresource.png' alt='" . __('Declare a forced holiday', 'resources') . "'>";
         echo "<br>" . __('Declare a forced holiday', 'resources') . "</a>";
         echo "</td>";
         echo "<td class='tab_td_menu center'>";
         echo "<a href=\"./resourceholiday.php\">";
         echo "<img src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/holidaylist.png' alt='" . __('List of forced holidays', 'resources') . "'>";
         echo "<br>" . __('List of forced holidays', 'resources') . "</a>";
         echo "</td>";
      }
      echo "</tr></table>";
      echo "</div>";

   }

   //Show form from helpdesk to add holiday of a resource

   /**
    * @param       $ID
    * @param array $options
    */
   function showForm($ID, $options = []) {
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

      echo "<form method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR. "/front/resourceholiday.form.php\">";

      echo "<div class=\"form-row plugin_resources_wizard_margin\">";
      echo "<div class=\"bt-feature col-md-12 \">";
      echo "<h4 class=\"bt-title-divider\">";
      echo "<img class='resources_wizard_resp_img' src='" . PLUGIN_RESOURCES_WEBDIR. "/pics/holidayresource.png' alt='holidayresource'/>&nbsp;";
      $title = __('Declare a forced holiday', 'resources');
      if ($ID > 0) {
         $title = __('Detail of the forced holiday', 'resources');
      }
      echo $title;
      echo "</h4></div></div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-4 \">";
      echo PluginResourcesResource::getTypeName(1);
      echo "</div>";
      echo "<div class=\"bt-feature col-md-4 \">";
      PluginResourcesResource::dropdown(['name'    => 'plugin_resources_resources_id',
                                         'display' => true,
                                         'value'   => $this->fields["plugin_resources_resources_id"],
                                         'entity'  => $_SESSION['glpiactiveentities']]);
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
      echo "<a href=\"./resourceholiday.form.php\">";
      echo __('Declare a forced holiday', 'resources');
      echo "</a>";
      echo "&nbsp;/&nbsp;<a href=\"./resourceholiday.php\">";
      echo __('List of forced holidays', 'resources');
      echo "</a>";
      echo "</div>";
      echo "</div></div>";

      echo "<div class=\"form-row\">";
      echo "<div class=\"bt-feature col-md-12 \">";
      echo "<div class='next'>";

      if ($ID > 0) {
         echo Html::hidden('id', ['value' => $ID]);
         echo Html::hidden('plugin_resources_resources_id', ['value' => $this->fields["plugin_resources_resources_id"]]);
         echo Html::submit(_sx('button', 'Update'), ['name' => 'updateholidayresources', 'class' => 'btn btn-primary']);
         echo "&nbsp;&nbsp;";
         echo Html::submit(_sx('button', 'Delete permanently'), ['name' => 'deleteholidayresources', 'class' => 'btn btn-primary']);

      } else {
         echo Html::submit(_sx('button', 'Add'), ['name' => 'addholidayresources', 'class' => 'btn btn-primary']);
      }

      echo "</div>";
      echo "</div></div>";

      Html::closeForm();

      echo "</div>";
      echo "</div>";
      echo "</div>";

   }
}

