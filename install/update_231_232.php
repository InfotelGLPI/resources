<?php

/*
 -------------------------------------------------------------------------
 Activity plugin for GLPI
 Copyright (C) 2013 by the Activity Development Team.
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Activity.

 Activity is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Activity is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Activity. If not, see <http://www.gnu.org/licenses/>.
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

   if ($result = $DB->query($query)) {
      if ($DB->numrows($result) > 0) {
         while ($data = $DB->fetch_assoc($result)) {


            $query_add = "INSERT INTO `glpi_plugin_resources_habilitations` (entities_id, is_recursive, name, completename, 
                                                                              comment, allow_resource_creation) 
                          VALUES ('".$data['entities_id']."', '".$data['is_recursive']."', 
                          '".$data['name']."', '".$data['name']."', '".$data['comment']."', '1')";
            $id = $DB->query($query_add);

            $query_resource = "SELECT *
                              FROM `glpi_plugin_resources_resources`
                              WHERE `is_template` = 0 AND `plugin_resources_habilitations_id` = '".$data['id']."'";
            if ($result_resource = $DB->query($query_resource)) {
               if ($DB->numrows($result_resource) > 0) {
                  while ($data_resource = $DB->fetch_assoc($result_resource)) {
                     $query_update = "UPDATE glpi_plugin_resources_resources 
                                      SET `plugin_resources_habilitations_id` = $id
                                      WHERE `id` = '".$data_resource['id']."'";
                     $DB->query($query_update);
                  }
               }
            }

         }
      }

   }

   $query_add = "DROP TABLE IF EXISTS `glpi_plugin_resources_accessprofiles`;";
   $DB->query($query_add);


   return true;
}