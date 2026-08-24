--
-- -------------------------------------------------------------------------
-- resources plugin for GLPI
-- Copyright (C) 2015-2026 by the resources Development Team.
--
-- https://github.com/InfotelGLPI/resources
-- -------------------------------------------------------------------------
--
-- LICENSE
--
-- This file is part of resources.
--
-- resources is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- resources is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with resources. If not, see <http://www.gnu.org/licenses/>.
-- --------------------------------------------------------------------------
--

ALTER TABLE `glpi_plugin_resources_configs`
    ADD `mandatory_checklist` tinyint(1) NOT NULL default '0';
ALTER TABLE `glpi_plugin_resources_configs`
    ADD `mandatory_adcreation` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs`
    ADD `allow_without_contract` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs`
    ADD `plugin_resources_resourcetemplates_id` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs`
    ADD `plugin_resources_resourcestates_id_arrival` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs`
    ADD `plugin_resources_resourcestates_id_departure` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_adconfigs`
    ADD `mail_prefix` INT(11) NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_adconfigs`
    ADD `mail_suffix` varchar(255) NOT NULL default '';

ALTER TABLE `glpi_plugin_resources_resources`
    ADD `plugin_resources_roles_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_resources_roles (id)';
ALTER TABLE `glpi_plugin_resources_resources`
    ADD `matricule` varchar(255) NOT NULL default '';
ALTER TABLE `glpi_plugin_resources_configs`
    ADD `reaffect_checklist_change` TINYINT(1) NOT NULL DEFAULT '1';

CREATE TABLE `glpi_plugin_resources_roles`
(
    `id`           int(11)    NOT NULL auto_increment,
    `entities_id`  int(11)    NOT NULL                  default '0',
    `is_recursive` tinyint(1) NOT NULL                  DEFAULT '0',
    `name`         varchar(255) collate utf8_unicode_ci default NULL,
    `comment`      text collate utf8_unicode_ci,
    PRIMARY KEY (`id`),
    KEY `name` (`name`),
    KEY `entities_id` (`entities_id`),
    KEY `is_recursive` (`is_recursive`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

