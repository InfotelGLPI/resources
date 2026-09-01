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

use Glpi\Application\View\TemplateRenderer;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkRight('plugin_resources', READ);

global $CFG_GLPI;

if (isset($_POST["begin"]) && !empty($_POST["begin"])) {
    $begin = $_POST["begin"];
} else {
    $minute = (floor(date('i') / 10) * 10);
    if ($minute < 10) {
        $minute = '0' . $minute;
    }

    $begin = date("Y-m-d H") . ":$minute:00";
}

if (isset($_POST["end"]) && !empty($_POST["end"])) {
    $end = $_POST["end"];
} else {
    $end = date("Y-m-d H:i:s", strtotime($begin) + HOUR_TIMESTAMP);
}

$begin_field = (string) Html::showDateTimeField("plan[begin]", [
    'value' => $begin,
    'maybeempty' => false,
    'mintime' => $CFG_GLPI["planning_begin"],
    'maxtime' => $CFG_GLPI["planning_end"],
    'display' => false,
]);

$default_delay = floor((strtotime($end) - strtotime($begin)) / 15 / MINUTE_TIMESTAMP) * 15 * MINUTE_TIMESTAMP;

// The end-date container is targeted by id, so fix the rand here instead of reading it
// back from Dropdown::showTimeStamp(), which returns the markup when display is off.
$rand = mt_rand();

$duration_field = (string) Dropdown::showTimeStamp("plan[_duration]", [
    'min' => 0,
    'max' => 50 * HOUR_TIMESTAMP,
    'value' => $default_delay,
    'emptylabel' => __('Specify an end date'),
    'rand' => $rand,
    'display' => false,
]);

TemplateRenderer::getInstance()->display('@resources/taskplanning_planning.html.twig', [
    'plan_id' => (int) ($_POST["id"] ?? 0),
    'begin_field' => $begin_field,
    'duration_field' => $duration_field,
    'rand' => $rand,
]);

// No duration picked: let the user enter an explicit end date instead. The container
// has just been rendered by the template above.
if ($default_delay == 0) {
    Ajax::updateItem("date_end$rand", PLUGIN_RESOURCES_WEBDIR . "/ajax/planningend.php", [
        'duration' => 0,
        'end' => $end,
        'name' => "plan[end]",
        'global_begin' => $CFG_GLPI["planning_begin"],
        'global_end' => $CFG_GLPI["planning_end"],
    ]);
}
