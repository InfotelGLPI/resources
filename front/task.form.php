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
        $_POST["plugin_resources_resources_id"]
    );
} //from central
//restore task
elseif (isset($_POST["restore"])) {
    $task->check($_POST['id'], UPDATE);
    $task->restore($_POST);
    Html::redirect(
        Toolbox::getItemTypeFormURL(Resource::class) . "?id=" .
        $_POST["plugin_resources_resources_id"]
    );
} //from central
//purge task
elseif (isset($_POST["purge"])) {
    $task->check($_POST['id'], UPDATE);
    $task->delete($_POST, 1);
    Html::redirect(
        Toolbox::getItemTypeFormURL(Resource::class) . "?id=" .
        $_POST["plugin_resources_resources_id"]
    );
} //from central
//add item to task
elseif (isset($_POST["addtaskitem"])) {
    if ($task->canCreate()) {
        $task_item->addTaskItem($_POST);
    }
    Html::back();
} //from central
//delete item to task
elseif (isset($_POST["deletetaskitem"])) {
    if ($task->canCreate()) {
        $task_item->delete(['id' => $_POST["id"]]);
    }
    Html::back();
} else {
    $task->checkGlobal(READ);
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class);
    $task->display(
        [
            'id' => $_GET["id"],
            'plugin_resources_resources_id' => $_GET["plugin_resources_resources_id"],
            'withtemplate' => $_GET["withtemplate"]
        ]
    );
    Html::footer();
}
