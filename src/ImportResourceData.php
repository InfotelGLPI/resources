<?php

/*
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2015-2026 by the resources Development Team.

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

namespace GlpiPlugin\Resources;

use CommonDBChild;
use DBConnection;
use Migration;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ImportResourceData
 */
class ImportResourceData extends CommonDBChild
{

    static $rightname = 'plugin_resources_importresourcedatas';

    // From CommonDBChild
    public static $itemtype = ImportResource::class;
    public static $items_id = 'plugin_resources_importresources_id';


    public function prepareInput($name, $value, $parent_id, $column_id)
    {
        return [
            'name' => $name,
            'value' => $value,
            self::$items_id => $parent_id,
            'plugin_resources_importcolumns_id' => $column_id
        ];
    }

    public function purgeDatabase()
    {
        global $DB;

        return $DB->delete(self::getTable(), [1]);
    }

    public function purgeDataByImportResource($importResourceId)
    {
        global $DB;

        return $DB->delete(self::getTable(), ['plugin_resources_importresources_id' => $importResourceId]);
    }

    public function getFromParentAndIdentifierLevel($importResourceId, $identifierLevel = null, $order = [])
    {
        global $DB;

        $criteria = [
            'SELECT'     => [
                'data.id',
                'data.name',
                'data.value',
                'ic.resource_column',
                'ic.type',
            ],
            'FROM'       => self::getTable() . ' AS data',
            'INNER JOIN' => [
                ImportColumn::getTable() . ' AS ic' => [
                    'ON' => [
                        'ic'   => 'id',
                        'data' => 'plugin_resources_importcolumns_id',
                    ],
                ],
            ],
            'WHERE'      => ['data.plugin_resources_importresources_id' => (int) $importResourceId],
        ];

        if ($identifierLevel) {
            $criteria['WHERE']['ic.is_identifier'] = (int) $identifierLevel;
        }

        if (count($order)) {
            $criteria['ORDER'] = $order;
        }

        $iterator = $DB->request($criteria);
        $temp = [];

        foreach ($iterator as $data) {
            $temp[] = $data;
        }
        return $temp;
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();
        $table  = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                        `id`           int {$default_key_sign} NOT NULL auto_increment,
                        `name`                                varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                        `value`                               varchar(255) COLLATE utf8mb4_unicode_ci NULL,
                        `plugin_resources_importresources_id` int {$default_key_sign}                            NOT NULL DEFAULT '0',
                        `plugin_resources_importcolumns_id`   int {$default_key_sign}                            NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
