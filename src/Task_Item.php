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
use Glpi\Exception\Http\BadRequestHttpException;
use Html;
use Migration;
use Session;

/**
 * Class Task_Item
 */
class Task_Item extends CommonDBTM
{
    // No entity of its own: a link is read and written with its task
    use TaskChildItemRightTrait;

    public static $rightname = 'plugin_resources_task';

    /**
     * Set by server-side code that checked the linked object itself, the next add() of this
     * object then skips the READ test of the session on it: the copy of the tasks of a
     * template (Task::cloneItem()), which checks each object against the entity of the new
     * resource through Resource_Item::isLinkableTo(). Consumed by prepareInputForAdd(), never
     * read from the input.
     */
    public bool $item_checked_by_caller = false;

    /**
     * @return bool
     */
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
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
     * @param array $input
     *
     * @return array|false
     */
    public function prepareInputForAdd($input)
    {
        $checked_by_caller            = $this->item_checked_by_caller;
        $this->item_checked_by_caller = false;

        // The itemtype travels in the posted form (front/task.form.php) and in the
        // massive action input (Task::processMassiveActionsForOneItemtype), and every
        // reader of this table instantiates it. Validate the value here, at the sink,
        // the way ajax/linkItems.php does, so no writer can bypass the allow-list.
        if (!in_array($input['itemtype'] ?? '', Resource::getTypes(true), true)) {
            return false;
        }

        // The callers authorise the task, never the object linked to it, whose name and state
        // the tab of the task displays back: the session has to be allowed to read that very
        // object (right and entity), unless the caller checked it (item_checked_by_caller).
        try {
            $items_id = Resource::getPostedId($input['items_id'] ?? null);
        } catch (BadRequestHttpException) {
            $items_id = 0;
        }
        $item = getItemForItemtype($input['itemtype']);
        if ($items_id <= 0
            || !$item
            || !($checked_by_caller ? $item->getFromDB($items_id) : $item->can($items_id, READ))) {
            Session::addMessageAfterRedirect(
                __("You don't have permission to perform this action."),
                false,
                ERROR,
            );
            return false;
        }
        $input['items_id'] = $items_id;

        return $input;
    }

    /**
     * A link is created or deleted, never changed: the task and the object were checked when
     * it was created, and an update (the API one included) is authorised on the task stored
     * only. Moving the link to another task, or pointing it to another object or to a type
     * out of the allow-list, would skip both checks.
     *
     * @param array $input
     *
     * @return array
     */
    public function prepareInputForUpdate($input)
    {
        unset($input['plugin_resources_tasks_id'], $input['itemtype'], $input['items_id']);

        return $input;
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
            // The type comes from the database: only the ones the plugin links are instantiated
            if (!in_array($type, Resource::getTypes(true), true)) {
                continue;
            }
            $item = getItemForItemtype($type);
            if (!$item || !$item->canView()) {
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
                // canView() above is the global right on the type: each object is listed only
                // if the session may read it (right and entity), a link created before that
                // was checked included.
                $linked = getItemForItemtype($type);
                if (!$linked || !$linked->can((int) $itemID, READ)) {
                    continue;
                }
                if ($_SESSION["glpiis_ids_visible"] || empty($data["name"])) {
                    $ID = " (" . $data["id"] . ")";
                }
                $itemname = $data["name"];
                if ($type == 'User') {
                    $itemname = $dbu->getUserName($itemID);
                }

                $rows[] = [
                    'type'        => $item->getTypeName(),
                    'url'         => $type::getFormURLWithID($itemID),
                    'name'        => $itemname . $ID,
                    'is_deleted'  => isset($data['is_deleted']) && $data['is_deleted'] == '1',
                    'delete_id'   => $data["items_id"],
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
            'delete_action'  => PLUGIN_RESOURCES_WEBDIR . '/front/task.form.php',
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
