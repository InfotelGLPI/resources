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

use Alert;
use CommonDBTM;
use CommonGLPI;
use DBConnection;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QuerySubQuery;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\RichText\RichText;
use Html;
use MassiveAction;
use Migration;
use NotificationEvent;
use Plugin;
use PluginPdfSimplePDF;
use Search;
use Session;
use Toolbox;

/**
 * Class Task
 */
class Task extends CommonDBTM
{
    public static $rightname = 'plugin_resources_task';

    public $itemtype = Resource::class;
    public $items_id = 'plugin_resources_resources_id';
    public $dohistory = true;

    public const STATE_KO = 0;
    public const STATE_OK = 1;

    /**
     * Set by Resource::transferResource() only, which moves the tasks along with their
     * resource: the next update() of this object may then write entities_id. Consumed by
     * prepareInputForUpdate(), never read from the input.
     */
    public bool $allow_entity_change = false;

    /**
     * Set by cloneItem() only, which copies the tasks of a checked template to the resource
     * just created from it (Resource::post_addItem()): the next add() of this object then
     * takes that resource without requiring UPDATE on it, the creator having been allowed to
     * create it (a profile may create resources without being allowed to change them).
     * Consumed by prepareInputForAdd(), never read from the input.
     */
    public bool $resource_checked_by_caller = false;

    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Task', 'Tasks', $nb);
    }

    public static function getIcon()
    {
        return "ti ti-checklist";
    }


    /**
     * @return bool
     */
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }


    public function cleanDBonPurge()
    {
        $temp = new Task_Item();
        $temp->deleteByCriteria(['plugin_resources_tasks_id' => $this->fields['id']]);

        $temp = new TaskPlanning();
        $temp->deleteByCriteria(['plugin_resources_tasks_id' => $this->fields['id']]);
    }

    /**
     * @param array $input
     *
     * @return array|bool
     */
    public function prepareInputForAdd($input)
    {
        Toolbox::manageBeginAndEndPlanDates($input['plan']);

        if (isset($input['plan'])) {
            $input['_plan'] = $input['plan'];
            unset($input['plan']);
        }

        if (isset($input["hour"]) && isset($input["minute"])) {
            $input["actiontime"] = $input["hour"] * HOUR_TIMESTAMP + $input["minute"] * MINUTE_TIMESTAMP;
            $input["_hour"] = $input["hour"];
            $input["_minute"] = $input["minute"];
            unset($input["hour"]);
            unset($input["minute"]);
        }

        unset($input["minute"]);
        unset($input["hour"]);

        // A task is a change of the resource it is attached to: the caller only checked the
        // task right and the entity it posted, so the resource is authorised here, for every
        // writer (form, checklist massive action, duplication), except the copy of a template
        // to the resource just created from it (resource_checked_by_caller). The task then
        // takes the entity of that resource, whatever entity came with the input.
        $resource_checked_by_caller       = $this->resource_checked_by_caller;
        $this->resource_checked_by_caller = false;
        try {
            $resources_id = Resource::getPostedId($input['plugin_resources_resources_id'] ?? null);
        } catch (BadRequestHttpException) {
            $resources_id = 0;
        }
        $resource = new Resource();
        if ($resources_id <= 0
            || !($resource_checked_by_caller
                ? $resource->getFromDB($resources_id)
                : $resource->can($resources_id, UPDATE))) {
            Session::addMessageAfterRedirect(
                __("You don't have permission to perform this action."),
                false,
                ERROR,
            );
            return false;
        }
        $input['plugin_resources_resources_id'] = $resources_id;
        $input['entities_id']                   = $resource->fields['entities_id'];
        $input['is_recursive']                  = $resource->fields['is_recursive'];

        return $input;
    }

    /**
     * @return bool|void
     */
    public function post_addItem()
    {
        global $CFG_GLPI;

        if (isset($this->input["_plan"])) {
            $this->input["_plan"]['plugin_resources_tasks_id'] = $this->fields['id'];
            // A new task has no planning row yet: a posted row id would be inserted as the
            // primary key of the new row.
            unset($this->input["_plan"]['id']);
            $pt = new TaskPlanning();

            if (!$pt->add($this->input["_plan"])) {
                return false;
            }
        }

        $Resource = new Resource();
        if ($CFG_GLPI["notifications_mailing"]) {
            //no notification when adding a task if created by checklist
            if (!isset($this->input['container'])
                || (isset($this->input['container'])
                    && (strpos($this->input['container'], 'mass' . 'GlpiPlugin\Resources\Checklist') === false))) {
                $options = ['tasks_id' => $this->fields["id"]];
                if ($Resource->getFromDB($this->fields["plugin_resources_resources_id"])) {
                    NotificationEvent::raiseEvent("newtask", $Resource, $options);
                }
            }
        }
    }

    /**
     * @param array $input
     *
     * @return array
     */
    public function prepareInputForUpdate($input)
    {
        Toolbox::manageBeginAndEndPlanDates($input['plan']);
        if (isset($input["hour"])
            && isset($input["minute"])
        ) {
            $input["actiontime"] = $input["hour"] * HOUR_TIMESTAMP + $input["minute"] * MINUTE_TIMESTAMP;
            unset($input["hour"]);
            unset($input["minute"]);
        }

        if (isset($input["plan"])) {
            $input["_plan"] = $input["plan"];
            unset($input["plan"]);
        }

        // The form hands the whole request to update(), which the caller authorised on the
        // stored task only: the resource and the entity a task belongs to are not the
        // request's to change. A task follows its resource, which moves it through
        // Resource::transferResource() (allow_entity_change).
        $allow_entity_change       = $this->allow_entity_change;
        $this->allow_entity_change = false;
        unset($input['plugin_resources_resources_id']);
        if (!$allow_entity_change) {
            unset($input['entities_id'], $input['is_recursive']);
        }

        $this->getFromDB($input["id"]);
        $input["_old_name"] = $this->fields["name"];
        $input["_old_plugin_resources_tasktypes_id"] = $this->fields["plugin_resources_tasktypes_id"];
        $input["_old_users_id"] = $this->fields["users_id"];
        $input["_old_groups_id"] = $this->fields["groups_id"];
        $input["_old_actiontime"] = $this->fields["actiontime"];
        $input["_old_is_finished"] = $this->fields["is_finished"];
        $input["_old_comment"] = $this->fields["comment"];

        return $input;
    }

    /**
     * @param int $history
     *
     * @return bool|void
     */
    public function post_updateItem($history = 1)
    {
        global $CFG_GLPI;

        if (isset($this->input["_plan"])) {
            $pt = new TaskPlanning();
            // The planning row written is the one stored for this task, looked up here: the
            // row id of the form is a hidden field, and trusting it let a task update rewrite
            // the planning of any other task and re-parent it.
            $plan = $this->input["_plan"];
            unset($plan['id']);
            $plan['plugin_resources_tasks_id'] = $this->getID();
            unset($this->input["_plan"]);
            if ($pt->getFromDBbyTask($this->getID())) {
                // Update case
                if (!$pt->update(['id' => $pt->getID()] + $plan)) {
                    return false;
                }
            } elseif (countElementsInTable(
                TaskPlanning::getTable(),
                ['plugin_resources_tasks_id' => $this->getID()],
            ) === 0) {
                // Add case. With several rows (left by an older version), none of them can be
                // told to be the one edited: nothing is written.
                if (!$pt->add($plan)) {
                    return false;
                }
            }
        }

        if (!isset($this->input["withtemplate"])
            || (isset($this->input["withtemplate"])
                && $this->input["withtemplate"] != 1)
        ) {
            if ($CFG_GLPI["notifications_mailing"]) {
                $options = ['tasks_id' => $this->fields["id"]];
                $Resource = new Resource();
                if ($Resource->getFromDB($this->fields["plugin_resources_resources_id"])) {
                    NotificationEvent::raiseEvent("updatetask", $Resource, $options);
                }
            }
        }
    }

    /**
     * @return bool
     */
    public function pre_deleteItem()
    {
        global $CFG_GLPI;

        if ($CFG_GLPI["notifications_mailing"]
            && isset($this->input['_delete'])
        ) {
            $Resource = new Resource();
            $options = ['tasks_id' => $this->fields["id"]];
            if ($Resource->getFromDB($this->fields["plugin_resources_resources_id"])) {
                NotificationEvent::raiseEvent("deletetask", $Resource, $options);
            }
        }
        return true;
    }

    public function cleanDBonMarkDeleted()
    {
        global $DB;

        $DB->update(
            'glpi_plugin_resources_checklists',
            ['plugin_resources_tasks_id' => 0],
            ['plugin_resources_tasks_id' => (int) $this->fields["id"]],
        );
    }

    /**
     * @param CommonGLPI $item
     * @param int $withtemplate
     *
     * @return array|string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == Resource::class
            && $this->canView()) {
            if ($_SESSION['glpishow_count_on_tabs']) {
                return self::createTabEntry(self::getTypeName(2), self::countForItem($item));
            }
            return self::getTypeName(2);
        } elseif ($item->getType() == 'Central'
            && $this->canView()
        ) {
            return self::createTabEntry(Resource::getTypeName(2));
        }
        return '';
    }


    /**
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $self = new self();
        if ($item->getType() == Resource::class) {
            if (Session::haveRight(self::$rightname, READ)) {
                self::addNewTasks($item, $withtemplate);
                self::showMinimalList([
                    'id' => $item->getID(),
                    'withtemplate' => $withtemplate,
                ]);
            }
        } elseif ($item->getType() == 'Central') {
            $self->showCentral(Session::getLoginUserID());
        }
        return true;
    }

    /**
     * @param CommonDBTM $item
     *
     * @return int
     */
    public static function countForItem(CommonDBTM $item)
    {
        $dbu = new DbUtils();
        // Same scope as the list of the tab (a search, restricted to the active entities):
        // the count must not reveal tasks the list does not show.
        $restrict = [
            "plugin_resources_resources_id" => $item->getField('id'),
            "is_deleted" => 0,
            "NOT" => ["is_finished" => 1],
        ] + $dbu->getEntitiesRestrictCriteria(self::getTable(), '', '', true);
        $nb = $dbu->countElementsInTable(['glpi_plugin_resources_tasks'], $restrict);

        return $nb;
    }

    /**
     * @return array
     */
    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id' => '2',
            'table' => 'glpi_users',
            'field' => 'name',
            'name' => __('Technician'),
            'datatype' => 'dropdown',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '3',
            'table' => 'glpi_groups',
            'field' => 'completename',
            'name' => __('Group'),
            'condition' => '`is_assign`',
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4',
            'table' => 'glpi_plugin_resources_taskplannings',
            'field' => 'id',
            'name' => __('Planning'),
            'massiveaction' => false,
            'datatype' => 'number',
        ];

        $tab[] = [
            'id' => '7',
            'table' => $this->getTable(),
            'field' => 'actiontime',
            'name' => __('Effective duration', 'resources'),
            'datatype' => 'timestamp',
            'massiveaction' => false,
            'nosearch' => true,
        ];

        $tab[] = [
            'id' => '8',
            'table' => $this->getTable(),
            'field' => 'comment',
            'name' => __('Comments'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id' => '9',
            'table' => $this->getTable(),
            'field' => 'is_finished',
            'name' => __('Carried out task', 'resources'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id' => '10',
            'table' => 'glpi_plugin_resources_tasks_items',
            'field' => 'items_id',
            'name' => _n('Associated item', 'Associated items', 2),
            'massiveaction' => false,
            'forcegroupby' => true,
            'joinparams' => [
                'jointype' => 'child',
            ],
        ];

        $tab[] = [
            'id' => '11',
            'table' => 'glpi_plugin_resources_tasktypes',
            'field' => 'name',
            'name' => TaskType::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '13',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'id',
            'name' => Resource::getTypeName(1) . " " . __('ID'),
            'massiveaction' => false,
            'datatype' => 'number',
        ];

        // The id comes along with the name: plugin_resources_giveItem() checks that the viewer
        // may read the resource before printing it. Both columns are restricted by the join
        // (plugin_resources_getTaskResourceJoinRestriction()).
        $tab[] = [
            'id' => '12',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'name',
            'name' => Resource::getTypeName(2),
            'massiveaction' => false,
            'additionalfields' => ['id'],
        ];

        $tab[] = [
            'id' => '30',
            'table' => $this->getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'massiveaction' => false,
            'datatype' => 'number',
        ];

        $tab[] = [
            'id' => '80',
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __('Entity'),
            'datatype' => 'dropdown',
        ];

        return $tab;
    }


    /**
     * @param array $options
     *
     * @return array
     */
    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(Task_Item::class, $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     * Duplicate task of resources from an item template to its clone
     *
     * @param $itemtype     itemtype of the item
     * @param $oldid        ID of the item to clone
     * @param $newid        ID of the item cloned
     * @param $newitemtype  itemtype of the new item (= $itemtype if empty) (DEFAULT '')
     **@since version 0.84
     *
     */
    public static function cloneItem($oldid, $newid)
    {
        $task_item = new Task_Item();

        // The copies belong to the new resource: they take its entity, not the one of the
        // template, which may sit in a parent entity (or any other one, for a forged source).
        $resource = new Resource();
        if (!$resource->getFromDB($newid)) {
            return;
        }

        $restrict = [
            "plugin_resources_resources_id" => $oldid,
            "NOT" => ["is_deleted" => 1],
        ];
        $dbu = new DbUtils();
        $ptasks = $dbu->getAllDataFromTable("glpi_plugin_resources_tasks", $restrict);
        $skipped = 0;
        if (!empty($ptasks)) {
            foreach ($ptasks as $ptask) {
                $item = new self();
                $values = $ptask;
                $taskid = $values["id"];
                unset($values["id"]);
                $values["plugin_resources_resources_id"] = $newid;
                $values["entities_id"] = $resource->fields["entities_id"];
                $values["is_recursive"] = $resource->fields["is_recursive"];
                $values["name"] = $ptask["name"];
                $values["comment"] = $ptask["comment"];

                // The template was checked by Resource::prepareInputForAdd() and the resource
                // was just created by the caller: its tasks are copied without requiring
                // UPDATE on it, which the CREATE right alone does not grant.
                $item->resource_checked_by_caller = true;
                $newtid = $item->add($values);
                if (!$newtid) {
                    // Refused by prepareInputForAdd(): no row to attach the items to
                    continue;
                }

                $restrictitems = ["plugin_resources_tasks_id" => $taskid];
                $tasksitems = $dbu->getAllDataFromTable(
                    "glpi_plugin_resources_tasks_items",
                    $restrictitems,
                );
                foreach ($tasksitems as $tasksitem) {
                    // The objects of the template are checked against the entity of the new
                    // resource, as the links of the resource itself (Resource_Item::cloneItem()),
                    // not against the rights of the session on each type: the creator may well
                    // hold none on the hardware a template lists.
                    if (!Resource_Item::isLinkableTo((string) $tasksitem["itemtype"], (int) $tasksitem["items_id"], $resource)) {
                        $skipped++;
                        continue;
                    }
                    $task_item->item_checked_by_caller = true;
                    $task_item->add([
                        'plugin_resources_tasks_id' => $newtid,
                        'itemtype' => $tasksitem["itemtype"],
                        'items_id' => $tasksitem["items_id"],
                    ]);
                }
            }
        }

        if ($skipped > 0) {
            Resource_Item::addTemplateItemsSkippedMessage();
        }
    }

    /**
     * @param CommonDBTM $item
     * @param string $withtemplate
     */
    public static function addNewTasks(CommonDBTM $item, $withtemplate = '')
    {
        $ID = $item->getField('id');

        if (!Session::haveRight(self::$rightname, READ)
            || !$item->can($ID, UPDATE)
            || $withtemplate >= 2
        ) {
            return;
        }

        TemplateRenderer::getInstance()->display('@resources/task_add_link.html.twig', [
            'url' => PLUGIN_RESOURCES_WEBDIR . "/front/task.form.php?plugin_resources_resources_id=" . $ID
                . "&entities_id=" . $item->getField('entities_id'),
        ]);
    }

    /**
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

        $plugin_resources_resources_id = -1;
        if (isset($options['plugin_resources_resources_id'])) {
            $plugin_resources_resources_id = $options['plugin_resources_resources_id'];
        }
        $entities_id = $_SESSION['glpiactive_entity'];
        $item = new Resource();
        if ($item->getFromDB($plugin_resources_resources_id)) {
            $entities_id = $item->fields["entities_id"];
        }

        if ($ID > 0) {
            $this->check($ID, READ);
            $plugin_resources_resources_id = $this->fields["plugin_resources_resources_id"];
        } else {
            // Create item
            $input = [
                'plugin_resources_resources_id' => $plugin_resources_resources_id,
                'entities_id' => $entities_id,
            ];
            $this->check(-1, UPDATE, $input);
        }

        // The task may be readable where its resource is not (a recursive task, a row left by
        // an older version): the identity of the resource is only shown to who may read it.
        if ((int) $plugin_resources_resources_id > 0
            && (new Resource())->can((int) $plugin_resources_resources_id, READ)) {
            $user = Resource::getResourceName($plugin_resources_resources_id, 2);
            $resource_link = '<a href="' . htmlescape($user['link']) . '">' . htmlescape($user["name"]);
            if ($_SESSION["glpiis_ids_visible"]) {
                $resource_link .= ' (' . (int) $plugin_resources_resources_id . ')';
            }
            $resource_link .= '</a>';
        } else {
            $resource_link = '(' . (int) $plugin_resources_resources_id . ')';
        }

        // TaskPlanning echoes its block and the JS driving it: capture both.
        ob_start();
        (new TaskPlanning())->showFormForTask($plugin_resources_resources_id, $this);
        $planning_block = (string) ob_get_clean();

        $toadd = [];
        for ($i = 9; $i <= 100; $i++) {
            $toadd[] = $i * HOUR_TIMESTAMP;
        }

        TemplateRenderer::getInstance()->display('@resources/task_form.html.twig', [
            'item'               => $this,
            'params'             => $options,
            'resources_id'       => $plugin_resources_resources_id,
            'withtemplate_value' => $options['withtemplate'] ?? '',
            'resource_label'     => Resource::getTypeName(2),
            'resource_link'      => $resource_link,
            'tasktype_class'     => TaskType::class,
            'tasktype_label'     => TaskType::getTypeName(1),
            'planning_block'     => $planning_block,
            'max_actiontime'     => 8 * HOUR_TIMESTAMP,
            'actiontime_toadd'   => $toadd,
        ]);

        return true;
    }

    /**
     * get the task status list
     *
     * @param $withmetaforsearch boolean
     *
     * @return array array
     */
    public static function getAllStatusArray()
    {
        $tab = [
            self::STATE_OK => __('Yes'),
            self::STATE_KO => __('No'),
        ];

        return $tab;
    }

    /**
     * @param $state
     *
     * @return string
     */
    public static function getStatusImg($state)
    {
        $img = "";
        switch ($state) {
            case self::STATE_OK:
                $img = "<i style='color:green' class='ti ti-circle-check fa-2x' title='" . Task::getTaskStatus(
                    $state,
                ) . "'></i>";
                break;
            case self::STATE_KO:
                $img = "<i style='color:red' class='ti ti-circle-x fa-2x' title='" . Task::getTaskStatus(
                    $state,
                ) . "'></i>";
                break;
        }
        return $img;
    }

    /**
     * Get task status Name
     *
     * @param $value status ID
     */
    public static function getTaskStatus($value)
    {
        $tab = self::getAllStatusArray();
        return ($tab[$value] ?? '');
    }

    /**
     * @param array $options
     */
    public static function showMinimalList($options = [])
    {
        $task = new Task();

        if (!empty($options)) {
            foreach ($options as $key => $val) {
                $params[$key] = $val;
            }
        }

        // Set search params
        $params = [
            'start' => 0,
            'order' => 'DESC',
            'is_deleted' => 0,
            'as_map' => 0,
        ];

        $toview = null;
        foreach ($task->rawSearchOptions() as $option) {
            if (isset($option['table'])) {
                if ($option['table'] == "glpi_plugin_resources_resources" && $option['field'] == "id") {
                    $params['criteria'][] = [
                        'field' => $option['id'],
                        'searchtype' => 'contains',
                        'value' => $options['id'],
                    ];
                    $toview = $option['id'];
                }
                if ($option['table'] == $task->getTable() && $option['field'] == "name") {
                    $params['sort'] = $option['id'];
                }
            }
        }

        $data = Search::prepareDatasForSearch(self::getType(), $params);
        // Force to view resource id
        if ($toview != null && !in_array($toview, $data['toview'])) {
            array_push($data['toview'], $toview);
        }
        Search::constructSQL($data);
        Search::constructData($data);
        Search::displayData($data);
    }

    /**
     * @param $who
     */
    public function showCentral($who)
    {
        $Checklist = new Checklist();

        TemplateRenderer::getInstance()->display('@resources/task_central.html.twig', [
            'tasks'          => $this->getCentralParams(),
            'checklist_todo' => $Checklist->getCentralParams(false),
            'checklist_done' => $Checklist->getCentralParams(true),
        ]);
    }

    /**
     * Parameters of central_datatable.html.twig for the tasks in progress of the current user.
     *
     * @return array<string, mixed>|null Null when there is nothing to display
     */
    public function getCentralParams(): ?array
    {
        global $DB;

        if (!$this->canView()) {
            return null;
        }

        $who = Session::getLoginUserID();

        $dbu = new DbUtils();
        $self_table = $this->getTable();

        $or = [];
        if ($who > 0) {
            $or[$self_table . '.users_id'] = (int) $who;
        }
        $or[$self_table . '.groups_id'] = new QuerySubQuery([
            'SELECT' => 'groups_id',
            'FROM'   => 'glpi_groups_users',
            'WHERE'  => ['users_id' => (int) $who],
        ]);

        $where = [
            'glpi_plugin_resources_resources.is_template' => 0,
            'glpi_plugin_resources_resources.is_deleted'  => 0,
            $self_table . '.is_deleted'  => 0,
            $self_table . '.is_finished' => 0,
            ['OR' => $or],
        ];

        // Add Restrict to current entities
        $Resource = new Resource();
        $itemtable = "glpi_plugin_resources_resources";
        if ($Resource->isEntityAssign()) {
            $entities_crit = $dbu->getEntitiesRestrictCriteria($itemtable);
            if (count($entities_crit)) {
                $where[] = $entities_crit;
            }
        }

        $iterator = $DB->request([
            'SELECT'     => [
                $self_table . '.id AS plugin_resources_tasks_id',
                $self_table . '.name AS name_task',
                $self_table . '.plugin_resources_tasktypes_id AS plugin_resources_tasktypes_id',
                $self_table . '.is_deleted AS is_deleted',
                $self_table . '.users_id AS users_id_task',
                'glpi_plugin_resources_resources.id AS id',
                'glpi_plugin_resources_resources.name AS name',
                'glpi_plugin_resources_resources.firstname AS firstname',
                'glpi_plugin_resources_resources.entities_id',
                'glpi_plugin_resources_resources.users_id AS users_id',
            ],
            'FROM'       => $self_table,
            'INNER JOIN' => [
                'glpi_plugin_resources_resources' => [
                    'ON' => [
                        $self_table                       => 'plugin_resources_resources_id',
                        'glpi_plugin_resources_resources' => 'id',
                    ],
                ],
            ],
            'WHERE'      => $where,
            'ORDER'      => 'glpi_plugin_resources_resources.name DESC',
            'LIMIT'      => 10,
        ]);
        // Raw values: central_datatable.html.twig builds the link / list cells and escapes them.
        $entries = [];
        foreach ($iterator as $data) {
            $task_label = (string) $data["name_task"];
            $resource_label = $data["name"] . " " . $data["firstname"];
            if ($_SESSION["glpiis_ids_visible"]) {
                $task_label .= ' (' . $data["plugin_resources_tasks_id"] . ')';
                $resource_label .= ' (' . $data["id"] . ')';
            }

            $plans = $dbu->getAllDataFromTable(
                "glpi_plugin_resources_taskplannings",
                ["plugin_resources_tasks_id" => $data['plugin_resources_tasks_id']],
            );
            $planning = [];
            foreach ($plans as $plan) {
                $planning[] = Html::convDateTime($plan["begin"]) . "\u{00A0}->\u{00A0}"
                    . Html::convDateTime($plan["end"]);
            }
            if ($planning === []) {
                $planning[] = __('None');
            }

            $entries[] = [
                'row_class' => $data["is_deleted"] == '1' ? 'tab_bg_1_2' : 'tab_bg_1',
                'name'      => [
                    'url'   => PLUGIN_RESOURCES_WEBDIR . '/front/task.form.php?id='
                        . (int) $data["plugin_resources_tasks_id"],
                    'label' => $task_label,
                ],
                'entity'    => Dropdown::getDropdownName("glpi_entities", $data['entities_id']),
                'tasktype'  => Dropdown::getDropdownName(
                    "glpi_plugin_resources_tasktypes",
                    $data["plugin_resources_tasktypes_id"],
                ),
                'planning'  => $planning,
                'resource'  => [
                    'url'   => PLUGIN_RESOURCES_WEBDIR . '/front/resource.form.php?id=' . (int) $data["id"],
                    'label' => $resource_label,
                ],
                'manager'   => $dbu->getUserName($data["users_id"]),
                'user'      => $dbu->getUserName($data["users_id_task"]),
            ];
        }

        if (count($entries) === 0) {
            return null;
        }

        $columns = ['name' => __('Name')];
        if (Session::isMultiEntitiesMode()) {
            $columns['entity'] = __('Entity');
        }
        $columns['tasktype'] = TaskType::getTypeName(2);
        $columns['planning'] = __('Planning');
        $columns['resource'] = Resource::getTypeName(1);
        $columns['manager']  = __('Resource manager', 'resources');
        $columns['user']     = __('User');

        return [
            'super_header' => Resource::getTypeName(2) . ': ' . __('Tasks in progress', 'resources'),
            'super_link'   => [
                'url'   => PLUGIN_RESOURCES_WEBDIR
                    . '/front/task.php?contains%5B0%5D=0&field%5B0%5D=9&sort=1&is_deleted=0&start=0',
                'label' => __('All'),
            ],
            'columns'      => $columns,
            'cell_kinds'   => [
                'name'     => 'link',
                'planning' => 'lines',
                'resource' => 'link',
            ],
            'entries'      => $entries,
        ];
    }

    // Cron action

    /**
     * @param $name
     *
     * @return array
     */
    public static function cronInfo($name)
    {
        switch ($name) {
            case 'ResourcesTask':
                return [
                    'description' => __('Not finished tasks', 'resources'),
                ];   // Optional
                break;
        }
        return [];
    }

    /**
     * Cron action on tasks : ExpiredTasks
     *
     * @param $task for log, if NULL display
     *
     **/
    public static function cronResourcesTask($task = null)
    {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["notifications_mailing"]) {
            return 0;
        }

        $message = [];
        $cron_status = 0;

        $resourcetask = new self();
        $query_expired = $resourcetask->QueryAlert();

        $querys = [Alert::END => $query_expired];

        $task_infos = [];
        $task_messages = [];

        foreach ($querys as $type => $query) {
            $task_infos[$type] = [];
            foreach ($DB->request($query) as $data) {
                $entity = $data['entities_id'];
                // Accumulated into the message handed to addMessageAfterRedirect() below, which
                // the core documents as an unescaped HTML sink. Escaped leaf by leaf so the <br>
                // separator stays markup (same pattern as Employment::cronResourcesEmployment()).
                $message = htmlescape($data["name"]) . ": "
                    . Html::convDate($data["date_end"]) . "<br>\n";
                $task_infos[$type][$entity][] = $data;

                if (!isset($task_messages[$type][$entity])) {
                    $task_messages[$type][$entity] = __('Not finished tasks', 'resources') . "<br />";
                }
                $task_messages[$type][$entity] .= $message;
            }
        }

        foreach ($querys as $type => $query) {
            foreach ($task_infos[$type] as $entity => $tasks) {
                Plugin::loadLang('resources');

                // The entity completename is free text and the message it prefixes is HTML:
                // escaped once here, for the cron log and for the session message alike.
                $entity_label = htmlescape(Dropdown::getDropdownName("glpi_entities", $entity));

                if (NotificationEvent::raiseEvent(
                    "AlertExpiredTasks",
                    new Resource(),
                    [
                        'entities_id' => $entity,
                        'tasks' => $tasks,
                    ],
                )
                ) {
                    $message = $task_messages[$type][$entity];
                    $cron_status = 1;
                    if ($task) {
                        $task->log($entity_label . ":  $message\n");
                        $task->addVolume(1);
                    } else {
                        Session::addMessageAfterRedirect($entity_label . ":  $message");
                    }
                } else {
                    if ($task) {
                        $task->log($entity_label . ":  Send tasks alert failed\n");
                    } else {
                        Session::addMessageAfterRedirect(
                            $entity_label . ":  Send tasks alert failed",
                            false,
                            ERROR,
                        );
                    }
                }
            }
        }

        return $cron_status;
    }

    /**
     * @return array
     */
    public function queryAlert()
    {
        $date = date("Y-m-d");
        $query
            = [
                'SELECT' => [
                    $this->getTable() . '.*',
                    'glpi_plugin_resources_resources.entities_id',
                    'glpi_plugin_resources_taskplannings.end AS date_end',
                ],
                'FROM' => $this->getTable(),
                'LEFT JOIN'       => [
                    'glpi_plugin_resources_taskplannings' => [
                        'ON' => [
                            'glpi_plugin_resources_taskplannings' => 'plugin_resources_tasks_id',
                            $this->getTable()          => 'id',
                        ],
                    ],
                    'glpi_plugin_resources_resources' => [
                        'ON' => [
                            'glpi_plugin_resources_resources' => 'id',
                            $this->getTable()          => 'plugin_resources_resources_id',
                        ],
                    ],
                ],

                'WHERE' => [
                    'NOT'       => ['glpi_plugin_resources_taskplannings.end' => null, 'is_leaving' => 1],
                    'glpi_plugin_resources_taskplannings.end'    => ['<=', $date],
                    'glpi_plugin_resources_resources.is_deleted'    => 0,
                    'glpi_plugin_resources_resources.is_template'    => 0,
                    $this->getTable() . '.is_deleted'    => 0,
                    $this->getTable() . '.is_finished'    => 0,
                ],
            ];

        return $query;
    }

    /**
     * Get the specific massive actions
     *
     * @param $checkitem link item to check right   (default NULL)
     *
     * @return an array of massive actions
     * *@since version 0.84
     *
     */
    public function getSpecificMassiveActions($checkitem = null)
    {
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if ($isadmin) {
            $actions['GlpiPlugin\Resources\Task' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Install'] = __('Associate');
            $actions['GlpiPlugin\Resources\Task' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Desinstall'] = __(
                'Dissociate',
            );
            $actions['GlpiPlugin\Resources\Task' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Duplicate'] = _x(
                'button',
                'Duplicate',
            );
            // No transfer of tasks: a task belongs to the entity of its resource (see
            // prepareInputForAdd() and prepareInputForUpdate()), and moves with it when the
            // resource is transferred.
        }
        return $actions;
    }

    /**
     * @param MassiveAction $ma
     *
     * @return bool
     */
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case "Install":
                Dropdown::showSelectItemFromItemtypes([
                    'items_id_name' => "item_item",
                    'itemtypes' => Resource::getTypes(),
                ]);
                break;

            case "Desinstall":
                Dropdown::showSelectItemFromItemtypes([
                    'items_id_name' => "item_item",
                    'itemtypes' => Resource::getTypes(),
                ]);
                break;

            case "Duplicate":
                // The copy stays on the resource of the task copied, in the entity of that
                // resource: there is no destination entity to choose.
                break;
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     * */
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        $task_item = new Task_Item();
        $input = $ma->getInput();
        $itemtype = $ma->getItemtype(false);

        // Each branch checks a key with can() and then writes with it: only keys that are
        // plain ids go on, so that both use the same integer.
        $ids = Resource::getMassiveActionIds($ma, $item, $ids);
        if ($ids === []) {
            return;
        }

        switch ($ma->getAction()) {
            case "Duplicate":
                if ($itemtype == Task::class) {
                    // The copy stays on the resource of its source, in the entity of that
                    // resource: prepareInputForAdd() requires UPDATE on the resource and takes
                    // the entity from it. Only the task right is left to test here, once.
                    if (!Task::canCreate()) {
                        $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                        return;
                    }
                    foreach ($ids as $key => $val) {
                        // MassiveAction passes the posted ids through untouched: the source
                        // row has to be readable before it is copied.
                        if (!$item->can($key, READ)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            continue;
                        }
                        $values = $item->fields;
                        unset($values["id"]);
                        if ((new Task())->add($values)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    }
                }
                break;

            case "Install":
                // The object linked is posted with the form, and the per-task can() below
                // answers for the task only: the object has to be readable by the session
                // (Task_Item::prepareInputForAdd() refuses it otherwise, for every writer).
                $linked_item = getItemForItemtype((string) ($input['itemtype'] ?? ''));
                if (!in_array($input['itemtype'] ?? '', Resource::getTypes(true), true)
                    || !$linked_item
                    || !$linked_item->can((int) ($input['item_item'] ?? 0), READ)) {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                    $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    return;
                }
                foreach ($ids as $key => $val) {
                    // Task_Item carries no entity of its own: authorise the owning task,
                    // whose id comes straight from the posted massive action.
                    if (!$item->can($key, UPDATE)) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        continue;
                    }
                    $values = [
                        'plugin_resources_tasks_id' => $item->getID(),
                        'items_id' => $input["item_item"],
                        'itemtype' => $input['itemtype'],
                    ];
                    if ($task_item->add($values)) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                    }
                }
                break;

            case "Desinstall":
                foreach ($ids as $key => $val) {
                    // Task_Item carries no entity of its own: authorise the owning task,
                    // whose id comes straight from the posted massive action.
                    if (!$item->can($key, UPDATE)) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        continue;
                    }
                    if ($task_item->deleteItemByTaskAndItem($item->getID(), $input['item_item'], $input['itemtype'])) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                    }
                }
                break;
        }
    }

    /**
     * @param PluginPdfSimplePDF $pdf
     * @param CommonGLPI $item
     * @param                     $tab
     *
     * @return bool
     */
    public static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab)
    {
        if ($item->getType() == Resource::class) {
            self::pdfForResource($pdf, $item);
        } else {
            return false;
        }
        return true;
    }

    /**
     * Show for PDF an resources : tasks informations
     *
     * @param $pdf object for the output
     * @param $ID of the resources
     */
    public static function pdfForResource(PluginPdfSimplePDF $pdf, Resource $appli)
    {
        global $DB;

        $ID = $appli->fields['id'];

        if (!$appli->can($ID, READ)) {
            return false;
        }

        if (!Session::haveRight("plugin_resources", READ)) {
            return false;
        }

        // Same right as the tab on screen: reading the resource says nothing about its tasks.
        if (!self::canView()) {
            return false;
        }

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_resources_tasks',
            'WHERE' => [
                'plugin_resources_resources_id' => (int) $ID,
                'is_deleted'                    => 0,
            ],
        ]);
        $rows   = iterator_to_array($iterator, false);
        $number = count($rows);

        $i = $j = 0;

        $pdf->setColumnsSize(100);

        if ($number > 0) {
            // Only the static markup is kept: labels are escaped like the values below.
            $pdf->displayTitle('<b>' . htmlescape(self::getTypeName(2)) . '</b>');

            $pdf->setColumnsSize(14, 14, 14, 14, 16, 14, 14);
            $pdf->displayTitle(
                '<b><i>'
                . htmlescape(__('Name')),
                htmlescape(__('Type')),
                htmlescape(__('Comments')),
                htmlescape(__('Duration')),
                htmlescape(__('Planning')),
                htmlescape(__('Resource manager', 'resources')),
                htmlescape(__('Group')) . '</i></b>',
            );

            $i++;

            while ($j < $number) {
                $tID = $rows[$j]["id"];
                $actiontime_ID = $rows[$j]["actiontime"];

                $actiontime = '';
                $units = Toolbox::getTimestampTimeUnits($actiontime_ID);

                $hour = $units['hour'];
                $minute = $units['minute'];
                if ($hour) {
                    $actiontime = $hour . _n('Hour', 'Hours', 2);
                }
                if ($minute || !$hour) {
                    $actiontime .= $minute . _n('Minute', 'Minutes', 2);
                }

                $restrict = ["plugin_resources_tasks_id" => $tID];
                $dbu = new DbUtils();
                $plans = $dbu->getAllDataFromTable("glpi_plugin_resources_taskplannings", $restrict);

                // Only the arrow is markup: the dates and the label are escaped
                if (!empty($plans)) {
                    foreach ($plans as $plan) {
                        $planification = htmlescape((string) Html::convDateTime($plan["begin"])) . "&nbsp;->&nbsp;"
                            . htmlescape((string) Html::convDateTime($plan["end"]));
                    }
                } else {
                    $planification = htmlescape(__('None'));
                }

                $users_id = $rows[$j]["users_id"];

                $managers = getUserName($users_id);
                $name = $rows[$j]["name"];
                $task_type = $rows[$j]["plugin_resources_tasktypes_id"];
                $comment = $rows[$j]["comment"];
                $groups_id = $rows[$j]["groups_id"];

                // The PDF engine reads its cells as HTML: every value coming from the database
                // is encoded, the comment being reduced to its text first.
                $pdf->displayLine(
                    htmlescape((string) $name),
                    htmlescape(Dropdown::getDropdownName("glpi_plugin_resources_tasktypes", $task_type)),
                    htmlescape(RichText::getTextFromHtml((string) $comment, false, true)),
                    htmlescape($actiontime),
                    $planification,
                    htmlescape((string) $managers),
                    htmlescape(Dropdown::getDropdownName("glpi_groups", $groups_id)),
                );
                $j++;
            }
        } else {
            $pdf->displayLine(htmlescape(__('No results found')));
        }

        $pdf->displaySpace();
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
                        `name`                          varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `entities_id`                   int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `is_recursive`                  tinyint      NOT NULL                   DEFAULT '0',
                        `plugin_resources_resources_id` int {$default_key_sign} NOT NULL                   DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_resources (id)',
                        `plugin_resources_tasktypes_id` int {$default_key_sign} NOT NULL                   DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_tasktypes (id)',
                        `users_id`                      int {$default_key_sign} NOT NULL                   DEFAULT '0' COMMENT 'RELATION to glpi_users (id)',
                        `groups_id`                     int {$default_key_sign} NOT NULL                   DEFAULT '0' COMMENT 'RELATION to glpi_groups (id)',
                        `actiontime`                    int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `is_finished`                   tinyint      NOT NULL                   DEFAULT '0',
                        `comment`                       TEXT COLLATE utf8mb4_unicode_ci,
                        `is_deleted`                    tinyint      NOT NULL                   DEFAULT '0',
                        PRIMARY KEY (`id`),
                        KEY `name` (`name`),
                        KEY `entities_id` (`entities_id`),
                        KEY `plugin_resources_resources_id` (`plugin_resources_resources_id`),
                        KEY `plugin_resources_tasktypes_id` (`plugin_resources_tasktypes_id`),
                        KEY `users_id` (`users_id`),
                        KEY `groups_id` (`groups_id`),
                        KEY `is_finished` (`is_finished`),
                        KEY `is_deleted` (`is_deleted`),
                        KEY `is_recursive` (`is_recursive`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 2,
                    'rank' => 1,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 3,
                    'rank' => 2,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 4,
                    'rank' => 3,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 6,
                    'rank' => 4,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 7,
                    'rank' => 5,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

        }
    }
}
