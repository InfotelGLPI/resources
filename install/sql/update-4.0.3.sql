ALTER TABLE `glpi_plugin_resources_configs` CHANGE `plugin_resources_resourcetemplates_id` `plugin_resources_resourcetemplates_id` INT NULL DEFAULT '0';
ALTER TABLE `glpi_plugin_resources_configs` DROP `import_external_datas`;
