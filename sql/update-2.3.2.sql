DROP TABLE IF EXISTS `glpi_plugin_resources_configs`;
CREATE TABLE `glpi_plugin_resources_configs` (
   `id` int(11) NOT NULL auto_increment,
   `security_display` tinyint(1) NOT NULL default '0',
   PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `glpi_plugin_resources_configs` VALUES(1, 0);

ALTER TABLE glpi_plugin_resources_resources ADD `sensitize_security` tinyint(1) NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_resources ADD `read_chart` tinyint(1) NOT NULL default '0';
