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

ALTER TABLE `glpi_plugin_resources_resources`
    DROP `plugin_resources_habilitations_id`;

ALTER TABLE glpi_plugin_resources_contracttypes
    ADD `use_habilitation_wizard` tinyint(1) NOT NULL DEFAULT '0';

DROP TABLE IF EXISTS `glpi_plugin_resources_habilitationlevels`;
CREATE TABLE `glpi_plugin_resources_habilitationlevels`
(
    `id`                             int(11)    NOT NULL AUTO_INCREMENT,
    `entities_id`                    int(11)    NOT NULL                  DEFAULT '0',
    `is_recursive`                   tinyint(1) NOT NULL                  DEFAULT '0',
    `name`                           varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
    `comment`                        text COLLATE utf8_unicode_ci,
    `number`                         tinyint(1) NOT NULL                  DEFAULT '0',
    `is_mandatory_creating_resource` tinyint(1) NOT NULL                  DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `name` (`name`),
    KEY `entities_id` (`entities_id`),
    KEY `is_recursive` (`is_recursive`)
) ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

ALTER TABLE glpi_plugin_resources_habilitations
    ADD `plugin_resources_habilitationlevels_id` int(11) NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_habilitations
    DROP `allow_resource_creation`;

DELETE
FROM `glpi_ruleactions`
WHERE `field` LIKE 'requiredfields_plugin_resources_habilitations_id';
