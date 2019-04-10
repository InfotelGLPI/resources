<?php

include('../../../inc/includes.php');

//central or helpdesk access
if (Session::getCurrentInterface() == 'central') {
   Html::header(PluginResourcesResource::getTypeName(2), '', "admin", "pluginresourcesresource");
} else {
   Html::helpHeader(PluginResourcesResource::getTypeName(2));
}

$satisfaction = new PluginResourcesImport();
$satisfaction->checkGlobal(READ);

if ($satisfaction->canView()) {
   Search::show('PluginResourcesImport');

} else {
   Html::displayRightError();
}

Html::footer();
