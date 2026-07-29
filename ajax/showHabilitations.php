<?php

/*
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2015-2026 by the resources Development Team.

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

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Metademands\Form;
use GlpiPlugin\Metademands\Form_Value;
use GlpiPlugin\Metademands\Metademand;
use GlpiPlugin\Metademands\Wizard;
use GlpiPlugin\Resources\Resource;

header("Content-Type: application/json; charset=UTF-8");

Html::header_nocache();

Session::checkRight('plugin_resources', READ);

// The wizard is driven by a caller-supplied resource id ($_POST['value']): validate it and
// confirm the caller may actually read that specific resource (right + entity scope) before
// seeding the metademands session or redirecting. The global plugin_resources READ alone
// would otherwise let a user pivot the wizard onto any resource id, including ones outside
// their entity perimeter (mirror the ->can(id, READ) guard used by picture.send.php).
$resources_id   = (int) ($_POST['value'] ?? 0);
$metademands_id = (int) ($_POST['metademands_id'] ?? 0);
$resource       = new Resource();
if ($resources_id <= 0 || $metademands_id <= 0 || !$resource->can($resources_id, READ)) {
    throw new AccessDeniedHttpException();
}

$KO = false;

$metademands = new Metademand();
$wizard = new Wizard();
$form = new Form();
$resForm = $form->find(
    ['plugin_metademands_metademands_id' => $metademands_id, 'resources_id' => $resources_id]
);
if (count($resForm)) {
    foreach ($resForm as $res) {
        $last = $res['id'];
    }
    $form->getFromDB($last);
    unset($_SESSION['plugin_metademands']);
    $metademands->getFromDB($metademands_id);
    Form_Value::loadFormValues($metademands_id, $form->getField('id'));
    $form_name = $form->getField('name');

    // Resources id
    if (isset($_POST['resources_id'])) {
        $_SESSION['plugin_metademands']['fields']['resources_id'] = $resources_id;
    }

    //Category id if have category field
    $_SESSION['plugin_metademands']['field_type'] = $metademands->fields['type'];
    $_SESSION['plugin_metademands']['plugin_metademands_forms_id'] = $form->getField('id');
    $_SESSION['plugin_metademands']['plugin_metademands_forms_name'] = $form_name;


    Html::redirect(
        PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?see_form=1&resources_id=" . $resources_id . "&metademands_id=" . $metademands_id . "&step=2"
    );
} else {
    unset($_SESSION['plugin_metademands']);
    Html::redirect(
        PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?see_form=1&resources_id=" . $resources_id . "&metademands_id=" . $metademands_id . "&step=2"
    );
}
if ($KO === false) {
    echo 0;
} else {
    echo $KO;
}
