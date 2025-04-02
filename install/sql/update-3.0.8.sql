ALTER TABLE `glpi_plugin_resources_adconfigs`
    ADD `user_initial` tinyint NOT NULL DEFAULT '1';
ALTER TABLE `glpi_plugin_resources_adconfigs`
    ADD `user_date` varchar(10) NOT NULL  default '0';
ALTER TABLE `glpi_plugin_resources_adconfigs`
    ADD `password_end` varchar(255) NOT NULL DEFAULT '';
ALTER TABLE `glpi_plugin_resources_configs`
    ADD `ticket_ldap_create` tinyint NOT NULL DEFAULT '1';
ALTER TABLE `glpi_plugin_resources_configs`
    ADD `ticket_ldap_update` tinyint NOT NULL DEFAULT '1';
ALTER TABLE `glpi_plugin_resources_configs`
    ADD `ticket_ldap_delete` tinyint NOT NULL DEFAULT '1';
