ALTER TABLE glpi_plugin_resources_configs ADD `import_external_datas` tinyint(1) NOT NULL DEFAULT '0';
ALTER TABLE glpi_plugin_resources_resources ADD `matricule_external` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL;
ALTER TABLE glpi_plugin_resources_resources ADD `id_external`varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL;
ALTER TABLE glpi_plugin_resources_resources ADD `branching_agency_external` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL;
ALTER TABLE glpi_plugin_resources_resources ADD `email_external` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL;

DROP TABLE IF EXISTS `glpi_plugin_resources_imports`;
CREATE TABLE `glpi_plugin_resources_imports` (
   `id` int(11) NOT NULL auto_increment,
   `id_external` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
   `origin` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
   `matricule` varchar(255) NOT NULL default '',
   `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
   `firstname` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
   `branching_agency` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
   `users_id_sales` int(11) NOT NULL default '0',
   `date_begin` date default NULL,
   `date_end` date default NULL,
   `affected_client` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
   `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
   PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
