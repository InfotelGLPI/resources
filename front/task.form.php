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

use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Task;
use GlpiPlugin\Resources\Task_Item;

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}
if (!isset($_GET["withtemplate"])) {
    $_GET["withtemplate"] = "";
}
if (!isset($_GET["plugin_resources_resources_id"])) {
    $_GET["plugin_resources_resources_id"] = 0;
}

$task = new Task();
$task_item = new Task_Item();

//add tasks
if (isset($_POST['add'])) {
    // CREATE: the profile form offers a Create box for tasks, and the plugin update grants it
    // to the profiles that relied on canCreate() accepting UPDATE or DELETE.
    $task->check(-1, CREATE, $_POST);
    $newID = $task->add($_POST);
    Html::back();
} //update task
elseif (isset($_POST["update"])) {
    // The task written is the one checked: same integer for both, see
    // Resource::getPostedId(). Same for the branches below.
    $tasks_id = Resource::checkPostedItem($task, $_POST['id'] ?? null, UPDATE);
    $task->update(['id' => $tasks_id] + $_POST);
    //no sending mail here : see post_updateItem of Task
    Html::back();
} //from central
//delete task
elseif (isset($_POST["delete"])) {
    // Tasks are soft-deleted (the table carries is_deleted), so the destructive branches
    // ask for the bit that matches the operation instead of UPDATE: DELETE for the soft
    // delete and its restore, PURGE for the definitive one. Anyone allowed to edit a task
    // used to be allowed to destroy it.
    $tasks_id = Resource::checkPostedItem($task, $_POST['id'] ?? null, DELETE);
    $resources_id = (int) $task->fields['plugin_resources_resources_id'];
    $task->delete(['id' => $tasks_id] + $_POST);
    Html::redirect(
        Toolbox::getItemTypeFormURL(Resource::class) . "?id=" . $resources_id,
    );
} //from central
//restore task
elseif (isset($_POST["restore"])) {
    $tasks_id = Resource::checkPostedItem($task, $_POST['id'] ?? null, DELETE);
    $resources_id = (int) $task->fields['plugin_resources_resources_id'];
    $task->restore(['id' => $tasks_id] + $_POST);
    Html::redirect(
        Toolbox::getItemTypeFormURL(Resource::class) . "?id=" . $resources_id,
    );
} //from central
//purge task
elseif (isset($_POST["purge"])) {
    $tasks_id = Resource::checkPostedItem($task, $_POST['id'] ?? null, PURGE);
    $resources_id = (int) $task->fields['plugin_resources_resources_id'];
    $task->delete(['id' => $tasks_id] + $_POST, 1);
    Html::redirect(
        Toolbox::getItemTypeFormURL(Resource::class) . "?id=" . $resources_id,
    );
} //from central
//add item to task
elseif (isset($_POST["addtaskitem"])) {
    // canCreate() only answers for the global right bit: it looks at neither the record
    // nor its entity. The task id is client-supplied and Task carries entities_id, so
    // check the targeted task the way every other branch of this controller does, and link
    // the item to the id checked.
    $tasks_id = Resource::checkPostedItem($task, $_POST["plugin_resources_tasks_id"] ?? null, UPDATE);
    $task_item->addTaskItem(['plugin_resources_tasks_id' => $tasks_id] + $_POST);
    Html::back();
} //from central
//delete item to task
elseif (isset($_POST["deletetaskitem"])) {
    // Task_Item holds no entity of its own and the row id is client-supplied, while
    // CommonDBTM::delete() checks nothing by itself. Resolve the owning task from the
    // row that is about to be deleted -- never from an id posted alongside it -- and
    // require UPDATE there.
    $task_items_id = Resource::getPostedId($_POST["id"] ?? null);
    if ($task_items_id > 0 && $task_item->getFromDB($task_items_id)) {
        // An item left without a task is not deleted here: on 0, check() would only test
        // the right to create a task.
        Resource::checkPostedItem($task, $task_item->fields['plugin_resources_tasks_id'], UPDATE);
        $task_item->delete(['id' => $task_item->getID()]);
    }
    Html::back();
} else {
    $task->checkGlobal(READ);
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class);
    $task->display(
        [
            'id' => $_GET["id"],
            'plugin_resources_resources_id' => $_GET["plugin_resources_resources_id"],
            'withtemplate' => $_GET["withtemplate"],
        ],
    );
    Html::footer();
}
