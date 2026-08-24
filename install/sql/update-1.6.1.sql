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
    ADD `plugin_resources_resourcestates_id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_plugin_resources_resourcestates (id)',
    ADD `picture`                            varchar(100) collate utf8_unicode_ci default NULL,
    ADD INDEX (`plugin_resources_resourcestates_id`);

CREATE TABLE `glpi_plugin_resources_resourcestates`
(
    `id`          int(11) NOT NULL auto_increment,
    `entities_id` int(11) NOT NULL                     default '0',
    `name`        varchar(255) collate utf8_unicode_ci default NULL,
    `comment`     text collate utf8_unicode_ci,
    PRIMARY KEY (`id`),
    KEY `name` (`name`)
) ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;
