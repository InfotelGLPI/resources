<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2016 by the resources Development Team.

 https://github.com/InfotelGLPI/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of resources.

 resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

include('../../../inc/includes.php');
header("Content-Type: application/json; charset=UTF-8");

Html::header_nocache();

Session::checkLoginUser();

$KO = false;

$metademands = new PluginMetademandsMetademand();
$wizard      = new PluginMetademandsWizard();
$form      = new PluginMetademandsForm();
$resForm = $form->find(['plugin_metademands_metademands_id' => $_POST['metademands_id'],'resources_id' => $_POST['value']]);
if (count($resForm)) {
   foreach ($resForm as $res){
      $last = $res['id'];
   }
   $form->getFromDB($last);
   unset($_SESSION['plugin_metademands']);
   $metademands->getFromDB($_POST['metademands_id']);
   PluginMetademandsForm_Value::loadFormValues($form->getField('id'));
   $form_name = $form->getField('name');

   // Resources id
   if (isset($_POST['resources_id'])) {
      $_SESSION['plugin_metademands']['fields']['resources_id'] = $_POST['value'];
   }

   //Category id if have category field
   $_SESSION['plugin_metademands']['field_type']                                    = $metademands->fields['type'];
   $_SESSION['plugin_metademands']['plugin_metademands_forms_id']                  = $form->getField('id');
   $_SESSION['plugin_metademands']['plugin_metademands_forms_name']                = $form_name;


   Html::redirect(PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?see_form=1&resources_id=".$_POST['value']."&metademands_id=". $_POST['metademands_id']."&step=2");

}  else if(isset($_POST['value'])) {
   unset($_SESSION['plugin_metademands']);
   Html::redirect(PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?see_form=1&resources_id=".$_POST['value']."&metademands_id=". $_POST['metademands_id']."&step=2");
} else {
   $KO = true;
}
if ($KO === false) {
   echo 0;
} else {
   echo $KO;
}
