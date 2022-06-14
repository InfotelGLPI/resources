ALTER TABLE `glpi_plugin_resources_linkads` CHANGE `service` `service` varchar(255) collate utf8mb4_unicode_ci default NULL;
ALTER TABLE `glpi_plugin_resources_linkads` CHANGE `location` `location` varchar(255) collate utf8mb4_unicode_ci default NULL;
ALTER TABLE `glpi_plugin_resources_adconfigs` CHANGE `serviceAD` `serviceAD` varchar(255) collate utf8mb4_unicode_ci default NULL;
ALTER TABLE `glpi_plugin_resources_adconfigs` CHANGE `locationAD` `locationAD` varchar(255) collate utf8mb4_unicode_ci default NULL;
ALTER TABLE `glpi_plugin_resources_resources` CHANGE `secondary_services` `secondary_services` varchar(255) collate utf8mb4_unicode_ci default NULL;
ALTER TABLE `glpi_plugin_resources_resources` CHANGE `gender` `gender` varchar(3) collate utf8mb4_unicode_ci default NULL;

ALTER TABLE `glpi_plugin_resources_resources` CHANGE `date_declaration` `date_declaration` timestamp NULL DEFAULT NULL;
ALTER TABLE `glpi_plugin_resources_resources` CHANGE `date_begin` `date_begin` timestamp NULL DEFAULT NULL;
ALTER TABLE `glpi_plugin_resources_resources` CHANGE `date_end` `date_end` timestamp NULL DEFAULT NULL;
ALTER TABLE `glpi_plugin_resources_resources` CHANGE `date_declaration_leaving` `date_declaration_leaving` timestamp NULL DEFAULT NULL;
ALTER TABLE `glpi_plugin_resources_resources` CHANGE `date_mod` `date_mod` timestamp NULL DEFAULT NULL;

UPDATE `glpi_plugin_resources_checklists` SET `plugin_resources_contracttypes_id` = '0' WHERE `plugin_resources_contracttypes_id` = '-1';
UPDATE `glpi_plugin_resources_checklists` SET `plugin_resources_contracttypes_id` = '0' WHERE `plugin_resources_contracttypes_id` = '-1';
UPDATE `glpi_plugin_resources_employees` SET `plugin_resources_clients_id` = '0' WHERE `plugin_resources_clients_id` = '-1';
UPDATE `glpi_plugin_resources_resources` SET `plugin_resources_contracttypes_id` = '0' WHERE `plugin_resources_contracttypes_id` = '-1';
UPDATE `glpi_plugin_resources_resources` SET `plugin_resources_teams_id` = '0' WHERE `plugin_resources_teams_id` = '-1';
UPDATE `glpi_plugin_resources_resources` SET `users_id_sales` = '0' WHERE `users_id_sales` = '-1';

ALTER TABLE `glpi_plugin_resources_configs` ADD `use_service_department_ad` tinyint(1) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `use_secondary_service` tinyint(1) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `use_meta_for_changes` int(11) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `use_meta_for_leave` int(11) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `remove_habilitation_on_update` int(11) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `display_habilitations_txt` int(11) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `hide_view_commercial_resource` tinyint(1) NOT NULL default '0';
