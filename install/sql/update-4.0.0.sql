RENAME TABLE `glpi_plugin_resources_functions` TO `glpi_plugin_resources_resourcefunctions`;
ALTER TABLE `glpi_plugin_resources_contracttypes` ADD `use_documents_wizard` tinyint NOT NULL DEFAULT '0';
