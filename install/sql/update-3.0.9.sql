CREATE TABLE `glpi_plugin_resources_tickettemplates`
(
    `id`                int unsigned NOT NULL AUTO_INCREMENT,
    `name`              varchar(255)     DEFAULT NULL,
    `entities_id`       int unsigned NOT NULL DEFAULT '0',
    `template_type`     int unsigned NOT NULL DEFAULT '1',
    `type`              int unsigned NOT NULL DEFAULT '1',
    `content`           longtext,
    `title`             varchar(255)     DEFAULT NULL,
    `itilcategories_id` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY                 `name` (`name`),
    KEY                 `entities_id` (`entities_id`),
    KEY                 `template_type` (`template_type`),
    KEY                 `itilcategories_id` (`itilcategories_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `glpi_plugin_resources_tickettemplateusers`
(
    `id`                                  int unsigned NOT NULL AUTO_INCREMENT,
    `plugin_resources_tickettemplates_id` int unsigned NOT NULL DEFAULT '0',
    `users_id`                            int unsigned NOT NULL DEFAULT '0',
    `type`                                int NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unicity` (`plugin_resources_tickettemplates_id`,`type`,`users_id`),
    KEY                                   `user` (`users_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `glpi_plugin_resources_grouptickettemplates`
(
    `id`                                  int unsigned NOT NULL AUTO_INCREMENT,
    `plugin_resources_tickettemplates_id` int unsigned NOT NULL DEFAULT '0',
    `groups_id`                           int unsigned NOT NULL DEFAULT '0',
    `type`                                int NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unicity` (`plugin_resources_tickettemplates_id`,`type`,`groups_id`),
    KEY                                   `group` (`groups_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `glpi_plugin_resources_configs`
    ADD `create_ticket_template` int unsigned NOT NULL default '0';

ALTER TABLE `glpi_plugin_resources_configs`
    ADD `update_ticket_template` int unsigned NOT NULL default '0';

ALTER TABLE `glpi_plugin_resources_configs`
    ADD `leave_ticket_template` int unsigned NOT NULL default '0';
