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

use Glpi\Exception\Http\BadRequestHttpException;

Session::checkRight('plugin_resources', READ);

if (isset($_POST["sub_type"])) {
    $sub_type = $_POST["sub_type"];
} elseif (isset($_GET["sub_type"])) {
    $sub_type = $_GET["sub_type"];
} else {
    $sub_type = 0;
}

if (isset($_POST["rules_id"])) {
    $rules_id = $_POST["rules_id"];
} elseif (isset($_GET["rules_id"])) {
    $rules_id = $_GET["rules_id"];
} else {
    $rules_id = 0;
}
$dbu = new DbUtils();
if (!$rule = $dbu->getItemForItemtype($sub_type)) {
    throw new BadRequestHttpException();
}
$rule->checkGlobal(READ);

// The preview is opened in an iframe modal by the core rule form, which appends
// _in_modal=1: honour it so the page does not repeat the whole application chrome.
$in_modal = isset($_REQUEST['_in_modal']) ? (bool) $_REQUEST['_in_modal'] : false;
Html::popHeader(__('Setup'), '', $in_modal);

// Since GLPI 11 both preview methods build their own target and no longer take one.
$rule->showRulePreviewCriteriasForm($rules_id);

if (isset($_POST["test_rule"])) {
    $params = [];
    //Unset values that must not be processed by the rule
    unset($_POST["test_rule"], $_POST["rules_id"], $_POST["sub_type"]);
    $rule->getRuleWithCriteriasAndActions($rules_id, true, true);

    //Add rules specific POST fields to the param array
    $params = $rule->addSpecificParamsForPreview($params);

    $input = $rule->prepareAllInputDataForProcess($_POST, $params);
    $rule->showRulePreviewResultsForm($input, $params);
}

Html::popFooter();
