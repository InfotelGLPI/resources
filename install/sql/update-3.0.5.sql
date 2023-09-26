ALTER TABLE `glpi_plugin_resources_resources`
    ADD `date_of_last_location` timestamp NULL DEFAULT NULL;
ALTER TABLE `glpi_plugin_resources_resources`
    ADD `last_location` int unsigned NOT NULL DEFAULT '0';