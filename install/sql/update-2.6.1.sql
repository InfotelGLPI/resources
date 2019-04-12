DROP TABLE IF EXISTS `glpi_plugin_resources_imports`;
CREATE TABLE `glpi_plugin_resources_imports` (
   `id` int(11) NOT NULL auto_increment,
   `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
   `comment` text COLLATE utf8_unicode_ci,
   `is_active` tinyint(1) NOT NULL default '0',
   `is_deleted` tinyint(1) NOT NULL default '0',
   `date_creation` datetime DEFAULT NULL,
   `date_mod` datetime DEFAULT NULL,
   PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_plugin_resources_importcolumns`;
CREATE TABLE `glpi_plugin_resources_importcolumns` (
   `id` int(11) NOT NULL auto_increment,
   `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
   `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
   `resource_column` varchar(255) COLLATE utf8_unicode_ci NULL,
   `plugin_resources_imports_id` tinyint(1) NOT NULL DEFAULT '0',
   PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;