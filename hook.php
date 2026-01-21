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

use Glpi\Search\Provider\SQLProvider;
use GlpiPlugin\Badges\Badge;
use GlpiPlugin\Resources\Adconfig;
use GlpiPlugin\Resources\Budget;
use GlpiPlugin\Resources\BudgetType;
use GlpiPlugin\Resources\BudgetVolume;
use GlpiPlugin\Resources\BusinessUnit;
use GlpiPlugin\Resources\Checklist;
use GlpiPlugin\Resources\Checklistconfig;
use GlpiPlugin\Resources\Choice;
use GlpiPlugin\Resources\ChoiceItem;
use GlpiPlugin\Resources\Client;
use GlpiPlugin\Resources\ClientInjection;
use GlpiPlugin\Resources\ContractNature;
use GlpiPlugin\Resources\ContractType;
use GlpiPlugin\Resources\Cost;
use GlpiPlugin\Resources\DegreeGroup;
use GlpiPlugin\Resources\Department;
use GlpiPlugin\Resources\Destination;
use GlpiPlugin\Resources\Directory;
use GlpiPlugin\Resources\Employee;
use GlpiPlugin\Resources\Employer;
use GlpiPlugin\Resources\Employment;
use GlpiPlugin\Resources\EmploymentState;
use GlpiPlugin\Resources\Habilitation;
use GlpiPlugin\Resources\HabilitationInjection;
use GlpiPlugin\Resources\HabilitationLevel;
use GlpiPlugin\Resources\ImportResource;
use GlpiPlugin\Resources\LeavingReason;
use GlpiPlugin\Resources\LinkAd;
use GlpiPlugin\Resources\NotificationTargetResource;
use GlpiPlugin\Resources\Profession;
use GlpiPlugin\Resources\ProfessionCategory;
use GlpiPlugin\Resources\ProfessionLine;
use GlpiPlugin\Resources\Profile;
use GlpiPlugin\Resources\Rank;
use GlpiPlugin\Resources\Recap;
use GlpiPlugin\Resources\RecruitingSource;
use GlpiPlugin\Resources\ResignationReason;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Resource_Item;
use GlpiPlugin\Resources\ResourceFunction;
use GlpiPlugin\Resources\ResourceHoliday;
use GlpiPlugin\Resources\ResourceInjection;
use GlpiPlugin\Resources\ResourceResting;
use GlpiPlugin\Resources\ResourceSituation;
use GlpiPlugin\Resources\ResourceSpeciality;
use GlpiPlugin\Resources\ResourceState;
use GlpiPlugin\Resources\Role;
use GlpiPlugin\Resources\RuleChecklist;
use GlpiPlugin\Resources\RuleContracttype;
use GlpiPlugin\Resources\RuleContracttypeHidden;
use GlpiPlugin\Resources\Service;
use GlpiPlugin\Resources\Task;
use GlpiPlugin\Resources\TaskPlanning;
use GlpiPlugin\Resources\TaskType;
use GlpiPlugin\Resources\Team;
use GlpiPlugin\Resources\WorkProfile;

use function Safe\mkdir;

/**
 * @return bool
 */
function plugin_resources_install()
{
    global $DB;

    //    foreach (glob(PLUGIN_RESOURCES_DIR . '/src/*.php') as $file) {
    //        if (!preg_match('/Resourceinjection/', $file)
    //            && !preg_match('/Clientinjection/', $file)
    //            && !preg_match('/Habilitationinjection/', $file)
    //            && !preg_match('/Resourcepdf/', $file)
    //            && !preg_match('/Datecriteria/', $file)) {
    //            include_once($file);
    //        }
    //    }

    $update = false;
    $update78 = false;
    $update80 = false;
    $update171 = false;
    $dbu = new DbUtils();
    $install = false;

    if (!$DB->tableExists("glpi_plugin_resources_resources")
        && !$DB->tableExists("glpi_plugin_resources_employments")) {
        $install = true;
        $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/empty-4.0.10.sql");

        $query = "INSERT INTO `glpi_plugin_resources_contracttypes` ( `id`, `name`, `entities_id`, `is_recursive`)
         VALUES (1, '" . __('Long term contract', 'resources') . "', 0, 1)";

        $DB->doQuery($query) or die($DB->error());

        $query = "INSERT INTO `glpi_plugin_resources_contracttypes` ( `id`, `name`, `entities_id`, `is_recursive`)
               VALUES (2, '" . __('Fixed term contract', 'resources') . "', 0, 1)";

        $DB->doQuery($query) or die($DB->error());

        $query = "INSERT INTO `glpi_plugin_resources_contracttypes` ( `id`, `name`, `entities_id`, `is_recursive`)
               VALUES (3, '" . __('Trainee', 'resources') . "', 0, 1)";

        $DB->doQuery($query) or die($DB->error());

        // Add record notification
        //        include_once(PLUGIN_RESOURCES_DIR . "/inc/notificationtargetresource.class.php");
        call_user_func([NotificationTargetResource::class, 'install']);
    } else {
        if ($DB->tableExists("glpi_plugin_resources")
            && !$DB->tableExists("glpi_plugin_resources_employee")) {
            $update = true;
            $update78 = true;
            $update80 = true;
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.4.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.5.0.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.5.1.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.0.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.1.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.2.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.7.0.sql");
        } elseif ($DB->tableExists("glpi_plugin_resources")
            && $DB->tableExists("glpi_plugin_resources_profiles")
            && $DB->fieldExists("glpi_plugin_resources_profiles", "interface")) {
            $update = true;
            $update78 = true;
            $update80 = true;
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.5.0.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.5.1.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.0.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.1.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.2.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.7.0.sql");
        } elseif ($DB->tableExists("glpi_plugin_resources")
            && !$DB->fieldExists("glpi_plugin_resources", "helpdesk_visible")) {
            $update = true;
            $update78 = true;
            $update80 = true;
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.5.1.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.0.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.1.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.2.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.7.0.sql");
        } elseif (!$DB->tableExists("glpi_plugin_resources_contracttypes")) {
            $update = true;
            $update78 = true;
            $update80 = true;
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.0.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.1.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.2.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.7.0.sql");
        } elseif ($DB->tableExists("glpi_plugin_resources_contracttypes")
            && !$DB->fieldExists("glpi_plugin_resources_resources", "picture")) {
            $update = true;
            $update80 = true;
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.1.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.2.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.7.0.sql");
        } elseif (!$DB->tableExists("glpi_plugin_resources_reportconfigs")) {
            $update = true;
            $update80 = true;
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.6.2.sql");
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.7.0.sql");
        } elseif (!$DB->tableExists("glpi_plugin_resources_checklistconfigs")) {
            $update80 = true;
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.7.0.sql");
        }

        if ($update78) {
            $profiles = $dbu->getAllDataFromTable("glpi_plugin_resources_profiles");

            if (!empty($profiles)) {
                foreach ($profiles as $profile) {
                    $query = "UPDATE `glpi_plugin_resources_profiles`
                  SET `profiles_id` = '" . $profile["id"] . "'
                  WHERE `id` = '" . $profile["id"] . "';";
                    $DB->doQuery($query);
                }
            }

            $query = "ALTER TABLE `glpi_plugin_resources_profiles`
               DROP `name` ;";
            $DB->doQuery($query);

            $tables = [
                "glpi_displaypreferences",
                "glpi_documents_items",
                "glpi_savedsearches",
                "glpi_logs",
                "glpi_items_tickets",
            ];

            foreach ($tables as $table) {
                $query = "DELETE FROM `$table` WHERE (`itemtype` = '4302' ) ";
                $DB->doQuery($query);
            }

            //            Plugin::migrateItemType(
            //                [
            //                    4300 => Resource::class,
            //                    4301 => Task::class,
            //                    4303 => Directory::class
            //                ],
            //                [
            //                    "glpi_savedsearches",
            //                    "glpi_savedsearches_users",
            //                    "glpi_displaypreferences",
            //                    "glpi_documents_items",
            //                    "glpi_infocoms",
            //                    "glpi_logs",
            //                    "glpi_items_tickets"
            //                ],
            //                [
            //                    "glpi_plugin_resources_resources_items",
            //                    "glpi_plugin_resources_choices",
            //                    "glpi_plugin_resources_tasks_items"
            //                ]
            //            );
            //
            //            Plugin::migrateItemType(
            //                [1600 => Badge::class],
            //                [
            //                    "glpi_plugin_resources_resources_items",
            //                    "glpi_plugin_resources_choices",
            //                    "glpi_plugin_resources_tasks_items"
            //                ]
            //            );

            // Add record notification
            //            include_once(PLUGIN_RESOURCES_DIR . "/inc/notificationtargetresource.class.php");
            call_user_func([NotificationTargetResource::class, 'update78']);
        }

        if ($update80) {
            // Add record notification
            //            include_once(PLUGIN_RESOURCES_DIR . "/inc/notificationtargetresource.class.php");
            call_user_func([NotificationTargetResource::class, 'update80']);
        }

        //Version 1.7.1
        if (!$DB->tableExists("glpi_plugin_resources_choiceitems")) {
            $update171 = true;
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.7.1.sql");
        }

        //Version 1.9.0
        if (!$DB->tableExists("glpi_plugin_resources_employments")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.9.0.sql");

            $query = "SELECT * FROM `glpi_plugin_resources_employers`";
            $result = $DB->doQuery($query);
            if ($DB->numrows($result) > 0) {
                while ($data = $DB->fetchArray($result)) {
                    $queryUpdate = "UPDATE `glpi_plugin_resources_employers`
                            SET `completename`= '" . $data["name"] . "'
                            WHERE `id`= '" . $data["id"] . "'";
                    $DB->doQuery($queryUpdate) or die($DB->error());
                }
            }
        }

        //Version 1.9.1
        if ($DB->tableExists("glpi_plugin_resources_ranks") && !$DB->fieldExists(
                "glpi_plugin_resources_ranks",
                "begin_date"
            )) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-1.9.1.sql");
        }

        //Version 2.0.3
        if (!$DB->fieldExists("glpi_plugin_resources_reportconfigs", "send_report_notif")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.0.3.sql");

            // Add record notification
            //            include_once(PLUGIN_RESOURCES_DIR . "/inc/notificationtargetresource.class.php");
            call_user_func([NotificationTargetResource::class, 'update203']);
        }

        //Version 2.0.4
        if (!$DB->tableExists("glpi_plugin_resources_transferentities")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.0.4.sql");

            // Add record notification
            //            include_once(PLUGIN_RESOURCES_DIR . "/inc/notificationtargetresource.class.php");
            call_user_func([NotificationTargetResource::class, 'update204']);
        }

        //Version 2.3.1
        if (!$DB->tableExists("glpi_plugin_resources_resources_changes") && !$DB->tableExists(
                "glpi_plugin_resources_resourcebadges"
            )) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.3.1.sql");

            // Add record notification
            //            include_once(PLUGIN_RESOURCES_DIR . "/inc/notificationtargetresource.class.php");
            call_user_func([NotificationTargetResource::class, 'update231']);
        }

        //Version 2.3.2
        if (!$DB->tableExists("glpi_plugin_resources_configs")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.3.2.sql");

            include(PLUGIN_RESOURCES_DIR . "/install/update_231_232.php");
            update231_232();
        }

        //Version 2.3.3
        if (!$DB->fieldExists("glpi_plugin_resources_configs", "security_compliance")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.3.3.sql");
        }

        //Version 2.4.4
        if (!$DB->fieldExists("glpi_plugin_resources_contracttypes", "use_habilitation_wizard")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.4.4.sql");
        }

        //Version 2.6.1
        if (!$DB->tableExists("glpi_plugin_resources_imports")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.6.1.sql");
        }
        if (!$DB->fieldExists("glpi_plugin_resources_configs", "resource_manager")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.6.3.sql");
        }

        //Version 2.6.4
        if ($DB->fieldExists("glpi_plugin_resources_checklistconfigs", "is_deleted")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.6.4.sql");
        }
        //Version 2.7.1
        if (!$DB->tableExists("glpi_plugin_resources_adconfigs")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.7.1.sql");
        }
        //Version 2.7.2
        if (!$DB->fieldExists("glpi_plugin_resources_configs", "mandatory_checklist")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.7.2.sql");
        }
        //Version 2.7.3
        if (!$DB->fieldExists("glpi_plugin_resources_resources", "gender")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-2.7.3.sql");
        }

        //Version 3.0.0
        if (!$DB->fieldExists("glpi_plugin_resources_configs", "use_service_department_ad")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-3.0.0.sql");
        }
        if ($DB->tableExists("glpi_plugin_resources_teams")
            && !$DB->fieldExists("glpi_plugin_resources_teams", "users_id")) {
            $query = "ALTER TABLE `glpi_plugin_resources_teams` ADD `users_id` INT(11) NOT NULL DEFAULT '0' AFTER `comment`;";
            $DB->doQuery($query) or die($DB->error());
            $query = "ALTER TABLE `glpi_plugin_resources_teams` ADD `users_id_substitute` INT(11) NOT NULL DEFAULT '0';";
            $DB->doQuery($query) or die($DB->error());
        }
        if ($DB->tableExists("glpi_plugin_resources_teams")
            && !$DB->fieldExists("glpi_plugin_resources_teams", "code")) {
            $query = "ALTER TABLE `glpi_plugin_resources_teams` ADD   `code` varchar(255) collate utf8_unicode_ci default NULL;";
            $DB->doQuery($query) or die($DB->error());
        }
        if (!$DB->tableExists("glpi_plugin_resources_degreegroups")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-3.0.4.sql");
        }

        if (!$DB->fieldExists("glpi_plugin_resources_resources", "date_of_last_contract_type")) {
            $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-3.0.5.sql");
        }

        if ($update80) {
            $restrict = ["plugin_resources_resources_id" => -1];

            $checklists = $dbu->getAllDataFromTable("glpi_plugin_resources_checklists", $restrict);
            $Checklistconfig = new Checklistconfig();
            if (!empty($checklists)) {
                foreach ($checklists as $checklist) {
                    $values["name"] = addslashes($checklist["name"]);
                    $values["address"] = addslashes($checklist["address"]);
                    $values["comment"] = addslashes($checklist["comment"]);
                    $values["tag"] = $checklist["tag"];
                    $values["entities_id"] = $checklist["entities_id"];
                    $Checklistconfig->add($values);
                }
            }

            $query = "DELETE FROM `glpi_plugin_resources_checklists`
               WHERE `plugin_resources_resources_id` ='-1'
                  OR `plugin_resources_resources_id` ='0';";
            $DB->doQuery($query);

            // Put realtime in seconds
            if ($DB->fieldExists('glpi_plugin_resources_tasks', 'realtime')) {
                $query = "ALTER TABLE `glpi_plugin_resources_tasks`
            ADD `actiontime` INT( 11 ) NOT NULL DEFAULT 0 ;";
                $DB->doQuery($query, "0.80 Add actiontime in glpi_plugin_resources_tasks");

                $query = "UPDATE `glpi_plugin_resources_tasks`
                   SET `actiontime` = ROUND(realtime * 3600)";
                $DB->doQuery($query, "0.80 Compute actiontime value in glpi_plugin_resources_tasks");

                $query = "ALTER TABLE `glpi_plugin_resources_tasks`
            DROP `realtime` ;";
                $DB->doQuery($query, "0.80 DROP realtime in glpi_plugin_resources_tasks");
            }

            // ADD plannings for tasks
            $dbu = new DbUtils();
            $tasks = $dbu->getAllDataFromTable("glpi_plugin_resources_tasks");
            if (!empty($tasks)) {
                foreach ($tasks as $task) {
                    $query = "INSERT INTO `glpi_plugin_resources_taskplannings`
               ( `id` , `plugin_resources_tasks_id` , `begin` , `end` )
               VALUES (NULL , '" . $task["id"] . "', '" . $task["date_begin"] . "', '" . $task["date_end"] . "') ;";
                    $DB->doQuery($query);
                }
            }

            unset($input);

            $query = "ALTER TABLE `glpi_plugin_resources_tasks`
               DROP `date_begin`, DROP `date_end` ;";
            $DB->doQuery($query, "0.80 Drop date_begin and date_end in glpi_plugin_resources_tasks");

            // ADD tasks
            $Resource = new Resource();
            $dbu = new DbUtils();
            $taches = $dbu->getAllDataFromTable("glpi_plugin_resources_tasks");
            if (!empty($taches)) {
                foreach ($taches as $tache) {
                    $Resource->getFromDB($tache["plugin_resources_resources_id"]);
                    $input["entities_id"] = $Resource->fields["entities_id"];
                    $query = "UPDATE `glpi_plugin_resources_tasks`
               SET `entities_id` =  '" . $Resource->fields["entities_id"] . "' WHERE `id` = '" . $tache["id"] . "' ;";
                    $DB->doQuery($query);
                }
            }
        }

        if ($install || $update80) {
            $restrict = ["itemtype" => Resource::class];
            $unicities = $dbu->getAllDataFromTable("glpi_fieldunicities", $restrict);
            if (empty($unicities)) {
                $query = "INSERT INTO `glpi_fieldunicities`"
                    . "VALUES (NULL, 'Resources creation', 1, '" . Resource::class . "', '0',
                                             'name,firstname','1',
                                             '1', '1', '',NOW(),NOW());";
                $DB->doQuery($query, " 0.80 Create fieldunicities check");
            }
        }

        if ($update171) {
            $query = "SELECT * FROM `glpi_plugin_resources_choices`
      WHERE `itemtype`!= '' GROUP BY `comment`,`itemtype`";
            $result = $DB->doQuery($query);
            $number = $DB->numrows($result);

            $affectedchoices = [];

            if (!empty($number)) {
                while ($data = $DB->fetchAssoc($result)) {
                    $restrictaffected = [
                        "itemtype" => $data['raw']["ITEMtype"],
                        "comment" => addslashes($data["comment"]),
                    ];
                    $affected = $dbu->getAllDataFromTable("glpi_plugin_resources_choices", $restrictaffected);

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
                    $choice = new Choice();
                    $choice_item = new ChoiceItem();

                    $types = [
                        __('Computer') => Computer::class,
                        __('Monitor') => Monitor::class,
                        __('Software') => Software::class,
                        __('Network device') => NetworkEquipment::class,
                        __('Printer') => Printer::class,
                        __('Peripheral') => Peripheral::class,
                        __('Phone') => Phone::class,
                        __('Consumable model') => ConsumableItem::class,
                        __('Specific network rights', 'resources') => '4303',
                        __('Access to the applications', 'resources') => '4304',
                        __('Specific securities groups', 'resources') => '4305',
                        __('Specific distribution lists', 'resources') => '4306',
                        __('Others needs', 'resources') => '4307',
                        Badge::getTypeName(1) => Badge::class,
                    ];

                    if ($choice->getFromDB($key)) {
                        $key = array_search($choice->fields["itemtype"], $types);
                        if ($key) {
                            $name = $key;
                        } else {
                            $name = $choice->fields["itemtype"];
                        }
                        $valuesparent["name"] = $i . "." . $name;
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
                           SET `plugin_resources_choiceitems_id` = '" . $newidchild . "'
                          WHERE `plugin_resources_resources_id` = '" . $val . "'
                          AND `itemtype` = '" . $choice->fields["itemtype"] . "'
                           AND `comment` = '" . addslashes($choice->fields["comment"]) . "';";
                            $result = $DB->doQuery($query);
                        }
                    }
                }
            }

            $query = "ALTER TABLE `glpi_plugin_resources_choices`
   DROP `itemtype`,
   DROP `comment`,
   ADD UNIQUE KEY `unicity` (`plugin_resources_resources_id`,`plugin_resources_choiceitems_id`);";
            $DB->doQuery($query);

            $query = "ALTER TABLE `glpi_plugin_resources_choices`
   ADD `comment` text collate utf8_unicode_ci;";
            $DB->doQuery($query);
        }

        //0.83 - Drop Matricule
        if ($DB->tableExists("glpi_plugin_resources_employees") && $DB->fieldExists(
                "glpi_plugin_resources_employees",
                "matricule"
            )) {
            $query = "SELECT * FROM `glpi_users`";
            $result = $DB->doQuery($query);
            $number = $DB->numrows($result);

            if (!empty($number)) {
                while ($data = $DB->fetchAssoc($result)) {
                    $restrict = [
                        "items_id" => $data["id"],
                        "itemtype" => 'User',
                    ];
                    $links = $dbu->getAllDataFromTable("glpi_plugin_resources_resources_items", $restrict);

                    if (!empty($links)) {
                        foreach ($links as $link) {
                            $employee = new Employee();
                            if ($employee->getFromDBByCrit(
                                ['plugin_resources_resources_id' => $link['plugin_resources_resources_id']]
                            )) {
                                $matricule = $employee->fields["matricule"];

                                if (isset($matricule) && !empty($matricule)) {
                                    $query = "UPDATE `glpi_users`
                           SET `registration_number` = '" . $matricule . "'
                           WHERE `id` ='" . $link["items_id"] . "'";
                                    $DB->doQuery($query);
                                }
                            }
                        }
                    }
                }
            }

            $query = "ALTER TABLE `glpi_plugin_resources_employees`
               DROP `matricule` ;";
            $DB->doQuery($query);
        }

        if ($DB->tableExists("glpi_plugin_resources_profiles")) {
            $notepad_tables = ['glpi_plugin_resources_resources'];
            $dbu = new DbUtils();
            foreach ($notepad_tables as $t) {
                // Migrate data
                if ($DB->fieldExists($t, 'notepad')) {
                    $iterator = $DB->request([
                        'SELECT' => [
                            'notepad',
                            'id',
                        ],
                        'FROM' => $t,
                        'WHERE' => [
                            'NOT' => ['notepad' => null],
                            'notepad' => ['<>', ''],
                        ],
                    ]);
                    if (count($iterator) > 0) {
                        foreach ($iterator as $data) {
                            $iq = "INSERT INTO `glpi_notepads`
                             (`itemtype`, `items_id`, `content`, `date`, `date_mod`)
                      VALUES ('" . $dbu->getItemTypeForTable($t) . "', '" . $data['id'] . "',
                              '" . addslashes($data['notepad']) . "', NOW(), NOW())";
                            $DB->doQuery($iq, "0.85 migrate notepad data");
                        }
                    }
                    $query = "ALTER TABLE `glpi_plugin_resources_resources` DROP COLUMN `notepad`;";
                    $DB->doQuery($query);
                }
            }
        }
    }

    if (!$DB->fieldExists("glpi_plugin_resources_adconfigs", "fonctionAD")) {
        $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-3.0.8.sql");
    }

    //Version 4.0.0
    if (!$DB->tableExists("glpi_plugin_resources_resourcefunctions")) {
        $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-4.0.0.sql");
    }
    //Version 4.0.2
    $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-4.0.2.sql");

    //Version 4.0.3
    $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-4.0.3.sql");

    if ($DB->fieldExists("glpi_plugin_resources_configs", "import_external_datas")) {
        $query = "ALTER TABLE `glpi_plugin_resources_configs` DROP `import_external_datas`;";
        $DB->doQuery($query);
    }
    //Version 4.0.4
    //DisplayPreferences Migration
    $classes = ['PluginResourcesResource' => Resource::class,
        'GlpiPlugin\\Resources\\Resources' => Resource::class,
        'PluginResourcesTask' => Task::class,
        'PluginResourcesResourceResting' => ResourceResting::class,
        'PluginResourcesResourceHoliday' => ResourceHoliday::class,
        'PluginResourcesChecklistconfig' => Checklistconfig::class,
        'PluginResourcesContractType' => ContractType::class,
        'PluginResourcesHabilitation' => Habilitation::class,
        'PluginResourcesChecklist' => Checklist::class,
        'PluginResourcesResignationReason' => ResignationReason::class,
        'PluginResourcesTeam' => Team::class,
        'PluginResourcesChoiceItem' => ChoiceItem::class,
        'PluginResourcesEmployment' => Employment::class,
        'PluginResourcesBudget' => Budget::class,
        'PluginResourcesDirectory' => Directory::class,
        'PluginResourcesRecap' => Recap::class,
        'PluginResourcesClient' => Client::class];

    foreach ($classes as $old => $new) {
        $displayusers = $DB->request([
            'SELECT' => [
                'users_id'
            ],
            'DISTINCT' => true,
            'FROM' => 'glpi_displaypreferences',
            'WHERE' => [
                'itemtype' => $old,
            ],
        ]);

        if (count($displayusers) > 0) {
            foreach ($displayusers as $displayuser) {
                $iterator = $DB->request([
                    'SELECT' => [
                        'num',
                        'id'
                    ],
                    'FROM' => 'glpi_displaypreferences',
                    'WHERE' => [
                        'itemtype' => $old,
                        'users_id' => $displayuser['users_id'],
                        'interface' => 'central'
                    ],
                ]);

                if (count($iterator) > 0) {
                    foreach ($iterator as $data) {
                        $iterator2 = $DB->request([
                            'SELECT' => [
                                'id'
                            ],
                            'FROM' => 'glpi_displaypreferences',
                            'WHERE' => [
                                'itemtype' => $new,
                                'users_id' => $displayuser['users_id'],
                                'num' => $data['num'],
                                'interface' => 'central'
                            ],
                        ]);
                        if (count($iterator2) > 0) {
                            foreach ($iterator2 as $dataid) {
                                $query = $DB->buildDelete(
                                    'glpi_displaypreferences',
                                    [
                                        'id' => $dataid['id'],
                                    ]
                                );
                                $DB->doQuery($query);
                            }
                        } else {
                            $query = $DB->buildUpdate(
                                'glpi_displaypreferences',
                                [
                                    'itemtype' => $new,
                                ],
                                [
                                    'id' => $data['id'],
                                ]
                            );
                            $DB->doQuery($query);
                        }
                    }
                }
            }
        }
    }

    $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-4.0.6.sql");

    //DisplayPreferences Migration for helpdesk
    $classes = ['PluginResourcesResource' => Resource::class,
        'PluginResourcesDirectory' => Directory::class];

    foreach ($classes as $old => $new) {
        $iterator = $DB->request([
            'SELECT' => [
                'num',
                'rank'
            ],
            'FROM' => 'glpi_displaypreferences',
            'WHERE' => [
                'itemtype' => $new,
                'users_id' => 0,
                'interface' => 'central'
            ],
        ]);

        if (count($iterator) > 0) {
            foreach ($iterator as $data) {

                $fields = [
                    'num' => $data['num'],
                    'rank' => $data['rank'],
                    'itemtype' => $new,
                    'users_id' => 0,
                    'interface' => 'helpdesk'
                ];
                $check = $DB->request([
                    'SELECT' => [
                        'id',
                    ],
                    'FROM' => 'glpi_displaypreferences',
                    'WHERE' => [
                        'num' => $data['num'],
                        'itemtype' => $new,
                        'users_id' => 0,
                        'interface' => 'helpdesk'
                    ],
                ]);
                if ($check->count() == 0) {
                    $DB->insert(
                        "glpi_displaypreferences",
                        $fields
                    );
                }

            }
        }
    }

    $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-4.0.7.sql");

    $DB->runFile(PLUGIN_RESOURCES_DIR . "/install/sql/update-4.0.8.sql");

    $rep_files_resources = GLPI_PLUGIN_DOC_DIR . "/resources";
    if (!is_dir($rep_files_resources)) {
        mkdir($rep_files_resources);
    }

    if (!is_dir($rep_files_resources . "/pictures")) {
        mkdir($rep_files_resources . "/pictures");
    }
    if (!is_dir($rep_files_resources . "/import")) {
        mkdir($rep_files_resources . "/import");
    }
    if (!is_dir($rep_files_resources . "/import/done")) {
        mkdir($rep_files_resources . "/import/done");
    }
    if (!is_dir($rep_files_resources . "/import/fail")) {
        mkdir($rep_files_resources . "/import/fail");
    }
    if (!is_dir($rep_files_resources . "/import/verify")) {
        mkdir($rep_files_resources . "/import/verify");
    }

    CronTask::Register(Resource::class, 'Resources', DAY_TIMESTAMP);
    CronTask::Register(Task::class, 'ResourcesTask', DAY_TIMESTAMP);
    CronTask::Register(Checklist::class, 'ResourcesChecklist', DAY_TIMESTAMP);
    CronTask::Register(
        Employment::class,
        'ResourcesLeaving',
        DAY_TIMESTAMP,
        ['state' => CronTask::STATE_DISABLE]
    );
    CronTask::Register(
        Resource::class,
        'AlertCommercialManager',
        MONTH_TIMESTAMP,
        ['state' => CronTask::STATE_DISABLE]
    );
    CronTask::Register(
        ImportResource::class,
        'ResourceImport',
        MONTH_TIMESTAMP,
        ['state' => CronTask::STATE_DISABLE]
    );
    CronTask::Register(
        Resource::class,
        'UpdateResourcesState',
        DAY_TIMESTAMP,
        ['state' => CronTask::STATE_DISABLE]
    );

    Profile::initProfile();
    Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
    $migration = new Migration("2.3.0");
    $migration->dropTable('glpi_plugin_resources_profiles');
    return true;
}

/**
 * @return bool
 */
function plugin_resources_uninstall()
{
    global $DB;

    $tables = [
        "glpi_plugin_resources_resources",
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
        "glpi_plugin_resources_habilitationlevels",
        "glpi_plugin_resources_imports",
        "glpi_plugin_resources_importcolumns",
        "glpi_plugin_resources_importresourcedatas",
        "glpi_plugin_resources_importresources",
        "glpi_plugin_resources_resourceimports",
        "glpi_plugin_resources_adconfigs",
        "glpi_plugin_resources_roles",
        "glpi_plugin_resources_resourcefunctions",
        "glpi_plugin_resources_teams",
        "glpi_plugin_resources_services",
        "glpi_plugin_resources_roles_services",
        "glpi_plugin_resources_departments_services",
        "glpi_plugin_resources_linkads",
        "glpi_plugin_resources_linkmetademands",
        "glpi_plugin_resources_contracttypeprofiles",
        "glpi_plugin_resources_actionprofiles",
        "glpi_plugin_resources_businessunits",
        "glpi_plugin_resources_degreegroups",
        "glpi_plugin_resources_recruitingsources",
        "glpi_plugin_resources_destinations",
        "glpi_plugin_resources_resignationreasons",
        "glpi_plugin_resources_leavingdetails",
        "glpi_plugin_resources_workprofiles",
        "glpi_plugin_resources_leavinginformations",
        'glpi_plugin_resources_candidateorigins',
    ];

    foreach ($tables as $table) {
        $DB->dropTable($table, true);
    }

    //old versions
    $tables = [
        "glpi_plugin_resources",
        "glpi_plugin_resources_device",
        "glpi_plugin_resources_needs",
        "glpi_plugin_resources_employee",
        "glpi_dropdown_plugin_resources_employer",
        "glpi_dropdown_plugin_resources_client",
        "glpi_dropdown_plugin_resources_type",
        "glpi_dropdown_plugin_resources_department",
        "glpi_dropdown_plugin_resources_tasks_type",
        "glpi_plugin_resources_mailingsettings",
        "glpi_plugin_resources_mailing",
    ];

    foreach ($tables as $table) {
        $DB->dropTable($table, true);
    }

    $itemtypes = [
        'Alert',
        'DisplayPreference',
        'Document_Item',
        'ImpactItem',
        'Item_Ticket',
        'Link_Itemtype',
        'Notepad',
        'SavedSearch',
        'DropdownTranslation',
        'NotificationTemplate',
        'Notification'
    ];
    foreach ($itemtypes as $itemtype) {
        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => Checklistconfig::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => Directory::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => ChoiceItem::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => Employment::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => Budget::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => Recap::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => Client::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => Habilitation::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => ContractType::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => Team::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => ResignationReason::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => Resource::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => ResourceHoliday::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => ResourceResting::class]);

        $item = new $itemtype();
        $item->deleteByCriteria(['itemtype' => Task::class]);
    }

    $tables = [
        "glpi_fieldunicities",
    ];

    foreach ($tables as $table) {
        $DB->doQuery(
            "DELETE
                  FROM `$table`
                  WHERE `name` LIKE 'GlpiPlugin\\Resources%'"
        );
    }

    //drop rules
    $Rule = new Rule();
    $a_rules = $Rule->find(['sub_type' => RuleChecklist::class]);
    foreach ($a_rules as $data) {
        $Rule->delete($data);
    }

    $Rule = new Rule();
    $a_rules = $Rule->find(['sub_type' => RuleContracttype::class]);
    foreach ($a_rules as $data) {
        $Rule->delete($data);
    }
    $Rule = new Rule();
    $a_rules = $Rule->find(['sub_type' => RuleContracttypeHidden::class]);
    foreach ($a_rules as $data) {
        $Rule->delete($data);
    }

    $notif = new Notification();

    $options = [
        'itemtype' => Resource::class,
    ];
    foreach (
        $DB->request([
            'FROM' => 'glpi_notifications',
            'WHERE' => $options,
        ]) as $data
    ) {
        $notif->delete($data);
    }

    //templates
    $template = new NotificationTemplate();
    $translation = new NotificationTemplateTranslation();
    $notif_template = new Notification_NotificationTemplate();
    $options = [
        'itemtype' => Resource::class,
    ];
    foreach (
        $DB->request([
            'FROM' => 'glpi_notificationtemplates',
            'WHERE' => $options,
        ]) as $data
    ) {
        $options_template = [
            'notificationtemplates_id' => $data['id'],
        ];

        foreach (
            $DB->request([
                'FROM' => 'glpi_notificationtemplatetranslations',
                'WHERE' => $options_template,
            ]) as $data_template
        ) {
            $translation->delete($data_template);
        }
        $template->delete($data);

        foreach (
            $DB->request([
                'FROM' => 'glpi_notifications_notificationtemplates',
                'WHERE' => $options_template,
            ]) as $data_template
        ) {
            $notif_template->delete($data_template);
        }
    }

    if (class_exists(PluginDatainjectionModel::class)) {
        PluginDatainjectionModel::clean(['itemtype' => Resource::class]);
        PluginDatainjectionModel::clean(['itemtype' => Client::class]);
    }

    $rep_files_resources = GLPI_PLUGIN_DOC_DIR . "/resources";
    Toolbox::deleteDir($rep_files_resources);

    Profile::removeRightsFromSession();

    return true;
}

function plugin_resources_postinit()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['pre_item_update']['resources'] = ['User' => 'plugin_pre_item_update_resources'];
    $PLUGIN_HOOKS['pre_item_add']['resources'] = ['ITILSolution' => 'plugin_pre_item_add_solutions'];
    $PLUGIN_HOOKS['item_purge']['resources'] = [];

    foreach (Resource::getTypes(true) as $type) {
        $PLUGIN_HOOKS['item_purge']['resources'][$type]
            = [Resource_Item::class, 'cleanForItem'];

        CommonGLPI::registerStandardTab($type, Resource_Item::class);
    }

    CommonGLPI::registerStandardTab("Central", Task::class);
}

/**
 * @param $types
 *
 * @return mixed
 */
function plugin_resources_AssignToTicket($types)
{
    if (Session::haveRight("plugin_resources_open_ticket", 1)) {
        $types[Resource::class] = Resource::getTypeName(2);
    }

    return $types;
}

// Define dropdown relations
/**
 * @return array
 */
function plugin_resources_getDatabaseRelations()
{
    if (Plugin::isPluginActive("resources")) {
        return [
            "glpi_entities" => [
                "glpi_plugin_resources_resources" => "entities_id",
                "glpi_plugin_resources_resourcestates" => "entities_id",
                "glpi_plugin_resources_choiceitems" => "entities_id",
                "glpi_plugin_resources_employers" => "entities_id",
                "glpi_plugin_resources_clients" => "entities_id",
                "glpi_plugin_resources_contracttypes" => "entities_id",
                "glpi_plugin_resources_departments" => "entities_id",
                "glpi_plugin_resources_tasks" => "entities_id",
                "glpi_plugin_resources_tasktypes" => "entities_id",
                "glpi_plugin_resources_checklists" => "entities_id",
                "glpi_plugin_resources_checklistconfigs" => "entities_id",
                "glpi_plugin_resources_resourcesituations" => "entities_id",
                "glpi_plugin_resources_contractnatures" => "entities_id",
                "glpi_plugin_resources_ranks" => "entities_id",
                "glpi_plugin_resources_resourcespecialities" => "entities_id",
                "glpi_plugin_resources_leavingreasons" => "entities_id",
                "glpi_plugin_resources_professions" => "entities_id",
                "glpi_plugin_resources_professionlines" => "entities_id",
                "glpi_plugin_resources_professioncategories" => "entities_id",
                "glpi_plugin_resources_employments" => "entities_id",
                "glpi_plugin_resources_employmentstates" => "entities_id",
                "glpi_plugin_resources_budgets" => "entities_id",
                "glpi_plugin_resources_costs" => "entities_id",
                "glpi_plugin_resources_budgettypes" => "entities_id",
                "glpi_plugin_resources_budgetvolumes" => "entities_id",
                "glpi_plugin_resources_transferentities" => "entities_id",
            ],
            "glpi_plugin_resources_contracttypes" => [
                "glpi_plugin_resources_resources" => "plugin_resources_contracttypes_id",
                "glpi_plugin_resources_checklists" => "plugin_resources_contracttypes_id",
            ],
            "glpi_users" => [
                "glpi_plugin_resources_resources" => [
                    'users_id',
                    'users_id_recipient',
                    'users_id_recipient_leaving',
                    'users_id_sales',
                ],
                "glpi_plugin_resources_tasks" => "users_id",
            ],
            "glpi_plugin_resources_departments" => ["glpi_plugin_resources_resources" => "plugin_resources_departments_id"],
            "glpi_plugin_resources_habilitations" => ["glpi_plugin_resources_resourcehabilitations" => "plugin_resources_habilitations_id"],
            "glpi_plugin_resources_resourcestates" => ["glpi_plugin_resources_resources" => "plugin_resources_resourcestates_id"],
            "glpi_plugin_resources_resourcesituations" => ["glpi_plugin_resources_resources" => "plugin_resources_resourcesituations_id"],
            "glpi_plugin_resources_contractnatures" => ["glpi_plugin_resources_resources" => "plugin_resources_contractnatures_id"],
            "glpi_plugin_resources_ranks" => ["glpi_plugin_resources_resources" => "plugin_resources_ranks_id"],
            "glpi_plugin_resources_resourcespecialities" => ["glpi_plugin_resources_resources" => "plugin_resources_resourcespecialities_id"],
            "glpi_locations" => [
                "glpi_plugin_resources_resources" => "locations_id",
                "glpi_plugin_resources_employers" => "locations_id",
                "glpi_plugin_resources_resourcerestings" => "locations_id",
            ],
            "glpi_plugin_resources_leavingreasons" => ["glpi_plugin_resources_resources" => "plugin_resources_leavingreasons_id"],
            "glpi_plugin_resources_resources" => [
                "glpi_plugin_resources_choices" => "plugin_resources_resources_id",
                "glpi_plugin_resources_resources_items" => "plugin_resources_resources_id",
                "glpi_plugin_resources_employees" => "plugin_resources_resources_id",
                "glpi_plugin_resources_tasks" => "plugin_resources_resources_id",
                "glpi_plugin_resources_checklists" => "plugin_resources_resources_id",
                "glpi_plugin_resources_reportconfigs" => "plugin_resources_resources_id",
                "glpi_plugin_resources_resourcerestings" => "plugin_resources_resources_id",
                "glpi_plugin_resources_resourceholidays" => "plugin_resources_resources_id",
                "glpi_plugin_resources_employments" => "plugin_resources_resources_id",
            ],
            "glpi_plugin_resources_choiceitems" => [
                "glpi_plugin_resources_choices" => "plugin_resources_choiceitems_id",
                "glpi_plugin_resources_choiceitems" => "plugin_resources_choiceitems_id",
            ],
            "glpi_plugin_resources_employers" => [
                "glpi_plugin_resources_employees" => "plugin_resources_employers_id",
                "glpi_plugin_resources_employers" => "plugin_resources_employers_id",
                "glpi_plugin_resources_employments" => "plugin_resources_employers_id",
            ],
            "glpi_plugin_resources_clients" => ["glpi_plugin_resources_employees" => "plugin_resources_clients_id"],
            "glpi_plugin_resources_tasktypes" => ["glpi_plugin_resources_tasks" => "plugin_resources_tasktypes_id"],
            "glpi_groups" => ["glpi_plugin_resources_tasks" => "groups_id"],
            "glpi_plugin_resources_tasks" => [
                "glpi_plugin_resources_tasks_items" => "plugin_resources_tasks_id",
                "glpi_plugin_resources_checklists" => "plugin_resources_tasks_id",
                "glpi_plugin_resources_taskplannings" => "plugin_resources_tasks_id",
            ],
            //"glpi_itilcategories"                      => ["glpi_plugin_resources_ticketcategories" => "ticketcategories_id"],
            "glpi_plugin_resources_professions" => [
                "glpi_plugin_resources_ranks" => "plugin_resources_professions_id",
                "glpi_plugin_resources_employments" => "plugin_resources_professions_id",
                "glpi_plugin_resources_budgets" => "plugin_resources_professions_id",
                "glpi_plugin_resources_costs" => "plugin_resources_professions_id",
            ],
            "glpi_plugin_resources_ranks" => [
                "glpi_plugin_resources_resourcespecialities" => "plugin_resources_ranks_id",
                "glpi_plugin_resources_employments" => "plugin_resources_ranks_id",
                "glpi_plugin_resources_budgets" => "plugin_resources_ranks_id",
                "glpi_plugin_resources_costs" => "plugin_resources_ranks_id",
            ],
            "glpi_plugin_resources_professionlines" => ["glpi_plugin_resources_professions" => "plugin_resources_professionlines_id"],
            "glpi_plugin_resources_professioncategories" => ["glpi_plugin_resources_professions" => "plugin_resources_professioncategories_id"],
            "glpi_plugin_resources_employmentstates" => ["glpi_plugin_resources_employments" => "plugin_resources_employmentstates_id"],
            "glpi_plugin_resources_budgettypes" => ["glpi_plugin_resources_budgets" => "plugin_resources_budgettypes_id"],
            "glpi_plugin_resources_budgetvolumes" => ["glpi_plugin_resources_budgets" => "plugin_resources_budgetvolumes_id"],
            //            "glpi_plugin_resources_habilitationlevels" => ["glpi_plugin_resources_habilitations" => "plugin_resources_habilitationlevels_id"],
        ];
    } else {
        return [];
    }
}

// Define Dropdown tables to be manage in GLPI :
/**
 * @return array
 */
function plugin_resources_getDropdown()
{
    if (Plugin::isPluginActive("resources")) {
        return [
            ContractType::class => ContractType::getTypeName(2),
            TaskType::class => TaskType::getTypeName(2),
            ResourceState::class => Resource::getTypeName(
                    2
                ) . " - " . ResourceSituation::getTypeName(2),
            Department::class => Department::getTypeName(2),
            Employer::class => Employer::getTypeName(2),
            Client::class => Client::getTypeName(2),
            ChoiceItem::class => ChoiceItem::getTypeName(2),
            ResourceSituation::class => Employer::getTypeName(
                    2
                ) . " - " . ResourceSituation::getTypeName(2),
            ContractNature::class => ContractNature::getTypeName(2),
            Rank::class => Rank::getTypeName(2),
            ResourceSpeciality::class => ResourceSpeciality::getTypeName(2),
            LeavingReason::class => LeavingReason::getTypeName(2),
            Profession::class => Profession::getTypeName(2),
            ProfessionLine::class => ProfessionLine::getTypeName(2),
            ProfessionCategory::class => ProfessionCategory::getTypeName(2),
            EmploymentState::class => EmploymentState::getTypeName(2),
            BudgetType::class => BudgetType::getTypeName(2),
            BudgetVolume::class => BudgetVolume::getTypeName(2),
            Habilitation::class => Habilitation::getTypeName(2),
            HabilitationLevel::class => HabilitationLevel::getTypeName(2),
            Role::class => Role::getTypeName(2),
            ResourceFunction::class => ResourceFunction::getTypeName(2),
            Service::class => Service::getTypeName(2),
            Team::class => Team::getTypeName(2),
            Cost::class => Cost::getTypeName(2),
            BusinessUnit::class => BusinessUnit::getTypeName(2),
            DegreeGroup::class => DegreeGroup::getTypeName(2),
            RecruitingSource::class => RecruitingSource::getTypeName(2),
            Destination::class => Destination::getTypeName(2),
            ResignationReason::class => ResignationReason::getTypeName(2),
            WorkProfile::class => WorkProfile::getTypeName(2),

        ];
    } else {
        return [];
    }
}

////// SEARCH FUNCTIONS ///////() {

/**
 * @param $itemtype
 *
 * @return array
 */
function plugin_resources_getAddSearchOptions($itemtype)
{
    $sopt = [];

    if ($itemtype == "User") {
        if (Session::haveRight("plugin_resources", READ)) {
            $sopt[4311]['table'] = 'glpi_plugin_resources_contracttypes';
            $sopt[4311]['field'] = 'name';
            $sopt[4311]['name'] = Resource::getTypeName(
                    2
                ) . " - " . ContractType::getTypeName(1);

            $sopt[4313]['table'] = 'glpi_plugin_resources_resources';
            $sopt[4313]['field'] = 'date_begin';
            $sopt[4313]['name'] = Resource::getTypeName(2) . " - " . __('Begin date');
            $sopt[4313]['datatype'] = 'date';

            $sopt[4314]['table'] = 'glpi_plugin_resources_resources';
            $sopt[4314]['field'] = 'date_end';
            $sopt[4314]['name'] = Resource::getTypeName(2) . " - " . __('End date');
            $sopt[4314]['datatype'] = 'date';

            $sopt[4315]['table'] = 'glpi_plugin_resources_departments';
            $sopt[4315]['field'] = 'name';
            $sopt[4315]['name'] = Resource::getTypeName(
                    2
                ) . " - " . Department::getTypeName(1);

            $sopt[4316]['table'] = 'glpi_plugin_resources_resources';
            $sopt[4316]['field'] = 'date_declaration';
            $sopt[4316]['name'] = Resource::getTypeName(2) . " - " . __('Request date');
            $sopt[4316]['datatype'] = 'date';
            $sopt[4316]['massiveaction'] = false;

            $sopt[4317]['table'] = 'glpi_plugin_resources_locations';
            $sopt[4317]['field'] = 'completename';
            $sopt[4317]['name'] = Resource::getTypeName(2) . " - " . __('Location');
            $sopt[4317]['massiveaction'] = false;

            $sopt[4318]['table'] = 'glpi_plugin_resources_resources';
            $sopt[4318]['field'] = 'is_leaving';
            $sopt[4318]['name'] = Resource::getTypeName(2) . " - " . __(
                    'Declared as leaving',
                    'resources'
                );
            $sopt[4318]['datatype'] = 'bool';

            $sopt[4320]['table'] = 'glpi_plugin_resources_employers';
            $sopt[4320]['field'] = 'name';
            $sopt[4320]['name'] = Resource::getTypeName(2) . " - " . __('Employer', 'resources');

            $sopt[4321]['table'] = 'glpi_plugin_resources_clients';
            $sopt[4321]['field'] = 'name';
            $sopt[4321]['name'] = Resource::getTypeName(2) . " - " . __('Affected client', 'resources');

            $sopt[4322]['table'] = 'glpi_plugin_resources_managers';
            $sopt[4322]['field'] = 'name';
            $sopt[4322]['linkfield'] = 'users_id';
            $sopt[4322]['name'] = Resource::getTypeName(2) . " - " . __('Resource manager', 'resources');
            $sopt[4322]['massiveaction'] = false;

            $sopt[4323]['table'] = 'glpi_plugin_resources_recipients';
            $sopt[4323]['field'] = 'name';
            $sopt[4323]['linkfield'] = 'users_id_recipient';
            $sopt[4323]['name'] = Resource::getTypeName(2) . " - " . __('Recipient');
            $sopt[4323]['massiveaction'] = false;

            $sopt[4324]['table'] = 'glpi_plugin_resources_recipients_leaving';
            $sopt[4324]['field'] = 'name';
            $sopt[4324]['linkfield'] = 'users_id_recipient_leaving';
            $sopt[4324]['name'] = Resource::getTypeName(2) . " - " . __(
                    'Informant of leaving',
                    'resources'
                );
            $sopt[4324]['massiveaction'] = false;

            $sopt[4325]['table'] = 'glpi_plugin_resources_salemanagers';
            $sopt[4325]['field'] = 'name';
            $sopt[4325]['linkfield'] = 'users_id_sales';
            $sopt[4325]['name'] = Resource::getTypeName(2) . " - " . __('Sales manager', 'resources');
            $sopt[4325]['massiveaction'] = false;

            $sopt[4326]['table'] = 'glpi_plugin_resources_teams';
            $sopt[4326]['field'] = 'name';
            $sopt[4326]['name'] = Resource::getTypeName(2) . " - " . Team::getTypeName(1);
            $sopt[4326]['massiveaction'] = false;
        }
    } elseif ($itemtype == "Computer") {
        if (Session::haveRight("plugin_resources", READ)) {
            $sopt[4331]['table'] = 'glpi_plugin_resources_resources';
            $sopt[4331]['field'] = 'name';
            $sopt[4331]['datatype'] = 'itemlink';
            //         $sopt[4331]['forcegroupby'] = true;
            //         $sopt[4331]['usehaving'] = true;
            //         $sopt[4331]['joinparams'] =   [
            //            'jointype'           => 'items_id',
            //            'beforejoin'         => [
            //               'table'              => 'glpi_plugin_resources_resources_items',
            //               'joinparams'         => [
            //                  'jointype'           => 'itemtype_item'
            //               ]
            //            ]
            //         ];
            $sopt[4331]['name'] = Resource::getTypeName(
                    2
                ) . " - " . Resource::getTypeName(1);
        }
    }
    return $sopt;
}

/**
 * @param $type
 * @param $ID
 * @param $num
 *
 * @return string
 */
function plugin_resources_addSelect($type, $ID, $num)
{
    global $DB;

    $searchopt = Search::getOptions($type);
    $table = $searchopt[$ID]["table"];
    $field = $searchopt[$ID]["field"];

    $NAME = "ITEM_{$type}_{$ID}";
    // Example of standard Select clause but use it ONLY for specific Select
    // No need of the function if you do not have specific cases
    switch ($type) {
        case "Computer":
//            switch ($table . "." . $field) {
//                case "glpi_plugin_resources_resources.name":
//                    return " GROUP_CONCAT(DISTINCT CONCAT(IFNULL(`$table`.`id`, '__NULL__')) ORDER BY `$table`.`id` SEPARATOR '$$##$$') AS `ITEM_$num`, ";
//                    //return " GROUP_CONCAT(DISTINCT CONCAT(IFNULL(`$table`.`$field`, '__NULL__'), '$#$',`$table`.`id`) ORDER BY `$table`.`id` SEPARATOR '$$##$$') AS `ITEM_$num`, ";
//                    //return "`" . $table . "`.`" . $field . "` AS META_$num,`" . $table . "`.`" . $field . "` AS ITEM_$num, `" . $table . "`.`id` AS ITEM_" . $num . "_2, ";
//                    break;
//            }
            break;
        default:
            switch ($table . "." . $field) {
                case "glpi_plugin_resources_resources.name":
//                    return $DB::quoteName("$table.$field AS META_{$num}").",".
//                        $DB::quoteName("$table.$field AS ITEM_{$num}").",".
//                        $DB::quoteName("$table.id AS ITEM_{$num}_2");

//                    $SELECT = [
//                        $DB::quoteName("$table.$field AS META_{$num}"),
//                        $DB::quoteName("$table.$field AS ITEM_{$num}"),
//                        $DB::quoteName("$table.id AS ITEM_{$num}_2"),
//                    ];
//                    return $SELECT;

                    break;
                case "glpi_plugin_resources_managers.name":
                case "glpi_plugin_resources_recipients_leaving.name":
                case "glpi_plugin_resources_recipients.name":
                case "glpi_plugin_resources_salemanagers.name":

//                $SELECT = [
//                    $DB::quoteName("$table.$field AS ITEM_{$num}"),
//                    $DB::quoteName("$table.id AS ITEM_{$num}_2"),
//                    $DB::quoteName("$table.firstname AS ITEM_{$num}_3"),
//                    $DB::quoteName("$table.realname AS ITEM_{$num}_4"),
//                ];

//                return $SELECT;

                    return $DB::quoteName("$table.$field AS ITEM_{$num}") . "," .
                        $DB::quoteName("$table.id AS ITEM_{$num}_2") . "," .
                        $DB::quoteName("$table.firstname AS ITEM_{$num}_3") . "," .
                        $DB::quoteName("$table.realname AS ITEM_{$num}_4");

                    break;
            }
            break;
    }

    return "";
}

/**
 * @param $type
 * @param $ref_table
 * @param $already_link_tables
 *
 * @return array
 */
function plugin_resources_addDefaultJoin($type, $ref_table, &$already_link_tables)
{
    switch ($type) {
        case Directory::class:
        case Recap::class:
            $out['LEFT JOIN'] = [
                'glpi_plugin_resources_resources_items' => [
                    'ON' => [
                        'glpi_users' => 'id',
                        'glpi_plugin_resources_resources_items' => 'items_id',
                        [
                            'AND' => [
                                'glpi_plugin_resources_resources_items.itemtype' => 'User',
                            ],
                        ],
                    ],
                ],
                'glpi_plugin_resources_resources' => [
                    'ON' => [
                        'glpi_plugin_resources_resources' => 'id',
                        'glpi_plugin_resources_resources_items' => 'plugin_resources_resources_id',
                    ],
                ],
            ];
            return $out;
    }
    return [];
}

/**
 * @param $type
 *
 * @return int[]
 */
function plugin_resources_addDefaultWhere($type)
{
    // Example of default WHERE item to be added
    // No need of the function if you do not have specific cases
    switch ($type) {
        case Directory::class:
        case Recap::class:

            $criteria = [
                'glpi_plugin_resources_resources.is_leaving' => 0,
                'glpi_users.is_active' => 1
            ];

            return $criteria;

        case Resource::class:
            $who = Session::getLoginUserID();
            if (!Session::haveRight("plugin_resources_all", READ)) {
                $criteria = [
                    'OR' => [
                        'glpi_plugin_resources_resources.users_id_recipient' => $who,
                        'glpi_plugin_resources_resources.users_id' => $who
                    ],
                ];

                return $criteria;
            }
            break;
    }
    return [];
}

/**
 * @param $link
 * @param $nott
 * @param $type
 * @param $ID
 * @param $val
 *
 * @return string
 */
function plugin_resources_addWhere($link, $nott, $type, $ID, $val)
{
    $searchopt = Search::getOptions($type);
    $table = $searchopt[$ID]["table"];
    $field = $searchopt[$ID]["field"];

    $SEARCH = Search::makeTextSearch($val, $nott);

    switch ($table . "." . $field) {
        case "glpi_plugin_resources_managers.name":
        case "glpi_plugin_resources_recipients_leaving.name":
        case "glpi_plugin_resources_recipients.name":
        case "glpi_plugin_resources_salemanagers.name":
            $ADD = " OR `" . $table . "`.`firstname` LIKE '%" . $val . "%' OR `" . $table . "`.`realname` LIKE '%" . $val . "%' ";
            if ($nott && $val != "NULL") {
                $ADD = " OR `$table`.`$field` IS NULL";
            }
            return $link . " (`$table`.`$field` $SEARCH " . $ADD . " ) ";
    }
    return "";
}

/**
 * @param $type
 * @param $ref_table
 * @param $new_table
 * @param $linkfield
 * @param $already_link_tables
 *
 * @return array
 */
function plugin_resources_addLeftJoin($type, $ref_table, $new_table, $linkfield, &$already_link_tables)
{
    // Rename table for meta left join
    $AS = "";
    $AS_device = "";
    $nt = "glpi_plugin_resources_resources";
    $nt_device = "glpi_plugin_resources_resources_items";
    // Multiple link possibilies case
    if ($new_table == "glpi_plugin_resources_locations"
        || $new_table == "glpi_plugin_resources_managers"
        || $new_table == "glpi_plugin_resources_salemanagers"
        || $new_table == "glpi_plugin_resources_recipients"
        || $new_table == "glpi_plugin_resources_teams"
        || $new_table == "glpi_plugin_resources_contracttypes"
        || $new_table == "glpi_plugin_resources_recipients_leaving") {
        $AS = " AS glpi_plugin_resources_resources_" . $linkfield;
        $AS_device = " AS glpi_plugin_resources_resources_items_" . $linkfield;
        $nt .= "_" . $linkfield;
        $nt_device .= "_" . $linkfield;
    }
    switch ($new_table) {
        case "glpi_plugin_resources_resources_items":
            if ($type != Resource_Item::class) {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_resources_items' => [
                        'ON' => [
                            $ref_table => 'id',
                            'glpi_plugin_resources_resources_items' => 'items_id',
                            [
                                'AND' => [
                                    'glpi_plugin_resources_resources_items.itemtype' => $type,
                                ],
                            ],
                        ],
                    ],
                ];
            } else {
                return $out['LEFT JOIN'] = [];
            }
            return $out;
        case "glpi_plugin_resources_taskplannings":
            $out['LEFT JOIN'] = [
                'glpi_plugin_resources_taskplannings' => [
                    'ON' => [
                        $ref_table => 'id',
                        'glpi_plugin_resources_taskplannings' => 'plugin_resources_tasks_id'
                    ],
                ],
            ];

            return $out;

        case "glpi_plugin_resources_tasks_items":
            $out['LEFT JOIN'] = [
                'glpi_plugin_resources_tasks_items' => [
                    'ON' => [
                        $ref_table => 'id',
                        'glpi_plugin_resources_tasks_items' => 'items_id',
                        [
                            'AND' => [
                                'glpi_plugin_resources_tasks_items.itemtype' => $type,
                            ],
                        ],
                    ],
                ],
            ];

            return $out;

        case "glpi_plugin_resources_resources": // From items
//            $out['LEFT JOIN'] = [];
//            if ($type != Directory::class && $type != Recap::class) {
//                if ($ref_table != 'glpi_plugin_resources_tasks'
//                    && $ref_table != 'glpi_plugin_resources_resourcerestings'
//                    && $ref_table != 'glpi_plugin_resources_resourceholidays'
//                    && $ref_table != 'glpi_plugin_resources_employments'
//                    && $type != Resource_Item::class) {
//                    $out = SQLProvider::getLeftJoinCriteria(
//                        $type,
//                        $ref_table,
//                        $already_link_tables,
//                        "glpi_plugin_resources_resources_items",
//                        "plugin_resources_resources_id"
//                    );
//                    $left = [
//                        'glpi_plugin_resources_resources' => [
//                            'ON' => [
//                                'glpi_plugin_resources_resources' => 'id',
//                                'glpi_plugin_resources_resources_items' => 'plugin_resources_resources_id',
//                                [
//                                    'AND' => [
//                                        'glpi_plugin_resources_resources_items.itemtype' => $type,
//                                    ],
//                                ],
//                            ],
//                        ],
//                    ];
//                    if (isset($out['LEFT JOIN'])) {
//                        $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
//                    } else {
//                        $out['LEFT JOIN'] = $left;
//                    }
//                } else {
//                    $out['LEFT JOIN'] = [
//                        'glpi_plugin_resources_resources' => [
//                            'ON' => [
//                                $ref_table => 'plugin_resources_resources_id',
//                                'glpi_plugin_resources_resources' => 'id'
//                            ],
//                        ],
//                    ];
//                }
//            }
//            return $out;
        case "glpi_plugin_resources_contracttypes": // From items
            if ($type != Recap::class) {
                if ($linkfield == "last_contract_type") {
                    return [];
                }

                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_plugin_resources_contracttypes",
                    "plugin_resources_resources_id"
                );
                $left = [
                    'glpi_plugin_resources_contracttypes' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_contracttypes_id',
                            'glpi_plugin_resources_contracttypes' => 'id'
                        ],
                    ],
                ];
                if (isset($out['LEFT JOIN'])) {
                    $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                } else {
                    $out['LEFT JOIN'] = $left;
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_contracttypes'. $AS => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_contracttypes_id',
                            'glpi_plugin_resources_contracttypes' => 'id'
                        ],
                    ],
                ];
            }

            return $out;
        case "glpi_plugin_resources_managers": // From items
            if ($type == Directory::class) {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_resources_items' . $AS_device => [
                        'ON' => [
                            $ref_table => 'id',
                            $nt_device => 'items_id'
                        ],
                    ],
                    'glpi_plugin_resources_resources' . $AS => [
                        'ON' => [
                            $nt => 'id',
                            $nt_device => 'plugin_resources_resources_id',
                            [
                                'AND' => [
                                    $nt_device . '.itemtype' => $type,
                                ],
                            ],
                        ],
                    ],
                    'glpi_users  AS glpi_plugin_resources_managers' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'users_id',
                            'glpi_plugin_resources_managers' => 'id'
                        ],
                    ],
                ];
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_resources_items' . $AS_device => [
                        'ON' => [
                            $ref_table => 'id',
                            $nt_device => 'items_id',
                            [
                                'AND' => [
                                    'glpi_plugin_resources_resources_items.itemtype' => $type,
                                ],
                            ],
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_plugin_resources_salemanagers": // From items
            $out['LEFT JOIN'] = [
                'glpi_plugin_resources_resources_items' . $AS_device => [
                    'ON' => [
                        $ref_table => 'id',
                        $nt_device => 'items_id'
                    ],
                ],
                'glpi_plugin_resources_resources' . $AS => [
                    'ON' => [
                        $nt => 'id',
                        $nt_device => 'plugin_resources_resources_id',
                        [
                            'AND' => [
                                $nt_device . '.itemtype' => $type,
                            ],
                        ],
                    ],
                ],
                'glpi_users AS glpi_plugin_resources_salemanagers' => [
                    'ON' => [
                        $nt => 'users_id_sales',
                        'glpi_plugin_resources_salemanagers' => 'id'
                    ],
                ],
            ];
            return $out;
        case "glpi_plugin_resources_recipients": // From items
            $out['LEFT JOIN'] = [
                'glpi_plugin_resources_resources_items' . $AS_device => [
                    'ON' => [
                        $ref_table => 'id',
                        $nt_device => 'items_id'
                    ],
                ],
                'glpi_plugin_resources_resources' . $AS => [
                    'ON' => [
                        $nt => 'id',
                        $nt_device => 'plugin_resources_resources_id',
                        [
                            'AND' => [
                                $nt_device . '.itemtype' => $type,
                            ],
                        ],
                    ],
                ],
                'glpi_users AS glpi_plugin_resources_recipients' => [
                    'ON' => [
                        $nt => 'users_id_recipient',
                        'glpi_plugin_resources_recipients' => 'id'
                    ],
                ],
            ];

            return $out;
        case "glpi_plugin_resources_recipients_leaving": // From items
            $out['LEFT JOIN'] = [
                'glpi_plugin_resources_resources_items' . $AS_device => [
                    'ON' => [
                        $ref_table => 'id',
                        $nt_device => 'items_id'
                    ],
                ],
                'glpi_plugin_resources_resources' . $AS => [
                    'ON' => [
                        $nt => 'id',
                        $nt_device => 'plugin_resources_resources_id',
                        [
                            'AND' => [
                                $nt_device . '.itemtype' => $type,
                            ],
                        ],
                    ],
                ],
                'glpi_users AS glpi_plugin_resources_recipients_leaving' => [
                    'ON' => [
                        $nt => 'users_id_recipient_leaving',
                        'glpi_plugin_resources_recipients_leaving' => 'id'
                    ],
                ],
            ];

            return $out;
        case "glpi_plugin_resources_locations": // From items
            $out['LEFT JOIN'] = [
                'glpi_plugin_resources_resources_items' . $AS_device => [
                    'ON' => [
                        $ref_table => 'id',
                        $nt_device => 'items_id'
                    ],
                ],
                'glpi_plugin_resources_resources' . $AS => [
                    'ON' => [
                        $nt => 'id',
                        $nt_device => 'plugin_resources_resources_id',
                        [
                            'AND' => [
                                $nt_device . '.itemtype' => $type,
                            ],
                        ],
                    ],
                ],
                'glpi_locations AS glpi_plugin_resources_locations' => [
                    'ON' => [
                        $nt => 'locations_id',
                        'glpi_plugin_resources_locations' => 'id'
                    ],
                ],
            ];

            return $out;
        case "glpi_plugin_resources_departments": // From items
            if ($type != Directory::class && $type != Recap::class) {
                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_plugin_resources_resources",
                    "plugin_resources_resources_id"
                );
                $left = [
                    'glpi_plugin_resources_departments' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_departments_id',
                            'glpi_plugin_resources_departments' => 'id'
                        ],
                    ],
                ];
                if (isset($out['LEFT JOIN'])) {
                    $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                } else {
                    $out['LEFT JOIN'] = $left;
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_departments' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_departments_id',
                            'glpi_plugin_resources_departments' => 'id'
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_plugin_resources_teams": // From items
            if ($type != Directory::class && $type != Recap::class) {
                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_plugin_resources_resources",
                    "plugin_resources_resources_id"
                );
                $left = [
                    'glpi_plugin_resources_teams' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_teams_id',
                            'glpi_plugin_resources_teams' => 'id'
                        ],
                    ],
                ];
                if (isset($out['LEFT JOIN'])) {
                    $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                } else {
                    $out['LEFT JOIN'] = $left;
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_teams' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_teams_id',
                            'glpi_plugin_resources_teams' => 'id'
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_plugin_resources_resourcestates": // From items
            if ($type != Directory::class && $type != Recap::class) {
                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_plugin_resources_resources",
                    "plugin_resources_resources_id"
                );
                $left = [
                    'glpi_plugin_resources_resourcestates' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_resourcestates_id',
                            'glpi_plugin_resources_resourcestates' => 'id'
                        ],
                    ],
                ];
                if (isset($out['LEFT JOIN'])) {
                    $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                } else {
                    $out['LEFT JOIN'] = $left;
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_resourcestates' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_resourcestates_id',
                            'glpi_plugin_resources_resourcestates' => 'id'
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_plugin_resources_employees": // From items
            if ($type != Directory::class && $type != Recap::class) {
                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_plugin_resources_resources",
                    "plugin_resources_resources_id"
                );
                $left = [
                    'glpi_plugin_resources_employees' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'id',
                            'glpi_plugin_resources_employees' => 'plugin_resources_resources_id'
                        ],
                    ],
                ];
                if (isset($out['LEFT JOIN'])) {
                    $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                } else {
                    $out['LEFT JOIN'] = $left;
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_employees' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'id',
                            'glpi_plugin_resources_employees' => 'plugin_resources_resources_id'
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_plugin_resources_resourcesituations": // For recap class
            if ($type != Directory::class && $type != Recap::class) {
                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_plugin_resources_resources",
                    "plugin_resources_resources_id"
                );
                $left = [
                    'glpi_plugin_resources_resourcesituations' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_resourcesituations_id',
                            'glpi_plugin_resources_resourcesituations' => 'id'
                        ],
                    ],
                ];
                if (isset($out['LEFT JOIN'])) {
                    $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                } else {
                    $out['LEFT JOIN'] = $left;
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_resourcesituations' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_resourcesituations_id',
                            'glpi_plugin_resources_resourcesituations' => 'id'
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_plugin_resources_contractnatures": // For recap class
            if ($type != Directory::class && $type != Recap::class) {
                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_plugin_resources_resources",
                    "plugin_resources_resources_id"
                );
                $left = [
                    'glpi_plugin_resources_contractnatures' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_contractnatures_id',
                            'glpi_plugin_resources_contractnatures' => 'id'
                        ],
                    ],
                ];
                if (isset($out['LEFT JOIN'])) {
                    $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                } else {
                    $out['LEFT JOIN'] = $left;
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_contractnatures' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_contractnatures_id',
                            'glpi_plugin_resources_contractnatures' => 'id'
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_plugin_resources_resourcespecialities": // For recap class
            if ($type != Directory::class && $type != Recap::class) {
                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_plugin_resources_resources",
                    "plugin_resources_resources_id"
                );
                $left = [
                    'glpi_plugin_resources_resourcespecialities' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_resourcespecialities_id',
                            'glpi_plugin_resources_resourcespecialities' => 'id'
                        ],
                    ],
                ];
                if (isset($out['LEFT JOIN'])) {
                    $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                } else {
                    $out['LEFT JOIN'] = $left;
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_resourcespecialities' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_resourcespecialities_id',
                            'glpi_plugin_resources_resourcespecialities' => 'id'
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_plugin_resources_employments": // For recap class
            if ($type != Directory::class && $type != Recap::class) {
                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_plugin_resources_resources",
                    "plugin_resources_resources_id"
                );
                $left = [
                    'glpi_plugin_resources_employments' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'id',
                            'glpi_plugin_resources_employments' => 'plugin_resources_resources_id'
                        ],
                    ],
                ];
                if (isset($out['LEFT JOIN'])) {
                    $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                } else {
                    $out['LEFT JOIN'] = $left;
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_employments' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'id',
                            'glpi_plugin_resources_employments' => 'plugin_resources_resources_id'
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_plugin_resources_ranks": // For recap class
            if ($type != Directory::class && $type != Recap::class) {
                if ($type == Resource::class) {
                    $out = SQLProvider::getLeftJoinCriteria(
                        $type,
                        $ref_table,
                        $already_link_tables,
                        "glpi_plugin_resources_resources",
                        "plugin_resources_resources_id"
                    );
                    $left = [
                        'glpi_plugin_resources_ranks' => [
                            'ON' => [
                                'glpi_plugin_resources_resources' => 'plugin_resources_ranks_id',
                                'glpi_plugin_resources_ranks' => 'id'
                            ],
                        ],
                    ];
                    if (isset($out['LEFT JOIN'])) {
                        $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                    } else {
                        $out['LEFT JOIN'] = $left;
                    }
                } elseif ($type == Employment::class) {
                    $out['LEFT JOIN'] = [
                        'glpi_plugin_resources_ranks' => [
                            'ON' => [
                                'glpi_plugin_resources_employments' => 'plugin_resources_ranks_id',
                                'glpi_plugin_resources_ranks' => 'id'
                            ],
                        ],
                    ];
                } elseif ($type == Budget::class) {
                    $out['LEFT JOIN'] = [
                        'glpi_plugin_resources_ranks' => [
                            'ON' => [
                                'glpi_plugin_resources_budgets' => 'plugin_resources_ranks_id',
                                'glpi_plugin_resources_ranks' => 'id'
                            ],
                        ],
                    ];
                } elseif ($type == Cost::class) {
                    $out['LEFT JOIN'] = [
                        'glpi_plugin_resources_ranks' => [
                            'ON' => [
                                'glpi_plugin_resources_costs' => 'plugin_resources_ranks_id',
                                'glpi_plugin_resources_ranks' => 'id'
                            ],
                        ],
                    ];
                } elseif ($type == ResourceSpeciality::class) {
                    $out['LEFT JOIN'] = [
                        'glpi_plugin_resources_ranks' => [
                            'ON' => [
                                'glpi_plugin_resources_resourcespecialities' => 'plugin_resources_ranks_id',
                                'glpi_plugin_resources_ranks' => 'id'
                            ],
                        ],
                    ];
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_ranks' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'plugin_resources_ranks_id',
                            'glpi_plugin_resources_ranks' => 'id'
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_plugin_resources_professions": // For recap class
            $out['LEFT JOIN'] = [];
            if ($type == Employment::class) { // for employment
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_professions' => [
                        'ON' => [
                            'glpi_plugin_resources_employments' => 'plugin_resources_professions_id',
                            'glpi_plugin_resources_professions' => 'id'
                        ],
                    ],
                ];
            } elseif ($type == Budget::class) {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_professions' => [
                        'ON' => [
                            'glpi_plugin_resources_budgets' => 'plugin_resources_professions_id',
                            'glpi_plugin_resources_professions' => 'id'
                        ],
                    ],
                ];
            } elseif ($type == Cost::class) {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_professions' => [
                        'ON' => [
                            'glpi_plugin_resources_costs' => 'plugin_resources_professions_id',
                            'glpi_plugin_resources_professions' => 'id'
                        ],
                    ],
                ];
            } elseif ($type == Rank::class) {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_professions' => [
                        'ON' => [
                            'glpi_plugin_resources_ranks' => 'plugin_resources_professions_id',
                            'glpi_plugin_resources_professions' => 'id'
                        ],
                    ],
                ];
            } elseif ($type == Recap::class) {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_professions' => [
                        'ON' => [
                            'glpi_plugin_resources_ranks' => 'plugin_resources_professions_id',
                            'glpi_plugin_resources_professions' => 'id'
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_plugin_resources_professionlines": // For recap class
            $out['LEFT JOIN'] = [
                'glpi_plugin_resources_professionlines' => [
                    'ON' => [
                        'glpi_plugin_resources_professions' => 'plugin_resources_professionlines_id',
                        'glpi_plugin_resources_professionlines' => 'id'
                    ],
                ],
            ];
            return $out;
        case "glpi_plugin_resources_professioncategories": // For recap class
            $out['LEFT JOIN'] = [
                'glpi_plugin_resources_professioncategories' => [
                    'ON' => [
                        'glpi_plugin_resources_professions' => 'plugin_resources_professioncategories_id',
                        'glpi_plugin_resources_professioncategories' => 'id'
                    ],
                ],
            ];
            return $out;
        case "glpi_plugin_resources_employmentranks": // For recap class
            $out['LEFT JOIN'] = [
                'glpi_plugin_resources_ranks AS glpi_plugin_resources_employmentranks' => [
                    'ON' => [
                        'glpi_plugin_resources_employments' => 'plugin_resources_ranks_id',
                        'glpi_plugin_resources_employmentranks' => 'id'
                    ],
                ],
            ];
            return $out;
        case "glpi_plugin_resources_employmentprofessions": // For recap class
            $out['LEFT JOIN'] = [];
            return $out;
        case "glpi_plugin_resources_employmentprofessionlines": // For recap class

            if ($type != Recap::class) {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_professionlines AS glpi_plugin_resources_employmentprofessionlines' => [
                        'ON' => [
                            'glpi_plugin_resources_employmentprofessions' => 'plugin_resources_professionlines_id',
                            'glpi_plugin_resources_employmentprofessionlines' => 'id'
                        ],
                    ],
                ];
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_professions AS glpi_plugin_resources_employmentprofessions' => [
                        'ON' => [
                            'glpi_plugin_resources_employments' => 'plugin_resources_professions_id',
                            'glpi_plugin_resources_employmentprofessions' => 'id'
                        ],
                    ],
                    'glpi_plugin_resources_professionlines AS glpi_plugin_resources_employmentprofessionlines' => [
                        'ON' => [
                            'glpi_plugin_resources_employmentprofessions' => 'plugin_resources_professionlines_id',
                            'glpi_plugin_resources_employmentprofessionlines' => 'id'
                        ],
                    ],

                ];
            }

            return $out;
        case "glpi_plugin_resources_employmentprofessioncategories": // For recap class
            $out['LEFT JOIN'] = [
                'glpi_plugin_resources_professioncategories AS glpi_plugin_resources_employmentprofessioncategories' => [
                    'ON' => [
                        'glpi_plugin_resources_employmentprofessions' => 'plugin_resources_professioncategories_id',
                        'glpi_plugin_resources_employmentprofessioncategories' => 'id'
                    ],
                ],
            ];
            return $out;
        case "glpi_plugin_resources_employers": // From recap class
            if ($type != Recap::class && $type != Employment::class) {
                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_plugin_resources_employees",
                    "plugin_resources_employees_id"
                );
                $left = [
                    'glpi_plugin_resources_employers' => [
                        'ON' => [
                            'glpi_plugin_resources_employees' => 'plugin_resources_employers_id',
                            'glpi_plugin_resources_employers' => 'id'
                        ],
                    ],
                ];
                if (isset($out['LEFT JOIN'])) {
                    $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                } else {
                    $out['LEFT JOIN'] = $left;
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_employers' => [
                        'ON' => [
                            'glpi_plugin_resources_employments' => 'plugin_resources_employers_id',
                            'glpi_plugin_resources_employers' => 'id'
                        ],
                    ],
                ];
            }

            return $out;
        case "glpi_plugin_resources_clients": // From items
            $out = SQLProvider::getLeftJoinCriteria(
                $type,
                $ref_table,
                $already_link_tables,
                "glpi_plugin_resources_employees",
                "plugin_resources_employees_id"
            );
            $left = [
                'glpi_plugin_resources_clients' => [
                    'ON' => [
                        'glpi_plugin_resources_employees' => 'plugin_resources_clients_id',
                        'glpi_plugin_resources_clients' => 'id'
                    ],
                ],
            ];
            if (isset($out['LEFT JOIN'])) {
                $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
            } else {
                $out['LEFT JOIN'] = $left;
            }

            return $out;
        case "glpi_plugin_resources_employmentstates": // For recap class
            if ($type != Directory::class && $type != Recap::class) {
                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_plugin_resources_employments",
                    "plugin_resources_employments_id"
                );

                $left = [
                    'glpi_plugin_resources_employmentstates' => [
                        'ON' => [
                            'glpi_plugin_resources_employments' => 'plugin_resources_employmentstates_id',
                            'glpi_plugin_resources_employmentstates' => 'id'
                        ],
                    ],
                ];
                if (isset($out['LEFT JOIN'])) {
                    $out['LEFT JOIN'] = array_merge($out['LEFT JOIN'], $left);
                } else {
                    $out['LEFT JOIN'] = $left;
                }
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_plugin_resources_employmentstates' => [
                        'ON' => [
                            'glpi_plugin_resources_employments' => 'plugin_resources_employmentstates_id',
                            'glpi_plugin_resources_employmentstates' => 'id'
                        ],
                    ],
                ];
            }
            return $out;
        case "glpi_locations": // From recap class
            if ($type != Recap::class) {
                $out = SQLProvider::getLeftJoinCriteria(
                    $type,
                    $ref_table,
                    $already_link_tables,
                    "glpi_locations",
                    "locations_id"
                );
            } else {
                $out['LEFT JOIN'] = [
                    'glpi_locations' => [
                        'ON' => [
                            'glpi_plugin_resources_employers' => 'locations_id',
                            'glpi_locations' => 'id'
                        ],
                    ],
                ];
            }
            return $out;
    }
    return [];
}

/**
 * @param $type
 *
 * @return bool
 */
function plugin_resources_forceGroupBy($type)
{
    return true;
}

/**
 * @param $type
 * @param $ID
 * @param $data
 * @param $num
 *
 * @return string
 */
function plugin_resources_giveItem($type, $ID, $data, $num)
{
    global $CFG_GLPI, $DB;

    $searchopt = Search::getOptions($type);
    $table = $searchopt[$ID]["table"];
    $field = $searchopt[$ID]["field"];

    $dbu = new DbUtils();
    $output_type = Search::HTML_OUTPUT;
    if (isset($_GET['display_type'])) {
        $output_type = $_GET['display_type'];
    }

    switch ($type) {
        case Resource::class:
            switch ($table . '.' . $field) {
                case "glpi_plugin_resources_resources.name":
                    $out = "";
                    if (!empty($data['raw']["ITEM_" . $num . "_2"])) {
                        $link = Toolbox::getItemTypeFormURL(Resource::class);
                        if ($output_type == Search::HTML_OUTPUT) {
                            $out = "<a href=\"" . $link . "?id=" . $data['raw']["ITEM_" . $num . "_2"] . "\">";
                        }
                        $out .= $data['raw']["META_$num"];
                        if ($output_type == Search::HTML_OUTPUT) {
                            if ($_SESSION["glpiis_ids_visible"] || empty($data['raw']["META_$num"])) {
                                $out .= " (" . $data['raw']["ITEM_" . $num . "_2"] . ")";
                            }
                            $out .= "</a>";
                        }

                        if (Session::haveRight("plugin_resources_task", READ) && $output_type == Search::HTML_OUTPUT) {
                            $query_tasks = "SELECT COUNT(`id`) AS nb_tasks,SUM(`is_finished`) AS is_finished
                                 FROM `glpi_plugin_resources_tasks`
                                 WHERE `plugin_resources_resources_id` = " . $data['id'] . "
                                 AND `is_deleted` = 0";
                            $result_tasks = $DB->doQuery($query_tasks);
                            $nb_tasks = $DB->result($result_tasks, 0, "nb_tasks");
                            $is_finished = $DB->result($result_tasks, 0, "is_finished");
                            $out .= "&nbsp;(<a href=\"" . PLUGIN_RESOURCES_WEBDIR . "/front/task.php?plugin_resources_resources_id=" . $data["id"] . "\">";
                            if (($nb_tasks - $is_finished) > 0) {
                                $out .= "<span class='plugin_resources_date_over_color'>";
                                $out .= $nb_tasks - $is_finished . "</span></a>)";
                            } else {
                                $out .= "<span class='plugin_resources_date_day_color'>";
                                $out .= $nb_tasks . "</span></a>)";
                            }
                        }
                    }
                    return $out;
                case "glpi_plugin_resources_resources.date_end":
                    if ($data['raw']["ITEM_$num"] <= date('Y-m-d') && !empty($data['raw']["ITEM_$num"])) {
                        $out = "<span class='plugin_resources_date_color'>" . Html::convDate(
                                $data['raw']["ITEM_$num"]
                            ) . "</span>";
                    } elseif (empty($data['raw']["ITEM_$num"])) {
                        $out = __('Not defined', 'resources');
                    } else {
                        $out = Html::convDate($data['raw']["ITEM_$num"]);
                    }
                    return $out;
                case "glpi_plugin_resources_resources_items.items_id":
                    $restrict = ["plugin_resources_resources_id" => $data['id']]
                        + ["ORDER" => "`itemtype`, `items_id`"];
                    $items = $dbu->getAllDataFromTable("glpi_plugin_resources_resources_items", $restrict);
                    $out = '';
                    if (!empty($items)) {
                        foreach ($items as $device) {
                            if (!class_exists($device["itemtype"])) {
                                continue;
                            }
                            $item = new $device["itemtype"]();
                            $item->getFromDB($device["items_id"]);
                            $out .= $item->getTypeName() . " - ";
                            if ($device["itemtype"] == 'User') {
                                if ($output_type == Search::HTML_OUTPUT) {
                                    $link = Toolbox::getItemTypeFormURL(User::class);
                                    $out .= "<a href=\"" . $link . "?id=" . $device["items_id"] . "\">";
                                }
                                $out .= $dbu->getUserName($device["items_id"]);
                                if ($output_type == Search::HTML_OUTPUT) {
                                    $out .= "</a>";
                                }
                            } else {
                                $out .= $item->getLink();
                            }
                            $out .= "<br>";
                        }
                    } else {
                        $out = ' ';
                    }
                    return $out;
                case "glpi_plugin_resources_resources.quota":
                    if (!empty($data['raw']["ITEM_$num"])) {
                        $out = floatval($data['raw']["ITEM_$num"]);
                    }
                    return $out;
            }
            return "";
        case Task::class:
            switch ($table . '.' . $field) {
                case "glpi_plugin_resources_resources.name":
                    $out = "";
                    if (!empty($data['raw']["ITEM_" . $num . "_2"])) {
                        $user = Resource::getResourceName($data['raw']["ITEM_" . $num . "_2"], 2);
                        $out = "<a href='" . $user['link'] . "'>";
                        $out .= $user["name"];
                        if ($_SESSION["glpiis_ids_visible"] || empty($user["name"])) {
                            $out .= " (" . $data['raw']["ITEM_" . $num . "_2"] . ")";
                        }
                        $out .= "</a>";
                    }
                    return $out;
                case 'glpi_plugin_resources_tasks.is_finished':
                    return Task::getStatusImg($data['raw']["ITEM_$num"]);
                case "glpi_plugin_resources_tasks_items.items_id":
                    $restrict = ["plugin_resources_tasks_id" => $data['id']]
                        + ["ORDER" => "`itemtype`, `items_id`"];
                    $items = $dbu->getAllDataFromTable("glpi_plugin_resources_tasks_items", $restrict);
                    $out = '';
                    if (!empty($items)) {
                        foreach ($items as $device) {
                            $item = new $device["itemtype"]();
                            $item->getFromDB($device["items_id"]);
                            $out .= $item->getTypeName() . " - " . $item->getLink() . "<br>";
                        }
                    }
                    return $out;
                case "glpi_plugin_resources_taskplannings.id":
                    if (!empty($data['raw']["ITEM_$num"])) {
                        $plan = new TaskPlanning();
                        $plan->getFromDB($data['raw']["ITEM_$num"]);
                        $out = Html::convDateTime($plan->fields["begin"]) . "<br>&nbsp;->&nbsp;"
                            . Html::convDateTime($plan->fields["end"]);
                    } else {
                        $out = __('None');
                    }
                    return $out;
            }
            return "";
        case 'User':
            switch ($table . '.' . $field) {
                case "glpi_plugin_resources_recipients_leaving.name":
                case "glpi_plugin_resources_managers.name":
                case "glpi_plugin_resources_salemanagers.name":
                case "glpi_plugin_resources_recipients.name":
                    $out = getUserName($data['raw']["ITEM_" . $num . "_2"]);
                    return $out;
            }
            return "";
        case ResourceResting::class:
            switch ($table . '.' . $field) {
                case "glpi_plugin_resources_resources.name":
                    if (!empty($data["id"])) {
                        $link = Toolbox::getItemTypeFormURL(ResourceResting::class);
                        $out = "<a href=\"" . $link . "?id=" . $data["id"] . "\">";
                        $out .= $data['raw']["ITEM_$num"];
                        if ($_SESSION["glpiis_ids_visible"] || empty($data['raw']["ITEM_$num"])) {
                            $out .= " (" . $data["id"] . ")";
                        }
                        $out .= "</a>";
                    }
                    return $out;
            }
            return "";
        case ResourceHoliday::class:
            switch ($table . '.' . $field) {
                case "glpi_plugin_resources_resources.name":
                    if (!empty($data["id"])) {
                        $link = Toolbox::getItemTypeFormURL(ResourceHoliday::class);
                        $out = "<a href=\"" . $link . "?id=" . $data["id"] . "\">";
                        $out .= $data['raw']["ITEM_$num"];
                        if ($_SESSION["glpiis_ids_visible"] || empty($data['raw']["ITEM_$num"])) {
                            $out .= " (" . $data["id"] . ")";
                        }
                        $out .= "</a>";
                    }
                    return $out;
            }
            return "";
        case Directory::class:
            switch ($table . '.' . $field) {
                case "glpi_plugin_resources_managers.name":
                    $out = "";
                    if (!empty($data['raw']["ITEM_" . $num . "_2"])) {
                        $out = getUserName($data['raw']["ITEM_" . $num . "_2"]);
                    } else {
                        $out = "";
                    }
                    return $out;
            }
            return "";
        case Employment::class:
            switch ($table . '.' . $field) {
                case "glpi_plugin_resources_resources.name":
                    $out = "";
                    if (!empty($data['raw']["ITEM_" . $num . "_2"])) {
                        $user = Resource::getResourceName($data['raw']["ITEM_" . $num . "_2"], 2);
                        $out = "<a href='" . $user['link'] . "'>";
                        $out .= $user["name"];
                        if ($_SESSION["glpiis_ids_visible"] || empty($user["name"])) {
                            $out .= " (" . $data['raw']["ITEM_" . $num . "_2"] . ")";
                        }
                        $out .= "</a>";
                    }
                    return $out;
            }
            return "";
        case Computer::class:
            switch ($ID) {
                case 4331:
                    $result = "";
                    if (isset($data["Computer_4331"]) && !is_null($data["Computer_4331"])) {
                        if (isset($data["Computer_4331"]["count"])) {
                            $count = $data["Computer_4331"]["count"];
                            $i = 0;
                            for ($i; $i < $count; $i++) {
                                if ($i != 0) {
                                    $result .= "\n";
                                }
                                $result .= Resource::getResourceName($data["Computer_4331"][$i]["name"]);
                            }
                        }
                    }
                    return $result;
            }
            return "";
    }
    return "";
}

////// SPECIFIC MODIF MASSIVE FUNCTIONS ///////
/**
 * @param $type
 *
 * @return array|mixed
 */
function plugin_resources_MassiveActions($type)
{
    if (Plugin::isPluginActive('resources')) {
        if (in_array($type, Resource::getTypes())) {
            $resource = new Resource();
            return $resource->massiveActions($type);
        }
    }
    return [];
}

// Do special actions for dynamic report
/**
 * @param $parm
 *
 * @return bool
 */
function plugin_resources_dynamicReport($parm)
{
    $allowed = [Directory::class, Recap::class];

    if (in_array($parm["item_type"], $allowed)) {
        $params = Search::manageParams($parm["item_type"], $parm);
        $data = Search::prepareDatasForSearch($parm["item_type"], $params);
        Search::constructSQL($data);
        Search::constructData($data);
        Search::displayData($data);
        return true;
    }

    return false;
}

// Hook done on before add item case
/**
 * @param $item
 */
function plugin_pre_item_update_resources($item)
{
    if (Session::getCurrentInterface()
        && !isset($item->input["_UpdateFromResource_"])) {
        $restrict = [
            "itemtype" => get_class($item),
            "items_id" => $item->getField('id'),
        ];
        $dbu = new DbUtils();
        $items = $dbu->getAllDataFromTable("glpi_plugin_resources_resources_items", $restrict);
        if (!empty($items)) {
            foreach ($items as $device) {
                $Resource = new Resource();
                $Resource->GetfromDB($device["plugin_resources_resources_id"]);
                if (isset($Resource->fields["locations_id"]) && isset($item->input["locations_id"])) {
                    if ($item->input["locations_id"] != 0 && $Resource->fields["locations_id"] != $item->input["locations_id"]) {
                        $values = [];
                        $values["id"] = $device["plugin_resources_resources_id"];
                        $values["locations_id"] = $item->input["locations_id"];
                        $values["withtemplate"] = 0;
                        $values["_UpdateFromUser_"] = 1;
                        $Resource->update($values);
                        Session::addMessageAfterRedirect(
                            __("Modification of the associated resource's location", "resources"),
                            true
                        );
                    }
                }
            }
        }
    }
}


// Hook done on before add item case
/**
 * @param $item
 */
function plugin_pre_item_add_solutions($item)
{
    if (isset($item->fields["itemtype"])) {
        $ticket = new $item->fields["itemtype"]();
        if ($ticket->getFromDB($item->fields["items_id"])) {
            $adconfig = new Adconfig();
            $adconfig->getFromDB(1);
            $adconfig->fields = $adconfig->prepareFields($adconfig->fields);
            $linkad = new LinkAd();
            $items = new Item_Ticket();
            $conf = new Config();
            $conf->getFromDB(1);
            if (is_array($adconfig->fields["creation_categories_id"])
                && in_array($ticket->fields["itilcategories_id"], $adconfig->fields["creation_categories_id"])) {
                if ($items->getFromDBByCrit([
                    "tickets_id" => $ticket->getID(),
                    "itemtype" => Resource::getType(),
                ])) {
                    if ($conf->fields["mandatory_adcreation"] == 1) {
                        if (!$linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]
                            ) || ($linkad->getFromDBByCrit(
                                    ['plugin_resources_resources_id' => $items->getField('items_id')]
                                ) && $linkad->getField('action_done') == 0)) {
                            $item->input = null;
                            Session::addMessageAfterRedirect(
                                __('You have to perform the action on the LDAP directory before', 'resources'),
                                false,
                                ERROR
                            );
                        }
                    }
                    if ($conf->fields["mandatory_checklist"] == 1) {
                        $checklist = new Checklist();
                        $checklists = $checklist->find(
                            [
                                "plugin_resources_resources_id" => $items->getField('items_id'),
                                "is_checked" => 0,
                                "checklist_type" => Checklist::RESOURCES_CHECKLIST_IN,
                            ]
                        );
                        if (!empty($checklists)) {
                            $item->input = null;
                            Session::addMessageAfterRedirect(
                                __('You have to do all checklist in action before', 'resources'),
                                false,
                                ERROR
                            );
                        }
                    }
                }
                //         } else if ($ticket->fields["itilcategories_id"] == $adconfig->fields["modification_categories_id"]) {
                //            if ($items->getFromDBByCrit(["tickets_id" => $ticket->getID(), "itemtype" => Resource::getType()])) {
                //               if (!$linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) || ($linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) && $linkad->getField('action_done') == 0)) {
                //                  $item->input = null;
                //                  Session::addMessageAfterRedirect(
                //                     __('You have to perform the action on the LDAP directory before', 'resources'),
                //                     false, ERROR);
                //               }
                //
                //            }
            } elseif (is_array($adconfig->fields["deletion_categories_id"])
                && in_array($ticket->fields["itilcategories_id"], $adconfig->fields["deletion_categories_id"])) {
                if ($items->getFromDBByCrit(
                    ["tickets_id" => $ticket->getID(), "itemtype" => Resource::getType()]
                )) {
                    if ($conf->fields["mandatory_adcreation"] == 1) {
                        if (!$linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]
                            ) || ($linkad->getFromDBByCrit(
                                    ['plugin_resources_resources_id' => $items->getField('items_id')]
                                ) && $linkad->getField('action_done') == 0)) {
                            $item->input = null;
                            Session::addMessageAfterRedirect(
                                __('You have to perform the action on the LDAP directory before', 'resources'),
                                false,
                                ERROR
                            );
                        }
                    }
                    if ($conf->fields["mandatory_checklist"] == 1) {
                        $checklist = new Checklist();
                        $checklists = $checklist->find(
                            [
                                "plugin_resources_resources_id" => $items->getField('items_id'),
                                "is_checked" => 0,
                                "checklist_type" => Checklist::RESOURCES_CHECKLIST_OUT,
                            ]
                        );
                        if (!empty($checklists)) {
                            $item->input = null;
                            Session::addMessageAfterRedirect(
                                __('You have to do all checklist out action before', 'resources'),
                                false,
                                ERROR
                            );
                        }
                    }
                }
            }
        }
    }
}

function plugin_datainjection_populate_resources()
{
    global $INJECTABLE_TYPES;
    $INJECTABLE_TYPES[ResourceInjection::class] = 'resources';
    $INJECTABLE_TYPES[ClientInjection::class] = 'resources';
    $INJECTABLE_TYPES[HabilitationInjection::class] = 'resources';
}
