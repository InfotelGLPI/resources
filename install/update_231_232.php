<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2022 by the resources Development Team.

 https://github.com/InfotelGLPI/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of resources.

 resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/**
 * Update from 2.3.1 to 2.3.2
 *
 * @return bool for success (will die for most error)
 * */
function update231_232() {
   global $DB;

   $query = "SELECT *
             FROM `glpi_plugin_resources_accessprofiles`";

   if ($result = $DB->doQuery($query)) {
      if ($DB->numrows($result) > 0) {
         while ($data = $DB->fetchAssoc($result)) {

            $query_add = "INSERT INTO `glpi_plugin_resources_habilitations` (entities_id, is_recursive, name, completename, 
                                                                              comment, allow_resource_creation) 
                          VALUES ('" . $data['entities_id'] . "', '" . $data['is_recursive'] . "', 
                          '" . $data['name'] . "', '" . $data['name'] . "', '" . $data['comment'] . "', '1')";
            $DB->doQuery($query_add);

            $query_id = "SELECT `id`
                         FROM `glpi_plugin_resources_habilitations`
                         WHERE `name` LIKE '" . $data['name'] . "'";
            if ($result_id = $DB->doQuery($query_id)) {
               $id = $DB->result($result_id, 1, 'id');

               $query_resource = "SELECT *
                                 FROM `glpi_plugin_resources_resources`
                                 WHERE `plugin_resources_habilitations_id` = '" . $data['id'] . "'";
               if ($result_resource = $DB->doQuery($query_resource)) {
                  if ($DB->numrows($result_resource) > 0) {
                     while ($data_resource = $DB->fetchAssoc($result_resource)) {
                        if ($data_resource['is_template'] == 0) {
                           $query_insert = "INSERT INTO `glpi_plugin_resources_resourcehabilitations` 
                                             (`plugin_resources_resources_id`, `plugin_resources_habilitations_id`) 
                                             VALUES ('" . $data_resource['id'] . "', '$id')";

                           $DB->doQuery($query_insert);
                        } else {
                           $query_update = "UPDATE glpi_plugin_resources_resources 
                                         SET `plugin_resources_habilitations_id` = '$id'
                                         WHERE `id` = '" . $data_resource['id'] . "'";
                           $DB->doQuery($query_update);
                        }

                     }
                  }
               }
            }

         }
      }

   }

   $query_drop = "DROP TABLE IF EXISTS `glpi_plugin_resources_accessprofiles`;";
   $DB->doQuery($query_drop);

   $query_delete_rule = "UPDATE `glpi_ruleactions` SET `field` = 'requiredfields_plugin_resources_habilitations_id'
                          WHERE `glpi_ruleactions`.`field` = 'requiredfields_plugin_resources_accessprofiles_id';";
   $DB->doQuery($query_delete_rule);

   return true;
}
