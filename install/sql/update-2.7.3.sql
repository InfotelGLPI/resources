ALTER TABLE `glpi_plugin_resources_resources` ADD `plugin_resources_functions_id` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_resources_functions (id)';
ALTER TABLE `glpi_plugin_resources_resources` ADD `plugin_resources_teams_id` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_resources_teams (id)';
ALTER TABLE `glpi_plugin_resources_resources` ADD `plugin_resources_services_id` int unsigned NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_resources_services (id)';
ALTER TABLE `glpi_plugin_resources_resources` ADD `matricule_second` varchar(255) NOT NULL default '' ;
ALTER TABLE `glpi_plugin_resources_contracttypes`
   ADD `use_second_list_employer` tinyint NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_contracttypes`
   ADD `use_second_matricule` tinyint NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_employers`
   ADD `second_list` tinyint NOT NULL DEFAULT '0';


CREATE TABLE `glpi_plugin_resources_functions` (
   `id` int unsigned NOT NULL auto_increment,
   `entities_id` int unsigned NOT NULL default '0',
   `is_recursive` tinyint NOT NULL DEFAULT '0',
   `name` varchar(255) collate utf8_unicode_ci default NULL,
   `comment` text collate utf8_unicode_ci,
   PRIMARY KEY  (`id`),
   KEY `name` (`name`),
   KEY `entities_id` (`entities_id`),
   KEY `is_recursive` (`is_recursive`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `glpi_plugin_resources_teams` (
   `id` int unsigned NOT NULL auto_increment,
   `entities_id` int unsigned NOT NULL default '0',
   `is_recursive` tinyint NOT NULL DEFAULT '0',
   `name` varchar(255) collate utf8_unicode_ci default NULL,
   `code` varchar(255) collate utf8_unicode_ci default NULL,
   `users_id` int unsigned NOT NULL default '0',
   `users_id_substitute` int unsigned NOT NULL default '0',
   `comment` text collate utf8_unicode_ci,
   PRIMARY KEY  (`id`),
   KEY `name` (`name`),
   KEY `entities_id` (`entities_id`),
   KEY `is_recursive` (`is_recursive`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `glpi_plugin_resources_services` (
   `id` int unsigned NOT NULL auto_increment,
   `entities_id` int unsigned NOT NULL default '0',
   `is_recursive` tinyint NOT NULL DEFAULT '0',
   `name` varchar(255) collate utf8_unicode_ci default NULL,
   `comment` text collate utf8_unicode_ci,
   PRIMARY KEY  (`id`),
   KEY `name` (`name`),
   KEY `entities_id` (`entities_id`),
   KEY `is_recursive` (`is_recursive`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `glpi_plugin_resources_roles_services` (
   `id` int unsigned NOT NULL auto_increment,
   `plugin_resources_roles_id` int unsigned NOT NULL default '0',
   `plugin_resources_services_id` tinyint NOT NULL DEFAULT '0',
   PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `glpi_plugin_resources_departments_services` (
   `id` int unsigned NOT NULL auto_increment,
   `plugin_resources_departments_id` int unsigned NOT NULL default '0',
   `plugin_resources_services_id` tinyint NOT NULL DEFAULT '0',
   PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `glpi_plugin_resources_contracttypeprofiles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_resources_contracttypes_id` varchar(255) NOT NULL default '0',
  `profiles_id` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

CREATE TABLE `glpi_plugin_resources_actionprofiles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `actions_id` varchar(255) NOT NULL default '0',
  `profiles_id` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

ALTER TABLE `glpi_plugin_resources_linkads` ADD `service` varchar(255) NOT NULL default '' ;
ALTER TABLE `glpi_plugin_resources_linkads` ADD `location` varchar(255) NOT NULL default '' ;
ALTER TABLE `glpi_plugin_resources_adconfigs` ADD `serviceAD` varchar(255) NOT NULL default '' ;
ALTER TABLE `glpi_plugin_resources_adconfigs` ADD `locationAD` varchar(255) NOT NULL default '' ;

ALTER TABLE `glpi_plugin_resources_linkmetademands` ADD `habilitation` TEXT collate utf8_unicode_ci default NULL;
ALTER TABLE `glpi_plugin_resources_linkmetademands` ADD `is_leaving_resource` tinyint(1) NOT NULL default '0';

ALTER TABLE `glpi_plugin_resources_configs` ADD `use_service_department_ad` tinyint(1) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `use_secondary_service` tinyint(1) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `use_meta_for_changes` int(11) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `use_meta_for_leave` int(11) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `remove_habilitation_on_update` int(11) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `display_habilitations_txt` int(11) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs` ADD `hide_view_commercial_resource` tinyint(1) NOT NULL default '0';

ALTER TABLE `glpi_plugin_resources_resources` ADD `secondary_services` varchar(255) NOT NULL default '';
ALTER TABLE `glpi_plugin_resources_resources` ADD `gender` varchar(3) NOT NULL default '';
