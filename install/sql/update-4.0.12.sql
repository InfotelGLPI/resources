UPDATE `glpi_fieldunicities` SET `itemtype` = 'GlpiPlugin\\Resources\\Resource' WHERE `itemtype` = 'PluginResourcesResource';
UPDATE `glpi_savedsearches` set query = REPLACE(query,'PluginResourcesResource','GlpiPlugin\\Resources\\Resource');
