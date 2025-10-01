<?php

/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2022 by the resources Development Team.

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

use GlpiPlugin\Resources\Choice;
use GlpiPlugin\Resources\ContractType;
use GlpiPlugin\Resources\Employee;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\ResourceHabilitation;
use GlpiPlugin\Resources\Wizard;
use GlpiPlugin\Servicecatalog\Main;

$wizard = new Wizard();
$resource = new Resource();
$employee = new Employee();
$choice = new Choice();
$resourcehabilitation = new ResourceHabilitation();

$resource->checkGlobal(READ);

if (Session::getCurrentInterface() == 'central') {
    //from central
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class);
} else {
    //from helpdesk
    if (Plugin::isPluginActive('servicecatalog')) {
        Main::showDefaultHeaderHelpdesk(Resource::getTypeName(2));
    } else {
        Html::helpHeader(Resource::getTypeName(2));
    }
}
if (empty($_POST)) {
    $_POST = $_GET;
}

if (isset($_POST["secondary_services"])) {
    $_POST["secondary_services"] = json_encode($_POST["secondary_services"]);
} else {
    $_POST["secondary_services"] = "";
}

if (isset($_POST["second_step"]) || isset($_GET["second_step"])) {
    if (!isset($_POST["template"])) {
        $_POST["template"] = $_GET["template"];
    }
    if (!isset($_POST["withtemplate"])) {
        $_POST["withtemplate"] = $_GET["withtemplate"];
    }

    // Set default value...
    $values = [
        'name' => '',
        'firstname' => '',
        'comment' => '',
        'locations_id' => 0,
        'users_id' => 0,
        'users_id_sales' => 0,
        'plugin_resources_departments_id' => 0,
        'date_begin' => 'NULL',
        'date_end' => 'NULL',
        'quota' => 1.0000,
        'plugin_resources_resourcesituations_id' => 0,
        'plugin_resources_contractnatures_id' => 0,
        'plugin_resources_ranks_id' => 0,
        'plugin_resources_resourcespecialities_id' => 0,
        'plugin_resources_leavingreasons_id' => 0,
        'plugin_resources_habilitations_id' => 0,
        'sensitize_security' => 0,
        'read_chart' => 0,
    ];

    // Clean text fields
    $values['name'] = stripslashes($values['name']);

    $values['target'] = PLUGIN_RESOURCES_WEBDIR . "/front/wizard.form.php";
    $values['withtemplate'] = $_POST["withtemplate"];
    $values['new'] = 1;
    //OK
    $wizard->wizardSecondStep($_POST["template"], $values);

} else if (isset($_POST["third_step"])) {

    $required = $resource->checkRequiredFields($_POST);

    if (count($required) > 0) {
        // Set default value...
        foreach ($_POST as $key => $val) {
            $values[$key] = $val;
        }

        // Clean text fields
        $values['name'] = stripslashes($values['name']);
        $values['withtemplate'] = $_POST["withtemplate"];
        $values["requiredfields"] = 1;

        Session::addMessageAfterRedirect(
            __('Required fields are not filled. Please try again.', 'resources'),
            false,
            ERROR
        );
        //OK
        $wizard->wizardSecondStep($_POST['id_template'], $values);

    } elseif (isset($_POST['date_begin'])
        && !empty($_POST['date_begin'])
        && isset($_POST['date_end'])
        && !empty($_POST['date_end'])
        && $_POST['date_end'] < $_POST['date_begin']) {

        foreach ($_POST as $key => $val) {
            $values[$key] = $val;
        }

        // Clean text fields
        $values['name'] = stripslashes($values['name']);
        $values['withtemplate'] = $_POST["withtemplate"];
        $values["requiredfields"] = 1;

        Session::addMessageAfterRedirect(
            __('The start date must be greater than the end date', 'resources'),
            false,
            ERROR
        );
        //OK
        $wizard->wizardSecondStep($_POST['id_template'], $values);

    } else {
        $newresource = 0;
        if ($resource->canCreate() && isset($_POST["third_step"])) {
            unset($_POST['id']);
            $newresource = $resource->add($_POST);
            if (isset($_POST['plugin_resources_employers_id']) && $newresource > 0) {
                $employee = new Employee();
                $employee->add([
                    'plugin_resources_employers_id' => $_POST['plugin_resources_employers_id'],
                    'plugin_resources_resources_id' => $newresource,
                    'plugin_resources_clients_id' => 0,
                ]);
            }
        } else {

            foreach ($_POST as $key => $val) {
                $values[$key] = $val;
            }
            // Clean text fields
            $values['name'] = stripslashes($values['name']);
            $values['withtemplate'] = $_POST["withtemplate"];
            $values["requiredfields"] = 1;

            Session::addMessageAfterRedirect(
                __('There is a right problem', 'resources'),
                false,
                ERROR
            );
            //OK
            $wizard->wizardSecondStep($_POST['id_template'], $values);
        }
//        elseif ($resource->canCreate() && isset($_POST["second_step_update"])) {
//            $resource->update($_POST);
//            $newresource = $_POST["id"];
//            if (isset($_POST['plugin_resources_employers_id'])) {
//                $employee = new Employee();
//                if ($employee->getFromDBByCrit(['plugin_resources_resources_id' => $newresource])) {
//                    $employee->update([
//                        'id' => $employee->getID(),
//                        'plugin_resources_employers_id' => $_POST['plugin_resources_employers_id'],
//                        'plugin_resources_resources_id' => $newresource,
//                        'plugin_resources_clients_id' => 0,
//                    ]);
//                } else {
//                    $employee->add([
//                        'plugin_resources_employers_id' => $_POST['plugin_resources_employers_id'],
//                        'plugin_resources_resources_id' => $newresource,
//                        'plugin_resources_clients_id' => 0,
//                    ]);
//                }
//            }
//        }

        //if employee right : next step
        if ($newresource > 0) {

            $resources_id = $newresource;

            $wizard_employee = ContractType::checkWizardSetup($resources_id, "use_employee_wizard");
            $wizard_need = ContractType::checkWizardSetup($resources_id, "use_need_wizard");
            $wizard_picture = ContractType::checkWizardSetup($resources_id, "use_picture_wizard");
            $wizard_habilitation = ContractType::checkWizardSetup($resources_id, "use_habilitation_wizard");
            $wizard_documents = ContractType::checkWizardSetup($resources_id, "use_documents_wizard");
            $wizard_entrance_information = ContractType::checkWizardSetup($resources_id, "use_entrance_information");

            if ($employee->canCreate() && $wizard_employee) {
                $wizard->wizardThirdStep($resources_id);
            } elseif ($wizard_need) {
                $wizard->wizardFourStep($resources_id);
            } elseif ($wizard_picture) {
                $wizard->wizardFiveStep($resources_id);
            } elseif ($wizard_habilitation) {
                $wizard->wizardSixStep($resources_id);
            } elseif ($wizard_documents) {
                $wizard->wizardSevenStep($resources_id);
            } elseif ($wizard_entrance_information) {
                $wizard->wizardEightStep($resources_id);
            } else {
                $resource->fields['resources_step'] = 'third_step';
                Plugin::doHook('item_show', $resource);
                $resource->redirectToList();
            }
        } else {
            Html::back();
        }
    }
} elseif (isset($_POST["four_step"])) {
    if (isset($_POST['id']) && $_POST['id'] > 0) {
        $employee->update($_POST);
    } else {
        $newid = $employee->add($_POST);
    }

    $resources_id = $_POST["plugin_resources_resources_id"];

    $wizard_need = ContractType::checkWizardSetup($resources_id, "use_need_wizard");
    $wizard_picture = ContractType::checkWizardSetup($resources_id, "use_picture_wizard");
    $wizard_habilitation = ContractType::checkWizardSetup($resources_id, "use_habilitation_wizard");
    $wizard_documents = ContractType::checkWizardSetup($resources_id, "use_documents_wizard");
    $wizard_entrance_information = ContractType::checkWizardSetup($resources_id, "use_entrance_information");

    if ($wizard_need) {
        $wizard->wizardFourStep($resources_id);
    } elseif ($wizard_picture) {
        $wizard->wizardFiveStep($resources_id);
    } elseif ($wizard_habilitation) {
        $wizard->wizardSixStep($resources_id);
    } elseif ($wizard_documents) {
        $wizard->wizardSevenStep($resources_id);
    } elseif ($wizard_entrance_information) {
        $wizard->wizardEightStep($resources_id);
    } else {
        $resource->fields['plugin_resources_resources_id'] = $_POST['plugin_resources_resources_id'];
        $resource->fields['resources_step'] = 'four_step';
        Plugin::doHook('item_show', $resource);
        $resource->redirectToList();
    }
} elseif (isset($_POST["updateneedcomment"])) {
    $resources_id = $_POST["plugin_resources_resources_id"];
    if ($resource->canCreate()) {
        foreach ($_POST["updateneedcomment"] as $key => $val) {
            $varcomment = "commentneed" . $key;
            $values['id'] = $key;
            $values['commentneed'] = $_POST[$varcomment];
            $choice->addNeedComment($values);
        }
    }

    $wizard->wizardFourStep($resources_id);

} elseif (isset($_POST["addcomment"])) {
    $resources_id = $_POST["plugin_resources_resources_id"];
    if ($resource->canCreate()) {
        $choice->addComment($_POST);
    }

    $wizard->wizardFourStep($resources_id);
} elseif (isset($_POST["updatecomment"])) {
    $resources_id = $_POST["plugin_resources_resources_id"];
    if ($resource->canCreate()) {
        $choice->updateComment($_POST);
    }

    $wizard->wizardFourStep($resources_id);
} elseif (isset($_POST["addchoice"])) {
    $resources_id = $_POST["plugin_resources_resources_id"];
    if ($resource->canCreate(
        ) && $_POST['plugin_resources_choiceitems_id'] > 0 && $_POST['plugin_resources_resources_id'] > 0) {
        $choice->addHelpdeskItem($_POST);
    }
    $wizard->wizardFourStep($resources_id);
} elseif (isset($_POST["deletechoice"])) {
    $resources_id = $_POST["plugin_resources_resources_id"];
    if ($resource->canCreate()) {
        $choice->delete(['id' => $_POST["id"]]);
    }

    $wizard->wizardFourStep($resources_id);

} elseif (isset($_POST["five_step"])) {

    $resources_id = $_POST["plugin_resources_resources_id"];

    $wizard_picture = ContractType::checkWizardSetup($resources_id, "use_picture_wizard");
    $wizard_habilitation = ContractType::checkWizardSetup($resources_id, "use_habilitation_wizard");
    $wizard_documents = ContractType::checkWizardSetup($resources_id, "use_documents_wizard");
    $wizard_entrance_information = ContractType::checkWizardSetup($resources_id, "use_entrance_information");


    if ($wizard_picture) {
        $wizard->wizardFiveStep($resources_id);
    } elseif ($wizard_habilitation) {
        $wizard->wizardSixStep($resources_id);
    } elseif ($wizard_documents) {
        $wizard->wizardSevenStep($resources_id);
    } elseif ($wizard_entrance_information) {
        $wizard->wizardEightStep($resources_id);
    } else {
        $resource->fields['plugin_resources_resources_id'] = $_POST['plugin_resources_resources_id'];
        $resource->fields['resources_step'] = 'four_step';
        Plugin::doHook('item_show', $resource);
        $resource->redirectToList();
    }
} elseif (isset($_POST["six_step"])) {

    $resources_id = $_POST["plugin_resources_resources_id"];

    $wizard_habilitation = ContractType::checkWizardSetup($resources_id, "use_habilitation_wizard");
    $wizard_documents = ContractType::checkWizardSetup($resources_id, "use_documents_wizard");
    $wizard_entrance_information = ContractType::checkWizardSetup($resources_id, "use_entrance_information");

    if ($wizard_habilitation) {
        $wizard->wizardSixStep($resources_id);
    } elseif ($wizard_documents) {
        $wizard->wizardSevenStep($resources_id);
    } elseif ($wizard_entrance_information) {
        $wizard->wizardEightStep($resources_id);
    } else {
        $resource->fields['plugin_resources_resources_id'] = $resources_id;
        $resource->fields['resources_step'] = 'five_step';
        Plugin::doHook('item_show', $resource);
        $resource->redirectToList();
    }
} elseif (isset($_POST["upload_five_step"])) {

    if (isset($_FILES) && isset($_FILES['picture'])) {

        $resources_id = $_POST["plugin_resources_resources_id"];

        if ($_FILES['picture']['type'] == "image/jpeg" || $_FILES['picture']['type'] == "image/pjpeg") {
            $max_size = Toolbox::return_bytes_from_ini_vars(ini_get("upload_max_filesize"));
            if ($_FILES['picture']['size'] <= $max_size) {
                $resource->getFromDB($resources_id);
                $_POST['picture'] = $resource->addPhoto($resource);

                $_POST["id"] = $resources_id;
                $resource->update($_POST);

            } else {
                Session::addMessageAfterRedirect(
                    __('Failed to send the file (probably too large)'),
                    false,
                    ERROR
                );
            }
        } else {
            Session::addMessageAfterRedirect(
                __('Invalid filename') . " : " . $_FILES['picture']['type'],
                false,
                ERROR
            );
        }
    }

    $wizard->wizardFiveStep($_POST["plugin_resources_resources_id"]);

} elseif (isset($_POST["seven_step"])) {

    $resources_id = $_POST["plugin_resources_resources_id"];

    $wizard_documents = ContractType::checkWizardSetup($resources_id, "use_documents_wizard");
    $wizard_entrance_information = ContractType::checkWizardSetup($resources_id, "use_entrance_information");

    if ($wizard_documents) {
        $wizard->wizardSevenStep($resources_id);
    } elseif ($wizard_entrance_information) {
        $wizard->wizardEightStep($resources_id);
    } else {
        $resource->fields['plugin_resources_resources_id'] = $resources_id;
        $resource->fields['resources_step'] = 'six_step';
        Plugin::doHook('item_show', $resource);
        $resource->redirectToList();
    }

} elseif (isset($_POST["add_doc_seven_step"])) {

    $resources_id = $_POST["items_id"];
    $document_item = new Document_Item();
    $document_item->check(-1, CREATE, $_POST);
    $document_item->add($_POST);
    $wizard->wizardSevenStep($resources_id);

} elseif (isset($_POST["upload_seven_step"])) {

    $resources_id = $_POST["items_id"];

    $doc = new Document();
    $doc->check(-1, CREATE, $_POST);
    if (isset($_POST['_filename']) && is_array($_POST['_filename'])) {
        $fic = $_POST['_filename'];
        $tag = $_POST['_tag_filename'];
        $prefix = $_POST['_prefix_filename'];
        foreach (array_keys($fic) as $key) {
            $_POST['_filename'] = [$fic[$key]];
            $_POST['_tag_filename'] = [$tag[$key]];
            $_POST['_prefix_filename'] = [$prefix[$key]];
            $newID = $doc->add($_POST);
        }
    }
    if (isset($newID) && $newID > 0) {
        $document_item = new Document_Item();
        $input['items_id'] = $resources_id;
        $input['itemtype'] = Resource::getType();
        $input['documents_id'] = $newID;
        $document_item->add($input);
    }

    $wizard->wizardSevenStep($resources_id);

} elseif (isset($_POST["eight_step"])) {

    $resources_id = $_POST["plugin_resources_resources_id"];
    $wizard_entrance_information = ContractType::checkWizardSetup($resources_id, "use_entrance_information");
    if ($wizard_entrance_information) {
        $wizard->wizardEightStep($resources_id);
    } else {
        $resource->fields['plugin_resources_resources_id'] = $resources_id;
        $resource->fields['resources_step'] = 'seven_step';
        Plugin::doHook('item_show', $resource);
        $resource->redirectToList();
    }

} elseif (isset($_POST["nine_step"])) {

    $resources_id = $_POST["plugin_resources_resources_id"];
    $data = [];
    $data['id'] = $_POST['plugin_resources_resources_id'];
    $data['date_agreement_candidate'] = $_POST['date_agreement_candidate'] ?? NULL;
    $data['plugin_resources_degreegroups_id'] = $_POST['plugin_resources_degreegroups_id'] ?? 0;
    $data['plugin_resources_recruitingsources_id'] = $_POST['plugin_resources_recruitingsources_id'] ?? 0;
    $data['yearsexperience'] = $_POST['yearsexperience'] ?? 0;
    $data['reconversion'] = $_POST['reconversion'] ?? 0;

    $resource->update($data);
    $resource->fields['plugin_resources_resources_id'] = $resources_id;
    $resource->fields['resources_step'] = 'eight_step';
    Plugin::doHook('item_show', $resource);
    $resource->redirectToList();


    //Revert cases//
} elseif (isset($_POST["undo_second_step"])) {
    // Set default value...
    $values['withtemplate'] = 0;
    $values['new'] = 0;
    //OK
    $wizard->wizardFirstStep(0, $values);

} elseif (isset($_POST["undo_third_step"])) {
    $resources_id = $_POST['plugin_resources_resources_id'];
    //OK
    $wizard->wizardSecondStep($resources_id);

} elseif (isset($_POST["undo_four_step"])) {

    $resources_id = $_POST['plugin_resources_resources_id'];

    $wizard_employee = ContractType::checkWizardSetup($resources_id, "use_employee_wizard");

    if ($employee->canCreate() && $wizard_employee) {
        $wizard->wizardThirdStep($resources_id);
    } else {
        $values['target'] = PLUGIN_RESOURCES_WEBDIR . "/front/wizard.form.php";
        $values['withtemplate'] = 0;
        $values['new'] = 0;

        $wizard->wizardSecondStep($resources_id, $values);
    }
} elseif (isset($_POST["undo_five_step"])) {
    $resources_id = $_POST['plugin_resources_resources_id'];

    $wizard_employee = ContractType::checkWizardSetup($resources_id, "use_employee_wizard");
    $wizard_need = ContractType::checkWizardSetup($resources_id, "use_need_wizard");

    if ($wizard_need) {
        $wizard->wizardFourStep($resources_id);
    } elseif ($employee->canCreate() && $wizard_employee) {
        $wizard->wizardThirdStep($resources_id);
    } else {
        // Set default value...
        $values['target'] = PLUGIN_RESOURCES_WEBDIR . "/front/wizard.form.php";
        $values['withtemplate'] = 0;
        $values['new'] = 0;

        $wizard->wizardSecondStep($resources_id, $values);
    }
} elseif (isset($_POST["undo_six_step"])) {
    $resources_id = $_POST['plugin_resources_resources_id'];

    $wizard_employee = ContractType::checkWizardSetup($resources_id, "use_employee_wizard");
    $wizard_need = ContractType::checkWizardSetup($resources_id, "use_need_wizard");
    $wizard_picture = ContractType::checkWizardSetup($resources_id, "use_picture_wizard");

    if ($wizard_picture) {
        $wizard->wizardFiveStep($resources_id);
    } elseif ($wizard_need) {
        $wizard->wizardFourStep($resources_id);
    } elseif ($employee->canCreate() && $wizard_employee) {
        $wizard->wizardThirdStep($resources_id);
    } else {
        $values['withtemplate'] = 0;
        $values['new'] = 0;
        $wizard->wizardSecondStep($resources_id, $values);
    }
} elseif (isset($_POST["undo_seven_step"])) {
    $resources_id = $_POST['plugin_resources_resources_id'];

    $wizard_employee = ContractType::checkWizardSetup($resources_id, "use_employee_wizard");
    $wizard_need = ContractType::checkWizardSetup($resources_id, "use_need_wizard");
    $wizard_picture = ContractType::checkWizardSetup($resources_id, "use_picture_wizard");
    $wizard_habilitation = ContractType::checkWizardSetup($resources_id, "use_habilitation_wizard");

    if ($wizard_habilitation) {
        $wizard->wizardSixStep($resources_id);
    } elseif ($wizard_picture) {
        $wizard->wizardFiveStep($resources_id);
    } elseif ($wizard_need) {
        $wizard->wizardFourStep($resources_id);
    } elseif ($employee->canCreate() && $wizard_employee) {
        $wizard->wizardThirdStep($resources_id);
    } else {
        $values['withtemplate'] = 0;
        $values['new'] = 0;
        $wizard->wizardSecondStep($resources_id, $values);
    }

    //next step : email and finish resource creation
} elseif (isset($_POST["undo_eight_step"])) {
    $resources_id = $_POST['plugin_resources_resources_id'];

    $wizard_employee = ContractType::checkWizardSetup($resources_id, "use_employee_wizard");
    $wizard_need = ContractType::checkWizardSetup($resources_id, "use_need_wizard");
    $wizard_picture = ContractType::checkWizardSetup($resources_id, "use_picture_wizard");
    $wizard_habilitation = ContractType::checkWizardSetup($resources_id, "use_habilitation_wizard");
    $wizard_documents = ContractType::checkWizardSetup($resources_id, "use_documents_wizard");

    if ($wizard_documents) {
        $wizard->wizardSevenStep($resources_id);
    } elseif ($wizard_habilitation) {
        $wizard->wizardSixStep($resources_id);
    } elseif ($wizard_picture) {
        $wizard->wizardFiveStep($resources_id);
    } elseif ($wizard_need) {
        $wizard->wizardFourStep($resources_id);
    } elseif ($employee->canCreate() && $wizard_employee) {
        $wizard->wizardThirdStep($resources_id);
    } else {
        $values['withtemplate'] = 0;
        $values['new'] = 0;
        $wizard->wizardSecondStep($resources_id, $values);
    }
} else {
    $wizard->wizardFirstStep();
}

if (Session::getCurrentInterface() != 'central'
    && Plugin::isPluginActive('servicecatalog')) {
    Main::showNavBarFooter('resources');
}

if (Session::getCurrentInterface() == 'central') {
    Html::footer();
} else {
    Html::helpFooter();
}
