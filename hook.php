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

function plugin_resources_install() {
   global $DB;

   foreach (glob(GLPI_ROOT.'/plugins/resources/inc/*.php') as $file) {
      if (!preg_match('/resourceinjection/', $file)
          && !preg_match('/clientinjection/', $file)
          && !preg_match('/resourcepdf/', $file)
          && !preg_match('/datecriteria/', $file)) {
         include_once ($file);
      }
   }

   $update    = false;
   $update78  = false;
   $update80  = false;
   $update171 = false;

   $install = false;
   if (!$DB->tableExists("glpi_plugin_resources_resources") && !$DB->tableExists("glpi_plugin_resources_employments")) {
      $install = true;
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/empty-2.4.4.sql");

      $query = "INSERT INTO `glpi_plugin_resources_contracttypes` ( `id`, `name`, `entities_id`, `is_recursive`)
         VALUES (1, '".__('Long term contract', 'resources')."', 0, 1)";

      $DB->query($query) or die($DB->error());

      $query = "INSERT INTO `glpi_plugin_resources_contracttypes` ( `id`, `name`, `entities_id`, `is_recursive`)
               VALUES (2, '".__('Fixed term contract', 'resources')."', 0, 1)";

      $DB->query($query) or die($DB->error());

      $query = "INSERT INTO `glpi_plugin_resources_contracttypes` ( `id`, `name`, `entities_id`, `is_recursive`)
               VALUES (3, '".__('Trainee', 'resources')."', 0, 1)";

      $DB->query($query) or die($DB->error());

      // Add record notification
      include_once(GLPI_ROOT . "/plugins/resources/inc/notificationtargetresource.class.php");
      call_user_func(["PluginResourcesNotificationTargetResource", 'install']);

   } else if ($DB->tableExists("glpi_plugin_resources") && !$DB->tableExists("glpi_plugin_resources_employee")) {
      $update = true;
      $update78 = true;
      $update80 = true;
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.4.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.5.0.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.5.1.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.0.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.1.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.2.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.7.0.sql");

   } else if ($DB->tableExists("glpi_plugin_resources_profiles") && $DB->fieldExists("glpi_plugin_resources_profiles", "interface")) {
      $update = true;
      $update78 = true;
      $update80 = true;
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.5.0.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.5.1.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.0.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.1.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.2.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.7.0.sql");

   } else if ($DB->tableExists("glpi_plugin_resources") && !$DB->fieldExists("glpi_plugin_resources", "helpdesk_visible")) {
      $update = true;
      $update78 = true;
      $update80 = true;
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.5.1.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.0.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.1.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.2.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.7.0.sql");

   } else if (!$DB->tableExists("glpi_plugin_resources_contracttypes")) {
      $update = true;
      $update78 = true;
      $update80 = true;
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.0.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.1.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.2.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.7.0.sql");

   } else if ($DB->tableExists("glpi_plugin_resources_contracttypes") && !$DB->fieldExists("glpi_plugin_resources_resources", "plugin_resources_resourcestates_id")) {
      $update = true;
      $update80 = true;
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.1.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.2.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.7.0.sql");

   } else if (!$DB->tableExists("glpi_plugin_resources_reportconfigs")) {
      $update = true;
      $update80 = true;
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.6.2.sql");
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.7.0.sql");

   } else if (!$DB->tableExists("glpi_plugin_resources_checklistconfigs")) {
      $update80 = true;
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.7.0.sql");

   }

   if ($update) {
      // Add record notification
      include_once(GLPI_ROOT . "/plugins/resources/inc/notificationtargetresource.class.php");
      call_user_func(["PluginResourcesNotificationTargetResource", 'update_notif']);
   }

   if ($update78) {

      $profiles = getAllDatasFromTable("glpi_plugin_resources_profiles");

      if (!empty($profiles)) {
         foreach ($profiles as $profile) {
            $query = "UPDATE `glpi_plugin_resources_profiles`
                  SET `profiles_id` = '" . $profile["id"] . "'
                  WHERE `id` = '" . $profile["id"] . "';";
            $DB->query($query);
         }
      }

      $query = "ALTER TABLE `glpi_plugin_resources_profiles`
               DROP `name` ;";
      $DB->query($query);

      $tables = [
         "glpi_displaypreferences",
         "glpi_documents_items",
         "glpi_savedsearches",
         "glpi_logs",
         "glpi_items_tickets"
      ];

      foreach ($tables as $table) {
         $query = "DELETE FROM `$table` WHERE (`itemtype` = '4302' ) ";
         $DB->query($query);
      }

      Plugin::migrateItemType(
         [4300 => 'PluginResourcesResource',
          4301 => 'PluginResourcesTask',
          4303 => 'PluginResourcesDirectory'],
         ["glpi_savedsearches", "glpi_savedsearches_users", "glpi_displaypreferences",
          "glpi_documents_items", "glpi_infocoms", "glpi_logs", "glpi_items_tickets"],
         ["glpi_plugin_resources_resources_items", "glpi_plugin_resources_choices", "glpi_plugin_resources_tasks_items"]);

      Plugin::migrateItemType(
         [1600 => "PluginBadgesBadge"],
         ["glpi_plugin_resources_resources_items", "glpi_plugin_resources_choices", "glpi_plugin_resources_tasks_items"]);

      // Add record notification
      include_once(GLPI_ROOT . "/plugins/resources/inc/notificationtargetresource.class.php");
      call_user_func(["PluginResourcesNotificationTargetResource", 'update78']);
   }

   if ($update80) {
      // Add record notification
      include_once(GLPI_ROOT . "/plugins/resources/inc/notificationtargetresource.class.php");
      call_user_func(["PluginResourcesNotificationTargetResource", 'update80']);
   }

   //Version 1.7.1
   if (!$DB->tableExists("glpi_plugin_resources_choiceitems")) {
      $update171 = true;
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.7.1.sql");

   }

   //Version 1.9.0
   if (!$DB->tableExists("glpi_plugin_resources_employments")) {
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.9.0.sql");

      $query = "SELECT * FROM `glpi_plugin_resources_employers`";
      $result = $DB->query($query);
      if ($DB->numrows($result) > 0) {
         while ($data = $DB->fetch_array($result)) {
            $queryUpdate = "UPDATE `glpi_plugin_resources_employers`
                            SET `completename`= '".$data["name"]."'
                            WHERE `id`= '".$data["id"]."'";
            $DB->query($queryUpdate) or die($DB->error());
         }
      }

   }

   //Version 1.9.1
   if ($DB->tableExists("glpi_plugin_resources_ranks") && !$DB->fieldExists("glpi_plugin_resources_ranks", "begin_date")) {
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-1.9.1.sql");
   }

   //Version 2.0.3
   if (!$DB->fieldExists("glpi_plugin_resources_reportconfigs", "send_report_notif")) {
      $DB->runFile(GLPI_ROOT."/plugins/resources/install/sql/update-2.0.3.sql");

      // Add record notification
      include_once(GLPI_ROOT . "/plugins/resources/inc/notificationtargetresource.class.php");
      call_user_func(["PluginResourcesNotificationTargetResource", 'update203']);
   }

   //Version 2.0.4
   if (!$DB->tableExists("glpi_plugin_resources_transferentities")) {
      $DB->runFile(GLPI_ROOT ."/plugins/resources/install/sql/update-2.0.4.sql");

      // Add record notification
      include_once(GLPI_ROOT . "/plugins/resources/inc/notificationtargetresource.class.php");
      call_user_func(["PluginResourcesNotificationTargetResource", 'update204']);
   }

   //Version 2.3.1
   if (!$DB->tableExists("glpi_plugin_resources_resources_changes") && !$DB->tableExists("glpi_plugin_resources_resourcebadges")) {
      $DB->runFile(GLPI_ROOT ."/plugins/resources/install/sql/update-2.3.1.sql");

      // Add record notification
      include_once(GLPI_ROOT . "/plugins/resources/inc/notificationtargetresource.class.php");
      call_user_func(["PluginResourcesNotificationTargetResource", 'update231']);

   }

   //Version 2.3.2
   if (!$DB->tableExists("glpi_plugin_resources_configs")) {
      $DB->runFile(GLPI_ROOT ."/plugins/resources/install/sql/update-2.3.2.sql");

      include(GLPI_ROOT."/plugins/resources/install/update_231_232.php");
      update231_232();

   }

   //Version 2.3.3
   if (!$DB->fieldExists("glpi_plugin_resources_configs", "security_compliance")) {
      $DB->runFile(GLPI_ROOT ."/plugins/resources/install/sql/update-2.3.3.sql");

   }

   //Version 2.4.4
   if (!$DB->fieldExists("glpi_plugin_resources_contracttypes", "use_habilitation_wizard")) {
      $DB->runFile(GLPI_ROOT ."/plugins/resources/install/sql/update-2.4.4.sql");

   }

   if ($update80) {

      $restrict = "`plugin_resources_resources_id` ='-1'";

      $checklists = getAllDatasFromTable("glpi_plugin_resources_checklists", $restrict);
      $PluginResourcesChecklistconfig = new PluginResourcesChecklistconfig();
      if (!empty($checklists)) {
         foreach ($checklists as $checklist) {
            $values["name"] = addslashes($checklist["name"]);
            $values["address"] = addslashes($checklist["address"]);
            $values["comment"] = addslashes($checklist["comment"]);
            $values["tag"] = $checklist["tag"];
            $values["entities_id"] = $checklist["entities_id"];
            $PluginResourcesChecklistconfig->add($values);
         }
      }

      $query = "DELETE FROM `glpi_plugin_resources_checklists`
               WHERE `plugin_resources_resources_id` ='-1'
                  OR `plugin_resources_resources_id` ='0';";
      $DB->query($query);

      // Put realtime in seconds
      if ($DB->fieldExists('glpi_plugin_resources_tasks', 'realtime')) {

         $query = "ALTER TABLE `glpi_plugin_resources_tasks`
            ADD `actiontime` INT( 11 ) NOT NULL DEFAULT 0 ;";
         $DB->queryOrDie($query, $this->version." 0.80 Add actiontime in glpi_plugin_resources_tasks");

         $query = "UPDATE `glpi_plugin_resources_tasks`
                   SET `actiontime` = ROUND(realtime * 3600)";
         $DB->queryOrDie($query, $this->version." 0.80 Compute actiontime value in glpi_plugin_resources_tasks");

         $query = "ALTER TABLE `glpi_plugin_resources_tasks`
            DROP `realtime` ;";
         $DB->queryOrDie($query, $this->version." 0.80 DROP realtime in glpi_plugin_resources_tasks");
      }

      // ADD plannings for tasks
      $tasks = getAllDatasFromTable("glpi_plugin_resources_tasks");
      if (!empty($tasks)) {
         foreach ($tasks as $task) {
            $query = "INSERT INTO `glpi_plugin_resources_taskplannings`
               ( `id` , `plugin_resources_tasks_id` , `begin` , `end` )
               VALUES (NULL , '".$task["id"]."', '".$task["date_begin"]."', '".$task["date_end"]."') ;";
            $DB->query($query);
         }
      }

      unset($input);

      $query = "ALTER TABLE `glpi_plugin_resources_tasks`
               DROP `date_begin`, DROP `date_end` ;";
      $DB->queryOrDie($query, $this->version." 0.80 Drop date_begin and date_end in glpi_plugin_resources_tasks");

      // ADD tasks
      $PluginResourcesResource = new PluginResourcesResource();
      $taches = getAllDatasFromTable("glpi_plugin_resources_tasks");
      if (!empty($taches)) {
         foreach ($taches as $tache) {
            $PluginResourcesResource->getFromDB($tache["plugin_resources_resources_id"]);
            $input["entities_id"] = $PluginResourcesResource->fields["entities_id"];
            $query = "UPDATE `glpi_plugin_resources_tasks`
               SET `entities_id` =  '".$PluginResourcesResource->fields["entities_id"]."' WHERE `id` = '".$tache["id"]."' ;";
            $DB->query($query);
         }
      }
   }

   if ($install || $update80) {
      $restrict = "`itemtype` = 'PluginResourcesResource'";
      $unicities = getAllDatasFromTable("glpi_fieldunicities", $restrict);
      if (empty($unicities)) {
         $query = "INSERT INTO `glpi_fieldunicities`
                                      VALUES (NULL, 'Resources creation', 1, 'PluginResourcesResource', '0',
                                             'name,firstname','1',
                                             '1', '1', '',NOW(),NOW());";
         $DB->queryOrDie($query, " 0.80 Create fieldunicities check");
      }

   }

   if ($update171) {
      $query = "SELECT * FROM `glpi_plugin_resources_choices`
      WHERE `itemtype`!= '' GROUP BY `comment`,`itemtype`";
      $result = $DB->query($query);
      $number = $DB->numrows($result);

      $affectedchoices = [];

      if (!empty($number)) {
         while ($data = $DB->fetch_assoc($result)) {

            $restrictaffected = "`itemtype` = '".$data['raw']["ITEMtype"]."'
               AND `comment` = '".addslashes($data["comment"])."'";
            $affected = getAllDatasFromTable("glpi_plugin_resources_choices", $restrictaffected);

            if (!empty($affected)) {
               foreach ($affected as $affect) {
                  if ($affect["itemtype"] == $data['raw']["ITEMtype"]
                      && $affect["comment"] == $data["comment"]) {
                     $affectedchoices[$data["id"]][] = $affect["plugin_resources_resources_id"];
                  }
               }
            }
         }
      }
      $i = 0;
      if (!empty($affectedchoices)) {
         foreach ($affectedchoices as $key => $ressources) {
            $i++;
            $choice = new PluginResourcesChoice();
            $choice_item = new PluginResourcesChoiceItem();

            $types = [__('Computer') => 'Computer',
                      __('Monitor') => 'Monitor',
                      __('Software') => 'Software',
                      __('Network device') => 'NetworkEquipment',
                      __('Printer') => 'Printer',
                      __('Peripheral') => 'Peripheral',
                      __('Phone') => 'Phone',
                      __('Consumable model') => 'ConsumableItem',
                      __('Specific network rights', 'resources') => '4303',
                      __('Access to the applications', 'resources') => '4304',
                      __('Specific securities groups', 'resources') => '4305',
                      __('Specific distribution lists', 'resources') => '4306',
                      __('Others needs', 'resources') => '4307',
                      'PluginBadgesBadge' => 'PluginBadgesBadge'];

            if ($choice->getFromDB($key)) {
               $key = array_search($choice->fields["itemtype"], $types);
               if ($key) {
                  $name = $key;
               } else {
                  $name = $choice->fields["itemtype"];
               }
               $valuesparent["name"] = $i.".".$name;
               $valuesparent["entities_id"] = 0;
               $valuesparent["is_recursive"] = 1;
               $newidparent = $choice_item->add($valuesparent);

               $comment = "N/A";
               if (!empty($choice->fields["comment"])) {
                  $comment = $choice->fields["comment"];
               }
               $valueschild["name"] = addslashes(Html::resume_text($comment, 50));
               $valueschild["comment"] = addslashes($comment);
               $valueschild["entities_id"] = 0;
               $valueschild["is_recursive"] = 1;
               $valueschild["plugin_resources_choiceitems_id"] = $newidparent;
               $newidchild = $choice_item->add($valueschild);

               foreach ($ressources as $id => $val) {
                  $query = "UPDATE `glpi_plugin_resources_choices`
                           SET `plugin_resources_choiceitems_id` = '".$newidchild."'
                          WHERE `plugin_resources_resources_id` = '".$val."'
                          AND `itemtype` = '".$choice->fields["itemtype"]."'
                           AND `comment` = '".addslashes($choice->fields["comment"])."';";
                  $result = $DB->query($query);
               }
            }
         }
      }

      $query = "ALTER TABLE `glpi_plugin_resources_choices`
   DROP `itemtype`,
   DROP `comment`,
   ADD UNIQUE KEY `unicity` (`plugin_resources_resources_id`,`plugin_resources_choiceitems_id`);";
      $DB->query($query);

      $query = "ALTER TABLE `glpi_plugin_resources_choices`
   ADD `comment` text collate utf8_unicode_ci;";
      $DB->query($query);
   }

   //0.83 - Drop Matricule
   if ($DB->tableExists("glpi_plugin_resources_employees") && $DB->fieldExists("glpi_plugin_resources_employees", "matricule")) {

      $query = "SELECT * FROM `glpi_users`";
      $result = $DB->query($query);
      $number = $DB->numrows($result);

      if (!empty($number)) {
         while ($data = $DB->fetch_assoc($result)) {

            $restrict = "`items_id` = '".$data["id"]."'
               AND `itemtype` = 'User'";
            $links = getAllDatasFromTable("glpi_plugin_resources_resources_items", $restrict);

            if (!empty($links)) {

               foreach ($links as $link) {

                  $employee = new PluginResourcesEmployee();
                  if ($employee->getFromDBbyResources($link["plugin_resources_resources_id"])) {
                     $matricule = $employee->fields["matricule"];

                     if (isset($matricule) && !empty($matricule)) {
                        $query = "UPDATE `glpi_users`
                           SET `registration_number` = '".$matricule."'
                           WHERE `id` ='".$link["items_id"]."'";
                        $DB->query($query);
                     }
                  }
               }
            }
         }
      }

      $query = "ALTER TABLE `glpi_plugin_resources_employees`
               DROP `matricule` ;";
      $DB->query($query);
   }

   if ($DB->tableExists("glpi_plugin_resources_profiles")) {

      $notepad_tables = ['glpi_plugin_resources_resources'];

      foreach ($notepad_tables as $t) {
         // Migrate data
         if ($DB->fieldExists($t, 'notepad')) {
            $query = "SELECT id, notepad
                      FROM `$t`
                      WHERE notepad IS NOT NULL
                            AND notepad <>'';";
            foreach ($DB->request($query) as $data) {
               $iq = "INSERT INTO `glpi_notepads`
                             (`itemtype`, `items_id`, `content`, `date`, `date_mod`)
                      VALUES ('".getItemTypeForTable($t)."', '".$data['id']."',
                              '".addslashes($data['notepad'])."', NOW(), NOW())";
               $DB->queryOrDie($iq, "0.85 migrate notepad data");
            }
            $query = "ALTER TABLE `glpi_plugin_resources_resources` DROP COLUMN `notepad`;";
            $DB->query($query);
         }
      }
   }

   $rep_files_resources = GLPI_PLUGIN_DOC_DIR."/resources";
   if (!is_dir($rep_files_resources)) {
      mkdir($rep_files_resources);
   }

   CronTask::Register('PluginResourcesResource', 'Resources', DAY_TIMESTAMP);
   CronTask::Register('PluginResourcesTask', 'ResourcesTask', DAY_TIMESTAMP);
   CronTask::Register('PluginResourcesChecklist', 'ResourcesChecklist', DAY_TIMESTAMP);
   CronTask::Register('PluginResourcesEmployment', 'ResourcesLeaving', DAY_TIMESTAMP, ['state' => CronTask::STATE_DISABLE]);
   CronTask::Register('PluginResourcesResource', 'AlertCommercialManager', MONTH_TIMESTAMP, ['state' => CronTask::STATE_DISABLE]);

   PluginResourcesProfile::initProfile();
   PluginResourcesProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
   $migration = new Migration("2.3.0");
   $migration->dropTable('glpi_plugin_resources_profiles');
   return true;
}

function plugin_resources_uninstall() {
   global $DB;

   $tables = ["glpi_plugin_resources_resources",
              "glpi_plugin_resources_resources_items",
              "glpi_plugin_resources_employees",
              "glpi_plugin_resources_employers",
              "glpi_plugin_resources_clients",
              "glpi_plugin_resources_choices",
              "glpi_plugin_resources_choiceitems",
              "glpi_plugin_resources_departments",
              "glpi_plugin_resources_contracttypes",
              "glpi_plugin_resources_resourcestates",
              "glpi_plugin_resources_tasktypes",
              "glpi_plugin_resources_profiles",
              "glpi_plugin_resources_tasks",
              "glpi_plugin_resources_taskplannings",
              "glpi_plugin_resources_tasks_items",
              "glpi_plugin_resources_checklists",
              "glpi_plugin_resources_checklistconfigs",
              "glpi_plugin_resources_reportconfigs",
              "glpi_plugin_resources_resourcerestings",
              "glpi_plugin_resources_resourceholidays",
              "glpi_plugin_resources_ticketcategories",
              "glpi_plugin_resources_resourcesituations",
              "glpi_plugin_resources_contractnatures",
              "glpi_plugin_resources_ranks",
              "glpi_plugin_resources_resourcespecialities",
              "glpi_plugin_resources_leavingreasons",
              "glpi_plugin_resources_professions",
              "glpi_plugin_resources_professionlines",
              "glpi_plugin_resources_professioncategories",
              "glpi_plugin_resources_employments",
              "glpi_plugin_resources_employmentstates",
              "glpi_plugin_resources_budgets",
              "glpi_plugin_resources_costs",
              "glpi_plugin_resources_budgettypes",
              "glpi_plugin_resources_budgetvolumes",
              "glpi_plugin_resources_configs",
              "glpi_plugin_resources_notifications",
              "glpi_plugin_resources_resourcebadges",
              "glpi_plugin_resources_resourcehabilitations",
              "glpi_plugin_resources_transferentities",
              "glpi_plugin_resources_resources_changes",
              "glpi_plugin_resources_confighabilitations",
              "glpi_plugin_resources_habilitations",
   ];

   foreach ($tables as $table) {
      $DB->query("DROP TABLE IF EXISTS `$table`;");
   }

   //old versions
   $tables = ["glpi_plugin_resources",
              "glpi_plugin_resources_device",
              "glpi_plugin_resources_needs",
              "glpi_plugin_resources_employee",
              "glpi_dropdown_plugin_resources_employer",
              "glpi_dropdown_plugin_resources_client",
              "glpi_dropdown_plugin_resources_type",
              "glpi_dropdown_plugin_resources_department",
              "glpi_dropdown_plugin_resources_tasks_type",
              "glpi_plugin_resources_mailingsettings",
              "glpi_plugin_resources_mailing"];

   foreach ($tables as $table) {
      $DB->query("DROP TABLE IF EXISTS `$table`;");
   }

   $tables = [
      "glpi_displaypreferences",
      "glpi_documents_items",
      "glpi_savedsearches",
      "glpi_logs",
      "glpi_items_tickets",
      "glpi_dropdowntranslations",
   ];

   foreach ($tables as $table) {
      $DB->query("DELETE
                  FROM `$table`
                  WHERE `itemtype` LIKE 'PluginResources%'");
   }

   $tables = [
      "glpi_fieldunicities",
   ];

   foreach ($tables as $table) {
      $DB->query("DELETE
                  FROM `$table`
                  WHERE `name` LIKE 'PluginResources%'");
   }

   //drop rules
   $Rule = new Rule();
   $a_rules = $Rule->find("`sub_type`='PluginResourcesRuleChecklist'
                              OR `sub_type`='PluginResourcesRuleContracttype'");
   foreach ($a_rules as $data) {
      $Rule->delete($data);
   }

   $notif = new Notification();

   $options = ['itemtype' => 'PluginResourcesResource',
               'FIELDS' => 'id'];
   foreach ($DB->request('glpi_notifications', $options) as $data) {
      $notif->delete($data);
   }

   //templates
   $template = new NotificationTemplate();
   $translation = new NotificationTemplateTranslation();
   $options = ['itemtype' => 'PluginResourcesResource',
               'FIELDS' => 'id'];
   foreach ($DB->request('glpi_notificationtemplates', $options) as $data) {
      $options_template = ['notificationtemplates_id' => $data['id'],
                           'FIELDS' => 'id'];

      foreach ($DB->request('glpi_notificationtemplatetranslations', $options_template) as $data_template) {
         $translation->delete($data_template);
      }
      $template->delete($data);
   }

   if (class_exists('PluginDatainjectionModel')) {
      PluginDatainjectionModel::clean(['itemtype' => 'PluginResourcesResource']);
      PluginDatainjectionModel::clean(['itemtype' => 'PluginResourcesClient']);
   }

   $rep_files_resources = GLPI_PLUGIN_DOC_DIR."/resources";
   Toolbox::deleteDir($rep_files_resources);

   include_once (GLPI_ROOT . "/plugins/resources/inc/profile.class.php");

   PluginResourcesProfile::removeRightsFromSession();

   return true;
}

function plugin_resources_postinit() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['pre_item_update']['resources'] = ['User' => 'plugin_pre_item_update_resources'];

   $PLUGIN_HOOKS['item_purge']['resources'] = [];

   foreach (PluginResourcesResource::getTypes(true) as $type) {

      $PLUGIN_HOOKS['item_purge']['resources'][$type]
         = ['PluginResourcesResource_Item', 'cleanForItem'];

      CommonGLPI::registerStandardTab($type, 'PluginResourcesResource_Item');
   }

   CommonGLPI::registerStandardTab("Central", 'PluginResourcesTask');
}

function plugin_resources_AssignToTicket($types) {

   if (Session::haveRight("plugin_resources_open_ticket", 1)) {
      $types['PluginResourcesResource'] = PluginResourcesResource::getTypeName(2);
   }

   return $types;
}

// Define dropdown relations
function plugin_resources_getDatabaseRelations() {

   $plugin = new Plugin();
   if ($plugin->isActivated("resources")) {
      return [
         "glpi_entities"                                   => ["glpi_plugin_resources_resources"               => "entities_id",
                                                               "glpi_plugin_resources_resourcestates"          => "entities_id",
                                                               "glpi_plugin_resources_choiceitems"             => "entities_id",
                                                               "glpi_plugin_resources_employers"               => "entities_id",
                                                               "glpi_plugin_resources_clients"                 => "entities_id",
                                                               "glpi_plugin_resources_contracttypes"           => "entities_id",
                                                               "glpi_plugin_resources_departments"             => "entities_id",
                                                               "glpi_plugin_resources_tasks"                   => "entities_id",
                                                               "glpi_plugin_resources_tasktypes"               => "entities_id",
                                                               "glpi_plugin_resources_checklists"              => "entities_id",
                                                               "glpi_plugin_resources_checklistconfigs"        => "entities_id",
                                                               "glpi_plugin_resources_resourcesituations"      => "entities_id",
                                                               "glpi_plugin_resources_contractnatures"         => "entities_id",
                                                               "glpi_plugin_resources_ranks"                   => "entities_id",
                                                               "glpi_plugin_resources_resourcespecialities"    => "entities_id",
                                                               "glpi_plugin_resources_leavingreasons"          => "entities_id",
                                                               "glpi_plugin_resources_professions"             => "entities_id",
                                                               "glpi_plugin_resources_professionlines"         => "entities_id",
                                                               "glpi_plugin_resources_professioncategories"    => "entities_id",
                                                               "glpi_plugin_resources_employments"             => "entities_id",
                                                               "glpi_plugin_resources_employmentstates"        => "entities_id",
                                                               "glpi_plugin_resources_budgets"                 => "entities_id",
                                                               "glpi_plugin_resources_costs"                   => "entities_id",
                                                               "glpi_plugin_resources_budgettypes"             => "entities_id",
                                                               "glpi_plugin_resources_budgetvolumes"           => "entities_id",
                                                               "glpi_plugin_resources_transferentities"        => "entities_id"],
         "glpi_plugin_resources_contracttypes"             => ["glpi_plugin_resources_resources"               => "plugin_resources_contracttypes_id",
                                                               "glpi_plugin_resources_checklists"              => "plugin_resources_contracttypes_id"],
         "glpi_users"                                      => ["glpi_plugin_resources_resources"               => ['users_id', 'users_id_recipient', 'users_id_recipient_leaving', 'users_id_sales'],"glpi_plugin_resources_tasks" => "users_id"],
         "glpi_plugin_resources_departments"               => ["glpi_plugin_resources_resources"               => "plugin_resources_departments_id"],
         "glpi_plugin_resources_habilitations"             => ["glpi_plugin_resources_resourcehabilitations"   => "plugin_resources_habilitations_id"],
         "glpi_plugin_resources_resourcestates"            => ["glpi_plugin_resources_resources"               => "plugin_resources_resourcestates_id"],
         "glpi_plugin_resources_resourcesituations"        => ["glpi_plugin_resources_resources"               => "plugin_resources_resourcesituations_id"],
         "glpi_plugin_resources_contractnatures"           => ["glpi_plugin_resources_resources"               => "plugin_resources_contractnatures_id"],
         "glpi_plugin_resources_ranks"                     => ["glpi_plugin_resources_resources"               => "plugin_resources_ranks_id"],
         "glpi_plugin_resources_resourcespecialities"      => ["glpi_plugin_resources_resources"               => "plugin_resources_resourcespecialities_id"],
         "glpi_locations"                                  => ["glpi_plugin_resources_resources"               => "locations_id",
                                                               "glpi_plugin_resources_employers"               => "locations_id",
                                                               "glpi_plugin_resources_resourcerestings"        => "locations_id"],
         "glpi_plugin_resources_leavingreasons"            => ["glpi_plugin_resources_resources"               => "plugin_resources_leavingreasons_id"],
         "glpi_plugin_resources_resources"                 => ["glpi_plugin_resources_choices"                 => "plugin_resources_resources_id",
                                                               "glpi_plugin_resources_resources_items"         => "plugin_resources_resources_id",
                                                               "glpi_plugin_resources_employees"               => "plugin_resources_resources_id",
                                                               "glpi_plugin_resources_tasks"                   => "plugin_resources_resources_id",
                                                               "glpi_plugin_resources_checklists"              => "plugin_resources_resources_id",
                                                               "glpi_plugin_resources_reportconfigs"           => "plugin_resources_resources_id",
                                                               "glpi_plugin_resources_resourcerestings"        => "plugin_resources_resources_id",
                                                               "glpi_plugin_resources_resourceholidays"        => "plugin_resources_resources_id",
                                                               "glpi_plugin_resources_employments"             => "plugin_resources_resources_id"],
         "glpi_plugin_resources_choiceitems"               => ["glpi_plugin_resources_choices"                 => "plugin_resources_choiceitems_id",
                                                               "glpi_plugin_resources_choiceitems"             => "plugin_resources_choiceitems_id"],
         "glpi_plugin_resources_employers"                 => ["glpi_plugin_resources_employees"               => "plugin_resources_employers_id",
                                                               "glpi_plugin_resources_employers"               => "plugin_resources_employers_id",
                                                               "glpi_plugin_resources_employments"             => "plugin_resources_employers_id"],
         "glpi_plugin_resources_clients"                   => ["glpi_plugin_resources_employees"               => "plugin_resources_clients_id"],
         "glpi_plugin_resources_tasktypes"                 => ["glpi_plugin_resources_tasks"                   => "plugin_resources_tasktypes_id"],
         "glpi_groups"                                     => ["glpi_plugin_resources_tasks"                   => "groups_id"],
         "glpi_plugin_resources_tasks"                     => ["glpi_plugin_resources_tasks_items"             => "plugin_resources_tasks_id",
                                                               "glpi_plugin_resources_checklists"              => "plugin_resources_tasks_id",
                                                               "glpi_plugin_resources_taskplannings"           => "plugin_resources_tasks_id"],
         "glpi_ticketcategories"                           => ["glpi_plugin_resources_ticketcategories"        => "ticketcategories_id"],
         "glpi_plugin_resources_professions"               => ["glpi_plugin_resources_ranks"                   => "plugin_resources_professions_id",
                                                               "glpi_plugin_resources_employments"             => "plugin_resources_professions_id",
                                                               "glpi_plugin_resources_budgets"                 => "plugin_resources_professions_id",
                                                               "glpi_plugin_resources_costs"                   => "plugin_resources_professions_id"],
         "glpi_plugin_resources_ranks"                     => ["glpi_plugin_resources_resourcespecialities"    => "plugin_resources_ranks_id",
                                                               "glpi_plugin_resources_employments"             => "plugin_resources_ranks_id",
                                                               "glpi_plugin_resources_budgets"                 => "plugin_resources_ranks_id",
                                                               "glpi_plugin_resources_costs"                   => "plugin_resources_ranks_id"],
         "glpi_plugin_resources_professionlines"           => ["glpi_plugin_resources_professions"             => "plugin_resources_professionlines_id"],
         "glpi_plugin_resources_professioncategories"      => ["glpi_plugin_resources_professions"             => "plugin_resources_professioncategories_id"],
         "glpi_plugin_resources_employmentstates"          => ["glpi_plugin_resources_employments"             => "plugin_resources_employmentstates_id"],
         "glpi_plugin_resources_budgettypes"               => ["glpi_plugin_resources_budgets"                 => "plugin_resources_budgettypes_id"],
         "glpi_plugin_resources_budgetvolumes"             => ["glpi_plugin_resources_budgets"                 => "plugin_resources_budgetvolumes_id"],
         "glpi_plugin_resources_habilitationlevels"        => ["glpi_plugin_resources_habilitations"           => "plugin_resources_habilitationlevels_id"],
      ];
   } else {
      return [];
   }
}

// Define Dropdown tables to be manage in GLPI :
function plugin_resources_getDropdown() {

   $plugin = new Plugin();
   if ($plugin->isActivated("resources")) {
      return ['PluginResourcesContractType'       => PluginResourcesContractType::getTypeName(2),
              'PluginResourcesTaskType'           => PluginResourcesTaskType::getTypeName(2),
              'PluginResourcesResourceState'      => PluginResourcesResource::getTypeName(2) . " - " . PluginResourcesResourceSituation::getTypeName(2),
              'PluginResourcesDepartment'         => PluginResourcesDepartment::getTypeName(2),
              'PluginResourcesEmployer'           => PluginResourcesEmployer::getTypeName(2),
              'PluginResourcesClient'             => PluginResourcesClient::getTypeName(2),
              'PluginResourcesChoiceItem'         => PluginResourcesChoiceItem::getTypeName(2),
              'PluginResourcesResourceSituation'  => PluginResourcesEmployer::getTypeName(2) . " - " . PluginResourcesResourceSituation::getTypeName(2),
              'PluginResourcesContractNature'     => PluginResourcesContractNature::getTypeName(2),
              'PluginResourcesRank'               => PluginResourcesRank::getTypeName(2),
              'PluginResourcesResourceSpeciality' => PluginResourcesResourceSpeciality::getTypeName(2),
              'PluginResourcesLeavingReason'      => PluginResourcesLeavingReason::getTypeName(2),
              'PluginResourcesProfession'         => PluginResourcesProfession::getTypeName(2),
              'PluginResourcesProfessionLine'     => PluginResourcesProfessionLine::getTypeName(2),
              'PluginResourcesProfessionCategory' => PluginResourcesProfessionCategory::getTypeName(2),
              'PluginResourcesEmploymentState'    => PluginResourcesEmploymentState::getTypeName(2),
              'PluginResourcesBudgetType'         => PluginResourcesBudgetType::getTypeName(2),
              'PluginResourcesBudgetVolume'       => PluginResourcesBudgetVolume::getTypeName(2),
              'PluginResourcesHabilitation'       => PluginResourcesHabilitation::getTypeName(2),
              'PluginResourcesHabilitationLevel'  => PluginResourcesHabilitationLevel::getTypeName(2),
              'PluginResourcesCost'               => PluginResourcesCost::getTypeName(2)];
   } else {
      return [];
   }
}

////// SEARCH FUNCTIONS ///////() {

function plugin_resources_getAddSearchOptions($itemtype) {

   $sopt = [];

   if ($itemtype == "User") {
      if (Session::haveRight("plugin_resources", READ)) {
         $sopt[4311]['table'] = 'glpi_plugin_resources_contracttypes';
         $sopt[4311]['field'] = 'name';
         $sopt[4311]['name'] = PluginResourcesResource::getTypeName(2)." - ".PluginResourcesContractType::getTypeName(1);

         $sopt[4313]['table'] = 'glpi_plugin_resources_resources';
         $sopt[4313]['field'] = 'date_begin';
         $sopt[4313]['name'] = PluginResourcesResource::getTypeName(2)." - ".__('Begin date');
         $sopt[4313]['datatype'] = 'date';

         $sopt[4314]['table'] = 'glpi_plugin_resources_resources';
         $sopt[4314]['field'] = 'date_end';
         $sopt[4314]['name'] = PluginResourcesResource::getTypeName(2)." - ".__('End date');
         $sopt[4314]['datatype'] = 'date';

         $sopt[4315]['table'] = 'glpi_plugin_resources_departments';
         $sopt[4315]['field'] = 'name';
         $sopt[4315]['name'] = PluginResourcesResource::getTypeName(2)." - ".PluginResourcesDepartment::getTypeName(1);

         $sopt[4316]['table'] = 'glpi_plugin_resources_resources';
         $sopt[4316]['field'] = 'date_declaration';
         $sopt[4316]['name'] = PluginResourcesResource::getTypeName(2)." - ".__('Request date');
         $sopt[4316]['datatype'] = 'date';
         $sopt[4316]['massiveaction'] = false;

         $sopt[4317]['table'] = 'glpi_plugin_resources_locations';
         $sopt[4317]['field'] = 'completename';
         $sopt[4317]['name'] = PluginResourcesResource::getTypeName(2)." - ".__('Location');
         $sopt[4317]['massiveaction'] = false;

         $sopt[4318]['table'] = 'glpi_plugin_resources_resources';
         $sopt[4318]['field'] = 'is_leaving';
         $sopt[4318]['name'] = PluginResourcesResource::getTypeName(2)." - ".__('Declared as leaving', 'resources');
         $sopt[4318]['datatype'] = 'bool';

         $sopt[4320]['table'] = 'glpi_plugin_resources_employers';
         $sopt[4320]['field'] = 'name';
         $sopt[4320]['name'] = PluginResourcesResource::getTypeName(2)." - ".__('Employer', 'resources');

         $sopt[4321]['table'] = 'glpi_plugin_resources_clients';
         $sopt[4321]['field'] = 'name';
         $sopt[4321]['name'] = PluginResourcesResource::getTypeName(2)." - ".__('Affected client', 'resources');

         $sopt[4322]['table'] = 'glpi_plugin_resources_managers';
         $sopt[4322]['field'] = 'name';
         $sopt[4322]['linkfield'] = 'users_id';
         $sopt[4322]['name'] = PluginResourcesResource::getTypeName(2)." - ".__('Resource manager', 'resources');
         $sopt[4322]['massiveaction'] = false;

         $sopt[4323]['table'] = 'glpi_plugin_resources_recipients';
         $sopt[4323]['field'] = 'name';
         $sopt[4323]['linkfield'] = 'users_id_recipient';
         $sopt[4323]['name'] = PluginResourcesResource::getTypeName(2)." - ".__('Recipient');
         $sopt[4323]['massiveaction'] = false;

         $sopt[4324]['table'] = 'glpi_plugin_resources_recipients_leaving';
         $sopt[4324]['field'] = 'name';
         $sopt[4324]['linkfield'] = 'users_id_recipient_leaving';
         $sopt[4324]['name'] = PluginResourcesResource::getTypeName(2)." - ".__('Informant of leaving', 'resources');
         $sopt[4324]['massiveaction'] = false;

         $sopt[4325]['table'] = 'glpi_plugin_resources_salemanagers';
         $sopt[4325]['field'] = 'name';
         $sopt[4325]['linkfield'] = 'users_id_sales';
         $sopt[4325]['name'] = PluginResourcesResource::getTypeName(2)." - ".__('Sales manager', 'resources');
         $sopt[4325]['massiveaction'] = false;

      }
   }
   return $sopt;
}

function plugin_resources_addSelect($type, $ID, $num) {

   $searchopt = &Search::getOptions($type);
   $table = $searchopt[$ID]["table"];
   $field = $searchopt[$ID]["field"];

   // Example of standard Select clause but use it ONLY for specific Select
   // No need of the function if you do not have specific cases
   switch ($table.".".$field) {
      case "glpi_plugin_resources_resources.name":
         return "`".$table."`.`".$field."` AS META_$num,`".$table."`.`".$field."` AS ITEM_$num, `".$table."`.`id` AS ITEM_".$num."_2, ";
         break;
      case "glpi_plugin_resources_managers.name":
      case "glpi_plugin_resources_recipients_leaving.name":
      case "glpi_plugin_resources_recipients.name":
         return "`".$table."`.`".$field."` AS ITEM_$num, `".$table."`.`id` AS ITEM_".$num."_2, `".$table."`.`firstname` AS ITEM_".$num."_3,`".$table."`.`realname` AS ITEM_".$num."_4, ";
         break;
   }
   return "";
}

function plugin_resources_addDefaultJoin($type, $ref_table, &$already_link_tables) {

   // Example of default JOIN clause
   // No need of the function if you do not have specific cases
   switch ($type) {
      case "PluginResourcesDirectory" :
         $out = " LEFT JOIN `glpi_plugin_resources_resources_items` ON (`glpi_users`.`id` = `glpi_plugin_resources_resources_items`.`items_id` AND `glpi_plugin_resources_resources_items`.`itemtype`= 'User')";
         $out.= " LEFT JOIN `glpi_plugin_resources_resources` ON (`glpi_plugin_resources_resources`.`id` = `glpi_plugin_resources_resources_items`.`plugin_resources_resources_id`) ";
         $out.= " LEFT JOIN `glpi_profiles_users` ON (`glpi_users`.`id` = `glpi_profiles_users`.`users_id` ) ";
         return $out;
         break;
      case "PluginResourcesRecap" :
         $out = " LEFT JOIN `glpi_plugin_resources_resources` 
                  ON (`glpi_plugin_resources_resources`.`id` = `glpi_plugin_resources_employments`.`plugin_resources_resources_id` ".
                "AND `glpi_plugin_resources_resources`.`is_deleted` = 0 
                AND `glpi_plugin_resources_resources`.`is_template` = 0) ";
         $out.= " LEFT JOIN `glpi_plugin_resources_resources_items` 
                  ON (`glpi_plugin_resources_resources`.`id` = `glpi_plugin_resources_resources_items`.`plugin_resources_resources_id` 
                  AND `glpi_plugin_resources_resources_items`.`itemtype`= 'User')";
         $out.= " LEFT JOIN `glpi_users` 
                  ON (`glpi_users`.`id` = `glpi_plugin_resources_resources_items`.`items_id` AND `glpi_users`.`is_active` = 1)";
         $out.= " LEFT JOIN `glpi_plugin_resources_ranks` 
                   ON (`glpi_plugin_resources_resources`.`plugin_resources_ranks_id` = `glpi_plugin_resources_ranks`.`id`) ";
         $out.= " LEFT JOIN `glpi_plugin_resources_professions` 
                  ON (`glpi_plugin_resources_ranks`.`plugin_resources_professions_id` = `glpi_plugin_resources_professions`.`id`) ";
         $out.= " LEFT JOIN `glpi_plugin_resources_professions` AS `glpi_plugin_resources_employmentprofessions` 
                  ON (`glpi_plugin_resources_employments`.`plugin_resources_professions_id` = `glpi_plugin_resources_employmentprofessions`.`id`) ";
         $out.= " LEFT JOIN `glpi_plugin_resources_employers` 
                  ON (`glpi_plugin_resources_employments`.`plugin_resources_employers_id` = `glpi_plugin_resources_employers`.`id`) ";
         return $out;
         break;
   }
   return "";
}

function plugin_resources_addDefaultWhere($type) {

   // Example of default WHERE item to be added
   // No need of the function if you do not have specific cases
   switch ($type) {
      case "PluginResourcesResource" :
         $who = Session::getLoginUserID();
         if (!Session::haveRight("plugin_resources_all", READ)) {
            return " (`glpi_plugin_resources_resources`.`users_id_sales` = '$who' OR `glpi_plugin_resources_resources`.`users_id` = '$who') ";
         }
         break;
   }
   return "";
}

function plugin_resources_addWhere($link, $nott, $type, $ID, $val) {

   $searchopt = &Search::getOptions($type);
   $table = $searchopt[$ID]["table"];
   $field = $searchopt[$ID]["field"];

   $SEARCH = Search::makeTextSearch($val, $nott);

   switch ($table.".".$field) {
      case "glpi_plugin_resources_managers.name":
      case "glpi_plugin_resources_recipients_leaving.name":
      case "glpi_plugin_resources_recipients.name":
         $ADD = " OR `".$table."`.`firstname` LIKE '%".$val."%' OR `".$table."`.`realname` LIKE '%".$val."%' ";
         if ($nott && $val != "NULL") {
            $ADD = " OR `$table`.`$field` IS NULL";
         }
         return $link." (`$table`.`$field` $SEARCH ".$ADD." ) ";

         break;
   }
   return "";
}

function plugin_resources_addLeftJoin($type, $ref_table, $new_table, $linkfield, &$already_link_tables) {

   // Rename table for meta left join
   $AS = "";
   $AS_device = "";
   $nt = "glpi_plugin_resources_resources";
   $nt_device = "glpi_plugin_resources_resources_items";
   // Multiple link possibilies case
   if ($new_table == "glpi_plugin_resources_locations" || $new_table == "glpi_plugin_resources_managers" || $new_table == "glpi_plugin_resources_recipients" || $new_table == "glpi_plugin_resources_recipients_leaving") {
      $AS = " AS glpi_plugin_resources_resources_".$linkfield;
      $AS_device = " AS glpi_plugin_resources_resources_items_".$linkfield;
      $nt.="_".$linkfield;
      $nt_device.="_".$linkfield;
   }

   switch ($new_table) {

      case "glpi_plugin_resources_resources_items" :
         return " LEFT JOIN `glpi_plugin_resources_resources_items` ON (`$ref_table`.`id` = `glpi_plugin_resources_resources_items`.`items_id` AND `glpi_plugin_resources_resources_items`.`itemtype`= '$type') ";
         break;
      case "glpi_plugin_resources_taskplannings" :
         return " LEFT JOIN `glpi_plugin_resources_taskplannings` ON (`glpi_plugin_resources_taskplannings`.`plugin_resources_tasks_id` = `$ref_table`.`id`) ";
         break;
      case "glpi_plugin_resources_tasks_items" :
         return " LEFT JOIN `glpi_plugin_resources_tasks_items` ON (`$ref_table`.`id` = `glpi_plugin_resources_tasks_items`.`items_id` AND `glpi_plugin_resources_tasks_items`.`itemtype`= '$type') ";
         break;
      case "glpi_plugin_resources_resources" : // From items
         $out = " ";
         if ($type != "PluginResourcesDirectory" && $type != "PluginResourcesRecap") {
            if ($ref_table != 'glpi_plugin_resources_tasks'
                && $ref_table != 'glpi_plugin_resources_resourcerestings'
                && $ref_table != 'glpi_plugin_resources_resourceholidays'
                && $ref_table != 'glpi_plugin_resources_employments') {
               $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_resources_items", "plugin_resources_resources_id");
               $out.= " LEFT JOIN `glpi_plugin_resources_resources` ON (`glpi_plugin_resources_resources`.`id` = `glpi_plugin_resources_resources_items`.`plugin_resources_resources_id` AND `glpi_plugin_resources_resources_items`.`itemtype` = '$type') ";
            } else {
               $out = " LEFT JOIN `glpi_plugin_resources_resources` ON (`$ref_table`.`plugin_resources_resources_id` = `glpi_plugin_resources_resources`.`id`) ";
            }
         }
         return $out;
         break;
      case "glpi_plugin_resources_contracttypes" : // From items
         if ($type != "PluginResourcesDirectory" && $type != "PluginResourcesRecap") {
            $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_resources", "plugin_resources_resources_id");
            $out.= " LEFT JOIN `glpi_plugin_resources_contracttypes` ON (`glpi_plugin_resources_resources`.`plugin_resources_contracttypes_id` = `glpi_plugin_resources_contracttypes`.`id`) ";
         } else {
            $out = " LEFT JOIN `glpi_plugin_resources_contracttypes` ON (`glpi_plugin_resources_resources`.`plugin_resources_contracttypes_id` = `glpi_plugin_resources_contracttypes`.`id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_managers" : // From items
         $out = " LEFT JOIN `glpi_plugin_resources_resources_items` $AS_device ON (`$ref_table`.`id` = `$nt_device`.`items_id`) ";
         $out.= " LEFT JOIN `glpi_plugin_resources_resources` $AS ON (`$nt`.`id` = `$nt_device`.`plugin_resources_resources_id` AND `$nt_device`.`itemtype` = '$type') ";
         if ($type == "PluginResourcesDirectory") {
            $out.= " LEFT JOIN `glpi_users` AS `glpi_plugin_resources_managers` ON (`glpi_plugin_resources_resources`.`users_id` = `glpi_plugin_resources_managers`.`id`) ";
         } else {
            $out.= " LEFT JOIN `glpi_users` AS `glpi_plugin_resources_managers` ON (`$nt`.`users_id` = `glpi_plugin_resources_managers`.`id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_recipients" : // From items
         $out = " LEFT JOIN `glpi_plugin_resources_resources_items` $AS_device ON (`$ref_table`.`id` = `$nt_device`.`items_id`) ";
         $out.= " LEFT JOIN `glpi_plugin_resources_resources` $AS ON (`$nt`.`id` = `$nt_device`.`plugin_resources_resources_id` AND `$nt_device`.`itemtype` = '$type') ";
         $out.= " LEFT JOIN `glpi_users` AS glpi_plugin_resources_recipients ON (`$nt`.`users_id_recipient` = `glpi_plugin_resources_recipients`.`id`) ";
         return $out;
         break;
      case "glpi_plugin_resources_recipients_leaving" : // From items
         $out = " LEFT JOIN `glpi_plugin_resources_resources_items` $AS_device ON (`$ref_table`.`id` = `$nt_device`.`items_id`) ";
         $out.= " LEFT JOIN `glpi_plugin_resources_resources` $AS ON (`$nt`.`id` = `$nt_device`.`plugin_resources_resources_id` AND `$nt_device`.`itemtype` = '$type') ";
         $out.= " LEFT JOIN `glpi_users` AS glpi_plugin_resources_recipients_leaving ON (`$nt`.`users_id_recipient_leaving` = `glpi_plugin_resources_recipients_leaving`.`id`) ";
         return $out;
         break;
      case "glpi_plugin_resources_locations" : // From items
         $out = " LEFT JOIN `glpi_plugin_resources_resources_items` $AS_device ON (`$ref_table`.`id` = `$nt_device`.`items_id`) ";
         $out.= " LEFT JOIN `glpi_plugin_resources_resources` $AS ON (`$nt`.`id` = `$nt_device`.`plugin_resources_resources_id` AND `$nt_device`.`itemtype` = '$type') ";
         $out.= " LEFT JOIN `glpi_locations` AS glpi_plugin_resources_locations ON (`$nt`.`locations_id` = `glpi_plugin_resources_locations`.`id`) ";
         return $out;
         break;
      case "glpi_plugin_resources_departments" : // From items
         if ($type != "PluginResourcesDirectory" && $type != "PluginResourcesRecap") {
            $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_resources", "plugin_resources_resources_id");
            $out.= " LEFT JOIN `glpi_plugin_resources_departments` ON (`glpi_plugin_resources_resources`.`plugin_resources_departments_id` = `glpi_plugin_resources_departments`.`id`) ";
         } else {
            $out = " LEFT JOIN `glpi_plugin_resources_departments` ON (`glpi_plugin_resources_resources`.`plugin_resources_departments_id` = `glpi_plugin_resources_departments`.`id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_resourcestates" : // From items
         if ($type != "PluginResourcesDirectory" && $type != "PluginResourcesRecap") {
            $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_resources", "plugin_resources_resources_id");
            $out.= " LEFT JOIN `glpi_plugin_resources_resourcestates` ON (`glpi_plugin_resources_resources`.`plugin_resources_resourcestates_id` = `glpi_plugin_resources_resourcestates`.`id`) ";
         } else {
            $out = " LEFT JOIN `glpi_plugin_resources_resourcestates` ON (`glpi_plugin_resources_resources`.`plugin_resources_resourcestates_id` = `glpi_plugin_resources_resourcestates`.`id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_employees" : // From items
         if ($type != "PluginResourcesDirectory" && $type != "PluginResourcesRecap") {
            $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_resources", "plugin_resources_resources_id");
            $out.= " LEFT JOIN `glpi_plugin_resources_employees` ON (`glpi_plugin_resources_resources`.`id` = `glpi_plugin_resources_employees`.`plugin_resources_resources_id`) ";
         } else {
            $out = " LEFT JOIN `glpi_plugin_resources_employees` ON (`glpi_plugin_resources_resources`.`id` = `glpi_plugin_resources_employees`.`plugin_resources_resources_id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_resourcesituations" : // For recap class
         if ($type != "PluginResourcesDirectory" && $type != "PluginResourcesRecap") {
            $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_resources", "plugin_resources_resources_id");
            $out.= " LEFT JOIN `glpi_plugin_resources_resourcesituations` ON (`glpi_plugin_resources_resources`.`plugin_resources_resourcesituations_id` = `glpi_plugin_resources_resourcesituations`.`id`) ";
         } else {
            $out = " LEFT JOIN `glpi_plugin_resources_resourcesituations` ON (`glpi_plugin_resources_resources`.`plugin_resources_resourcesituations_id` = `glpi_plugin_resources_resourcesituations`.`id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_contractnatures" : // For recap class
         if ($type != "PluginResourcesDirectory" && $type != "PluginResourcesRecap") {
            $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_resources", "plugin_resources_resources_id");
            $out.= " LEFT JOIN `glpi_plugin_resources_contractnatures` ON (`glpi_plugin_resources_resources`.`plugin_resources_contractnatures_id` = `glpi_plugin_resources_contractnatures`.`id`) ";
         } else {
            $out = " LEFT JOIN `glpi_plugin_resources_contractnatures` ON (`glpi_plugin_resources_resources`.`plugin_resources_contractnatures_id` = `glpi_plugin_resources_contractnatures`.`id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_resourcespecialities" : // For recap class
         if ($type != "PluginResourcesDirectory" && $type != "PluginResourcesRecap") {
            $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_resources", "plugin_resources_resources_id");
            $out.= " LEFT JOIN `glpi_plugin_resources_resourcespecialities` ON (`glpi_plugin_resources_resources`.`plugin_resources_resourcespecialities_id` = `glpi_plugin_resources_resourcespecialities`.`id`) ";
         } else {
            $out = " LEFT JOIN `glpi_plugin_resources_resourcespecialities` ON (`glpi_plugin_resources_resources`.`plugin_resources_resourcespecialities_id` = `glpi_plugin_resources_resourcespecialities`.`id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_employments" : // For recap class
         if ($type != "PluginResourcesDirectory" && $type != "PluginResourcesRecap") {
            $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_resources", "plugin_resources_resources_id");
            $out.= " LEFT JOIN `glpi_plugin_resources_employments` ON (`glpi_plugin_resources_resources`.`id` = `glpi_plugin_resources_employments`.`plugin_resources_resources_id`) ";
         } else if ($type == "PluginResourcesRecap") {
            $out = " ";
         } else {
            $out = " LEFT JOIN `glpi_plugin_resources_employments` ON (`glpi_plugin_resources_resources`.`id` = `glpi_plugin_resources_employments`.`plugin_resources_resources_id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_ranks" : // For recap class
         if ($type != "PluginResourcesDirectory" && $type != "PluginResourcesRecap") {
            if ($type == "PluginResourcesResource") {
               $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_resources", "plugin_resources_resources_id");
               $out.= " LEFT JOIN `glpi_plugin_resources_ranks` ON (`glpi_plugin_resources_resources`.`plugin_resources_ranks_id` = `glpi_plugin_resources_ranks`.`id`) ";
            } else if ($type == "PluginResourcesEmployment") {
               $out = " LEFT JOIN `glpi_plugin_resources_ranks` ON (`glpi_plugin_resources_employments`.`plugin_resources_ranks_id` = `glpi_plugin_resources_ranks`.`id`) ";
            } else if ($type == "PluginResourcesBudget") {
               $out = " LEFT JOIN `glpi_plugin_resources_ranks` ON (`glpi_plugin_resources_budgets`.`plugin_resources_ranks_id` = `glpi_plugin_resources_ranks`.`id`) ";
            } else if ($type == 'PluginResourcesCost') {
               $out = " LEFT JOIN `glpi_plugin_resources_ranks` ON (`glpi_plugin_resources_costs`.`plugin_resources_ranks_id` = `glpi_plugin_resources_ranks`.`id`) ";
            } else if ($type == 'PluginResourcesResourceSpeciality') {
               $out = " LEFT JOIN `glpi_plugin_resources_ranks` ON (`glpi_plugin_resources_resourcespecialities`.`plugin_resources_ranks_id` = `glpi_plugin_resources_ranks`.`id`) ";
            }
         } else if ($type == "PluginResourcesRecap") {
            $out = " ";
         } else {
            $out = " LEFT JOIN `glpi_plugin_resources_ranks` ON (`glpi_plugin_resources_resources`.`plugin_resources_ranks_id` = `glpi_plugin_resources_ranks`.`id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_professions" : // For recap class
         $out = " ";
         if ($type == "PluginResourcesRecap") {
            $out = " ";
         } else if ($type == "PluginResourcesEmployment") { // for employment
            $out = " LEFT JOIN `glpi_plugin_resources_professions` ON (`glpi_plugin_resources_employments`.`plugin_resources_professions_id` = `glpi_plugin_resources_professions`.`id`) ";
         } else if ($type == "PluginResourcesBudget") {
            $out = " LEFT JOIN `glpi_plugin_resources_professions` ON (`glpi_plugin_resources_budgets`.`plugin_resources_professions_id` = `glpi_plugin_resources_professions`.`id`) ";
         } else if ($type == 'PluginResourcesCost') {
            $out = " LEFT JOIN `glpi_plugin_resources_professions` ON (`glpi_plugin_resources_costs`.`plugin_resources_professions_id` = `glpi_plugin_resources_professions`.`id`) ";
         } else if ($type == 'PluginResourcesRank') {
            $out = " LEFT JOIN `glpi_plugin_resources_professions` ON (`glpi_plugin_resources_ranks`.`plugin_resources_professions_id` = `glpi_plugin_resources_professions`.`id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_professionlines" : // For recap class
         $out = " LEFT JOIN `glpi_plugin_resources_professionlines` ON (`glpi_plugin_resources_professions`.`plugin_resources_professionlines_id` = `glpi_plugin_resources_professionlines`.`id`) ";
         return $out;
         break;
      case "glpi_plugin_resources_professioncategories" : // For recap class
         $out = " LEFT JOIN `glpi_plugin_resources_professioncategories` ON (`glpi_plugin_resources_professions`.`plugin_resources_professioncategories_id` = `glpi_plugin_resources_professioncategories`.`id`) ";
         return $out;
         break;
      case "glpi_plugin_resources_employmentranks" : // For recap class
         $out = " LEFT JOIN `glpi_plugin_resources_ranks` AS `glpi_plugin_resources_employmentranks` ON (`glpi_plugin_resources_employments`.`plugin_resources_ranks_id` = `glpi_plugin_resources_employmentranks`.`id`) ";
         return $out;
         break;
      case "glpi_plugin_resources_employmentprofessions" : // For recap class
         $out = " ";
         return $out;
         break;
      case "glpi_plugin_resources_employmentprofessionlines" : // For recap class
         $out = " LEFT JOIN `glpi_plugin_resources_professionlines` AS `glpi_plugin_resources_employmentprofessionlines` ON (`glpi_plugin_resources_employmentprofessions`.`plugin_resources_professionlines_id` = `glpi_plugin_resources_employmentprofessionlines`.`id`) ";
         return $out;
         break;
      case "glpi_plugin_resources_employmentprofessioncategories" : // For recap class
         $out = " LEFT JOIN `glpi_plugin_resources_professioncategories` AS `glpi_plugin_resources_employmentprofessioncategories` ON (`glpi_plugin_resources_employmentprofessions`.`plugin_resources_professioncategories_id` = `glpi_plugin_resources_employmentprofessioncategories`.`id`) ";
         return $out;
         break;
      case "glpi_plugin_resources_employers" : // From recap class
         if ($type != "PluginResourcesRecap" && $type != "PluginResourcesEmployment") {
            $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_employees", "plugin_resources_employees_id");
            $out.= " LEFT JOIN `glpi_plugin_resources_employers` ON (`glpi_plugin_resources_employees`.`plugin_resources_employers_id` = `glpi_plugin_resources_employers`.`id`) ";
         } else if ($type == "PluginResourcesRecap") {
            $out = " ";
         } else {
            $out = " LEFT JOIN `glpi_plugin_resources_employers` ON (`glpi_plugin_resources_employments`.`plugin_resources_employers_id` = `glpi_plugin_resources_employers`.`id`) ";
         }
         return $out;
         break;
      case "glpi_plugin_resources_clients" : // From items
         $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_employees", "plugin_resources_employees_id");
         $out.= " LEFT JOIN `glpi_plugin_resources_clients` ON (`glpi_plugin_resources_employees`.`plugin_resources_clients_id` = `glpi_plugin_resources_clients`.`id`) ";
         return $out;
         break;
      case "glpi_plugin_resources_employmentstates" : // For recap class
         if ($type != "PluginResourcesDirectory" && $type != "PluginResourcesRecap") {
            $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_plugin_resources_employments", "plugin_resources_employments_id");
            $out.= " LEFT JOIN `glpi_plugin_resources_employmentstates` ON (`glpi_plugin_resources_employments`.`plugin_resources_employmentstates_id` = `glpi_plugin_resources_employmentstates`.`id`) ";
         } else {
            $out = " LEFT JOIN `glpi_plugin_resources_employmentstates` ON (`glpi_plugin_resources_employments`.`plugin_resources_employmentstates_id` = `glpi_plugin_resources_employmentstates`.`id`) ";
         }
         return $out;
         break;
      case "glpi_locations" : // From recap class
         if ($type != "PluginResourcesRecap") {
            $out = Search::addLeftJoin($type, $ref_table, $already_link_tables, "glpi_locations", "locations_id");
         } else {
            $out = " LEFT JOIN `glpi_locations` ON (`glpi_plugin_resources_employers`.`locations_id` = `glpi_locations`.`id`) ";
         }
         return $out;
         break;
   }
   return "";
}

function plugin_resources_forceGroupBy($type) {

   return true;
   switch ($type) {
      case 'PluginResourcesResource':
         return true;
         break;
   }
   return false;
}

function plugin_resources_giveItem($type, $ID, $data, $num) {
   global $CFG_GLPI, $DB;

   $searchopt = &Search::getOptions($type);
   $table = $searchopt[$ID]["table"];
   $field = $searchopt[$ID]["field"];

   $output_type = Search::HTML_OUTPUT;
   if (isset($_GET['display_type'])) {
      $output_type = $_GET['display_type'];
   }

   switch ($type) {
      case 'PluginResourcesResource':
         switch ($table.'.'.$field) {
            case "glpi_plugin_resources_resources.name" :
               $out = "";
               if (!empty($data['raw']["ITEM_".$num."_2"])) {
                  $link = Toolbox::getItemTypeFormURL('PluginResourcesResource');
                  if ($output_type == Search::HTML_OUTPUT) {
                     $out = "<a href=\"".$link."?id=".$data['raw']["ITEM_".$num."_2"]."\">";
                  }
                  $out.= $data['raw']["META_$num"];
                  if ($output_type == Search::HTML_OUTPUT) {
                     if ($_SESSION["glpiis_ids_visible"] || empty($data['raw']["META_$num"])) {
                        $out.= " (".$data['raw']["ITEM_".$num."_2"].")";
                     }
                     $out.= "</a>";
                  }

                  if (Session::haveRight("plugin_resources_task", READ) && $output_type == Search::HTML_OUTPUT) {

                     $query_tasks = "SELECT COUNT(`id`) AS nb_tasks,SUM(`is_finished`) AS is_finished
                                 FROM `glpi_plugin_resources_tasks`
                                 WHERE `plugin_resources_resources_id` = ".$data['id']."
                                 AND `is_deleted` = 0";
                     $result_tasks = $DB->query($query_tasks);
                     $nb_tasks = $DB->result($result_tasks, 0, "nb_tasks");
                     $is_finished = $DB->result($result_tasks, 0, "is_finished");
                     $out.= "&nbsp;(<a href=\"".$CFG_GLPI["root_doc"]."/plugins/resources/front/task.php?plugin_resources_resources_id=".$data["id"]."\">";
                     if (($nb_tasks - $is_finished) > 0) {
                        $out.= "<span class='plugin_resources_date_over_color'>";
                        $out.=$nb_tasks - $is_finished."</span></a>)";
                     } else {
                        $out.= "<span class='plugin_resources_date_day_color'>";
                        $out.=$nb_tasks."</span></a>)";
                     }
                  }
               }
               return $out;
               break;
            case "glpi_plugin_resources_resources.date_end" :
               if ($data['raw']["ITEM_$num"] <= date('Y-m-d') && !empty($data['raw']["ITEM_$num"])) {
                  $out = "<span class='plugin_resources_date_color'>".Html::convDate($data['raw']["ITEM_$num"])."</span>";
               } else if (empty($data['raw']["ITEM_$num"])) {
                  $out = __('Not defined', 'resources');
               } else {
                  $out = Html::convDate($data['raw']["ITEM_$num"]);
               }
               return $out;
               break;
            case "glpi_plugin_resources_resources_items.items_id" :
               $restrict = "`plugin_resources_resources_id` = '".$data['id']."'
                           ORDER BY `itemtype`, `items_id`";
               $items = getAllDatasFromTable("glpi_plugin_resources_resources_items", $restrict);
               $out = '';
               if (!empty($items)) {
                  foreach ($items as $device) {
                     if (!class_exists($device["itemtype"])) {
                        continue;
                     }
                     $item = new $device["itemtype"]();
                     $item->getFromDB($device["items_id"]);
                     $out.=$item->getTypeName()." - ";
                     if ($device["itemtype"] == 'User') {
                        if ($output_type == Search::HTML_OUTPUT) {
                           $link = Toolbox::getItemTypeFormURL('User');
                           $out.="<a href=\"".$link."?id=".$device["items_id"]."\">";
                        }
                        $out.=getUserName($device["items_id"]);
                        if ($output_type == Search::HTML_OUTPUT) {
                           $out.="</a>";
                        }
                     } else {
                        $out.=$item->getLink();
                     }
                     $out.="<br>";
                  }
               } else {
                  $out = ' ';
               }
               return $out;
               break;
            case "glpi_plugin_resources_resources.quota" :
               if (!empty($data['raw']["ITEM_$num"])) {
                  $out = floatval($data['raw']["ITEM_$num"]);
               }
               return $out;
               break;
         }
         return "";
         break;
      case 'PluginResourcesTask':

         switch ($table.'.'.$field) {

            case "glpi_plugin_resources_resources.name" :
               $out = "";
               if (!empty($data['raw']["ITEM_".$num."_2"])) {
                  $user = PluginResourcesResource::getResourceName($data['raw']["ITEM_".$num."_2"], 2);
                  $out = "<a href='".$user['link']."'>";
                  $out.= $user["name"];
                  if ($_SESSION["glpiis_ids_visible"] || empty($user["name"])) {
                     $out.= " (".$data['raw']["ITEM_".$num."_2"].")";
                  }
                  $out.= "</a>";
               }
               return $out;
               break;
            case 'glpi_plugin_resources_tasks.is_finished':
               return PluginResourcesTask::getStatusImg($data['raw']["ITEM_$num"]);

               break;
            case "glpi_plugin_resources_tasks_items.items_id" :
               $restrict = "`plugin_resources_tasks_id` = '".$data['id']."'
                           ORDER BY `itemtype`, `items_id`";
               $items = getAllDatasFromTable("glpi_plugin_resources_tasks_items", $restrict);
               $out = '';
               if (!empty($items)) {
                  foreach ($items as $device) {
                     $item = new $device["itemtype"]();
                     $item->getFromDB($device["items_id"]);
                     $out.=$item->getTypeName()." - ".$item->getLink()."<br>";
                  }
               }
               return $out;
               break;
            case "glpi_plugin_resources_taskplannings.id" :
               if (!empty($data['raw']["ITEM_$num"])) {
                  $plan = new PluginResourcesTaskPlanning();
                  $plan->getFromDB($data['raw']["ITEM_$num"]);
                  $out = Html::convDateTime($plan->fields["begin"])."<br>&nbsp;->&nbsp;".
                         Html::convDateTime($plan->fields["end"]);
               } else {
                  $out = __('None');
               }
               return $out;
               break;
         }
         return "";
         break;
      case 'User':

         switch ($table.'.'.$field) {

            case "glpi_plugin_resources_recipients.name" :
               $out = getUserName($data['raw']["ITEM_".$num."_2"]);
               return $out;
               break;
            case "glpi_plugin_resources_recipients_leaving.name" :
               $out = getUserName($data['raw']["ITEM_".$num."_2"]);
               return $out;
               break;
            case "glpi_plugin_resources_managers.name" :
               $out = getUserName($data['raw']["ITEM_".$num."_2"]);
               return $out;
               break;
         }
         return "";
         break;
      case 'PluginResourcesResourceResting':

         switch ($table.'.'.$field) {

            case "glpi_plugin_resources_resources.name" :
               if (!empty($data["id"])) {
                  $link = Toolbox::getItemTypeFormURL('PluginResourcesResourceResting');
                  $out = "<a href=\"".$link."?id=".$data["id"]."\">";
                  $out.= $data['raw']["ITEM_$num"];
                  if ($_SESSION["glpiis_ids_visible"] || empty($data['raw']["ITEM_$num"])) {
                     $out.= " (".$data["id"].")";
                  }
                  $out.= "</a>";
               }
               return $out;
               break;
         }
         return "";
         break;
      case 'PluginResourcesResourceHoliday':

         switch ($table.'.'.$field) {

            case "glpi_plugin_resources_resources.name" :
               if (!empty($data["id"])) {
                  $link = Toolbox::getItemTypeFormURL('PluginResourcesResourceHoliday');
                  $out = "<a href=\"".$link."?id=".$data["id"]."\">";
                  $out.= $data['raw']["ITEM_$num"];
                  if ($_SESSION["glpiis_ids_visible"] || empty($data['raw']["ITEM_$num"])) {
                     $out.= " (".$data["id"].")";
                  }
                  $out.= "</a>";
               }
               return $out;
               break;
         }
         return "";
         break;

      case 'PluginResourcesDirectory':

         switch ($table.'.'.$field) {
            case "glpi_plugin_resources_managers.name" :
               $out = "";
               if (!empty($data['raw']["ITEM_".$num."_2"])) {
                  $out = getUserName($data['raw']["ITEM_".$num."_2"]);
               }
               return $out;
               break;
         }
         return "";
         break;
      case 'PluginResourcesEmployment':

         switch ($table.'.'.$field) {

            case "glpi_plugin_resources_resources.name" :
               $out = "";
               if (!empty($data['raw']["ITEM_".$num."_2"])) {
                  $user = PluginResourcesResource::getResourceName($data['raw']["ITEM_".$num."_2"], 2);
                  $out = "<a href='".$user['link']."'>";
                  $out.= $user["name"];
                  if ($_SESSION["glpiis_ids_visible"] || empty($user["name"])) {
                     $out.= " (".$data['raw']["ITEM_".$num."_2"].")";
                  }
                  $out.= "</a>";
               }
               return $out;
               break;
         }
         return "";
         break;
   }
   return "";
}

////// SPECIFIC MODIF MASSIVE FUNCTIONS ///////
function plugin_resources_MassiveActions($type) {

   if (in_array($type, PluginResourcesResource::getTypes())) {
      $resource = new PluginResourcesResource();
      return $resource->massiveActions($type);
   }
   return [];
}

// Do special actions for dynamic report
function plugin_resources_dynamicReport($parm) {
   $allowed = ['PluginResourcesDirectory', 'PluginResourcesRecap'];

   if (in_array($parm["item_type"], $allowed)) {
      $params = Search::manageParams($parm["item_type"], $parm);
      $data   = Search::prepareDatasForSearch($parm["item_type"], $params);
      PluginResourcesDirectory::constructSQL($data);
      Search::constructDatas($data);
      Search::displayDatas($data);
      return true;
   }

   return false;
}

// Hook done on before add item case
function plugin_pre_item_update_resources($item) {

   if (isset ($_SESSION['glpiactiveprofile'])
       &&!isset($item->input["_UpdateFromResource_"])) {
      $restrict = "`itemtype` = '".get_class($item)."'
               AND `items_id` = '".$item->getField('id')."'";
      $items = getAllDatasFromTable("glpi_plugin_resources_resources_items", $restrict);
      if (!empty($items)) {
         foreach ($items as $device) {
            $PluginResourcesResource = new PluginResourcesResource();
            $PluginResourcesResource->GetfromDB($device["plugin_resources_resources_id"]);
            if (isset($PluginResourcesResource->fields["locations_id"]) && isset($item->input["locations_id"])) {
               if ($item->input["locations_id"] != 0 && $PluginResourcesResource->fields["locations_id"] != $item->input["locations_id"]) {
                  $values = [];
                  $values["id"] = $device["plugin_resources_resources_id"];
                  $values["locations_id"] = $item->input["locations_id"];
                  $values["withtemplate"] = 0;
                  $values["_UpdateFromUser_"] = 1;
                  $PluginResourcesResource->update($values);
                  Session::addMessageAfterRedirect(__("Modification of the associated resource's location", "resources"), true);
               }
            }
         }
      }
   }
}

function plugin_datainjection_populate_resources() {
   global $INJECTABLE_TYPES;
   $INJECTABLE_TYPES['PluginResourcesResourceInjection']             = 'resources';
   $INJECTABLE_TYPES['PluginResourcesClientInjection']               = 'resources';
}
