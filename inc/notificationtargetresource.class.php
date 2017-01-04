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
if (!defined('GLPI_ROOT')){
   die("Sorry. You can't access directly to this file");
}

// Class NotificationTarget
class PluginResourcesNotificationTargetResource extends NotificationTarget {

   const RESOURCE_MANAGER                     = 4300;
   const RESOURCE_AUTHOR                      = 4301;
   const RESOURCE_AUTHOR_LEAVING              = 4302;
   const RESOURCE_TASK_TECHNICIAN             = 4303;
   const RESOURCE_TASK_GROUP                  = 4304;
   const RESOURCE_USER                        = 4305;
   const RESOURCE_TARGET_ENTITY_GROUP         = 4306;
   const RESOURCE_SOURCE_ENTITY_GROUP         = 4307;
   const RESOURCE_SOURCE_ENTITY_GROUP_MANAGER = 4308;
   const RESOURCE_TARGET_ENTITY_GROUP_MANAGER = 4309;

   function getEvents() {

      return array ('new'                         => __('A resource has been added by', 'resources'),
                     'update'                      => __('A resource has been updated by', 'resources'),
                     'delete'                      => __('A resource has been removed by', 'resources'),
                     'newtask'                     => __('A task has been added by', 'resources'),
                     'updatetask'                  => __('A task has been updated by', 'resources'),
                     'deletetask'                  => __('A task has been removed by', 'resources'),
                     'AlertExpiredTasks'           => __('List of not finished tasks', 'resources'),
                     'AlertLeavingResources'       => __('These resources have normally left the company', 'resources'),
                     'AlertArrivalChecklists'      => __('Actions to do on these new resources', 'resources'),
                     'AlertLeavingChecklists'      => __('Actions to do on these leaving resources', 'resources'),
                     'LeavingResource'             => __('A resource has been declared leaving', 'resources'),
                     'report'                      => __('Creation report of the human resource', 'resources'),
                     'newresting'                  => __('A non contract period has been added', 'resources'),
                     'updateresting'               => __('A non contract period has been updated', 'resources'),
                     'deleteresting'               => __('A non contract period has been removed', 'resources'),
                     'newholiday'                  => __('A forced holiday has been added', 'resources'),
                     'updateholiday'               => __('A forced holiday has been updated', 'resources'),
                     'deleteholiday'               => __('A forced holiday has been removed', 'resources'),
                     'other'                       => __('Other resource notification', 'resources'),
                     'transfer'                    => __('Transfer resource notification', 'resources')
                     );
   }

   /**
    * Get additionnals targets for Tickets
    */
   function getAdditionalTargets($event='') {
      
      if ($event != 'AlertExpiredTasks'
            && $event != 'AlertLeavingResources'
               && $event != 'AlertLeavingChecklists'
                  && $event != 'AlertLeavingChecklists') {
         $this->addTarget(self::RESOURCE_MANAGER,__('Resource manager', 'resources'));
         $this->addTarget(self::RESOURCE_AUTHOR,__('Requester'));
         $this->addTarget(self::RESOURCE_AUTHOR_LEAVING,__('Informant of leaving', 'resources'));
         $this->addTarget(self::RESOURCE_USER,__('Resource user', 'resources'));
         if ($event == 'newtask'
               || $event == 'updatetask'
                  || $event == 'deletetask') {
            $this->addTarget(self::RESOURCE_TASK_TECHNICIAN,__("Task's responsible technician", "resources"));
            $this->addTarget(self::RESOURCE_TASK_GROUP,__("Task's responsible group", "resources"));
         }
      }
      
      if ($event == 'transfer') {
         // Value used for sort
         $this->notification_targets = array();
         // Displayed value
         $this->notification_targets_labels= array();
         $this->addTarget(self::RESOURCE_TARGET_ENTITY_GROUP, __('Target entity group', 'resources'));
         $this->addTarget(self::RESOURCE_SOURCE_ENTITY_GROUP, __('Source entity group', 'resources'));
         $this->addTarget(self::RESOURCE_SOURCE_ENTITY_GROUP_MANAGER , __('Source entity group manager', 'resources'));
         $this->addTarget(self::RESOURCE_TARGET_ENTITY_GROUP_MANAGER , __('Target entity group manager', 'resources'));
      }
   }

   function getSpecificTargets($data,$options) {

      //Look for all targets whose type is Notification::ITEM_USER
      switch ($data['items_id']) {

         case self::RESOURCE_MANAGER :
            $this->getManagerAddress();
            break;
         case self::RESOURCE_AUTHOR :
            $this->getAuthorAddress();
            break;
         case self::RESOURCE_AUTHOR_LEAVING :
            $this->getAuthorLeavingAddress();
            break;
         case self::RESOURCE_TASK_TECHNICIAN :
            $this->getTaskTechAddress($options);
            break;
         case self::RESOURCE_TASK_GROUP :
            $this->getTaskGroupAddress($options);
            break;
         case self::RESOURCE_USER :
            $this->getRessourceAddress($options);
            break;
         case self::RESOURCE_TARGET_ENTITY_GROUP :
            $this->getEntityGroup($options, 'target');
            break;
         case self::RESOURCE_SOURCE_ENTITY_GROUP :
            $this->getEntityGroup($options, 'source');
            break;
         case self::RESOURCE_SOURCE_ENTITY_GROUP_MANAGER :
            $this->getEntityGroup($options, 'source', true);
            break;
         case self::RESOURCE_TARGET_ENTITY_GROUP_MANAGER  :
            $this->getEntityGroup($options, 'target', true);
            break;
      }
   }
   
   function getEntityGroup($options, $type='source', $supervisor=false) {
      global $DB;
      
      switch($type){
         case 'target':
            $entity = $options['target_entity'];
            break;
         case 'source':
            $entity = $options['source_entity'];
            break;
      }
      
      $query = $this->getDistinctUserSql().
                    " FROM `glpi_users`
                      LEFT JOIN `glpi_groups_users` ON (`glpi_groups_users`.`users_id` = `glpi_users`.`id`) 
                      LEFT JOIN `glpi_plugin_resources_transferentities` ON (`glpi_plugin_resources_transferentities`.`groups_id` = `glpi_groups_users`.`groups_id`)
                      WHERE `glpi_plugin_resources_transferentities`.`entities_id` = '".$entity."'";
      if ($supervisor) {
         $query .= " AND `glpi_groups_users`.`is_manager` = 1";
      }

      foreach ($DB->request($query) as $data) {
         $this->addToAddressesList($data);
      }
   }
   
   //Get recipient
   function getManagerAddress() {
      return $this->getUserByField ("users_id");
   }
   
   function getAuthorAddress() {
      return $this->getUserByField ("users_id_recipient");
   }
   
   function getAuthorLeavingAddress() {
      return $this->getUserByField ("users_id_recipient_leaving");
   }
   
   function getRessourceAddress($options = array()) {
      global $DB;

      if (isset($options['reports_id'])) {
         $query = "SELECT DISTINCT `glpi_users`.`id` AS id,
                          `glpi_users`.`language` AS language
                   FROM `glpi_plugin_resources_resources_items`
                   LEFT JOIN `glpi_users` 
                     ON (`glpi_users`.`id` = `glpi_plugin_resources_resources_items`.`items_id` 
                           AND `glpi_plugin_resources_resources_items`.`itemtype` = 'USER')
                   LEFT JOIN `glpi_plugin_resources_reportconfigs` 
                     ON (`glpi_plugin_resources_resources_items`.`plugin_resources_resources_id` = `glpi_plugin_resources_reportconfigs`.`plugin_resources_resources_id`)
                   WHERE `glpi_plugin_resources_reportconfigs`.`id` = '".$options['reports_id']."'";

         foreach ($DB->request($query) as $data) {
            $data['email'] = UserEmail::getDefaultForUser($data['id']);
            $this->addToAddressesList($data);
         }
      }
   }
   
   function getTaskTechAddress($options=array()) {
      global $DB;

      if (isset($options['tasks_id'])) {
         $query = "SELECT DISTINCT `glpi_users`.`id` AS id,
                          `glpi_users`.`language` AS language
                   FROM `glpi_plugin_resources_tasks`
                   LEFT JOIN `glpi_users` ON (`glpi_users`.`id` = `glpi_plugin_resources_tasks`.`users_id`)
                   WHERE `glpi_plugin_resources_tasks`.`id` = '".$options['tasks_id']."'";

         foreach ($DB->request($query) as $data) {
            $data['email'] = UserEmail::getDefaultForUser($data['id']);
            $this->addToAddressesList($data);
         }
      }
   }
   
   function getTaskGroupAddress ($options=array()) {
      global $DB;

      if (isset($options['groups_id'])
                && $options['groups_id']>0
                && isset($options['tasks_id'])) {

         $query = $this->getDistinctUserSql().
                   " FROM `glpi_users`
                    LEFT JOIN `glpi_groups_users` ON (`glpi_groups_users`.`users_id` = `glpi_users`.`id`) 
                    LEFT JOIN `glpi_plugin_resources_tasks` ON (`glpi_plugin_resources_tasks`.`groups_id` = `glpi_groups_users`.`groups_id`)
                    WHERE `glpi_plugin_resources_tasks`.`id` = '".$options['tasks_id']."'";
         
         foreach ($DB->request($query) as $data) {
            $this->addToAddressesList($data);
         }
      }
   }

   function getDatasForTemplate($event,$options=array()) {
      global $CFG_GLPI, $DB;
      
      if ($event == 'AlertExpiredTasks') {
         
         $this->datas['##resource.entity##'] =
                           Dropdown::getDropdownName('glpi_entities',
                                                     $options['entities_id']);
         $this->datas['##lang.resource.entity##'] =__('Entity');
         $this->datas['##resource.action##'] = __('List of not finished tasks', 'resources');

         $this->datas['##lang.task.name##'] = __('Name');
         $this->datas['##lang.task.type##'] = __('Type');
         $this->datas['##lang.task.users##'] = __('Technician');
         $this->datas['##lang.task.groups##'] = __('Group');
         $this->datas['##lang.task.datebegin##'] = __('Begin date');
         $this->datas['##lang.task.dateend##'] = __('End date');
         $this->datas['##lang.task.planned##'] = __('Used for planning', 'resources');
         $this->datas['##lang.task.realtime##'] = __('Effective duration', 'resources');
         $this->datas['##lang.task.finished##'] = __('Carried out task', 'resources');
         $this->datas['##lang.task.comment##'] = __('Comments');
         $this->datas['##lang.task.resource##'] = PluginResourcesResource::getTypeName(1);
         
         foreach($options['tasks'] as $id => $task) {
            $tmp = array();

            $tmp['##task.name##'] = $task['name'];
            $tmp['##task.type##'] = Dropdown::getDropdownName('glpi_plugin_resources_tasktypes',
                                                       $task['plugin_resources_tasktypes_id']);
            $tmp['##task.users##'] = Html::clean(getUserName($task['users_id']));
            $tmp['##task.groups##'] = Dropdown::getDropdownName('glpi_groups',
                                                       $task['groups_id']);
            $restrict = " `plugin_resources_tasks_id` = '".$task['id']."' ";
            $plans = getAllDatasFromTable("glpi_plugin_resources_taskplannings",$restrict);
            
            if (!empty($plans)) {
               foreach ($plans as $plan) {
                  $tmp['##task.datebegin##'] = Html::convDateTime($plan["begin"]);
                  $tmp['##task.dateend##'] = Html::convDateTime($plan["end"]);
               }
            } else {
               $tmp['##task.datebegin##'] = '';
               $tmp['##task.dateend##'] = '';
            }
            
            $tmp['##task.planned##'] = '';
            $tmp['##task.finished##'] = Dropdown::getYesNo($task['is_finished']);
            $tmp['##task.realtime##'] = Ticket::getActionTime($task["actiontime"]);
            $comment = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br/>", $task['comment']));
            $tmp['##task.comment##'] = Html::clean($comment);
            $tmp['##task.resource##'] = Dropdown::getDropdownName('glpi_plugin_resources_resources',
                                                       $task['plugin_resources_resources_id']);
                                                       
            $this->datas['tasks'][] = $tmp;
         }
      } else if ($event == 'AlertLeavingResources') {

         $this->datas['##resource.entity##']      =
            Dropdown::getDropdownName('glpi_entities',
                                      $options['entities_id']);
         $this->datas['##lang.resource.entity##'] = __('Entity');
         $this->datas['##resource.action##']      = __('These resources have normally left the company', 'resources');

         $this->datas['##lang.resource.id##']              = "ID";
         $this->datas['##lang.resource.name##']            = __('Surname');
         $this->datas['##lang.resource.firstname##']       = __('First name');
         $this->datas['##lang.resource.type##']            = PluginResourcesContractType::getTypeName(1);
         $this->datas['##lang.resource.users##']           = __('Resource manager', 'resources');
         $this->datas['##lang.resource.usersrecipient##']  = __('Requester');
         $this->datas['##lang.resource.datedeclaration##'] = __('Request date');
         $this->datas['##lang.resource.datebegin##']       = __('Arrival date', 'resources');
         $this->datas['##lang.resource.dateend##']         = __('Departure date', 'resources');
         $this->datas['##lang.resource.department##']      = PluginResourcesDepartment::getTypeName(1);
         $this->datas['##lang.resource.accessprofile##']   = PluginResourcesAccessProfile::getTypeName(1);
         $this->datas['##lang.resource.status##']          = PluginResourcesResourceState::getTypeName(1);
         $this->datas['##lang.resource.location##']        = __('Location');
         $this->datas['##lang.resource.comment##']         = __('Description');
         $this->datas['##lang.resource.usersleaving##']    = __('Informant of leaving', 'resources');
         $this->datas['##lang.resource.leaving##']         = __('Declared as leaving', 'resources');
         $this->datas['##lang.resource.leavingreason##']   = PluginResourcesLeavingReason::getTypeName(1);
         $this->datas['##lang.resource.helpdesk##']        = __('Associable to a ticket');
         $this->datas['##lang.resource.url##']             = __('URL');
         
         foreach($options['resources'] as $id => $resource) {
            $tmp = array();

            $tmp['##resource.name##']            = $resource['name'];
            $tmp['##resource.firstname##']       = $resource['firstname'];
            $tmp['##resource.type##']            = Dropdown::getDropdownName('glpi_plugin_resources_contracttypes',
                                                                             $resource['plugin_resources_contracttypes_id']);
            $tmp['##resource.users##']           = Html::clean(getUserName($resource['users_id']));
            $tmp['##resource.usersrecipient##']  = Html::clean(getUserName($resource['users_id_recipient']));
            $tmp['##resource.datedeclaration##'] = Html::convDateTime($resource['date_declaration']);
            $tmp['##resource.datebegin##']       = Html::convDateTime($resource['date_begin']);
            $tmp['##resource.dateend##']         = Html::convDateTime($resource['date_end']);
            $tmp['##resource.department##']      = Dropdown::getDropdownName('glpi_plugin_resources_departments',
                                                                             $resource['plugin_resources_departments_id']);
            $tmp['##resource.accessprofile##']   = Dropdown::getDropdownName('glpi_plugin_resources_accessprofiles',
                                                                             $resource['plugin_resources_accessprofiles_id']);
            $tmp['##resource.status##']          = Dropdown::getDropdownName('glpi_plugin_resources_resourcestates',
                                                                             $resource['plugin_resources_resourcestates_id']);
            $tmp['##resource.location##']        = Dropdown::getDropdownName('glpi_locations',
                                                                             $resource['locations_id']);
            $comment                             = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br/>", $resource['comment']));
            $tmp['##resource.comment##']         = Html::clean($comment);
            $tmp['##resource.usersleaving##']    = Html::clean(getUserName($resource['users_id_recipient_leaving']));
            $tmp['##resource.leaving##']         = Dropdown::getYesNo($resource['is_leaving']);
            $tmp['##resource.leavingreason##']   = Dropdown::getDropdownName('glpi_plugin_resources_leavingreasons',
                                                                             $resource['plugin_resources_leavingreasons_id']);
            $tmp['##resource.helpdesk##']        = Dropdown::getYesNo($resource['is_helpdesk_visible']);
            $tmp['##resource.url##']             = urldecode($CFG_GLPI["url_base"] . "/index.php?redirect=PluginResourcesResource_" . $resource['id']);

            $this->datas['resources'][] = $tmp;
         }
      } else if ($event == 'AlertArrivalChecklists' || $event == 'AlertLeavingChecklists') {
         
         $this->datas['##checklist.entity##'] =
                           Dropdown::getDropdownName('glpi_entities',
                                                     $options['entities_id']);
         $this->datas['##lang.checklist.entity##'] =__('Entity');
         
         if ($event == 'AlertArrivalChecklists') {
            $checklist_type = PluginResourcesChecklist::RESOURCES_CHECKLIST_IN;
            $this->datas['##checklist.action##'] = __('Actions to do on these new resources', 'resources');
            $this->datas['##lang.checklist.title##'] = __('New resource - checklist needs to verificated', 'resources');
         } else {
            $checklist_type = PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT;
            $this->datas['##checklist.action##'] = __('Actions to do on these leaving resources', 'resources');
            $this->datas['##lang.checklist.title##'] = __('Leaving resource - checklist needs to verificated', 'resources');
         }
         $this->datas['##lang.checklist.title2##'] = __('Checklist needs to verificated', 'resources');

         $this->datas['##lang.checklist.id##']              = "ID";
         $this->datas['##lang.checklist.name##']            = __('Surname');
         $this->datas['##lang.checklist.firstname##']       = __('First name');
         $this->datas['##lang.checklist.type##']            = PluginResourcesContractType::getTypeName(1);
         $this->datas['##lang.checklist.users##']           = __('Resource manager', 'resources');
         $this->datas['##lang.checklist.usersrecipient##']  = __('Requester');
         $this->datas['##lang.checklist.datedeclaration##'] = __('Request date');
         $this->datas['##lang.checklist.datebegin##']       = __('Arrival date', 'resources');
         $this->datas['##lang.checklist.dateend##']         = __('Departure date', 'resources');
         $this->datas['##lang.checklist.department##']      = PluginResourcesDepartment::getTypeName(1);
         $this->datas['##lang.checklist.accessprofile##']   = PluginResourcesAccessProfile::getTypeName(1);
         $this->datas['##lang.checklist.status##']          = PluginResourcesResourceState::getTypeName(1);
         $this->datas['##lang.checklist.location##']        = __('Location');
         $this->datas['##lang.checklist.comment##']         = __('Description');
         $this->datas['##lang.checklist.usersleaving##']    = __('Informant of leaving', 'resources');
         $this->datas['##lang.checklist.leaving##']         = __('Declared as leaving', 'resources');
//         $this->datas['##lang.checklist.leavingreason##'] = PluginResourcesLeavingReason::getTypeName(1);
         $this->datas['##lang.checklist.helpdesk##'] = __('Associable to a ticket');
         $this->datas['##lang.checklist.url##']      = "URL";
         
         foreach($options['checklists'] as $id => $checklist) {
            $tmp = array();

            $tmp['##checklist.id##']              = $checklist['plugin_resources_resources_id'];
            $tmp['##checklist.name##']            = $checklist['resource_name'];
            $tmp['##checklist.firstname##']       = $checklist['resource_firstname'];
            $tmp['##checklist.type##']            = Dropdown::getDropdownName('glpi_plugin_resources_contracttypes',
                                                                              $checklist['plugin_resources_contracttypes_id']);
            $tmp['##checklist.users##']           = Html::clean(getUserName($checklist['users_id']));
            $tmp['##checklist.usersrecipient##']  = Html::clean(getUserName($checklist['users_id_recipient']));
            $tmp['##checklist.datedeclaration##'] = Html::convDateTime($checklist['date_declaration']);
            $tmp['##checklist.datebegin##']       = Html::convDateTime($checklist['date_begin']);
            $tmp['##checklist.dateend##']         = Html::convDateTime($checklist['date_end']);
            $tmp['##checklist.department##']      = Dropdown::getDropdownName('glpi_plugin_resources_departments',
                                                                              $checklist['plugin_resources_departments_id']);
            $tmp['##checklist.accessprofile##']   = Dropdown::getDropdownName('glpi_plugin_resources_accessprofiles',
                                                                              $checklist['plugin_resources_accessprofiles_id']);
            $tmp['##checklist.status##']          = Dropdown::getDropdownName('glpi_plugin_resources_resourcestates',
                                                                              $checklist['plugin_resources_resourcestates_id']);
            $tmp['##checklist.location##']        = Dropdown::getDropdownName('glpi_locations',
                                                                              $checklist['locations_id']);
            $comment                              = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br/>", $checklist['comment']));
            $tmp['##checklist.comment##']         = Html::clean($comment);
            $tmp['##checklist.usersleaving##']    = Html::clean(getUserName($checklist['users_id_recipient_leaving']));
            $tmp['##checklist.leaving##']         = Dropdown::getYesNo($checklist['is_leaving']);
//            $tmp['##checklist.leavingreason##'] = Dropdown::getDropdownName('glpi_plugin_resources_leavingreasons',
//                                                   $checklist['plugin_resources_leavingreasons_id']);
            $tmp['##checklist.helpdesk##'] = Dropdown::getYesNo($checklist['is_helpdesk_visible']);
            $tmp['##checklist.url##']      = urldecode($CFG_GLPI["url_base"] . "/index.php?redirect=PluginResourcesResource_" .
                                                       $checklist['plugin_resources_resources_id']);

            
            $query = PluginResourcesChecklist::queryListChecklists($checklist['plugin_resources_resources_id'],$checklist_type);
         
            $tmp['##tasklist.name##'] = '';
            foreach ($DB->request($query) as $data) {
         
               $tmp['##tasklist.name##'].=$data["name"];
               if ($_SESSION["glpiis_ids_visible"] == 1) $tmp['##tasklist.name##'].=" (".$data["id"].")";
               $tmp['##tasklist.name##'].="\n";
            }

            $this->datas['checklists'][] = $tmp;
            
         }
      } /*else if ($event == 'ArrivalChecklists' || $event == 'LeavingChecklists') {
         
         $this->datas['##resource.entity##'] =
                           Dropdown::getDropdownName('glpi_entities',
                                                     $options['entities_id']);
         $this->datas['##lang.resource.entity##'] =__('Entity');
         $this->datas['##resource.action##'] = __('New resource - checklist needs to verificated', 'resources');

         $this->datas['##lang.resource.id##'] = "ID";
         $this->datas['##lang.resource.name##'] = __('Surname');
         $this->datas['##lang.resource.firstname##'] = __('First name');
         $this->datas['##lang.resource.url##'] = "URL";      
         
         foreach($options['resources'] as $id => $resource) {
            $tmp = array();

            $tmp['##resource.name##'] = $resource['name'];
            $tmp['##resource.firstname##'] = $resource['firstname'];
            $tmp['##resource.url##'] = urldecode($CFG_GLPI["url_base"]."/index.php?redirect=PluginResourcesResource_".
                                    $resource['id']);
            
            
            $this->datas['resources'][] = $tmp;
         }
      } */else if ($event == 'LeavingResource') {

         $this->datas['##resource.entity##']      =
            Dropdown::getDropdownName('glpi_entities',
                                      $this->obj->getField('entities_id'));
         $this->datas['##lang.resource.entity##'] = __('Entity');
         $this->datas['##lang.resource.title##']  = __('A resource has been declared leaving', 'resources');

         $this->datas['##lang.resource.title2##'] = __('Please check the leaving checklist of the resource', 'resources');

         $this->datas['##lang.resource.id##']   = "ID";
         $this->datas['##resource.id##']        = $this->obj->getField("id");
         $this->datas['##lang.resource.name##'] = __('Surname');
         $this->datas['##resource.name##']      = $this->obj->getField("name");

         $this->datas['##lang.resource.firstname##'] = __('First name');
         $this->datas['##resource.firstname##']      = $this->obj->getField("firstname");

         $this->datas['##lang.resource.type##'] = PluginResourcesContractType::getTypeName(1);
         $this->datas['##resource.type##']      = Dropdown::getDropdownName('glpi_plugin_resources_contracttypes',
                                                                            $this->obj->getField('plugin_resources_contracttypes_id'));

         $this->datas['##lang.resource.situation##'] = PluginResourcesResourceSituation::getTypeName(1);
         $this->datas['##resource.situation##']      = Dropdown::getDropdownName('glpi_plugin_resources_resourcesituations',
                                                                                 $this->obj->getField('plugin_resources_resourcesituations_id'));

         $this->datas['##lang.resource.contractnature##'] = PluginResourcesContractNature::getTypeName(1);
         $this->datas['##resource.contractnature##']      = Dropdown::getDropdownName('glpi_plugin_resources_contractnatures',
                                                                                      $this->obj->getField('plugin_resources_contractnatures_id'));

         $this->datas['##lang.resource.quota##'] = __('Quota', 'resources');
         $this->datas['##resource.quota##']      = $this->obj->getField('quota');

         $this->datas['##lang.resource.department##'] = PluginResourcesDepartment::getTypeName(1);
         $this->datas['##resource.department##']      = Dropdown::getDropdownName('glpi_plugin_resources_departments',
                                                                                  $this->obj->getField('plugin_resources_departments_id'));

         $this->datas['##lang.resource.accessprofile##'] = PluginResourcesAccessProfile::getTypeName(1);
         $this->datas['##resource.accessprofile##']   = Dropdown::getDropdownName('glpi_plugin_resources_accessprofiles',
                                                                                  $this->obj->getField('plugin_resources_accessprofiles_id'));

         $this->datas['##lang.resource.rank##'] = PluginResourcesRank::getTypeName(1);
         $this->datas['##resource.rank##']      = Dropdown::getDropdownName('glpi_plugin_resources_ranks',
                                                                            $this->obj->getField('plugin_resources_ranks_id'));

         $this->datas['##lang.resource.speciality##'] = PluginResourcesResourceSpeciality::getTypeName(1);
         $this->datas['##resource.speciality##']      = Dropdown::getDropdownName('glpi_plugin_resources_resourcespecialities',
                                                                                  $this->obj->getField('plugin_resources_resourcespecialities_id'));

         $this->datas['##lang.resource.status##'] = PluginResourcesResourceState::getTypeName(1);
         $this->datas['##resource.status##']      = Dropdown::getDropdownName('glpi_plugin_resources_resourcestates',
                                                                              $this->obj->getField('plugin_resources_resourcestates_id'));

         $this->datas['##lang.resource.users##'] = __('Resource manager', 'resources');
         $this->datas['##resource.users##']      = Html::clean(getUserName($this->obj->getField("users_id")));

         $this->datas['##lang.resource.usersrecipient##'] = __('Requester');
         $this->datas['##resource.usersrecipient##']      = Html::clean(getUserName($this->obj->getField("users_id_recipient")));

         $this->datas['##lang.resource.datedeclaration##'] = __('Request date');
         $this->datas['##resource.datedeclaration##']      = Html::convDate($this->obj->getField('date_declaration'));

         $this->datas['##lang.resource.datebegin##'] = __('Arrival date', 'resources');
         $this->datas['##resource.datebegin##']      = Html::convDate($this->obj->getField('date_begin'));

         $this->datas['##lang.resource.dateend##'] = __('Departure date', 'resources');
         $this->datas['##resource.dateend##']      = Html::convDate($this->obj->getField('date_end'));

         $this->datas['##lang.resource.location##'] = __('Location');
         $this->datas['##resource.location##']      = Dropdown::getDropdownName('glpi_locations',
                                                                                $this->obj->getField('locations_id'));

         $this->datas['##lang.resource.helpdesk##'] = __('Associable to a ticket');
         $this->datas['##resource.helpdesk##']      = Dropdown::getYesNo($this->obj->getField('is_helpdesk_visible'));

         $this->datas['##lang.resource.leaving##'] = __('Declared as leaving', 'resources');
         $this->datas['##resource.leaving##']      = Dropdown::getYesNo($this->obj->getField('is_leaving'));

         $this->datas['##lang.resource.leavingreason##'] = PluginResourcesLeavingReason::getTypeName(1);
         $this->datas['##resource.leavingreason##']      = Dropdown::getDropdownName('glpi_plugin_resources_leavingreasons',
                                                                                     $this->obj->getField('plugin_resources_leavingreasons_id'));

         $this->datas['##lang.resource.usersleaving##'] = __('Informant of leaving', 'resources');
         $this->datas['##resource.usersleaving##']      = Html::clean(getUserName($this->obj->getField('users_id_recipient_leaving')));

         $this->datas['##lang.resource.comment##'] = __('Description');
         $comment                                  = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br/>", $this->obj->getField("comment")));
         $this->datas['##resource.comment##']      = Html::clean($comment);

         $this->datas['##lang.resource.url##'] = "URL";
         $this->datas['##resource.url##']      = urldecode($CFG_GLPI["url_base"] . "/index.php?redirect=PluginResourcesResource_" .
                                                           $this->obj->getField("id"));
         
         $this->datas['##lang.resource.badge##']=" ";
         if (isset($this->target_object->input['checkbadge'])) {
               if (!empty($this->target_object->input['checkbadge']))
                  $this->datas['##lang.resource.badge##'] = __('Thanks to recover his badges', 'resources');
               else
                  $this->datas['##lang.resource.badge##']=" ";
         }
                  
      } else {
      
         $events = $this->getAllEvents();

         $this->datas['##lang.resource.title##'] = $events[$event];
         $this->datas['##resource.action_user##'] = getUserName(Session::getLoginUserID());
         $this->datas['##lang.resource.entity##'] = __('Entity');
         $this->datas['##resource.entity##'] =
                              Dropdown::getDropdownName('glpi_entities',
                                                        $this->obj->getField('entities_id'));
         $this->datas['##resource.id##'] = $this->obj->getField("id");

         $this->datas['##lang.resource.name##'] = __('Surname');
         $this->datas['##resource.name##'] = $this->obj->getField("name");

         $this->datas['##lang.resource.firstname##'] = __('First name');
         $this->datas['##resource.firstname##'] = $this->obj->getField("firstname");
         
         $this->datas['##lang.resource.type##'] = PluginResourcesContractType::getTypeName(1);
         $this->datas['##resource.type##'] =  Dropdown::getDropdownName('glpi_plugin_resources_contracttypes',
                                                       $this->obj->getField('plugin_resources_contracttypes_id'));

         $this->datas['##lang.resource.situation##'] = PluginResourcesResourceSituation::getTypeName(1);
         $this->datas['##resource.situation##'] =  Dropdown::getDropdownName('glpi_plugin_resources_resourcesituations',
            $this->obj->getField('plugin_resources_resourcesituations_id'));

         $this->datas['##lang.resource.contractnature##'] = PluginResourcesContractNature::getTypeName(1);
         $this->datas['##resource.contractnature##'] =  Dropdown::getDropdownName('glpi_plugin_resources_contractnatures',
            $this->obj->getField('plugin_resources_contractnatures_id'));

         $this->datas['##lang.resource.quota##'] = __('Quota', 'resources');
         $this->datas['##resource.quota##'] =  $this->obj->getField('quota');

         $this->datas['##lang.resource.users##'] = __('Resource manager', 'resources');
         $this->datas['##resource.users##'] =  Html::clean(getUserName($this->obj->getField("users_id")));
         
         $this->datas['##lang.resource.usersrecipient##'] = __('Requester');
         $this->datas['##resource.usersrecipient##'] =  Html::clean(getUserName($this->obj->getField("users_id_recipient")));
         
         $this->datas['##lang.resource.datedeclaration##'] = __('Request date');
         $this->datas['##resource.datedeclaration##'] = Html::convDate($this->obj->getField('date_declaration'));
         
         $this->datas['##lang.resource.datebegin##'] = __('Arrival date', 'resources');
         $this->datas['##resource.datebegin##'] = Html::convDate($this->obj->getField('date_begin'));
         
         $this->datas['##lang.resource.dateend##'] = __('Departure date', 'resources');
         $this->datas['##resource.dateend##'] = Html::convDate($this->obj->getField('date_end'));
         
         $this->datas['##lang.resource.department##'] = PluginResourcesDepartment::getTypeName(1);
         $this->datas['##resource.department##'] =  Dropdown::getDropdownName('glpi_plugin_resources_departments',
                                                       $this->obj->getField('plugin_resources_departments_id'));

         $this->datas['##lang.resource.accessprofile##'] = PluginResourcesAccessProfile::getTypeName(1);
         $this->datas['##resource.accessprofile##'] =  Dropdown::getDropdownName('glpi_plugin_resources_accessprofiles',
                                                                              $this->obj->getField('plugin_resources_accessprofiles_id'));

         $this->datas['##lang.resource.rank##'] = PluginResourcesRank::getTypeName(1);
         $this->datas['##resource.rank##'] =  Dropdown::getDropdownName('glpi_plugin_resources_ranks',
            $this->obj->getField('plugin_resources_ranks_id'));

         $this->datas['##lang.resource.speciality##'] = PluginResourcesResourceSpeciality::getTypeName(1);
         $this->datas['##resource.speciality##'] =  Dropdown::getDropdownName('glpi_plugin_resources_resourcespecialities',
            $this->obj->getField('plugin_resources_resourcespecialities_id'));

         $this->datas['##lang.resource.status##'] = PluginResourcesResourceState::getTypeName(1);
         $this->datas['##resource.status##'] =  Dropdown::getDropdownName('glpi_plugin_resources_resourcestates',
                                                       $this->obj->getField('plugin_resources_resourcestates_id'));
                                                       
         $this->datas['##lang.resource.location##'] = __('Location');
         $this->datas['##resource.location##'] =  Dropdown::getDropdownName('glpi_locations',
                                                       $this->obj->getField('locations_id'));
                                                       
         $this->datas['##lang.resource.comment##'] = __('Description');
         $comment = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br/>", $this->obj->getField("comment")));
         $this->datas['##resource.comment##'] = Html::clean($comment);
         
         $this->datas['##lang.resource.usersleaving##'] = __('Informant of leaving', 'resources');
         $this->datas['##resource.usersleaving##'] =  Html::clean(getUserName($this->obj->getField("users_id_recipient_leaving")));
         
         $this->datas['##lang.resource.leaving##'] = __('Declared as leaving', 'resources');
         $this->datas['##resource.leaving##'] =  Dropdown::getYesNo($this->obj->getField('is_leaving'));

         $this->datas['##lang.resource.leavingreason##'] = PluginResourcesLeavingReason::getTypeName(1);
         $this->datas['##resource.leavingreason##'] =  Dropdown::getDropdownName('glpi_plugin_resources_leavingreasons',
            $this->obj->getField('plugin_resources_leavingreasons_id'));

         $this->datas['##lang.resource.helpdesk##'] = __('Associable to a ticket');
         $this->datas['##resource.helpdesk##'] =  Dropdown::getYesNo($this->obj->getField('is_helpdesk_visible'));
                                                      
         $this->datas['##lang.resource.url##'] = "URL";
         $this->datas['##resource.url##'] = urldecode($CFG_GLPI["url_base"]."/index.php?redirect=PluginResourcesResource_".
                                    $this->obj->getField("id"));
         
         if ($event == 'report') {
            
            $this->datas['##lang.resource.creationtitle##'] = __('Creation report of the human resource', 'resources');
            
            $this->datas['##resource.login##'] =  "";
            $this->datas['##resource.email##'] =  "";
            
            $restrict = "`itemtype` = 'User' 
                        AND `plugin_resources_resources_id` = '".$this->obj->getField("id")."'";
            $items = getAllDatasFromTable("glpi_plugin_resources_resources_items",$restrict);
            if (!empty($items)) {
               foreach ($items as $item) {
                  $user = new User();
                  $user->getFromDB($item["items_id"]);
                  $this->datas['##resource.login##'] =  $user->fields["name"];
                  $this->datas['##resource.email##'] =  $user->getDefaultEmail();
               }
            }
            
            $this->datas['##lang.resource.login##'] = __('Login');
            
            $this->datas['##lang.resource.creation##'] = __('Informations of the created user', 'resources');
            $this->datas['##lang.resource.datecreation##'] = __('Creation date');
            $this->datas['##resource.datecreation##'] = Html::convDate(date("Y-m-d"));
            
            $this->datas['##lang.resource.email##'] = __('Email');
            
            $this->datas['##lang.resource.informationtitle##'] = __('Additional informations', 'resources');
            
            $PluginResourcesReportConfig = new PluginResourcesReportConfig();
            $PluginResourcesReportConfig->getFromDB($options['reports_id']);
      
            $this->datas['##lang.resource.informations##'] = _n('Information', 'Informations', 2);
            $information = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br>", $PluginResourcesReportConfig->fields['information']));
            $this->datas['##resource.informations##'] =  Html::clean(nl2br($information));
            
            $this->datas['##lang.resource.commentaires##'] = __('Comments');
            $commentaire = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br>", $PluginResourcesReportConfig->fields['comment']));
            $this->datas['##resource.commentaires##'] =  Html::clean(nl2br($commentaire));
         }
         
         if ($event == 'transfer') {
            $this->datas['##lang.resource.transfertitle##'] = __('Transfer report of the human resource', 'resources');

            $this->datas['##resource.login##'] = "";
            $this->datas['##resource.email##'] = "";

            $restrict = "`itemtype` = 'User' 
                        AND `plugin_resources_resources_id` = '".$this->obj->getField("id")."'";
            $items    = getAllDatasFromTable("glpi_plugin_resources_resources_items", $restrict);
            if (!empty($items)) {
               foreach ($items as $item) {
                  $user                              = new User();
                  $user->getFromDB($item["items_id"]);
                  $this->datas['##resource.login##'] = $user->fields["name"];
                  $this->datas['##resource.email##'] = $user->getDefaultEmail();
               }
            }

            $this->datas['##lang.resource.login##'] = __('Login');

            $this->datas['##lang.resource.transfer##']     = __('Informations of the created user', 'resources');
            $this->datas['##lang.resource.datetransfer##'] = __('Transfer Date');
            $this->datas['##resource.datetransfer##']      = Html::convDate(date("Y-m-d"));

            $this->datas['##lang.resource.email##'] = __('Email');

            $this->datas['##lang.resource.informationtitle##'] = __('Additional informations', 'resources');

            $PluginResourcesReportConfig = new PluginResourcesReportConfig();
            $PluginResourcesReportConfig->getFromDB($options['reports_id']);

            $this->datas['##lang.resource.informations##'] = __('Information', 'Informations', 2);
            $information                                   = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br>", $PluginResourcesReportConfig->fields['information']));
            $this->datas['##resource.informations##']      = Html::clean(nl2br($information));

            $this->datas['##lang.resource.commentaires##'] = __('Comments');
            $commentaire                                   = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br>", $PluginResourcesReportConfig->fields['comment']));
            $this->datas['##resource.commentaires##']      = Html::clean(nl2br($commentaire));
            
            $this->datas['##lang.resource.targetentity##'] = __('Target entity', 'resources');
            $this->datas['##lang.resource.sourceentity##'] = __('Source entity', 'resources');
            
            $entity  = new Entity();
            if ($entity->getFromDB($options['target_entity'])) {
               $this->datas['##resource.targetentity##'] = $entity->fields['name'];
            }
            if ($entity->getFromDB($options['source_entity'])) {
               $this->datas['##resource.sourceentity##'] = $entity->fields['name'];
            }
         }
         
         if ($event == 'newresting' 
               || $event == 'updateresting' 
                  || $event == 'deleteresting') {
            
            $this->datas['##lang.resource.restingtitle##'] = _n('Non contract period management', 'Non contract periods management', 1, 'resources');
            
            $this->datas['##lang.resource.resting##'] = __('Detail of non contract period', 'resources');
            $this->datas['##lang.resource.datecreation##'] = __('Creation date');
            $this->datas['##resource.datecreation##'] = Html::convDate(date("Y-m-d"));
            
            $PluginResourcesResourceResting = new PluginResourcesResourceResting();
            $PluginResourcesResourceResting->getFromDB($options['resting_id']);
            
            $this->datas['##lang.resource.location##'] = __('Agency concerned', 'resources');
            $this->datas['##resource.location##'] =  Dropdown::getDropdownName('glpi_locations',
                                                       $PluginResourcesResourceResting->fields['locations_id']);
                                                       
            $this->datas['##lang.resource.home##'] = __('At home', 'resources');
            $this->datas['##resource.home##'] =  Dropdown::getYesNo($PluginResourcesResourceResting->fields['at_home']);
         
            $this->datas['##lang.resource.datebegin##'] = __('Begin date');
            $this->datas['##resource.datebegin##'] = Html::convDate($PluginResourcesResourceResting->fields['date_begin']);
         
            $this->datas['##lang.resource.dateend##'] = __('End date');
            $this->datas['##resource.dateend##'] = Html::convDate($PluginResourcesResourceResting->fields['date_end']);
            
            $this->datas['##lang.resource.informationtitle##'] = __('Additional informations', 'resources');
            
            $this->datas['##lang.resource.commentaires##'] = __('Comments');
            $commentaire = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br>", $PluginResourcesResourceResting->fields['comment']));
            $this->datas['##resource.commentaires##'] =  Html::clean(nl2br($commentaire));
            
            $this->datas['##lang.resource.openby##'] = __('Reported by', 'resources');
            $this->datas['##resource.openby##'] = Html::clean(getUserName(Session::getLoginUserID()));
            
            if (isset($options['oldvalues']) && !empty($options['oldvalues']))
               $this->target_object->oldvalues = $options['oldvalues'];
         }
         
         if ($event == 'newholiday' 
               || $event == 'updateholiday' 
                  || $event == 'deleteholiday') {
            
            $this->datas['##lang.resource.holidaytitle##'] = __('Forced holiday management', 'resources');
            
            $this->datas['##lang.resource.holiday##'] = __('Detail of the forced holiday', 'resources');
            $this->datas['##lang.resource.datecreation##'] = __('Creation date');
            $this->datas['##resource.datecreation##'] = Html::convDate(date("Y-m-d"));
            
            $PluginResourcesResourceHoliday = new PluginResourcesResourceHoliday();
            $PluginResourcesResourceHoliday->getFromDB($options['holiday_id']);
         
            $this->datas['##lang.resource.datebegin##'] = __('Begin date');
            $this->datas['##resource.datebegin##'] = Html::convDate($PluginResourcesResourceHoliday->fields['date_begin']);
         
            $this->datas['##lang.resource.dateend##'] = __('End date');
            $this->datas['##resource.dateend##'] = Html::convDate($PluginResourcesResourceHoliday->fields['date_end']);
            
            $this->datas['##lang.resource.informationtitle##'] = __('Additional informations', 'resources');
            
            $this->datas['##lang.resource.commentaires##'] = __('Comments');
            $commentaire = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br>", $PluginResourcesResourceHoliday->fields['comment']));
            $this->datas['##resource.commentaires##'] =  Html::clean(nl2br($commentaire));
            
            $this->datas['##lang.resource.openby##'] = __('Reported by', 'resources');
            $this->datas['##resource.openby##'] = Html::clean(getUserName(Session::getLoginUserID()));
            
            if (isset($options['oldvalues']) && !empty($options['oldvalues']))
               $this->target_object->oldvalues = $options['oldvalues'];
         }
          
         //old values infos
         if (isset($this->target_object->oldvalues) 
               && !empty($this->target_object->oldvalues) 
                  && ($event=='update' 
                     || $event=='updateresting' 
                        || $event=='updateholiday')) {
            
            $this->datas['##lang.update.title##'] = __('Modified fields', 'resources');
            
            $tmp = array();
               
            if (isset($this->target_object->oldvalues['name'])) {
               if (empty($this->target_object->oldvalues['name']))
                  $tmp['##update.name##'] = "---";
               else  
                  $tmp['##update.name##'] = $this->target_object->oldvalues['name'];
            }
            if (isset($this->target_object->oldvalues['firstname'])) {
               if (empty($this->target_object->oldvalues['firstname']))
                  $tmp['##update.firstname##'] = "---";
               else
                  $tmp['##update.firstname##'] = $this->target_object->oldvalues['firstname'];
            }
            
            if (isset($this->target_object->oldvalues['plugin_resources_contracttypes_id'])) {
               if (empty($this->target_object->oldvalues['plugin_resources_contracttypes_id']))
                  $tmp['##update.type##'] = "---";
               else
                  $tmp['##update.type##'] = Dropdown::getDropdownName('glpi_plugin_resources_contracttypes',
                                                       $this->target_object->oldvalues['plugin_resources_contracttypes_id']);
            }
            
            if (isset($this->target_object->oldvalues['users_id'])) {
               if (empty($this->target_object->oldvalues['users_id']))
                  $tmp['##update.users##'] = "---";
               else
                  $tmp['##update.users##'] = Html::clean(getUserName($this->target_object->oldvalues['users_id']));
            }
            
            if (isset($this->target_object->oldvalues['users_id_recipient'])) {
               if (empty($this->target_object->oldvalues['users_id_recipient']))
                  $tmp['##update.usersrecipient##'] = "---";
               else
                  $tmp['##update.usersrecipient##'] = Html::clean(getUserName($this->target_object->oldvalues['users_id_recipient']));
            }
            
            if (isset($this->target_object->oldvalues['date_declaration'])) {
               if (empty($this->target_object->oldvalues['date_declaration']))
                  $tmp['##update.datedeclaration##'] = "---";
               else
                  $tmp['##update.datedeclaration##'] = Html::convDate($this->target_object->oldvalues['date_declaration']);
            }
            
            if (isset($this->target_object->oldvalues['date_begin'])) {
               if (empty($this->target_object->oldvalues['date_begin']))
                  $tmp['##update.datebegin##'] = "---";
               else
                  $tmp['##update.datebegin##'] = Html::convDate($this->target_object->oldvalues['date_begin']);
            }
            
            if (isset($this->target_object->oldvalues['date_end'])) {
               if (empty($this->target_object->oldvalues['date_end']))
                  $tmp['##update.dateend##'] = "---";
               else
                  $tmp['##update.dateend##'] = Html::convDate($this->target_object->oldvalues['date_end']);
            }

            if (isset($this->target_object->oldvalues['quota'])) {
               if (empty($this->target_object->oldvalues['quota']))
                  $tmp['##update.quota##'] = "---";
               else
                  $tmp['##update.quota##'] = $this->target_object->oldvalues['quota'];
            }

            if (isset($this->target_object->oldvalues['plugin_resources_departments_id'])) {
               if (empty($this->target_object->oldvalues['plugin_resources_departments_id']))
                  $tmp['##update.department##'] = "---";
               else
                  $tmp['##update.department##'] = Dropdown::getDropdownName('glpi_plugin_resources_departments',
                                                       $this->target_object->oldvalues['plugin_resources_departments_id']);
            }

            if (isset($this->target_object->oldvalues['plugin_resources_accessprofiles_id'])) {
               if (empty($this->target_object->oldvalues['plugin_resources_accessprofiles_id']))
                  $tmp['##update.accessprofile##'] = "---";
               else
                  $tmp['##update.accessprofile##'] = Dropdown::getDropdownName('glpi_plugin_resources_accessprofiles',
                                                                            $this->target_object->oldvalues['plugin_resources_accessprofiles_id']);
            }
            
            if (isset($this->target_object->oldvalues['plugin_resources_resourcestates_id'])) {
               if (empty($this->target_object->oldvalues['plugin_resources_resourcestates_id']))
                  $tmp['##update.status##'] = "---";
               else
                  $tmp['##update.status##'] = Dropdown::getDropdownName('glpi_plugin_resources_resourcestates',
                                                       $this->target_object->oldvalues['plugin_resources_resourcestates_id']);
            }

            if (isset($this->target_object->oldvalues['plugin_resources_resourcesituations_id'])) {
               if (empty($this->target_object->oldvalues['plugin_resources_resourcesituations_id']))
                  $tmp['##update.situation##'] = "---";
               else
                  $tmp['##update.situation##'] = Dropdown::getDropdownName('glpi_plugin_resources_resourcesituations',
                     $this->target_object->oldvalues['plugin_resources_resourcesituations_id']);
            }

            if (isset($this->target_object->oldvalues['plugin_resources_contractnatures_id'])) {
               if (empty($this->target_object->oldvalues['plugin_resources_contractnatures_id']))
                  $tmp['##update.contractnature##'] = "---";
               else
                  $tmp['##update.contractnature##'] = Dropdown::getDropdownName('glpi_plugin_resources_contractnatures',
                     $this->target_object->oldvalues['plugin_resources_contractnatures_id']);
            }

            if (isset($this->target_object->oldvalues['plugin_resources_ranks_id'])) {
               if (empty($this->target_object->oldvalues['plugin_resources_ranks_id']))
                  $tmp['##update.rank##'] = "---";
               else
                  $tmp['##update.rank##'] = Dropdown::getDropdownName('glpi_plugin_resources_ranks',
                     $this->target_object->oldvalues['plugin_resources_ranks_id']);
            }

            if (isset($this->target_object->oldvalues['plugin_resources_resourcespecialities_id'])) {
               if (empty($this->target_object->oldvalues['plugin_resources_resourcespecialities_id']))
                  $tmp['##update.speciality##'] = "---";
               else
                  $tmp['##update.speciality##'] = Dropdown::getDropdownName('glpi_plugin_resources_resourcespecialities',
                     $this->target_object->oldvalues['plugin_resources_resourcespecialities_id']);
            }

            if (isset($this->target_object->oldvalues['locations_id'])) {
               if (empty($this->target_object->oldvalues['locations_id']))
                  $tmp['##update.location##'] = "---";
               else
                  $tmp['##update.location##'] = Dropdown::getDropdownName('glpi_locations',
                                                       $this->target_object->oldvalues['locations_id']);
            }
            
            if (isset($this->target_object->oldvalues['comment'])) {
               if (empty($this->target_object->oldvalues['comment'])) {
                  $tmp['##update.comment##'] = "---";
               } else {
                  $comment = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br/>", $this->target_object->oldvalues['comment']));
                  $tmp['##update.comment##'] = Html::clean($comment);
               }
            }
            
            if (isset($this->target_object->oldvalues['users_id_recipient_leaving'])) {
               if (empty($this->target_object->oldvalues['users_id_recipient_leaving']))
                  $tmp['##update.usersleaving##'] = "---";
               else
                  $tmp['##update.usersleaving##'] = Html::clean(getUserName($this->target_object->oldvalues['users_id_recipient_leaving']));
            }
            
            if (isset($this->target_object->oldvalues['is_leaving'])) {
               if (empty($this->target_object->oldvalues['is_leaving']))
                  $tmp['##update.leaving##'] = "---";
               else
                  $tmp['##update.leaving##'] = Dropdown::getYesNo($this->target_object->oldvalues['is_leaving']);
            }

            if (isset($this->target_object->oldvalues['plugin_resources_leavingreasons_id'])) {
               if (empty($this->target_object->oldvalues['plugin_resources_leavingreasons_id']))
                  $tmp['##update.leavingreason##'] = "---";
               else
                  $tmp['##update.leavingreason##'] = Dropdown::getDropdownName('glpi_plugin_resources_leavingreasons',
                     $this->target_object->oldvalues['plugin_resources_leavingreasons_id']);
            }

            if (isset($this->target_object->oldvalues['is_helpdesk_visible'])) {
               if (empty($this->target_object->oldvalues['is_helpdesk_visible']))
                  $tmp['##update.helpdesk##'] = "---";
               else
                  $tmp['##update.helpdesk##'] = Dropdown::getYesNo($this->target_object->oldvalues['is_helpdesk_visible']);
            }
            
            if (isset($this->target_object->oldvalues['at_home'])) {
               if (empty($this->target_object->oldvalues['at_home']))
                  $tmp['##update.home##'] = "---";
               else  
                  $tmp['##update.home##'] = Dropdown::getYesNo($this->target_object->oldvalues['at_home']);
            }

            $this->datas['updates'][] = $tmp;
         }

         //task infos
         $restrict = "`plugin_resources_resources_id`='".$this->obj->getField('id')."' AND `is_deleted` = 0";
         
         if (isset($options['tasks_id']) && $options['tasks_id']) {
            $restrict .= " AND `glpi_plugin_resources_tasks`.`id` = '".$options['tasks_id']."'";
         }
         $restrict .= " ORDER BY `name` DESC";
         $tasks = getAllDatasFromTable('glpi_plugin_resources_tasks',$restrict);
         
         $this->datas['##lang.task.title##'] = __('Associated tasks', 'resources');
         
         $this->datas['##lang.task.name##'] = __('Name');
         $this->datas['##lang.task.type##'] = __('Type');
         $this->datas['##lang.task.users##'] = __('Technician');
         $this->datas['##lang.task.groups##'] = __('Group');
         $this->datas['##lang.task.datebegin##'] = __('Begin date');
         $this->datas['##lang.task.dateend##'] = __('End date');
         $this->datas['##lang.task.planned##'] = __('Used for planning', 'resources');
         $this->datas['##lang.task.realtime##'] = __('Effective duration', 'resources');
         $this->datas['##lang.task.finished##'] = __('Carried out task', 'resources');
         $this->datas['##lang.task.comment##'] = __('Description');
         
         foreach ($tasks as $task) {
            $tmp = array();
            
            $tmp['##task.name##'] = $task['name'];
            $tmp['##task.type##'] = Dropdown::getDropdownName('glpi_plugin_resources_tasktypes',
                                                       $task['plugin_resources_tasktypes_id']);
            $tmp['##task.users##'] = Html::clean(getUserName($task['users_id']));
            $tmp['##task.groups##'] = Dropdown::getDropdownName('glpi_groups',
                                                       $task['groups_id']);
            $restrict = " `plugin_resources_tasks_id` = '".$task['id']."' ";
            $plans = getAllDatasFromTable("glpi_plugin_resources_taskplannings",$restrict);
            
            if (!empty($plans)) {
               foreach ($plans as $plan) {
                  $tmp['##task.datebegin##'] = Html::convDateTime($plan["begin"]);
                  $tmp['##task.dateend##'] = Html::convDateTime($plan["end"]);
               }
            } else {
               $tmp['##task.datebegin##'] = '';
               $tmp['##task.dateend##'] = '';
            }
            $tmp['##task.planned##'] = '';
            $tmp['##task.finished##'] = Dropdown::getYesNo($task['is_finished']);
            $tmp['##task.realtime##'] = Ticket::getActionTime($task["actiontime"]);
            $comment = stripslashes(str_replace(array('\r\n', '\n', '\r'), "<br/>", $task['comment']));
            $tmp['##task.comment##'] = Html::clean($comment);
 

            $this->datas['tasks'][] = $tmp;
         }
      }
   }
   
   function getTags() {

      $tags = array('resource.id'               => 'ID',
                   'resource.name'              => __('Surname'),
                   'resource.entity'            => __('Entity'),
                   'resource.action'            => __('List of not finished tasks', 'resources'),
                   'resource.firstname'         => __('First name'),
                   'resource.type'              => PluginResourcesContractType::getTypeName(1),
                   'resource.quota'             => __('Quota', 'resources'),
                   'resource.situation'         => PluginResourcesResourceSituation::getTypeName(1),
                   'resource.contractnature'    => PluginResourcesContractNature::getTypeName(1),
                   'resource.rank'              => PluginResourcesRank::getTypeName(1),
                   'resource.speciality'        => PluginResourcesResourceSpeciality::getTypeName(1),
                   'resource.users'             => __('Resource manager', 'resources'),
                   'resource.usersrecipient'    => __('Requester'),
                   'resource.datedeclaration'   => __('Request date'),
                   'resource.datebegin'         => __('Arrival date', 'resources'),
                   'resource.dateend'           => __('Departure date', 'resources'),
                   'resource.department'        => PluginResourcesDepartment::getTypeName(1),
                   'resource.status'            => PluginResourcesResourceState::getTypeName(1),
                   'resource.location'          => __('Location'),
                   'resource.restingtitle'      => __('Non contract period management', 'resources'),
                   'resource.resting'           => __('Detail of non contract period', 'resources'),
                   'resource.comment'           => __('Description'),
                   'resource.usersleaving'      => __('Informant of leaving', 'resources'),
                   'resource.leaving'           => __('Declared as leaving', 'resources'),
                   'resource.leavingreason'     => PluginResourcesLeavingReason::getTypeName(1),
                   'resource.helpdesk'          => __('Associable to a ticket'),
                   'resource.action_user'       => __('Last updater'),
                   'resource.holidaytitle'      => __('Forced holiday management', 'resources'),
                   'resource.holiday'           => __('Detail of the forced holiday', 'resources'),
                   'update.name'                => __('Surname'),
                   'update.firstname'           => __('First name'),
                   'update.type'                => PluginResourcesContractType::getTypeName(1),
                   'update.quota'               => __('Quota', 'resources'),
                   'update.situation'           => PluginResourcesResourceSituation::getTypeName(1),
                   'update.contractnature'      => PluginResourcesContractNature::getTypeName(1),
                   'update.rank'                => PluginResourcesRank::getTypeName(1),
                   'update.speciality'          => PluginResourcesResourceSpeciality::getTypeName(1),
                   'update.users'               => __('Resource manager', 'resources'),
                   'update.usersrecipient'      => __('Requester'),
                   'update.datedeclaration'     => __('Request date'),
                   'update.datebegin'           => __('Arrival date', 'resources'),
                   'update.dateend'             => __('Departure date', 'resources'),
                   'update.department'          => PluginResourcesDepartment::getTypeName(1),
                   'update.status'              => PluginResourcesResourceState::getTypeName(1),
                   'update.location'            => __('Location'),
                   'update.comment'             => __('Description'),
                   'update.usersleaving'        => __('Informant of leaving', 'resources'),
                   'update.leaving'             => __('Declared as leaving', 'resources'),
                   'update.leavingreason'       => PluginResourcesLeavingReason::getTypeName(1),
                   'update.helpdesk'            => __('Associable to a ticket'),
                   'task.name'                  => __('Name'),
                   'task.type'                  => __('Type'),
                   'task.users'                 => __('Technician'),
                   'task.groups'                => __('Group'),
                   'task.datebegin'             => __('Begin date'),
                   'task.dateend'               => __('End date'),
                   'task.planned'               => __('Used for planning', 'resources'),
                   'task.realtime'              => __('Effective duration', 'resources'),
                   'task.finished'              => __('Carried out task', 'resources'),
                   'task.comment'               => __('Description'),
                   'task.resource'              => PluginResourcesResource::getTypeName(1),
                   'resouce.sourceentity'       => __('Source entity', 'resources'),
                   'resouce.targetentity'       => __('Target entity', 'resources'));
      foreach ($tags as $tag => $label) {
         $this->addTagToList(array('tag'=>$tag,'label'=>$label,
                                   'value'=>true));
      }
      
      $this->addTagToList(array('tag'=>'resource',
                                'label'=>__('At creation, update, removal of a resource', 'resources'),
                                'value'=>false,
                                'foreach'=>true,
                                'events'=>array('new','update','delete','report','newresting','updateresting','deleteresting','newholiday','updateholiday','deleteholiday')));
      $this->addTagToList(array('tag'=>'updates',
                                'label'=>__('Modified fields', 'resources'),
                                'value'=>false,
                                'foreach'=>true,
                                'events'=>array('update','updateresting','updateholiday')));
      $this->addTagToList(array('tag'=>'tasks',
                                'label'=>__('At creation, update, removal of a task', 'resources'),
                                'value'=>false,
                                'foreach'=>true,
                                'events'=>array('newtask','updatetask','deletetask')));

      asort($this->tag_descriptions);
   }
}

?>