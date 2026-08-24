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

UPDATE glpi_notificationtemplates SET itemtype = 'GlpiPlugin\\Resources\\Resource' WHERE itemtype = 'PluginResourcesResource';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `search_default_my_resources` int unsigned NOT NULL default '1';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `hidden_first_form` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `needs_tab_access` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `assignment_group_second_ticket` int unsigned NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `send_second_ticket_validation` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `view_notification_tab` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `view_needs_parts` varchar(255) NOT NULL DEFAULT '';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `freeze_form_after_validation` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `can_view_synchronisationAD` varchar(255) NOT NULL DEFAULT '';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `order_order` varchar(4) NOT NULL DEFAULT 'ASC';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `order_column` int unsigned NOT NULL default '1';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `send_second_ticket_remove` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `remove_at_midnight` tinyint NOT NULL default '1';
ALTER TABLE glpi_plugin_resources_adconfigs ADD COLUMN `use_password_module` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_adconfigs ADD COLUMN `default_account_password` varchar(255) COLLATE utf8mb4_unicode_ci default '';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `use_module_validation` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `use_module_duplicata_ticket` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_configs ADD COLUMN `use_module_departure_instruction` tinyint NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_adconfigs ADD COLUMN `format_default_account_password` int unsigned NOT NULL default '0';
ALTER TABLE glpi_plugin_resources_adconfigs ADD COLUMN `prefix_default_account_password` int unsigned NOT NULL default '0';