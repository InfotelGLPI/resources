<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

$import = new PluginResourcesImport();

$resourceImport = new PluginResourcesResourceImport();

if (isset($_POST["add"])) {
   $import->check(-1, CREATE, $_POST);
   $resourceImport->add($_POST);
   Html::back();
} else if (isset($_POST["purge"])) {
   $import->check($_POST['id'], PURGE);
   $resourceImport->delete($_POST);
   $resourceImport->redirectToList();
} else if (isset($_POST["update"])) {
   $import->check($_POST['id'], UPDATE);
   $resourceImport->update($_POST);
   Html::back();
}
Html::displayErrorAndDie('Lost');