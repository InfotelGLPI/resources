<?php
include('../../../inc/includes.php');

Session::checkLoginUser();
if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}
$importResource = new PluginResourcesImportResource();
if (isset($_POST["add"])) {
   $importResource->add($_POST);
   Html::back();
} else if (isset($_POST["purge"])) {
   $importResource->delete($_POST);
   Html::back();
} else if (isset($_POST["update"])) {
   $importResource->update($_POST);
   Html::back();
}
Html::displayErrorAndDie('Lost');