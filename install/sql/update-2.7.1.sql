ALTER TABLE `glpi_plugin_resources_departments` ADD `plugin_resources_employers_id` INT(11) NULL DEFAULT '0' AFTER `entities_id`;

CREATE TABLE `glpi_plugin_resources_linkads` (
   `id` int(11) NOT NULL auto_increment,
   `plugin_resources_resources_id` int(11) NOT NULL default '0',
   `auth_id` int(11) NOT NULL default '0',
   `login` varchar(255) collate utf8_unicode_ci default NULL,
   `mail` varchar(255) collate utf8_unicode_ci default NULL,
   `phone` varchar(255) collate utf8_unicode_ci default NULL,
   `role` varchar(255) collate utf8_unicode_ci default NULL,
   `cellphone` varchar(255) collate utf8_unicode_ci default NULL,
   `action_done` tinyint(1) NOT NULL default '0',
   PRIMARY KEY  (`id`),
   UNIQUE KEY `unicity` (`login`),
   UNIQUE KEY `unicity2` (`plugin_resources_resources_id`),
   KEY `login` (`login`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `glpi_plugin_resources_linkmetademands` (
   `id` int(11) NOT NULL auto_increment,
   `plugin_metademands_fields_id` int(11) NOT NULL default '0',
   `plugin_metademands_metademands_id` int(11) NOT NULL default '0',
   `check_value` TEXT collate utf8_unicode_ci default NULL,
   `checklist_in` TEXT collate utf8_unicode_ci default NULL,
   `checklist_out` TEXT collate utf8_unicode_ci default NULL,
   PRIMARY KEY  (`id`),
   UNIQUE KEY `unicity` (`plugin_metademands_fields_id`),
   KEY `plugin_metademands_fields_id` (`plugin_metademands_fields_id`)

) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `glpi_plugin_resources_adconfigs` (
   `id` int(11) NOT NULL auto_increment,
   `auth_id` int(11) NOT NULL default '0',
   `login` varchar(255) collate utf8_unicode_ci default '',
   `password` varchar(255) collate utf8_unicode_ci default '',
   `creation_categories_id` TEXT NOT NULL ,
   `modification_categories_id` TEXT NOT NULL ,
   `deletion_categories_id` TEXT NOT NULL ,
   `logAD` varchar(255) collate utf8_unicode_ci default '',
   `nameAD` varchar(255) collate utf8_unicode_ci default '',
   `phoneAD` varchar(255) collate utf8_unicode_ci default NULL,
   `companyAD` varchar(255) collate utf8_unicode_ci default '',
   `departmentAD` varchar(255) collate utf8_unicode_ci default '',
   `firstnameAD` varchar(255) collate utf8_unicode_ci default '',
   `mailAD` varchar(255) collate utf8_unicode_ci default '',
   `contractEndAD` varchar(255) collate utf8_unicode_ci default '',
   `contractTypeAD` varchar(255) collate utf8_unicode_ci default '',
   `ouDesactivateUserAD` varchar(255) collate utf8_unicode_ci default '',
   `ouUser` varchar(255) collate utf8_unicode_ci default '',
   `cellPhoneAD` varchar(255) collate utf8_unicode_ci default '',
   `roleAD` varchar(255) collate utf8_unicode_ci default '',
   `first_form`  int(11) NOT NULL default '0',
   `second_form`  int(11) NOT NULL default '0',
   PRIMARY KEY  (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
INSERT INTO `glpi_plugin_resources_adconfigs` VALUES(1, 0,'','', 0, 0, 0,'','','','','','','','','','','','','',0,0);

ALTER TABLE `glpi_plugin_resources_configs` ADD `create_ticket_departure` tinyint(1) NOT NULL default '0' AFTER `sales_manager`;
ALTER TABLE `glpi_plugin_resources_configs` ADD `categories_id` INT(11) NULL DEFAULT '0' AFTER `create_ticket_departure`;

ALTER TABLE `glpi_plugin_resources_checklistconfigs` ADD `itemtype` VARCHAR(255) NOT NULL DEFAULT '' AFTER `comment`;
ALTER TABLE `glpi_plugin_resources_checklistconfigs` ADD `items` int(11) NOT NULL default '0' AFTER `itemtype`;