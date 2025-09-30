ALTER TABLE `glpi_plugin_resources_resources`
    ADD `date_of_last_location` timestamp NULL DEFAULT NULL;
ALTER TABLE `glpi_plugin_resources_resources`
    ADD `last_location` int unsigned NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_resources`
    ADD `plugin_resources_workprofiles_id_entrance` int unsigned NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_resources`
    ADD `plugin_resources_candidateorigins_id` int unsigned NOT NULL default '0';

CREATE TABLE `glpi_plugin_resources_candidateorigins`
(
    `id`           int unsigned NOT NULL auto_increment,
    `entities_id`  int unsigned NOT NULL                default '0',
    `is_recursive` tinyint      NOT NULL                DEFAULT '0',
    `name`         varchar(255) collate utf8_unicode_ci default NULL,
    `comment`      text collate utf8_unicode_ci,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci
  ROW_FORMAT = DYNAMIC;
