<?php
/*
 * @version $Id: tasktype.class.php 480 2012-11-09 tsmr $
 -------------------------------------------------------------------------
 Resources plugin for GLPI
 Copyright (C) 2006-2012 by the Resources Development Team.

 https://forge.indepnet.net/projects/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Resources.

 Resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */


if (!defined('GLPI_ROOT')) {
   include ('../../../inc/includes.php');
}

Session::checkCentralAccess();

if (isset($_POST["sub_type"])) {
   $sub_type = $_POST["sub_type"];
} else if (isset($_GET["sub_type"])) {
   $sub_type = $_GET["sub_type"];
} else {
   $sub_type = 0;
}

if (isset($_POST["condition"])) {
   $condition = $_POST["condition"];
} else if (isset($_GET["condition"])) {
   $condition = $_GET["condition"];
} else {
   $condition = 0;
}

$rulecollection = RuleCollection::getClassByType($sub_type);
if ($rulecollection->isRuleRecursive()) {
   $rulecollection->setEntity($_SESSION['glpiactive_entity']);
}
$rulecollection->checkGlobal(READ);

Html::popHeader(__('Setup'),$_SERVER['PHP_SELF']);

// Need for RuleEngines
foreach ($_POST as $key => $val) {
   $_POST[$key] = stripslashes($_POST[$key]);
}
$input = $rulecollection->showRulesEnginePreviewCriteriasForm($_SERVER['PHP_SELF'], $_POST, $condition);

if (isset($_POST["test_all_rules"])) {
   //Unset values that must not be processed by the rule
   unset($_POST["sub_type"]);
   unset($_POST["test_all_rules"]);

   echo "<br>";
   $rulecollection->showRulesEnginePreviewResultsForm($_SERVER['PHP_SELF'], $_POST, $condition);
}

Html::popFooter();
?>