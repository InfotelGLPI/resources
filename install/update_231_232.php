<?php

/**
 * -------------------------------------------------------------------------
 * resources plugin for GLPI
 * Copyright (C) 2015-2026 by the resources Development Team.
 *
 * https://github.com/InfotelGLPI/resources
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of resources.
 *
 * resources is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * resources is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with resources. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

/**
 * Update from 2.3.1 to 2.3.2
 *
 * Every statement goes through the query builder: the names and comments of the legacy
 * access profiles are stored values, an apostrophe in one of them used to end the SQL
 * literal they were concatenated into (and to make the profile migration fail silently).
 *
 * @return bool for success (will die for most error)
 * */
function update231_232()
{
    global $DB;

    foreach ($DB->request(['FROM' => 'glpi_plugin_resources_accessprofiles']) as $data) {
        $DB->insert('glpi_plugin_resources_habilitations', [
            'entities_id'             => (int) $data['entities_id'],
            'is_recursive'            => (int) $data['is_recursive'],
            'name'                    => $data['name'],
            'completename'            => $data['name'],
            'comment'                 => $data['comment'],
            'allow_resource_creation' => 1,
        ]);
        // The row just written, not a lookup by name: another habilitation could share it
        $id = (int) $DB->insertId();

        $resources = $DB->request([
            'SELECT' => ['id', 'is_template'],
            'FROM'   => 'glpi_plugin_resources_resources',
            'WHERE'  => ['plugin_resources_habilitations_id' => (int) $data['id']],
        ]);
        foreach ($resources as $data_resource) {
            if ($data_resource['is_template'] == 0) {
                $DB->insert('glpi_plugin_resources_resourcehabilitations', [
                    'plugin_resources_resources_id'     => (int) $data_resource['id'],
                    'plugin_resources_habilitations_id' => $id,
                ]);
            } else {
                $DB->update(
                    'glpi_plugin_resources_resources',
                    ['plugin_resources_habilitations_id' => $id],
                    ['id' => (int) $data_resource['id']],
                );
            }
        }
    }

    $DB->dropTable('glpi_plugin_resources_accessprofiles', true);

    $DB->update(
        'glpi_ruleactions',
        ['field' => 'requiredfields_plugin_resources_habilitations_id'],
        ['field' => 'requiredfields_plugin_resources_accessprofiles_id'],
    );

    return true;
}
