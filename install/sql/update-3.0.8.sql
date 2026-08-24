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

ALTER TABLE glpi_plugin_resources_adconfigs ADD COLUMN `fonctionAD` varchar(255) COLLATE utf8mb4_unicode_ci default '';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `automatic_notification_declare_arrival_form` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_resources ADD COLUMN `phone` varchar(20) COLLATE utf8mb4_unicode_ci   default NULL;
ALTER TABLE glpi_plugin_resources_resources ADD COLUMN `cellphone` varchar(20) COLLATE utf8mb4_unicode_ci   default NULL;
ALTER TABLE glpi_plugin_resources_resources ADD COLUMN `remove_manager` int unsigned NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_resources ADD COLUMN `remove_order` TEXT COLLATE utf8mb4_unicode_ci;
ALTER TABLE glpi_plugin_resources_resources ADD COLUMN `computer_phone_equipment` TEXT COLLATE utf8mb4_unicode_ci;
ALTER TABLE glpi_plugin_resources_resources ADD COLUMN `softwares_requirements` TEXT COLLATE utf8mb4_unicode_ci;
ALTER TABLE glpi_plugin_resources_resources ADD COLUMN `furnitures_needs` TEXT COLLATE utf8mb4_unicode_ci;
ALTER TABLE glpi_plugin_resources_resources ADD COLUMN `other_needs` TEXT COLLATE utf8mb4_unicode_ci;
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `create_ticket_departure_instructions` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `default_assignment_group` int unsigned NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_resources ADD COLUMN `valid_resource_information` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `text_ticket_validation` TEXT COLLATE utf8mb4_unicode_ci;
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `hide_fieds_arrival_form` TEXT COLLATE utf8mb4_unicode_ci;