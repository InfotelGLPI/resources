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

   $limit = 0;
   if(isset($_POST['glpilist_limit'])){
      $limit = $_POST['glpilist_limit'];
   }else if(isset($_SESSION['glpilist_limit'])){
      $limit = $_SESSION['glpilist_limit'];
   }

   $importResource->showList($_GET["type"],$limit);

} else {
   Html::displayRightError();
}

Html::footer();
