UPDATE `glpi_crontasks` SET `itemtype` = 'GlpiPlugin\\Resources\\Resource' WHERE `itemtype` = 'PluginResourcesResource';
UPDATE `glpi_crontasks` SET `itemtype` = 'GlpiPlugin\\Resources\\ResourceImport' WHERE `itemtype` = 'PluginResourcesImportResource';
UPDATE `glpi_crontasks` SET `itemtype` = 'GlpiPlugin\\Resources\\Task' WHERE `itemtype` = 'PluginResourcesTask';
UPDATE `glpi_crontasks` SET `itemtype` = 'GlpiPlugin\\Resources\\Checklist' WHERE `itemtype` = 'PluginResourcesChecklist';
UPDATE `glpi_crontasks` SET `itemtype` = 'GlpiPlugin\\Resources\\Employment' WHERE `itemtype` = 'PluginResourcesEmployment';
DELETE FROM `glpi_crontasks` WHERE `itemtype` LIKE '%PluginResources%';
