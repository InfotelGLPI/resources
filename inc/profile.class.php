<?php

/*
 * @version $Id: profile.class.php 480 2012-11-09 tynet $
  -------------------------------------------------------------------------
  Resources plugin for GLPI
  Copyright (C) 2006-2012 by the Resources Development Team.

  https://forge.indepnet.net/projects/resources
  -------------------------------------------------------------------------

  LICENSE

  This file is part of Resources.

  Resources is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  Resources is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with Resources. If not, see <http://www.gnu.org/licenses/>.
  --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginResourcesProfile extends Profile {

   static $rightname = "profile";

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == 'Profile') {
         return PluginResourcesResource::getTypeName(2);
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      global $CFG_GLPI;

      if ($item->getType() == 'Profile') {
         $ID = $item->getID();
         $prof = new self();

         self::addDefaultProfileInfos($ID, array('plugin_resources'                 => 0,
                                                  'plugin_resources_task'            => 0, 
                                                  'plugin_resources_checklist'       => 0, 
                                                  'plugin_resources_employee'        => 0, 
                                                  'plugin_resources_resting'         => 0, 
                                                  'plugin_resources_holiday'         => 0, 
                                                  'plugin_resources_employment'      => 0, 
                                                  'plugin_resources_budget'          => 0, 
                                                  'plugin_resources_dropdown_public' => 0,
                                                  'plugin_resources_open_ticket'     => 0,
                                                  'plugin_resources_all'             => 0));
         $prof->showForm($ID);
      }

      return true;
   }
   
   static function createFirstAccess($profiles_id) {
      self::addDefaultProfileInfos($profiles_id, 
                                   array('plugin_resources'                 => ALLSTANDARDRIGHT,
                                         'plugin_resources_task'            => ALLSTANDARDRIGHT, 
                                         'plugin_resources_checklist'       => ALLSTANDARDRIGHT, 
                                         'plugin_resources_employee'        => ALLSTANDARDRIGHT, 
                                         'plugin_resources_resting'         => ALLSTANDARDRIGHT, 
                                         'plugin_resources_holiday'         => ALLSTANDARDRIGHT, 
                                         'plugin_resources_employment'      => ALLSTANDARDRIGHT, 
                                         'plugin_resources_budget'          => ALLSTANDARDRIGHT, 
                                         'plugin_resources_dropdown_public' => ALLSTANDARDRIGHT,
                                         'plugin_resources_open_ticket'     => 1,
                                         'plugin_resources_all'             => 1), true);

   }

   /**
    * @param $profile
   **/
   static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false) {
      global $DB;
      
      $profileRight = new ProfileRight();
      foreach ($rights as $right => $value) {
         if (countElementsInTable('glpi_profilerights',
                                   "`profiles_id`='$profiles_id' AND `name`='$right'") && $drop_existing) {
            $profileRight->deleteByCriteria(array('profiles_id' => $profiles_id, 'name' => $right));
         }
         if (!countElementsInTable('glpi_profilerights',
                                   "`profiles_id`='$profiles_id' AND `name`='$right'")) {
            $myright['profiles_id'] = $profiles_id;
            $myright['name']        = $right;
            $myright['rights']      = $value;
            $profileRight->add($myright);

            //Add right to the current session
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }

   function showForm($profiles_id=0, $openform=TRUE, $closeform=TRUE) {

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         $profile = new Profile();
         echo "<form method='post' action='".$profile->getFormURL()."'>";
      }

      $profile = new Profile();
      $profile->getFromDB($profiles_id);

      $generalRights = $this->getAllRights(false, array('general'));
      $profile->displayRightsChoiceMatrix($generalRights, array('canedit'       => $canedit,
                                                                'default_class' => 'tab_bg_2',
                                                                'title'         => __('General')));
      
      echo "<table class='tab_cadre_fixehov'>";
      echo "<tr class='tab_bg_1'><th colspan='4'>".__('Helpdesk')."</th></tr>\n";

      $effective_rights = ProfileRight::getProfileRights($profiles_id, array('plugin_resources_open_ticket', 'plugin_resources_all'));
      echo "<tr class='tab_bg_2'>";
      echo "<td width='20%'>".__('Associable items to a ticket')."</td>";
      echo "<td colspan='5'>";
      Html::showCheckbox(array('name'    => '_plugin_resources_open_ticket',
                               'checked' => $effective_rights['plugin_resources_open_ticket']));
      echo "</td></tr>\n";
      
      echo "<tr class='tab_bg_2'>";
      echo "<td width='20%'>".__('All resources access', 'resources')."</td>";
      echo "<td colspan='5'>";
      Html::showCheckbox(array('name'    => '_plugin_resources_all',
                               'checked' => $effective_rights['plugin_resources_all']));
      echo "</td></tr>\n";
      
      echo "</table>";
      
      $ssiiRights = $this->getAllRights(false, array('ssii'));
      $profile->displayRightsChoiceMatrix($ssiiRights, array('canedit'       => $canedit,
                                                             'default_class' => 'tab_bg_2',
                                                             'title'         => __('Service company management', 'resources')));
      
      $publicRights = $this->getAllRights(false, array('public'));
      $profile->displayRightsChoiceMatrix($publicRights, array('canedit'       => $canedit,
                                                               'default_class' => 'tab_bg_2',
                                                               'title'         => __('Public service management', 'resources')));
      
      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo Html::hidden('id', array('value' => $profiles_id));
         echo Html::submit(_sx('button', 'Save'), array('name' => 'update'));
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";

      $this->showLegend();

   }
   
   static function getAllRights($all=true, $types=array()) {
      
  
      $rights = array(
          array('itemtype'  => 'PluginResourcesResource',
                'label'     => _n('Human resource', 'Human resources', 1, 'resources'),
                'field'     => 'plugin_resources',
                'type'      => 'general'
          ),
          array('itemtype'  => 'PluginResourcesTask',
                'label'     => _n('Task', 'Tasks', 1),
                'field'     => 'plugin_resources_task',
                'type'      => 'general'
          ),
          array('itemtype'  => 'PluginResourcesBudget',
                'label'     => _n('Budget', 'Budgets', 1),
                'field'     => 'plugin_resources_budget',
                'type'      => 'public'
          ),
          array('itemtype'  => 'PluginResourcesChecklist',
                'label'     => _n('Checklist', 'Checklists', 1, 'resources'),
                'field'     => 'plugin_resources_checklist',
                'type'      => 'general'
          ),
          array('itemtype'  => 'PluginResourcesEmployee',
                'label'     => _n('Employee', 'Employees', 1, 'resources'),
                'field'     => 'plugin_resources_employee',
                'type'      => 'general'
          ),
          array('itemtype'  => 'PluginResourcesResourceResting',
                'label'     => _n('Non contract period', 'Non contract periods', 1, 'resources'),
                'field'     => 'plugin_resources_resting',
                'type'      => 'ssii'
          ),
          array('itemtype'  => 'PluginResourcesResourceHoliday',
                'label'     => _n('Holiday', 'Holidays', 1, 'resources'),
                'field'     => 'plugin_resources_holiday',
                'type'      => 'ssii'
          ),
          array('itemtype'  => 'PluginResourcesEmployment',
                'label'     => _n('Employment', 'Employments', 1, 'resources'),
                'field'     => 'plugin_resources_employment',
                'type'      => 'public'
          ),
          array('itemtype'  => 'PluginResourcesResource',
                'label'     => __('Dropdown management', 'resources'),
                'field'     => 'plugin_resources_dropdown_public',
                'type'      => 'public'
          )
      );
      
      if ($all) {
         $rights[] = array('itemtype' => 'PluginResourcesResource',
                           'label'    =>  __('All resources access', 'resources'),
                           'field'    => 'plugin_resources_all');
         
         $rights[] = array('itemtype' => 'PluginResourcesResource',
                           'label'    =>  __('Associable items to a ticket'),
                           'field'    => 'plugin_resources_open_ticket');
      }
      if (!$all) {
         $customRights = array();
         foreach ($rights as $right) {
            if (in_array($right['type'], $types)) {
               $customRights[] = $right;
            }
         }

         return $customRights;
      }
      
      return $rights;
   }
   
  /**
    * Init profiles
    *
    **/
    
   static function translateARight($old_right) {
      switch ($old_right) {
         case '': 
            return 0;
         case 'r' :
            return READ;
         case 'w':
            return ALLSTANDARDRIGHT;
         case '0':
         case '1':
            return $old_right;
            
         default :
            return 0;
      }
   }
   
   
   /**
   * @since 0.85
   * Migration rights from old system to the new one for one profile
   * @param $profiles_id the profile ID
   */
   static function migrateOneProfile($profiles_id) {
      global $DB;
      //Cannot launch migration if there's nothing to migrate...
      if (!TableExists('glpi_plugin_resources_profiles')) {
         return true;
      }
      
      foreach ($DB->request('glpi_plugin_resources_profiles', 
                            "`profiles_id`='$profiles_id'") as $profile_data) {

         $matching = array('resources'       => 'plugin_resources',
                           'task'            => 'plugin_resources_task', 
                           'checklist'       => 'plugin_resources_checklist', 
                           'employee'        => 'plugin_resources_employee' , 
                           'resting'         => 'plugin_resources_resting', 
                           'holiday'         => 'plugin_resources_holiday', 
                           'employment'      => 'plugin_resources_employment', 
                           'budget'          => 'plugin_resources_budget', 
                           'dropdown_public' => 'plugin_resources_dropdown_public',
                           'open_ticket'     => 'plugin_resources_open_ticket',
                           'all'             => 'plugin_resources_all');
         
         $current_rights = ProfileRight::getProfileRights($profiles_id, array_values($matching));
         foreach ($matching as $old => $new) {
            if (!isset($current_rights[$old])) {
               $query = "UPDATE `glpi_profilerights` 
                         SET `rights`='".self::translateARight($profile_data[$old])."' 
                         WHERE `name`='$new' AND `profiles_id`='$profiles_id'";
               $DB->query($query);
            }
         }
      }
   }
   
  /**
   * Initialize profiles, and migrate it necessary
   */
   static function initProfile() {
      global $DB;
      $profile = new self();

      //Add new rights in glpi_profilerights table
      foreach ($profile->getAllRights(true) as $data) {
         if (countElementsInTable("glpi_profilerights", 
                                  "`name` = '".$data['field']."'") == 0) {
            ProfileRight::addProfileRights(array($data['field']));
         }
      }
      
      //Migration old rights in new ones
      foreach ($DB->request("SELECT `id` FROM `glpi_profiles`") as $prof) {
         self::migrateOneProfile($prof['id']);
      }
      foreach ($DB->request("SELECT *
                           FROM `glpi_profilerights` 
                           WHERE `profiles_id`='".$_SESSION['glpiactiveprofile']['id']."' 
                              AND `name` LIKE '%plugin_resources%'") as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights']; 
      }
   }

   static function removeRightsFromSession() {
      foreach (self::getAllRights(true) as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
      }
   }
   
}

?>