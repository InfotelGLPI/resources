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
    $task->check(-1, UPDATE, $_POST);
    $newID = $task->add($_POST);
    Html::back();
} //update task
elseif (isset($_POST["update"])) {
    $task->check($_POST['id'], UPDATE);
    $task->update($_POST);
    //no sending mail here : see post_updateItem of Task
    Html::back();
} //from central
//delete task
elseif (isset($_POST["delete"])) {
    $task->check($_POST['id'], UPDATE);
    $task->delete($_POST);
    Html::redirect(
        Toolbox::getItemTypeFormURL(Resource::class) . "?id=" .
        $_POST["plugin_resources_resources_id"],
    );
} //from central
//restore task
elseif (isset($_POST["restore"])) {
    $task->check($_POST['id'], UPDATE);
    $task->restore($_POST);
    Html::redirect(
        Toolbox::getItemTypeFormURL(Resource::class) . "?id=" .
        $_POST["plugin_resources_resources_id"],
    );
} //from central
//purge task
elseif (isset($_POST["purge"])) {
    $task->check($_POST['id'], UPDATE);
    $task->delete($_POST, 1);
    Html::redirect(
        Toolbox::getItemTypeFormURL(Resource::class) . "?id=" .
        $_POST["plugin_resources_resources_id"],
    );
} //from central
//add item to task
elseif (isset($_POST["addtaskitem"])) {
    // canCreate() only answers for the global right bit: it looks at neither the record
    // nor its entity. The task id is client-supplied and Task carries entities_id, so
    // check the targeted task the way every other branch of this controller does.
    $task->check((int) ($_POST["plugin_resources_tasks_id"] ?? 0), UPDATE);
    $task_item->addTaskItem($_POST);
    Html::back();
} //from central
//delete item to task
elseif (isset($_POST["deletetaskitem"])) {
    // Task_Item holds no entity of its own and the row id is client-supplied, while
    // CommonDBTM::delete() checks nothing by itself. Resolve the owning task from the
    // row that is about to be deleted -- never from an id posted alongside it -- and
    // require UPDATE there.
    if ($task_item->getFromDB((int) ($_POST["id"] ?? 0))) {
        $task->check((int) $task_item->fields['plugin_resources_tasks_id'], UPDATE);
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
