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

UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\Resource' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesResource';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\Task' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesTask';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\ResourceResting' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesResourceResting';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\ResourceHoliday' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesResourceHoliday';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\Directory' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesDirectory';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\Checklistconfig' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesChecklistconfig';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\ChoiceItem' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesChoiceItem';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\Employment' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesEmployment';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\Budget' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesBudget';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\Recap' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesRecap';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\Client' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesClient';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\Habilitation' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesHabilitation';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\ContractType' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesContractType';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\Team' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesTeam';
UPDATE `glpi_displaypreferences` SET `itemtype` = 'GlpiPlugin\\Resources\\ResignationReason' WHERE `glpi_displaypreferences`.`itemtype` = 'PluginResourcesResignationReason';
