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
