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

namespace GlpiPlugin\Resources;

use CommonDBTM;
use DBConnection;
use Glpi\Application\View\TemplateRenderer;
use Migration;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Import
 */
class Import extends CommonDBTM
{
    public static $rightname = 'plugin_resources_import';
    public $dohistory = true;

    public static $keyInOtherTables = 'plugin_resources_imports_id';

    public static function getFormUrl($full = true)
    {
        global $CFG_GLPI;
        return PLUGIN_RESOURCES_WEBDIR . "/front/import.form.php";
    }

    public static function getIndexUrl()
    {
        global $CFG_GLPI;
        return PLUGIN_RESOURCES_WEBDIR . "/front/import.php";
    }

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return _n('Import', 'Imports', $nb, 'resources');
    }

    /**
     * Define tabs to display
     *
     * NB : Only called for existing object
     *
     * @param $options array
     *     - withtemplate is a template view ?
     *
     * @return array containing the onglets
     **/
    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(ImportColumn::class, $ong, $options);
        return $ong;
    }

    /***
     *
     *
     * @param $identifier
     * @return array
     */
    public function getChildColumns($importID, $identifier = null)
    {
        $column = new ImportColumn();

        $input = [
            ImportColumn::$items_id => $importID,
        ];

        if (!is_null($identifier)) {
            $input['is_identifier'] = $identifier;
        }

        return $column->find($input);
    }

    public function showTitle($links = true, $display = true)
    {
        $html = TemplateRenderer::getInstance()->render('@resources/import_title.html.twig', [
            'title'      => $this->getTypeName(),
            'links'      => $links,
            'index_url'  => self::getIndexUrl(),
            'form_url'   => self::getFormUrl(),
            'can_create' => Session::haveright(self::$rightname, CREATE),
        ]);

        if ($display) {
            echo $html;
        }

        return $html;
    }

    /**
     * Print survey
     *
     * @param       $ID
     * @param array $options
     *
     * @return bool
     */
    public function showForm($ID, $options = [])
    {
        if (!$this->canView()) {
            return false;
        }
        $this->initForm($ID, $options);

        TemplateRenderer::getInstance()->display('@resources/import_form.html.twig', [
            'item'   => $this,
            'params' => $options,
        ]);

        return true;
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
                        `name`          varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `comment`       TEXT COLLATE utf8mb4_unicode_ci,
                        `is_active`     tinyint      NOT NULL                   DEFAULT '0',
                        `is_deleted`    tinyint      NOT NULL                   DEFAULT '0',
                        `date_creation` timestamp    NULL                       DEFAULT NULL,
                        `date_mod`      timestamp    NULL                       DEFAULT NULL,
                        PRIMARY KEY (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
