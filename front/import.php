<?php

use Glpi\Exception\Http\AccessDeniedHttpException;

include('../../../inc/includes.php');

Html::header(PluginResourcesMenu::getTypeName(2), '', "admin", "pluginresourcesmenu");

$import = new PluginResourcesImport();
$import->checkGlobal(READ);

if ($import->canView()) {

   $import->showTitle();
   Search::show('PluginResourcesImport');

} else {
    throw new AccessDeniedHttpException();
}

Html::footer();
