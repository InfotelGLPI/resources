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

use CommonDBTM;
use CommonGLPI;
use DBConnection;
use DbUtils;
use Dropdown;
use Html;
use Migration;
use Toolbox;
use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class TicketCategory
 */
class TicketCategory extends CommonDBTM
{

    /**
     * functions mandatory
     * getTypeName(), canCreate(), canView()
     * */
    static function getTypeName($nb = 0)
    {
        return __('Category of created tickets', 'resources');
    }

    /**
     * @param $category
     *
     * @return bool
     */
    public function getFromDBbyCategory($category)
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'  => $this->getTable(),
            'WHERE' => ['ticketcategories_id' => (int) $category],
        ]);
        if (count($iterator) != 1) {
            return false;
        }
        $this->fields = $iterator->current();
        if (is_array($this->fields) && count($this->fields)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $category
     */
    public function addTicketCategory($category)
    {
        if ($this->getFromDBbyCategory($category)) {
            $this->update([
                'id' => $this->fields['id'],
                'ticketcategories_id' => $category,
            ]);
        } else {
            $this->add([
                'id' => 1,
                'ticketcategories_id' => $category,
            ]);
        }
    }

    static function getIcon()
    {
        return "ti ti-file-info";
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return self::createTabEntry(self::getTypeName());
    }

    /**
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     * @see CommonGLPI::displayTabContentForItem()
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Config::class) {
            $self = new self();
            $self->showConfigForm();
        }
        return true;
    }

    /**
     * @param $target
     */
    public function showConfigForm()
    {
        $dbu        = new DbUtils();
        $categories = $dbu->getAllDataFromTable($this->getTable());

        $data = [
            'form_action'  => Toolbox::getItemTypeFormURL(Config::class),
            'warning'      => __('Define ticket category from checklist creation', 'resources'),
            'title'        => __('Category of created tickets', 'resources'),
            'has_category' => !empty($categories),
        ];

        if (!empty($categories)) {
            $category              = reset($categories);
            $data['id']            = $category['id'];
            $data['category_name'] = Dropdown::getDropdownName('glpi_itilcategories', $category['ticketcategories_id']);
        } else {
            ob_start();
            Dropdown::show('ITILCategory', ['name' => 'ticketcategories_id']);
            $data['category_dropdown'] = ob_get_clean();
        }

        TemplateRenderer::getInstance()->display('@resources/ticketcategory_config.html.twig', $data);
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
                        `id`                  int {$default_key_sign} NOT NULL auto_increment,
                        `ticketcategories_id` int {$default_key_sign} NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_ticketcategories (id)',
                        PRIMARY KEY (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
