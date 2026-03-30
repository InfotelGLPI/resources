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

namespace GlpiPlugin\Resources;

use CommonDBTM;
use DbUtils;
use Document_Item;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Profile_User;
use Session;
use Toolbox;
use UserCategory;

class Wizard extends CommonDBTM
{
    public static function WizardHeader($title = "", $img = "", $icon = "")
    {
        if (empty($title)) {
            $title = __('Resources management', 'resources');
        }
        echo "<h3 class='alert alert-secondary' role='alert' style='margin-top: 10px;'>";
        echo "<span class='resources_wizard_resp_img'>";
        if (empty($img)) {
            if (empty($icon)) {
                echo "<i class='" . Resource::getIcon() . "'></i>&nbsp;";
            } else {
                echo "<i class='" . $icon . "'></i>&nbsp;";
            }
        } else {
            echo "<img src='" . $img . "'/>&nbsp;";
        }
        echo $title;
        echo "</span>";
        echo "</h3>";
    }

    /**
     * Wizard for selecting a contract template
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardFirstStep()
    {
        TemplateRenderer::getInstance()->display('@resources/wizard_firststep_contractype.html.twig', [
            'can_edit' => Session::haveRight("plugin_resources", CREATE),
            'params' => [
                'title' => __('Welcome to the wizard resource', 'resources'),
                'target' => Toolbox::getItemTypeFormURL(Wizard::class),
                'icon' => '',
                'img' => PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png",
            ],
        ]);

        return true;
    }

    /**
     * Wizard for create resource
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardSecondStep($ID, $options = [])
    {
        $resource = new Resource();

        $input = [];
        $empty = 0;
        if ($ID > 0) {
            $resource->check($ID, READ);
            $options['new'] = $ID;
            $input['plugin_resources_resources_id'] = $ID;
        } else {
            // Create item
            $resource->check(-1, UPDATE);
            $resource->getEmpty();
            $empty = 1;
        }

        if (!isset($options["requiredfields"])) {
            $options["requiredfields"] = 0;
            $options["gender"] = 0;
            $options["name"] = "";
            $options["firstname"] = "";
            $options["locations_id"] = 0;
            $options["phone"] = "";
            $options["cellphone"] = "";
            $options["users_id"] = 0;
            $options["users_id_sales"] = 0;
            $options["plugin_resources_departments_id"] = 0;
            $options["plugin_resources_services_id"] = 0;
            $options["secondary_services"] = [];
            $options["plugin_resources_functions_id"] = 0;
            $options["plugin_resources_teams_id"] = 0;
            $options["date_begin"] = null;
            $options["date_end"] = null;
            $options["comment"] = "";
            $options["quota"] = 0;
            $options["plugin_resources_resourcesituations_id"] = 0;
            $options["plugin_resources_contractnatures_id"] = 0;
            $options["plugin_resources_ranks_id"] = 0;
            $options["plugin_resources_resourcespecialities_id"] = 0;
            $options["plugin_resources_leavingreasons_id"] = 0;
            $options["sensitize_security"] = 0;
            $options["read_chart"] = 0;
            $options["plugin_resources_roles_id"] = 0;
            $options["matricule"] = "";
            $options["matricule_second"] = "";
            $options["withtemplate"] = 0;
        }


        if (((isset($options['withtemplate']) && $options['withtemplate'] == 2)
                || (isset($options['new']) && $options["new"] != 1))
            && $options["requiredfields"] != 1) {
            $options["gender"] = $resource->fields["gender"];
            $options["name"] = $resource->fields["name"];
            $options["firstname"] = $resource->fields["firstname"];
            $options["phone"] = $resource->fields["phone"];
            $options["cellphone"] = $resource->fields["cellphone"];
            $options["locations_id"] = $resource->fields["locations_id"];
            $options["users_id"] = $resource->fields["users_id"];
            $options["users_id_sales"] = $resource->fields["users_id_sales"];
            $options["plugin_resources_departments_id"] = $resource->fields["plugin_resources_departments_id"];
            $options["plugin_resources_services_id"] = $resource->fields["plugin_resources_services_id"];
            $options["secondary_services"] = json_decode($resource->fields['secondary_services'], true);
            $options["plugin_resources_functions_id"] = $resource->fields["plugin_resources_functions_id"];
            $options["plugin_resources_teams_id"] = $resource->fields["plugin_resources_teams_id"];
            $options["date_begin"] = $resource->fields["date_begin"];
            $options["date_end"] = $resource->fields["date_end"];
            $options["comment"] = $resource->fields["comment"];
            $options["quota"] = $resource->fields["quota"];
            $options["plugin_resources_resourcesituations_id"] = $resource->fields["plugin_resources_resourcesituations_id"];
            $options["plugin_resources_contractnatures_id"] = $resource->fields["plugin_resources_contractnatures_id"];
            $options["plugin_resources_ranks_id"] = $resource->fields["plugin_resources_ranks_id"];
            $options["plugin_resources_resourcespecialities_id"] = $resource->fields["plugin_resources_resourcespecialities_id"];
            $options["plugin_resources_leavingreasons_id"] = $resource->fields["plugin_resources_leavingreasons_id"];
            $options["sensitize_security"] = $resource->fields["sensitize_security"];
            $options["read_chart"] = $resource->fields["read_chart"];
            $options["plugin_resources_roles_id"] = $resource->fields["plugin_resources_roles_id"];
            $options["matricule"] = $resource->fields["matricule"];
            $options["matricule_second"] = $resource->fields["matricule_second"];
        }
        $options["plugin_resources_employers_id"] = 0;

        //        if (isset($options['target']) && $options['target'] == 'item') {
        //            $target = null;
        //        } else {
        //            $target = Toolbox::getItemTypeFormURL(Wizard::class);
        //        }

        $target = Toolbox::getItemTypeFormURL(Wizard::class);

        if (isset($resource->fields["entities_id"]) || $empty == 1) {
            if ($empty == 1) {
                $input['plugin_resources_contracttypes_id'] = 0;
                $resourceTemplate = new Resource();
                if (isset($options['template'])
                    && $resourceTemplate->getFromDB($options['template'])) {
                    $input['plugin_resources_contracttypes_id'] = $resourceTemplate->fields['plugin_resources_contracttypes_id'];
                    $input['plugin_resources_resourcestates_id'] = $resourceTemplate->fields['plugin_resources_resourcestates_id'];
                    $input['template'] = $options['template'];
                }
                $input['entities_id'] = $_SESSION['glpiactive_entity'];
            } else {
                $input['plugin_resources_contracttypes_id'] = $resource->fields["plugin_resources_contracttypes_id"];
                $input['plugin_resources_resourcestates_id'] = $resource->fields['plugin_resources_resourcestates_id'];
                if (isset($options['withtemplate']) && $options['withtemplate'] == 2) {
                    $input['entities_id'] = $_SESSION['glpiactive_entity'];
                } else {
                    $input['entities_id'] = $resource->fields["entities_id"];
                }
            }
        }
        $input['plugin_resources_profiletypes_id'] = $_SESSION["glpiactiveprofile"]['id'];
        $input['plugin_resources_grouptypes_id'] = $_SESSION["glpigroups"];


        $mandatory = $resource->checkRequiredFields($input);
        $hidden = $resource->getHiddenFields($input);
        $readonly = $resource->getReadonlyFields($input);

        $config = new Config();
        $config->getFromDB(1);
        if (!empty($config->fields['hide_fieds_arrival_form'])) {
            $hidden = array_merge($hidden, json_decode($config->fields['hide_fieds_arrival_form']));
        }

        $mandatory = array_flip($mandatory);
        $hidden = array_flip($hidden);
        $readonly = array_flip($readonly);

        $contractType = new ContractType();
        $second_matricule = false;
        if ($contractType->getFromDB($input["plugin_resources_contracttypes_id"])) {
            if ($contractType->fields["use_second_matricule"] > 0) {
                $second_matricule = true;
            }
        }

        $services = [];
        if ($config->useSecondaryService() && $config->useServiceDepartmentAD()) {
            $userCat = new UserCategory();
            $usersCat = $userCat->find();
            foreach ($usersCat as $cat) {
                $services[$cat['id']] = $cat['name'];
            }
        }

        $contractType = new ContractType();
        $display_employee = false;
        $condition_emp = ['second_list' => 0];
        if ($contractType->getFromDB($input["plugin_resources_contracttypes_id"])) {
            if ($contractType->fields["use_employee_wizard"] > 0) {
                $display_employee = true;
            }
            if ($contractType->fields["use_second_list_employer"] > 0) {
                $condition_emp = ['second_list' => 1];
            }
        }


        $resource_managers = [];
        if ($config->getField('resource_manager') != "") {
            $tableProfileUser = Profile_User::getTable();

            $profile_User = new Profile_User();
            $prof = [];
            foreach (json_decode($config->getField('resource_manager')) as $profs) {
                $prof[$profs] = $profs;
            }
            $ids = join("','", $prof);
            $restrict = getEntitiesRestrictCriteria(
                $tableProfileUser,
                'entities_id',
                $_SESSION['glpiactive_entity'],
                true
            );
            $restrict = array_merge([$tableProfileUser . ".profiles_id" => [$ids]], $restrict);
            $profiles_User = $profile_User->find($restrict);

            $resource_managers[0] = Dropdown::EMPTY_VALUE;
            foreach ($profiles_User as $profileUser) {
                $user = new \User();
                $user->getFromDB($profileUser["users_id"]);
                $resource_managers[$profileUser["users_id"]] = $user->getFriendlyName();
            }
        }

        $sales_managers = [];
        if (($config->getField('sales_manager') != "")) {
            $tableProfileUser = Profile_User::getTable();

            $profile_User = new Profile_User();
            $prof = [];
            foreach (json_decode($config->getField('sales_manager')) as $profs) {
                $prof[$profs] = $profs;
            }

            $ids = join("','", $prof);
            $restrict = getEntitiesRestrictCriteria(
                $tableProfileUser,
                'entities_id',
                $_SESSION['glpiactive_entity'],
                true
            );
            $restrict = array_merge([$tableProfileUser . ".profiles_id" => [$ids]], $restrict);
            $profiles_User = $profile_User->find($restrict);

            $sales_managers[0] = Dropdown::EMPTY_VALUE;
            foreach ($profiles_User as $profileUser) {
                $user = new \User();
                $user->getFromDB($profileUser["users_id"]);
                $sales_managers[$profileUser["users_id"]] = $user->getFriendlyName();
            }
        }

        $rank = new Rank();

        TemplateRenderer::getInstance()->display('@resources/wizard_secondstep_resource.html.twig', [
            'can_edit' => Session::haveRight("plugin_resources", CREATE),
            'can_purge' => Session::haveRight("plugin_resources", PURGE),
            'can_read_employee' => Session::haveRight('plugin_resources_employee_core_form', READ),
            'can_view_rank' => $rank->canView(),
            'item' => $resource,
            'root_resources' => PLUGIN_RESOURCES_WEBDIR,
            'genders' => Resource::getGenders(),
            'services' => $services,
            'options' => $options,
            'inputs' => $input,
            'params' => [
                'title' => __('Enter general information about the resource', 'resources'),
                'target' => $target,
                'icon' => '',
                'img' => PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png",
                'plugin_resources_resources_id' => $ID,
                'default_button' => $options['default_button'] ?? false,
                'candel' => false,
                'readonly_fields' => $readonly,
                'hidden_fields' => $hidden,
                'mandatory_fields' => $mandatory,
                'second_matricule' => $second_matricule,
                'use_services_deparments_ad' => $config->useServiceDepartmentAD(),
                'use_secondary_services' => $config->useSecondaryService() && $config->useServiceDepartmentAD(),
                'use_security' => $config->useSecurity(),
                'use_notification' => ($config->fields['automatic_notification_declare_arrival_form'] == 0) ? 1 : 0,
                'display_employee' => $display_employee,
                'condition_emp' => $condition_emp,
                'date_declaration' => date('Y-m-d'),
                'users_id_recipient' => Session::getLoginUserID(),
                'resource_managers' => $resource_managers,
                'sales_managers' => $sales_managers,
            ],
        ]);

        return true;
    }

    /**
     * Wizard for Employer and Client association with the resource
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardThirdStep($plugin_resources_resources_id, $options = [])
    {
        $employee = new Employee();
        if (!$employee->canView()) {
            return false;
        }

        $employee_spotted = false;

        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);

        $restrict = ["plugin_resources_resources_id" => $plugin_resources_resources_id];
        $dbu = new DbUtils();
        $employees = $dbu->getAllDataFromTable($employee->getTable(), $restrict);

        $ID = 0;
        if (!empty($employees)) {
            foreach ($employees as $employer) {
                $ID = $employer["id"];
            }
        }
        if (empty($ID)) {
            if ($employee->getEmpty()) {
                $employee_spotted = true;
            }
        } else {
            if ($employee->getfromDB($ID)) {
                $employee_spotted = true;
            }
        }

        if (isset($options['target']) && $options['target'] == 'item') {
            $target = null;
        } else {
            $target = Toolbox::getItemTypeFormURL(Wizard::class);
        }

        if ($employee_spotted && $plugin_resources_resources_id) {
            $entity = $resource->fields["entities_id"];

            TemplateRenderer::getInstance()->display('@resources/wizard_thirdstep_employee.html.twig', [
                'can_edit' => Session::haveRight("plugin_resources", CREATE),
                'can_purge' => Session::haveRight("plugin_resources", PURGE),
                'item' => $employee,
                'root_resources' => PLUGIN_RESOURCES_WEBDIR,
                'params' => [
                    'title' => __('Enter employer information about the resource', 'resources'),
                    'target' => $target,
                    'icon' => '',
                    'img' => PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png",
                    'plugin_resources_resources_id' => $plugin_resources_resources_id,
                    'id' => $ID,
                    'entities_id' => $entity,
                    'default_button' => $options['default_button'] ?? false,
                    'compliant' => Client::isSecurityCompliance($employee->fields["plugin_resources_clients_id"]),
                ],
            ]);
        }
        return true;
    }

    /**
     * Wizard for Choice needs association with the resource
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardFourStep($plugin_resources_resources_id, $options = [])
    {
        $choice = new Choice();
        if (!$choice->canView()) {
            return false;
        }

        $spotted = false;

        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);

        $newrestrict = ["plugin_resources_resources_id" => $plugin_resources_resources_id];

        $dbu = new DbUtils();
        $newchoices = $dbu->getAllDataFromTable($choice->getTable(), $newrestrict);

        $ID = 0;
        if (!empty($newchoices)) {
            foreach ($newchoices as $newchoice) {
                $ID = $newchoice["id"];
            }
        }
        if (empty($ID)) {
            if ($choice->getEmpty()) {
                $spotted = true;
            }
        } else {
            if ($choice->getfromDB($ID)) {
                $spotted = true;
            }
        }

        if (isset($options['target']) && $options['target'] == 'item') {
            $target = null;
        } else {
            $target = Toolbox::getItemTypeFormURL(Wizard::class);
        }

        $restrict = ["plugin_resources_resources_id" => $plugin_resources_resources_id];
        $choices = $dbu->getAllDataFromTable($choice->getTable(), $restrict);
        $used = [];

        $entries = [];

        if (!empty($choices)) {
            foreach ($choices as $choice_item) {
                $used[] = $choice_item["plugin_resources_choiceitems_id"];

//                if (!empty($choice_item["comment"])) {
//                    Choice::showModifyCommentFrom($choice_item, $rand);
//                } else {
//                    Choice::showAddCommentForm($choice_item, $rand);
//                }

                $entries[] = [
                    'itemtype' => Choice::class,
                    'id' => $choice_item['id'],
                    'row_class' => (isset($data['is_deleted']) && $data['is_deleted']) ? 'table-deleted' : '',
                    'name' => Dropdown::getDropdownName(
                        "glpi_plugin_resources_choiceitems",
                        $choice_item["plugin_resources_choiceitems_id"]
                    ),
                    'comment' => Dropdown::getDropdownComments(
                        "glpi_plugin_resources_choiceitems",
                        $choice_item["plugin_resources_choiceitems_id"]
                    ),
                    'delete_choice' => [
                        'content' => _x('button', 'Delete permanently'),
                        'button-name' => 'delete_choice',
                        'id' => $choice_item["id"],
                        'plugin_resources_resources_id' => $choice_item["plugin_resources_resources_id"],
                        'button-onclick' => "",
                    ],
                ];
            }
        }

        $columns = [
            'name' => __('Name'),
            'comment' => __('Comment'),
            "delete_choice" => __('Action'),
        ];
        $formatters = [
            'name' => 'raw_html',
            'comment' => 'raw_html',
            'delete_choice' => 'button',
        ];
        $footers = [];
        $rand = mt_rand();

        if ($spotted && $plugin_resources_resources_id) {
            $entity = $resource->fields["entities_id"];

            TemplateRenderer::getInstance()->display('@resources/wizard_fourstep_choice.html.twig', [
                    'can_edit' => Session::haveRight("plugin_resources", CREATE),
                    'can_purge' => Session::haveRight("plugin_resources", PURGE),
                    'item' => $resource,
                    'comment' => $resource->fields['comment'],
                    'used' => $used,
                    'root_resources' => PLUGIN_RESOURCES_WEBDIR,
                    'params' => [
                        'title' => __('Enter the computing needs of the resource', 'resources'),
                        'target' => $target,
                        'icon' => '',
                        'img' => PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png",
                        'plugin_resources_resources_id' => $plugin_resources_resources_id,
                        'entities_id' => $entity,
                        'default_button' => $options['default_button'] ?? false,
                    ],
                    'datatable_params' => [
                        'is_tab' => true,
                        'nofilter' => true,
                        'nosort' => true,
                        'columns' => $columns,
                        'formatters' => $formatters,
                        'entries' => $entries,
                        'footers' => $footers,
                        'total_number' => count($entries),
                        'filtered_number' => count($entries),
//                        'showmassiveactions' => Session::haveRight("plugin_resources", CREATE),
//                        'massiveactionparams' => [
//                            'container' => 'massiveactioncontainer' . $rand,
//                            'itemtype' => Choice::class,
//                        ],
                    ],
                ]
            );
        }
        return true;
    }

    /**
     * Wizard for picture association with the resource
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardFiveStep($plugin_resources_resources_id)
    {
        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);
        $path = "";
        $path_send = "";
        $empty_picture = PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png";

        if (isset($resource->fields["picture"])) {
            $path = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $resource->fields["picture"];
            $path_send = PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $resource->fields["picture"];
        }

        TemplateRenderer::getInstance()->display('@resources/wizard_fivestep_photo.html.twig', [
            'can_edit' => Session::haveRight("plugin_resources", CREATE),
            'can_purge' => Session::haveRight("plugin_resources", PURGE),
            'root_resources' => PLUGIN_RESOURCES_WEBDIR,
            'params' => [
                'title' => __('Add the photo of the resource', 'resources'),
                'target' => Toolbox::getItemTypeFormURL(Wizard::class),
                'icon' => '',
                'img' => PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png",
                'plugin_resources_resources_id' => $plugin_resources_resources_id,
                'empty_picture' => $empty_picture,
                'path' => $path,
                'path_send' => $path_send,
            ],
        ]);

        return true;
    }

    /**
     * Wizard for habilitations
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardSixStep($plugin_resources_resources_id, $options = [])
    {
        $resourcehabilitation = new ResourceHabilitation();

        if (!$resourcehabilitation->canView()) {
            return false;
        }

        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);

        $existing_habilitations = [];

        $habilitation_level = new HabilitationLevel();
        $habilitation = new Habilitation();

        $dbu = new DbUtils();

        $condition = $dbu->getEntitiesRestrictCriteria(
            $habilitation_level->getTable(),
            'entities_id',
            $resource->getEntityID(),
            $habilitation_level->maybeRecursive()
        );
        $levels = $habilitation_level->find($condition, "name");

        $habilitation_levels = [];

        $resource_habilitations = $resourcehabilitation->find(
            ['plugin_resources_resources_id' => $plugin_resources_resources_id]
        );
        foreach ($resource_habilitations as $k => $resource_habilitation) {
            if ($habilitation->getFromDB($resource_habilitation['plugin_resources_habilitations_id'])) {
                $existing_habilitations[$habilitation->fields['plugin_resources_habilitationlevels_id']][] = $resource_habilitation['plugin_resources_habilitations_id'];
            }
        }

        if (count($levels) > 0) {
            //One line per level
            foreach ($levels as $level) {
                if ($habilitation_level->getFromDB($level['id'])) {
                    $mandatory = false;
                    if ($habilitation_level->getField('is_mandatory_creating_resource')) {
                        $mandatory = true;
                    }
                    $number = 0;
                    if ($habilitation_level->getField('number')) {
                        $number = 1;
                    }
                    //list of habilitations according to level
                    $habilitations = $habilitation->getHabilitationsWithLevel(
                        $habilitation_level,
                        $resource->fields["entities_id"]
                    );

                    $habilitation_levels[] = [
                        "id" => $habilitation_level->getID(),
                        "mandatory" => $mandatory,
                        "number" => $number,
                        "name" => $habilitation_level->getName(),
                        "values" => $habilitations,
                    ];
                }
            }
        }

        if (isset($options['target']) && $options['target'] == 'item') {
            $target = Toolbox::getItemTypeFormURL(ResourceHabilitation::class);
        } else {
            $target = Toolbox::getItemTypeFormURL(Wizard::class);
        }

        TemplateRenderer::getInstance()->display('@resources/wizard_sixstep_habilitation.html.twig', [
            'can_edit' => Session::haveRight("plugin_resources", CREATE),
            'can_purge' => Session::haveRight("plugin_resources", PURGE),
            'root_resources' => PLUGIN_RESOURCES_WEBDIR,
            'habilitation_levels' => $habilitation_levels,
            'existing_habilitations' => $existing_habilitations,
            'item' => $resource,
            'params' => [
                'title' => __('Enter habilitations about the resource', 'resources'),
                'target' => $target,
                'icon' => '',
                'img' => PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png",
                'plugin_resources_resources_id' => $plugin_resources_resources_id,
                'default_button' => $options['default_button'] ?? false,
            ],
        ]);


        return true;
    }

    /**
     * Wizard for documents association with the resource
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardSevenStep($plugin_resources_resources_id, $options = [])
    {
        $resource = new Resource();
        if ($plugin_resources_resources_id > 0) {
            $resource->check($plugin_resources_resources_id, READ);
        }

        $resource->getFromDB($plugin_resources_resources_id);

        echo "<div class='card container' style='min-width: 80%;'>";

        $title = __('Add documents to the resource', 'resources');
        $img = PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png";
        self::WizardHeader($title, $img);

        echo "<div class='card-body'>";

        $target = Toolbox::getItemTypeFormURL(Wizard::class);
        echo "<form action='$target' enctype='multipart/form-data' method='post'>";

        echo "<div class='row'>";
        Resource::showAddFormForItem($resource);
        echo "</div>";

        echo "<div class='row'>";
        Document_Item::showListForItem($resource, 99); // With template 99 to disable massive action
        echo "</div>";

        echo Html::hidden('plugin_resources_resources_id', ['value' => $resource->fields["id"]]);

        if ($resource->canCreate() && (!empty($plugin_resources_resources_id))) {
            echo "<br><div class='hr mt-3 mb-3'></div><div class='row'>";
            echo "<div class='hstack gap-1'>";
            echo "<div class='pe-5 ms-auto'>";
            if ($resource->canPurge()) {
                echo Html::submit(
                    _sx('button', 'Cancel the request', 'resources'),
                    [
                        'name' => 'cancel_request',
                        'class' => 'btn btn-danger ms-1',
                        'icon' => 'ti ti-x'
                    ]
                );
            }
            echo "&nbsp;";
            echo Html::submit(
                "< " . _sx('button', 'Previous', 'resources'),
                ['name' => 'undo_seven_step', 'class' => 'btn btn-primary']
            );
            echo "&nbsp;";
            echo Html::submit(
                _sx('button', 'Next', 'resources') . " >",
                ['name' => 'eight_step', 'class' => 'btn btn-success']
            );
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }

        Html::closeForm();

        echo "</div>";
        echo "</div>";

        return true;
    }

    /**
     * Wizard for recruiting information
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardEightStep($ID, $options = [])
    {
        $resource = new Resource();
        $resource->initForm($ID, $options);
        $resource->getFromDB($ID);

        $input['plugin_resources_contracttypes_id'] = $resource->fields['plugin_resources_contracttypes_id'];
        $input['entities_id'] = $resource->fields['entities_id'];
        $input['more_information'] = 1;

        $mandatory = $resource->checkRequiredFields($input);
        $mandatory = array_flip($mandatory);
        $hidden = $resource->getHiddenFields($input);
        $hidden = array_flip($hidden);
        $readonly = $resource->getReadonlyFields($input);
        $readonly = array_flip($readonly);

        if (isset($options['target']) && $options['target'] == 'item') {
            $target = null;
        } else {
            $target = Toolbox::getItemTypeFormURL(Wizard::class);
        }

        TemplateRenderer::getInstance()->display('@resources/wizard_eightstep_recruitinginformation.html.twig', [
            'can_edit' => Session::haveRight("plugin_resources", CREATE),
            'can_purge' => Session::haveRight("plugin_resources", PURGE),
            'item' => $resource,
            'root_resources' => PLUGIN_RESOURCES_WEBDIR,
            'params' => [
                'title' => __('Add recruiting informations to the resource', 'resources'),
                'target' => $target,
                'icon' => '',
                'img' => PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png",
                'plugin_resources_resources_id' => $ID,
                'hidden_fields' => $hidden,
                'mandatory_fields' => $mandatory,
                'readonly_fields' => $readonly,
                'default_button' => $options['default_button'] ?? false,
                'candel' => false,
            ],
        ]);

        return true;
    }
}
