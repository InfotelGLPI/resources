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

DROP TABLE IF EXISTS `glpi_plugin_resources_employee`;
CREATE TABLE `glpi_plugin_resources_employee`
(
    `ID`           int(11)                              NOT NULL auto_increment,
    `FK_resources` int(11)                              NOT NULL default '0',
    `FK_employer`  int(11)                              NOT NULL default '0',
    `FK_client`    int(11)                              NOT NULL default '0',
    `matricule`    varchar(255) collate utf8_unicode_ci NOT NULL default '',
    `is_template`  smallint(6)                          NOT NULL default '0',
    PRIMARY KEY (`ID`)
) ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_dropdown_plugin_resources_employer`;
CREATE TABLE `glpi_dropdown_plugin_resources_employer`
(
    `ID`          int(11)                              NOT NULL auto_increment,
    `FK_entities` int(11)                              NOT NULL default '0',
    `name`        varchar(255) collate utf8_unicode_ci NOT NULL default '',
    `comments`    text,
    PRIMARY KEY (`ID`),
    KEY `name` (`name`)
) ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

DROP TABLE IF EXISTS `glpi_dropdown_plugin_resources_client`;
CREATE TABLE `glpi_dropdown_plugin_resources_client`
(
    `ID`          int(11)                              NOT NULL auto_increment,
    `FK_entities` int(11)                              NOT NULL default '0',
    `name`        varchar(255) collate utf8_unicode_ci NOT NULL default '',
    `comments`    text,
    PRIMARY KEY (`ID`),
    KEY `name` (`name`)
) ENGINE = MyISAM
  DEFAULT CHARSET = utf8
  COLLATE = utf8_unicode_ci;

ALTER TABLE `glpi_plugin_resources_profiles`
    ADD `employer` char(1) default NULL;

ALTER TABLE `glpi_plugin_resources`
    ADD `firstname` varchar(255) collate utf8_unicode_ci NOT NULL default '' AFTER `name`;

INSERT INTO `glpi_displaypreferences` (`ID`, `type`, `num`, `rank`, `FK_users`)
VALUES (NULL, '4303', '3', '1', '0');
INSERT INTO `glpi_displaypreferences` (`ID`, `type`, `num`, `rank`, `FK_users`)
VALUES (NULL, '4303', '4', '2', '0');
INSERT INTO `glpi_displaypreferences` (`ID`, `type`, `num`, `rank`, `FK_users`)
VALUES (NULL, '4303', '5', '5', '0');
INSERT INTO `glpi_displaypreferences` (`ID`, `type`, `num`, `rank`, `FK_users`)
VALUES (NULL, '4303', '6', '4', '0');
INSERT INTO `glpi_displaypreferences` (`ID`, `type`, `num`, `rank`, `FK_users`)
VALUES (NULL, '4303', '7', '7', '0');
INSERT INTO `glpi_displaypreferences` (`ID`, `type`, `num`, `rank`, `FK_users`)
VALUES (NULL, '4303', '8', '6', '0');
INSERT INTO `glpi_displaypreferences` (`ID`, `type`, `num`, `rank`, `FK_users`)
VALUES (NULL, '4303', '9', '8', '0');
INSERT INTO `glpi_displaypreferences` (`ID`, `type`, `num`, `rank`, `FK_users`)
VALUES (NULL, '4303', '10', '3', '0');
INSERT INTO `glpi_displaypreferences` (`ID`, `type`, `num`, `rank`, `FK_users`)
VALUES (NULL, '4303', '11', '9', '0');
INSERT INTO `glpi_displaypreferences` (`ID`, `type`, `num`, `rank`, `FK_users`)
VALUES (NULL, '4303', '12', '10', '0');
