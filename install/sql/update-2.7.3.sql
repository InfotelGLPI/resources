

ALTER TABLE `glpi_plugin_resources_resources` ADD `plugin_resources_functions_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_resources_functions (id)';
ALTER TABLE `glpi_plugin_resources_resources` ADD `plugin_resources_teams_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_resources_teams (id)';
ALTER TABLE `glpi_plugin_resources_resources` ADD `plugin_resources_services_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_resources_services (id)';
ALTER TABLE `glpi_plugin_resources_resources` ADD `matricule_second` varchar(255) NOT NULL default '' ;
ALTER TABLE `glpi_plugin_resources_contracttypes`
   ADD `use_second_list_employer` tinyint(1) NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_contracttypes`
   ADD `use_second_matricule` tinyint(1) NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_employers`
   ADD `second_list` tinyint(1) NOT NULL DEFAULT '0';


CREATE TABLE `glpi_plugin_resources_functions` (
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

CREATE TABLE `glpi_plugin_resources_teams` (
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

CREATE TABLE `glpi_plugin_resources_services` (
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

CREATE TABLE `glpi_plugin_resources_roles_services` (
   `id` int(11) NOT NULL auto_increment,
   `plugin_resources_roles_id` int(11) NOT NULL default '0',
   `plugin_resources_services_id` tinyint(1) NOT NULL DEFAULT '0',
   PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `glpi_plugin_resources_departments_services` (
   `id` int(11) NOT NULL auto_increment,
   `plugin_resources_departments_id` int(11) NOT NULL default '0',
   `plugin_resources_services_id` tinyint(1) NOT NULL DEFAULT '0',
   PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `glpi_plugin_resources_contracttypeprofiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plugin_resources_contracttypes_id` varchar(255) NOT NULL default '0',
  `profiles_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;