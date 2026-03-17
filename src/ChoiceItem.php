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

namespace GlpiPlugin\Resources;

use CommonTreeDropdown;
use DBConnection;
use Migration;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ChoiceItem
 */
class ChoiceItem extends CommonTreeDropdown
{

    /**
     * @param $nb
     **@since 0.85
     *
     */
    static function getTypeName($nb = 0)
    {
        return _n('Type of need', 'Types of need', $nb, 'resources');
    }

    /**
     * Have I the global right to "view" the Object
     *
     * Default is true and check entity if the objet is entity assign
     *
     * May be overloaded if needed
     *
     * @return
     **/
    static function canView(): bool
    {
        return Session::haveRight('plugin_resources', READ);
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return
     **/
    static function canCreate(): bool
    {
        return Session::haveRightsOr('dropdown', [CREATE, UPDATE, DELETE]);
    }

    /**
     * Return Additional Fileds for this type
     **/
    function getAdditionalFields()
    {
        return [
            [
                'name' => $this->getForeignKeyField(),
                'label' => __('As child of'),
                'type' => 'parent',
                'list' => false
            ],
            [
                'name' => 'is_helpdesk_visible',
                'label' => __('Visible in the simplified interface'),
                'type' => 'bool',
                'list' => true
            ]
        ];
    }

    /**
     * Get search function for the class
     *
     * @return array of search option
     **/
    function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id' => '11',
            'table' => $this->getTable(),
            'field' => 'is_helpdesk_visible',
            'name' => __('Visible in the simplified interface'),
            'datatype' => 'bool'
        ];

        return $tab;
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
                        `entities_id`                     int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `is_recursive`                    tinyint      NOT NULL                   DEFAULT '0',
                        `plugin_resources_choiceitems_id` int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `completename`                    TEXT COLLATE utf8mb4_unicode_ci,
                        `level`                           int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `ancestors_cache`                 longTEXT COLLATE utf8mb4_unicode_ci,
                        `sons_cache`                      longTEXT COLLATE utf8mb4_unicode_ci,
                        `is_helpdesk_visible`             int {$default_key_sign} NOT NULL                   DEFAULT '1',
                        `name`                            varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `comment`                         TEXT COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unicity` (`entities_id`, `plugin_resources_choiceitems_id`, `name`),
                        KEY `name` (`name`),
                        KEY `entities_id` (`entities_id`),
                        KEY `plugin_resources_choiceitems_id` (`plugin_resources_choiceitems_id`),
                        KEY `is_recursive` (`is_recursive`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}

