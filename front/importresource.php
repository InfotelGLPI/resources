<?php

include('../../../inc/includes.php');

Session::checkLoginUser();
if (!isset($_GET["type"])) {
   $_GET["type"] = 0;
}

//central or helpdesk access
if (Session::getCurrentInterface() == 'central') {
   Html::header(PluginResourcesMenu::getTypeName(2), '', "admin", "pluginresourcesmenu");
} else {
   Html::helpHeader(PluginResourcesMenu::getTypeName(2));
}

$import = new PluginResourcesImport();
$import->checkGlobal(READ);

$importResource = new PluginResourcesImportResource();

if ($import->canView()) {

   $importResource->showList($_GET["type"]);

} else {
   Html::displayRightError();
}

Html::footer();
