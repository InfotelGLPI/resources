<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

$import = new PluginResourcesImport();

$pluginResourcesResourceImport = new PluginResourcesResourceImport();

if (isset($_POST["add"])) {

   $import->check(-1, CREATE, $_POST);

   if(!isset($_POST['select']) || !isset($_POST['import'])){
      Html::displayErrorAndDie('Wrong parameters');
   }

   foreach($_POST['select'] as $key=>$selected) {

      if ($selected) {

         $input = [
            'importID' => $key,
            'datas' => $_POST['import'][$key]
         ];

         $pluginResourcesResourceImport->add($input);
      }
   }
   Html::back();

} else if (isset($_POST["purge"])) {

   $import->check($_POST['id'], PURGE);
   $pluginResourcesResourceImport->delete($_POST);
   Html::back();

} else if (isset($_POST["update"])) {

   if(!isset($_POST['select']) || !isset($_POST['import'])){
      Html::displayErrorAndDie('Wrong parameters');
   }

   foreach($_POST['select'] as $key=>$selected) {

      if ($selected) {

         $input = [
           'resourceID' => $_POST['resource'][$key],
           'datas' => $_POST['import'][$key]
         ];

         $pluginResourcesResourceImport->update($input);
      }
   }
   Html::back();
} else if (isset($_POST["delete"])){
   $t = 1;

   foreach($_POST['select'] as $key=>$selected){
      if($selected){
         $pluginResourcesImportResource = new PluginResourcesImportResource();

         $input = [
            PluginResourcesImportResource::getIndexName() => $key
         ];

         $pluginResourcesImportResource->delete($input);
      }
   }

   Html::back();
}
Html::displayErrorAndDie('Lost');