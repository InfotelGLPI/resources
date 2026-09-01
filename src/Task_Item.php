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
use CommonGLPI;
use DBConnection;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Migration;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Task_Item
 */
class Task_Item extends CommonDBTM
{
    public static $rightname = 'plugin_resources_task';

    /**
     * @return bool
     */
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * @return bool
     */
    public static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
    }

    /**
     * @param \CommonGLPI $item
     * @param int $withtemplate
     *
     * @return array|string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate) {
            if ($item->getType() == Task::class) {
                if ($_SESSION['glpishow_count_on_tabs']) {
                    return self::createTabEntry(
                        _n('Associated item', 'Associated items', 2),
                        self::countForResourceTask($item),
                    );
                }
                return self::createTabEntry(_n('Associated item', 'Associated items', 2));
            }
        }
        return '';
    }

    /**
     * @param \CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $self = new self();
        if ($item->getType() == Task::class) {
            $self->showItemFromPlugin($item->getID(), $withtemplate);
        }
        return true;
    }

    /**
     * @param Task $item
     *
     * @return int
     */
    public static function countForResourceTask(Task $item)
    {
        $types = Resource::getTypes();
        if (count($types) == 0) {
            return 0;
        }
        $dbu = new DbUtils();
        return $dbu->countElementsInTable(
            'glpi_plugin_resources_tasks_items',
            [
                "plugin_resources_tasks_id" => $item->getID(),
                "itemtype" => $types,
            ],
        );
    }

    /**
     * @param $plugin_resources_tasks_id
     * @param $items_id
     * @param $itemtype
     *
     * @return bool
     */
    public function getFromDBbyTaskAndItem($plugin_resources_tasks_id, $items_id, $itemtype)
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'  => $this->getTable(),
            'WHERE' => [
                'plugin_resources_tasks_id' => (int) $plugin_resources_tasks_id,
                'itemtype'                  => $itemtype,
                'items_id'                  => (int) $items_id,
            ],
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
     * @param $values
     */
    public function addTaskItem($values)
    {
        $args = explode(",", $values['item_item']);
        if (isset($args[0]) && isset($args[1])) {
            $this->add([
                'plugin_resources_tasks_id' => $values["plugin_resources_tasks_id"],
                'items_id' => $args[0],
                'itemtype' => $args[1],
            ]);
        }
    }

    /**
     * @param $plugin_resources_tasks_id
     * @param $items_id
     * @param $itemtype
     *
     * @return bool
     */
    public function deleteItemByTaskAndItem($plugin_resources_tasks_id, $items_id, $itemtype)
    {
        if ($this->getFromDBbyTaskAndItem($plugin_resources_tasks_id, $items_id, $itemtype)) {
            return $this->delete(['id' => $this->fields["id"]]);
        }

        return false;
    }

    /**
     * @param        $instID
     * @param string $withtemplate
     */
    public function showItemFromPlugin($instID, $withtemplate = '')
    {
        global $DB;

        if (empty($withtemplate)) {
            $withtemplate = 0;
        }

        $Task = new Task();
        if (!$Task->getFromDB($instID)) {
            return;
        }

        $plugin_resources_resources_id = $Task->fields["plugin_resources_resources_id"];
        $Resource = new Resource();
        $Resource->getFromDB($plugin_resources_resources_id);

        $canedit = $Resource->can($plugin_resources_resources_id, UPDATE)
            && $this->canCreate()
            && $withtemplate < 2;

        $iterator = $DB->request([
            'SELECT' => ['items_id', 'itemtype'],
            'FROM'   => $this->getTable(),
            'WHERE'  => ['plugin_resources_tasks_id' => (int) $instID],
            'ORDER'  => 'itemtype',
        ]);

        $used = [];
        $rows = [];
        $dbu  = new DbUtils();

        foreach ($iterator as $line) {
            $type = $line["itemtype"];
            if (!class_exists($type)) {
                continue;
            }
            $item = new $type();
            if (!$item->canView()) {
                continue;
            }

            $table = $dbu->getTableForItemType($type);
            $iterator_linked = $DB->request([
                'SELECT'     => [
                    "$table.*",
                    $this->getTable() . '.id AS items_id',
                ],
                'FROM'       => $this->getTable(),
                'INNER JOIN' => [
                    $table => [
                        'ON' => [
                            $table            => 'id',
                            $this->getTable() => 'items_id',
                        ],
                    ],
                ],
                'WHERE'      => [
                    $this->getTable() . '.itemtype'                  => $type,
                    $this->getTable() . '.items_id'                  => (int) $line["items_id"],
                    $this->getTable() . '.plugin_resources_tasks_id' => (int) $instID,
                ],
                'ORDER'      => "$table.name",
            ]);

            foreach ($iterator_linked as $data) {
                $ID = "";
                $itemID = $data["id"];
                $used[] = $itemID;
                if ($_SESSION["glpiis_ids_visible"] || empty($data["name"])) {
                    $ID = " (" . $data["id"] . ")";
                }
                $itemname = $data["name"];
                if ($type == 'User') {
                    $itemname = $dbu->getUserName($itemID);
                }

                $delete_form = '';
                if ($canedit) {
                    $delete_form = Html::getSimpleForm(
                        PLUGIN_RESOURCES_WEBDIR . '/front/task.form.php',
                        'deletetaskitem',
                        _x('button', 'Delete permanently'),
                        ['id' => $data["items_id"]],
                    );
                }

                $rows[] = [
                    'type'        => $item->getTypeName(),
                    'link'        => '<a href="' . htmlescape($type::getFormURLWithID($itemID)) . '">'
                        . htmlescape($itemname . $ID) . '</a>',
                    'is_deleted'  => isset($data['is_deleted']) && $data['is_deleted'] == '1',
                    'delete_form' => $delete_form,
                ];
            }
        }

        $items_dropdown = '';
        if ($canedit) {
            $items_dropdown = (string) (new Resource_Item())->dropdownItems(
                $plugin_resources_resources_id,
                $used,
                false,
            );
        }

        TemplateRenderer::getInstance()->display('@resources/task_item_form.html.twig', [
            'form_action'    => "./task.form.php",
            'can_edit'       => $canedit,
            'rows'           => $rows,
            'tasks_id'       => $instID,
            'items_dropdown' => $items_dropdown,
        ]);
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
                        `plugin_resources_tasks_id` int {$default_key_sign}                            NOT NULL DEFAULT '0',
                        `items_id`                  int {$default_key_sign}                            NOT NULL DEFAULT '0' COMMENT 'RELATION to various table, according to itemtype (id)',
                        `itemtype`                  varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'see .class.php file',
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unicity` (`plugin_resources_tasks_id`, `itemtype`, `items_id`),
                        KEY `FK_device` (`items_id`, `itemtype`),
                        KEY `item` (`itemtype`, `items_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
