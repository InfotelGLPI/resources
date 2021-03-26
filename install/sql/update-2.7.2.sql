ALTER TABLE `glpi_plugin_resources_configs` ADD `mandatory_checklist` tinyint(1) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `mandatory_adcreation` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `allow_without_contract` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `plugin_resources_resourcetemplates_id` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `plugin_resources_resourcestates_id_arrival` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `plugin_resources_resourcestates_id_departure` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_adconfigs` ADD `mail_prefix` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_adconfigs` ADD `mail_suffix` varchar(255) NOT NULL default '';

ALTER TABLE `glpi_plugin_resources_resources` ADD `plugin_resources_roles_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_resources_roles (id)';
ALTER TABLE `glpi_plugin_resources_resources` ADD `matricule` varchar(255) NOT NULL default '' ;
ALTER TABLE `glpi_plugin_resources_configs` ADD `reaffect_checklist_change` TINYINT(1) NOT NULL DEFAULT '1' ;

CREATE TABLE `glpi_plugin_resources_roles` (
   `id` int(11) NOT NULL auto_increment,
   `entities_id` int(11) NOT NULL default '0',
   `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
   `name` varchar(255) collate utf8_unicode_ci default NULL,
   `comment` text collate utf8_unicode_ci,
   PRIMARY KEY  (`id`),
   KEY `name` (`name`),
   KEY `entities_id` (`entities_id`),
   KEY `is_recursive` (`is_recursive`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

