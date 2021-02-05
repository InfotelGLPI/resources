

ALTER TABLE `glpi_plugin_resources_configs` ADD `mandatory_checklist` tinyint(1) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `mandatory_adcreation` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `allow_without_contract` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `plugin_resources_resourcetemplates_id` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `plugin_resources_resourcestates_id_arrival` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `plugin_resources_resourcestates_id_departure` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_adconfigs` ADD `mail_prefix` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_adconfigs` ADD `mail_suffix` varchar(255) NOT NULL default '';

