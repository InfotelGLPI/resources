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

ALTER TABLE glpi_plugin_resources_configs
    ADD `security_compliance` tinyint(1) NOT NULL default '0';

ALTER TABLE glpi_plugin_resources_clients
    ADD `security_and` tinyint(1) NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_clients
    ADD `security_fifour` tinyint(1) NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_clients
    ADD `security_gisf` tinyint(1) NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_clients
    ADD `security_cfi` tinyint(1) NOT NULL default '0';
