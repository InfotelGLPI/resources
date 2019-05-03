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

   // Remove not selected imports
   foreach($_POST['select'] as $importID=>$select){
      if($select == 0){
         if(isset($_POST['import'][$importID])){
            unset($_POST['import'][$importID]);
         }
      }
   }
   if(!count($_POST['import'])){
      Html::back();
   }

   $pluginResourcesResourceImport->add($_POST);
   Html::back();

} else if (isset($_POST["purge"])) {

   $import->check($_POST['id'], PURGE);
   $pluginResourcesResourceImport->delete($_POST);
   Html::back();

} else if (isset($_POST["update"])) {
//   $import->check($_POST['resource'], UPDATE);

   if(!isset($_POST['select']) || !isset($_POST['import'])){
      Html::displayErrorAndDie('Wrong parameters');
   }

   if(count($_POST['import']) != count($_POST['resource'])){
      Html::displayErrorAndDie('Wrong parameters');
   }

   // Remove not selected imports
   foreach($_POST['select'] as $importID=>$select){
      if($select == 0){
         if(isset($_POST['import'][$importID])){
            unset($_POST['import'][$importID]);
            unset($_POST['resource'][$importID]);
            unset($_POST['to_update'][$importID]);
         }
      }
   }
   if(!count($_POST['import'])){
      Html::back();
   }

   $pluginResourcesResourceImport->update($_POST);
   Html::back();
}
Html::displayErrorAndDie('Lost');