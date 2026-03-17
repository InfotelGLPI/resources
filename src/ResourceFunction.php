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

use CommonDropdown;
use DBConnection;
use Migration;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ResourceFunction
 */
class ResourceFunction extends CommonDropdown
{

    static $rightname = 'plugin_resources_role';

    /**
     * @param $nb
     **@since 0.85
     *
     */
    static function getTypeName($nb = 0)
    {
        return _n('Function', 'Functions', $nb, 'resources');
    }

    /**
     * @return
     */
    static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * @return
     */
    static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
    }


    /**
     * @return array
     */
    function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();
        return $tab;
    }


    /**
     * is_active = 1 during a creation
     *
     * @return
     */
    function post_getEmpty()
    {
        $this->fields['is_active'] = 1;
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
                        `entities_id`  int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `is_recursive` tinyint      NOT NULL                   DEFAULT '0',
                        `name`         varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `comment`      TEXT COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`),
                        KEY `name` (`name`),
                        KEY `entities_id` (`entities_id`),
                        KEY `is_recursive` (`is_recursive`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
