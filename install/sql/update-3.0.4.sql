
CREATE TABLE `glpi_plugin_resources_businessunits` (
                                                       `id` int unsigned NOT NULL auto_increment,
                                                       `entities_id` int unsigned NOT NULL default '0',
                                                       `is_recursive` tinyint NOT NULL DEFAULT '0',
                                                       `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                                                       `comment` text collate utf8mb4_unicode_ci,
                                                       PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `glpi_plugin_resources_degreegroups` (
                                                      `id` int unsigned NOT NULL auto_increment,
                                                      `entities_id` int unsigned NOT NULL default '0',
                                                      `is_recursive` tinyint NOT NULL DEFAULT '0',
                                                      `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                                                      `comment` text collate utf8mb4_unicode_ci,
                                                      PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `glpi_plugin_resources_recruitingsources` (
                                                      `id` int unsigned NOT NULL auto_increment,
                                                      `entities_id` int unsigned NOT NULL default '0',
                                                      `is_recursive` tinyint NOT NULL DEFAULT '0',
                                                      `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                                                      `comment` text collate utf8mb4_unicode_ci,
                                                      PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `glpi_plugin_resources_destinations` (
                                                      `id` int unsigned NOT NULL auto_increment,
                                                      `entities_id` int unsigned NOT NULL default '0',
                                                      `is_recursive` tinyint NOT NULL DEFAULT '0',
                                                      `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                                                      `comment` text collate utf8mb4_unicode_ci,
                                                      PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `glpi_plugin_resources_resignationreasons` (
                                                            `id` int unsigned NOT NULL auto_increment,
                                                            `entities_id` int unsigned NOT NULL default '0',
                                                            `is_recursive` tinyint NOT NULL DEFAULT '0',
                                                            `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                                                            `comment` text collate utf8mb4_unicode_ci,
                                                            PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `glpi_plugin_resources_leavingdetails` (
                                                            `id` int unsigned NOT NULL auto_increment,
                                                            `entities_id` int unsigned NOT NULL default '0',
                                                            `is_recursive` tinyint NOT NULL DEFAULT '0',
                                                            `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                                                            `comment` text collate utf8mb4_unicode_ci,
                                                            PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `glpi_plugin_resources_workprofiles` (
                                                            `id` int unsigned NOT NULL auto_increment,
                                                            `entities_id` int unsigned NOT NULL default '0',
                                                            `is_recursive` tinyint NOT NULL DEFAULT '0',
                                                            `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                                                            `comment` text collate utf8mb4_unicode_ci,
                                                            PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `glpi_plugin_resources_leavinginformations` (
                                                        `id` int unsigned NOT NULL auto_increment,
                                                        `plugin_resources_resources_id` int unsigned NOT NULL default '0',
                                                        `plugin_resources_clients_id` int unsigned NOT NULL default '0',
                                                        `plugin_resources_destinations_id` int unsigned NOT NULL default '0',
                                                        `plugin_resources_workprofiles_id` int unsigned NOT NULL default '0',
                                                        `plugin_resources_resignationreasons_id` int unsigned NOT NULL default '0',
                                                        `users_id` int unsigned NOT NULL default '0',
                                                        `interview_date` timestamp NULL DEFAULT NULL,
                                                        `resignation_date` timestamp NULL DEFAULT NULL,
                                                        `wished_leaving_date` timestamp NULL DEFAULT NULL,
                                                        `effective_leaving_date` timestamp NULL DEFAULT NULL,
                                                        `pay_gap` tinyint NOT NULL DEFAULT '0',
                                                        `mission_lost` tinyint NOT NULL DEFAULT '0',
                                                        `company_name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                                                        `comment` text collate utf8mb4_unicode_ci,
                                                        PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

ALTER TABLE `glpi_plugin_resources_contracttypes`
    ADD `use_resignation_form` tinyint NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_contracttypes`
    ADD `use_entrance_information` tinyint NOT NULL DEFAULT '0';

ALTER TABLE `glpi_plugin_resources_resources`
    ADD `contract_type_change` tinyint NOT NULL DEFAULT '0';

ALTER TABLE `glpi_plugin_resources_resources`
    ADD `date_agreement_candidate` timestamp NULL DEFAULT NULL;
ALTER TABLE `glpi_plugin_resources_resources`
    ADD `plugin_resources_degreegroups_id` int unsigned NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_resources`
    ADD `plugin_resources_recruitingsources_id` int unsigned NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_resources`
    ADD `yearsexperience` int unsigned NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_resources`
    ADD `reconversion` tinyint NOT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_resources`
    ADD `date_of_last_contract_type` timestamp NULL DEFAULT NULL;
ALTER TABLE `glpi_plugin_resources_resources`
    ADD `last_contract_type` int unsigned NOT NULL DEFAULT '0';