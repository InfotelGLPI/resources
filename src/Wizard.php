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

use Ajax;
use CommonDBTM;
use DbUtils;
use Document;
use Document_Item;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Profile_User;
use Session;
use Toolbox;
use UserCategory;
use UserTitle;

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
        $resource = new Resource();

        echo "<div class='card container' style='min-width: 80%;'>";

        $title = __('Welcome to the wizard resource', 'resources');
        $img = PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png";
        self::WizardHeader($title, $img);

        echo "<div class='card-body'>";
        $target = Toolbox::getItemTypeFormURL(Wizard::class);
        echo "<form action='$target' method='post'>";

        echo "<div class='card-text'>";
        echo __('This wizard lets you create new resources in GLPI', 'resources');
        echo "<br /><br />";
        echo __('To begin, select type of contract', 'resources');
        echo "<br /><br />";

        $resource->dropdownTemplate("template");

        echo "</div>";

        echo "<div class='card-text'>";
        echo "<div class='next'>";
        echo Html::hidden('withtemplate', ['value' => 2]);
        echo Html::submit(_sx('button', 'Next', 'resources') . " >", [
            'name' => 'second_step',
            'class' => 'btn btn-success',
        ]);
        echo "</div></div>";

        Html::closeForm();

        echo "</div>";
        echo "</div>";
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

        $empty = 0;
        if ($ID > 0) {
            $resource->check($ID, READ);
        } else {
            // Create item
            $resource->check(-1, UPDATE);
            $resource->getEmpty();
            $empty = 1;
        }

        $rank = new Rank();

        if (!isset($options["requiredfields"])) {
            $options["requiredfields"] = 0;
        }
        if (($options['withtemplate'] == 2 || $options["new"] != 1) && $options["requiredfields"] != 1) {
            $options["gender"] = $resource->fields["gender"];
            $options["name"] = $resource->fields["name"];
            $options["firstname"] = $resource->fields["firstname"];
            $options["locations_id"] = $resource->fields["locations_id"];
            $options["users_id"] = $resource->fields["users_id"];
            $options["users_id_sales"] = $resource->fields["users_id_sales"];
            $options["plugin_resources_departments_id"] = $resource->fields["plugin_resources_departments_id"];
            $options["plugin_resources_services_id"] = $resource->fields["plugin_resources_services_id"];
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

        echo "<div class='card container' style='min-width: 80%;'>";

        $title = __('Enter general information about the resource', 'resources');
        $img = PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png";
        self::WizardHeader($title, $img);

        echo "<div class='card-body'>";

        $target = Toolbox::getItemTypeFormURL(Wizard::class);
        echo "<form action='$target' method='post'>";

        if (!$resource->canView()) {
            return false;
        }

        $input = [];
        if (isset($resource->fields["entities_id"]) || $empty == 1) {
            if ($empty == 1) {
                $input['plugin_resources_contracttypes_id'] = 0;
                $input['entities_id'] = $_SESSION['glpiactive_entity'];
                echo Html::hidden('entities_id', ['value' => $_SESSION["glpiactive_entity"]]);
            } else {
                $input['plugin_resources_contracttypes_id'] = $resource->fields["plugin_resources_contracttypes_id"];
                if (isset($options['withtemplate']) && $options['withtemplate'] == 2) {
                    $input['entities_id'] = $_SESSION['glpiactive_entity'];
                    echo Html::hidden('id_template', ['value' => $ID]);
                    echo Html::hidden('entities_id', ['value' => $_SESSION["glpiactive_entity"]]);
                } else {
                    $input['entities_id'] = $resource->fields["entities_id"];
                    echo Html::hidden('entities_id', ['value' => $resource->fields["entities_id"]]);
                }
            }
        }
        $input['plugin_resources_profiltypes_id'] = $_SESSION["glpiactiveprofile"]['id'];
        $input['plugin_resources_grouptypes_id'] = $_SESSION["glpigroups"];
        $required = $resource->checkRequiredFields($input);
        $hidden = $resource->getHiddenFields($input);
        $readonly = $resource->getReadonlyFields($input);

        $config = new Config();
        $config->getFromDB(1);
        if (!empty($config->fields['hide_fieds_arrival_form'])) {
            $hidden = array_merge($hidden,json_decode($config->fields['hide_fieds_arrival_form']));
        }
        $tohide = [];
        foreach ($resource->fields as $k => $f) {
            $tohide[$k] = "";
            if (in_array($k, $hidden)) {
                $tohide[$k] = "hidden";
            }
        }
        $tohide['plugin_resources_employers_id'] = "";
        if (in_array('plugin_resources_employers_id', $hidden)) {
            $tohide['plugin_resources_employers_id'] = "hidden";
        }

        echo "<div class='plugin_resources_wizard_margin card-text'>";

        echo "<div class='row'>";

        echo "<div class='col-md-4 mb-2'>";
        echo "<span class='b'>";
        echo ContractType::getTypeName(1);
        echo "</span>&nbsp;";
        if ($resource->fields["plugin_resources_contracttypes_id"]) {
            echo Dropdown::getDropdownName(
                "glpi_plugin_resources_contracttypes",
                $resource->fields["plugin_resources_contracttypes_id"]
            );
        } else {
            echo __('Without contract', 'resources');
        }
        echo "</div>";
        if (Session::isMultiEntitiesMode()) {
            echo "<div class='col-md-4 mb-2'>";
            echo "<span class='b'>";
            echo __('Entity');
            echo "</span>&nbsp;";
            echo Dropdown::getDropdownName("glpi_entities", $input['entities_id']);
            echo "</div>";
        }
        if ($resource->fields["plugin_resources_resourcestates_id"]) {
            echo "<div class='col-md-4 mb-2'>";
            echo "<span class='b'>";
            echo ResourceState::getTypeName(1);
            echo "</span>&nbsp;";
            echo Dropdown::getDropdownName(
                "glpi_plugin_resources_resourcestates",
                $resource->fields["plugin_resources_resourcestates_id"]
            );
            echo "</div>";
        }
        echo "</div>";

        echo "</div>";

        echo "<div class='plugin_resources_wizard_margin card-text'>";

        echo "<div class='row'>";

        echo "<div " . $tohide['gender'] . " class='col-md-3 mb-2'";
        if (in_array("gender", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Gender', 'resources');
        echo "</div>";

        echo "<div " . $tohide['gender'] . " class='col-md-3 mb-2'>";
        $genders = Resource::getGenders();
        $option = ['value' => $options["gender"]];
        if (in_array("gender", $readonly)) {
            $option['readonly'] = true;
        }
        Dropdown::showFromArray('gender', $genders, $option);
        echo "</div>";

        echo "</div>";

        echo "<div class='row'>";

        echo "<div " . $tohide['name'] . " class='col-md-3 mb-2'";
        if (in_array("name", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Surname');
        echo "</div>";

        echo "<div " . $tohide['name'] . " class='col-md-3 mb-2'>";
        $option = [
            'value' => $options["name"],
            'onchange' => "javascript:this.value=this.value.toUpperCase();",
        ];

        if (in_array("name", $readonly)) {
            $option['readonly'] = true;
        }
        echo Html::input('name', $option);
        echo "<br><span class='plugin_resources_wizard_comment' style='color:red;'>";
        echo __(
            "Thank you for paying attention to the spelling of the name and the firstname of the resource. For compound firstnames, separate them with a dash \"-\".",
            "resources"
        );
        echo "</span>";
        echo "</div>";

        echo "<div " . $tohide['firstname'] . " class='col-md-3 mb-2'";
        if (in_array("firstname", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('First name');
        echo "</div>";

        echo "<div " . $tohide['firstname'] . " class='col-md-3 mb-2'>";
        $option = [
            'value' => $options["firstname"],
            'onChange' => "javascript:this.value=First2UpperCase(this.value);style='text-transform:capitalize;'",
        ];

        if (in_array("firstname", $readonly)) {
            $option['readonly'] = true;
        }
        echo Html::input('firstname', $option);
        echo "</div>";

        echo "</div>";

        echo "<div class='row'>";

        echo "<div " . $tohide['matricule'] . " class='col-md-3 mb-2'";
        if (in_array("matricule", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Matricule', 'resources') . "</td>";
        echo "</div>";

        echo "<div " . $tohide['matricule'] . " class='col-md-3 mb-2'>";
        $option = ['value' => $options['matricule']];
        if (in_array("matricule", $readonly)) {
            $option['readonly'] = true;
        }
        echo Html::input('matricule', $option);
        echo "</div>";
        echo "</div>";

        echo "<div class='row'>";

        echo "<div  " . $tohide['phone'] . " class='col-md-3 mb-2'";
        if (in_array("phone", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Phone') . "</td>";
        echo "</div>";
        echo "<div  " . $tohide['phone'] . " class='col-md-3 mb-2'>";
        $option = ['value' => $this->fields['phone']];
        if (in_array("phone", $readonly)) {
            $option['readonly'] = true;
        }
        echo Html::input('phone', $option);
        echo "</div>";

        echo "<div " . $tohide['cellphone'] . " class='col-md-3 mb-2'";
        if (in_array("cellphone", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Mobile phone') . "</td>";
        echo "</div>";
        echo "<div " . $tohide['cellphone'] . " class='col-md-3 mb-2'>";
        $option = ['value' => $this->fields['cellphone']];
        if (in_array("cellphone", $readonly)) {
            $option['readonly'] = true;
        }
        echo Html::input('cellphone', $option);
        echo "</div>";

        echo "</div>";

        $contractType = new ContractType();
        $second_matricule = false;
        if ($contractType->getFromDB($resource->fields["plugin_resources_contracttypes_id"])) {
            if ($contractType->fields["use_second_matricule"] > 0) {
                $second_matricule = true;
            }
        }
        if ($second_matricule === true) {
            echo "<div " . $tohide['matricule_second'] . " class='col-md-3 mb-2'";
            if (in_array("matricule_second", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo __('Second matricule', 'resources') . "</td>";
            echo "</div>";
            echo "<div " . $tohide['matricule_second'] . " class='col-md-3 mb-2'>";
            $option = ['value' => $options['matricule_second']];
            if (in_array("matricule_second", $readonly)) {
                $option['readonly'] = true;
            }
            echo Html::input('matricule_second', $option);
            echo "</div>";
        }

        echo "</div>";

        echo "<div class='row'>";

        echo "<div " . $tohide['locations_id'] . " class='col-md-3 mb-2'";
        if (in_array("locations_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Location');
        echo "</div>";
        echo "<div " . $tohide['locations_id'] . " class='col-md-3 mb-2'>";
        $option = ['name' => "locations_id", 'value' => $options["locations_id"]];
        if (in_array("locations_id", $readonly)) {
            $option['readonly'] = true;
        }
        Dropdown::show('Location',$option);
        echo "</div>";

        echo "<div " . $tohide['quota'] . " class='col-md-3 mb-2'>";
        if (in_array("quota", $required)) {
            echo "<span class='red'>*</span>";
        }
        echo __('Quota', 'resources');
        echo "</div>";
        echo "<div " . $tohide['quota'] . " class='col-md-3 mb-2'>";
        $option = ['value' => Html::formatNumber($options["quota"], true, 4), 'size' => 14];
        if (in_array("quota", $readonly)) {
            $option['readonly'] = true;
        }
        echo Html::input('quota', $option);
        echo "</div>";

        echo "</div>";

        echo "</div>";

        if ($rank->canView()) {
            echo "<div class='plugin_resources_wizard_margin card-text'>";

            echo "<div class='row'>";

            echo "<div " . $tohide['plugin_resources_resourcesituations_id'] . " class='col-md-3 mb-2'";
            if (in_array("plugin_resources_resourcesituations_id", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo ResourceSituation::getTypeName(1);
            echo "</div>";
            echo "<div " . $tohide['plugin_resources_resourcesituations_id'] . " class='col-md-3 mb-2'>";
            $params = [
                'name' => 'plugin_resources_resourcesituations_id',
                'value' => $options['plugin_resources_resourcesituations_id'],
                'entity' => $resource->fields["entities_id"],
                'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownContractnature.php",
                'span' => 'span_contractnature',
            ];
            if (in_array("plugin_resources_resourcesituations_id", $readonly)) {
                $params['readonly'] = true;
            }
            Resource::showGenericDropdown(ResourceSituation::class, $params);
            echo "</div>";

            echo "<div " . $tohide['plugin_resources_contractnatures_id'] . " class='col-md-3 mb-2'";
            if (in_array("plugin_resources_contractnatures_id", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo ContractNature::getTypeName(1);
            echo "</div>";
            echo "<div " . $tohide['plugin_resources_contractnatures_id'] . " class='col-md-3 mb-2'>";
            echo "<span id='span_contractnature' name='span_contractnature'>";
            if ($options["plugin_resources_contractnatures_id"] > 0) {
                echo Dropdown::getDropdownName(
                    'glpi_plugin_resources_contractnatures',
                    $options["plugin_resources_contractnatures_id"]
                );
            } else {
                echo Html::hidden('plugin_resources_contractnatures_id', ['value' => 0]);
                echo __('None');
            }
            echo "</span>";
            echo "</div>";

            echo "</div>";

            echo "<div class='row'>";

            echo "<div " . $tohide['plugin_resources_ranks_id'] . " class='col-md-3 mb-2'";
            if (in_array("plugin_resources_ranks_id", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo Rank::getTypeName(1);
            echo "</div>";
            echo "<div " . $tohide['plugin_resources_ranks_id'] . " class='col-md-3 mb-2'>";
            $params = [
                'name' => 'plugin_resources_ranks_id',
                'value' => $options['plugin_resources_ranks_id'],
                'entity' => $resource->fields["entities_id"],
                'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownSpeciality.php",
                'span' => 'span_speciality',
            ];
            if (in_array("plugin_resources_ranks_id", $readonly)) {
                $params['readonly'] = true;
            }
            Resource::showGenericDropdown(Rank::class, $params);
            echo "</div>";

            echo "<div " . $tohide['plugin_resources_resourcespecialities_id'] . " class='col-md-3 mb-2'";
            if (in_array("plugin_resources_resourcespecialities_id", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo ResourceSpeciality::getTypeName(1);
            echo "</div>";
            echo "<div " . $tohide['plugin_resources_resourcespecialities_id'] . " class='col-md-3 mb-2'>";
            echo "<span id='span_speciality' name='span_speciality'>";
            if ($options["plugin_resources_resourcespecialities_id"] > 0) {
                echo Dropdown::getDropdownName(
                    'glpi_plugin_resources_resourcespecialities',
                    $options["plugin_resources_resourcespecialities_id"]
                );
            } else {
                echo Html::hidden('plugin_resources_resourcespecialities_id', ['value' => 0]);
                echo __('None');
            }
            echo "</div>";

            echo "</div>";

            echo "</div>";
        } else {
            echo Html::hidden('plugin_resources_resourcesituations_id', ['value' => 0]);
            echo Html::hidden('plugin_resources_contractnatures_id', ['value' => 0]);
            echo Html::hidden('plugin_resources_ranks_id', ['value' => 0]);
            echo Html::hidden('plugin_resources_resourcespecialities_id', ['value' => 0]);
        }

        echo "<div class='plugin_resources_wizard_margin card-text'>";

        echo "<div class='row'>";

        echo "<div " . $tohide['users_id'] . " class='col-md-3 mb-2'";
        if (in_array("users_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Resource manager', 'resources');
        echo "</div>";

        $config = new Config();
        if ($config->getField('resource_manager') != "") {
            echo "<div " . $tohide['users_id'] . " class='col-md-3 mb-2'>";


            $tableProfileUser = Profile_User::getTable();
            $tableUser = \User::getTable();
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
            $used = [];
            foreach ($profiles_User as $profileUser) {
                $user = new \User();
                $user->getFromDB($profileUser["users_id"]);
                $used[$profileUser["users_id"]] = $user->getFriendlyName();
            }
            $option = ['value' => $options["users_id"], 'display_emptychoice' => true];
            if (in_array("users_id", $readonly)) {
                $option['readonly'] = true;
            }
            Dropdown::showFromArray(
                "users_id",
                $used,
                $option
            );
            echo "</div>";
        } else {
            echo "<div " . $tohide['users_id'] . " class='col-md-3 mb-2'>";
            $option = [
                'value' => $options["users_id"],
                'name' => "users_id",
                'entity' => $input['entities_id'],
                'entity_sons' => true,
                'right' => 'all',
            ];
            if (in_array("users_id", $readonly)) {
                $option['readonly'] = true;
            }
            User::dropdown($option);
            echo "</div>";
        }

        echo "<div " . $tohide['users_id_sales'] . " class='col-md-3 mb-2'";
        if (in_array("users_id_sales", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Sales manager', 'resources');
        echo "</div>";

        if (($config->getField('sales_manager') != "")) {
            echo "<div " . $tohide['users_id_sales'] . " class='col-md-3 mb-2'>";
            $tableProfileUser = Profile_User::getTable();
            $tableUser = \User::getTable();
            $profile_User = new Profile_User();
            $prof = [];
            foreach (json_decode($config->getField('sales_manager')) as $profs) {
                $prof[$profs] = $profs;
            }

            $ids = join("','", $prof);
            $restrict = getEntitiesRestrictCriteria($tableProfileUser, 'entities_id', $input['entities_id'], true);
            $restrict = array_merge([$tableProfileUser . ".profiles_id" => [$ids]], $restrict);
            //         $profiles_User = $profile_User->find([$tableProfileUser . ".profiles_id" => [$ids], "entities_id" => $input['entities_id']]);
            $profiles_User = $profile_User->find($restrict);
            $used = [];
            foreach ($profiles_User as $profileUser) {
                $user = new User();
                $user->getFromDB($profileUser["users_id"]);
                $used[$profileUser["users_id"]] = $user->getFriendlyName();
            }
            $option = ['value' => $options["users_id_sales"], 'display_emptychoice' => true];
            if (in_array("users_id_sales", $readonly)) {
                $option['readonly'] = true;
            }
            Dropdown::showFromArray(
                "users_id_sales",
                $used,
                $option
            );
            //         Dropdown::show(User::getType(), ['value' => $options["users_id_sales"],
            //            'name' => "users_id_sales",
            //            'entity' => $input['entities_id'],
            //            'right' => 'all',
            //            'condition' => [$tableUser . ".id" => [$ids]]]);
            echo "</div>";
        } else {
            echo "<div " . $tohide['users_id_sales'] . " class='col-md-3 mb-2'>";
            $option = [
                'value' => $options["users_id_sales"],
                'name' => "users_id_sales",
                'entity' => $input['entities_id'],
                'entity_sons' => true,
                'right' => 'all',
            ];
            if (in_array("users_id_sales", $readonly)) {
                $option['readonly'] = true;
            }
            User::dropdown($option);
            echo "</div>";
        }
        echo "</div>";
        echo "</div>";

        echo "<div class='plugin_resources_wizard_margin card-text'>";

        $contractType = new ContractType();
        $display_employee = false;

        $condition_emp = ['second_list' => 0];
        if ($contractType->getFromDB($resource->fields["plugin_resources_contracttypes_id"])) {
            if ($contractType->fields["use_employee_wizard"] > 0) {
                $display_employee = true;
            }
            if ($contractType->fields["use_second_list_employer"] > 0) {
                $condition_emp = ['second_list' => 1];
            }
        }

        if (Session::haveRight('plugin_resources_employee_core_form', READ) && !$display_employee) {
            echo "<div class='row'>";

            echo "<div " . $tohide['plugin_resources_employers_id'] . " class='col-md-3 mb-2'";
            if (in_array("plugin_resources_employers_id", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo Employer::getTypeName(1);
            echo "</div>";

            echo "<div " . $tohide['plugin_resources_employers_id'] . " class='col-md-3 mb-2'>";
            $option = [
                'name' => "plugin_resources_employers_id",
                'value' => $options["plugin_resources_employers_id"],
                'entity' => $_SESSION['glpiactiveentities'],
                'condition' => $condition_emp,
            ];
            if (in_array("plugin_resources_employers_id", $readonly)) {
                $option['readonly'] = true;
            }
            Dropdown::show(
                Employer::class,
                $option
            );
            echo "</div>";


            echo "<div>";
            echo "</div>";
            echo "<div>";
            echo "</div>";

            echo "</div>";
        }

        echo "<div class='row'>";

        echo "<div " . $tohide['plugin_resources_departments_id'] . " class='col-md-3 mb-2'";
        if (in_array("plugin_resources_departments_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo Department::getTypeName(1);
        echo "</div>";
        $rand = mt_rand();
        echo "<div " . $tohide['plugin_resources_departments_id'] . " class='col-md-3 mb-2'>";
        if ($config->useServiceDepartmentAD()) {
            $option = [
                'name' => "plugin_resources_departments_id",
                'value' => $resource->fields["plugin_resources_departments_id"],
                'rand' => $rand,
            ];
            if (in_array("plugin_resources_departments_id", $readonly)) {
                $option['readonly'] = true;
            }
            UserTitle::dropdown(
                $option
            );
        } else {
            $option = [
                'name' => "plugin_resources_departments_id",
                'value' => $options["plugin_resources_departments_id"],
                'entity' => $_SESSION['glpiactiveentities'],
                'rand' => $rand,
            ];
            if (in_array("plugin_resources_departments_id", $readonly)) {
                $option['readonly'] = true;
            }
            Dropdown::show(
                Department::class,
                $option
            );
        }
        echo "</div>";

        echo "<div " . $tohide['plugin_resources_services_id'] . " class='col-md-3 mb-2'";
        if (in_array("plugin_resources_services_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo Service::getTypeName(1);
        echo "</div>";

        echo "<div " . $tohide['plugin_resources_services_id'] . " class='col-md-3 mb-2' id='show_services'>";
        if ($config->useServiceDepartmentAD()) {
            $option = ['name' => "plugin_resources_services_id",
                'value' => $resource->fields["plugin_resources_services_id"],
                'rand' => $rand,];
            if (in_array("plugin_resources_services_id", $readonly)) {
                $option['readonly'] = true;
            }
            UserCategory::dropdown(
                $option
            );
        } else {
            //      Dropdown::show(Service::class,
            //                     ['name'   => "plugin_resources_services_id",
            //                      'value'  => $options["plugin_resources_services_id"],
            //                      'entity' => $_SESSION['glpiactiveentities']]);
            $option = [
                'name' => "plugin_resources_services_id",
                'value' => $options["plugin_resources_services_id"],
                'entity' => $_SESSION['glpiactiveentities'],
                'rand' => $rand,
            ];
            if (in_array("plugin_resources_services_id", $readonly)) {
                $option['readonly'] = true;
            }
            Service::dropdownFromDepart($options["plugin_resources_departments_id"], $option);
            $params = [
                'plugin_resources_services_id' => '__VALUE__',
                'rand' => $rand,
            ];
            Ajax::updateItemOnSelectEvent(
                "dropdown_plugin_resources_services_id$rand",
                "show_roles",
                "../ajax/dropdownRole.php",
                $params
            );
        }
        echo "</div>";
        $params = [
            'plugin_resources_departments_id' => '__VALUE__',
            'rand' => $rand,
        ];
        Ajax::updateItemOnSelectEvent(
            "dropdown_plugin_resources_departments_id$rand",
            "show_services",
            "../ajax/dropdownService.php",
            $params
        );
        echo "<div " . $tohide['plugin_resources_roles_id'] . " class='col-md-3 mb-2'";
        if (in_array("plugin_resources_roles_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo Role::getTypeName(1);
        echo "</div>";
        echo "<div " . $tohide['plugin_resources_roles_id'] . " class='col-md-3 mb-2' id='show_roles'>";
        $option = [
            'name' => "plugin_resources_roles_id",
            'value' => $options["plugin_resources_roles_id"],
            'entity' => $_SESSION['glpiactiveentities'],
            'rand' => $rand,
        ];
        if (in_array("plugin_resources_roles_id", $readonly)) {
            $option['readonly'] = true;
        }
        Role::dropdownFromService($options['plugin_resources_services_id'], $option);

        echo "</div>";


        if ($config->useSecondaryService() && $config->useServiceDepartmentAD()) {
            echo "<div class='col-md-3 mb-2'>";
            echo __('Secondaries services', 'resources');

            $services = [];
            $userCat = new UserCategory();
            $usersCat = $userCat->find();
            foreach ($usersCat as $cat) {
                $services[$cat['id']] = $cat['name'];
            }
            echo "</div>";
            echo "<div class='col-md-3 mb-2' id='show_secondary_services'>";
            Dropdown::showFromArray(
                "secondary_services",
                $services,
                [
                    'values' => !empty($resource->fields['secondary_services']) ? json_decode(
                        $resource->fields['secondary_services'],
                        true
                    ) : [],
                    'multiple' => true,
                ]
            );
            echo "</div>";
        }

        echo "<div " . $tohide['plugin_resources_functions_id'] . " class='col-md-3 mb-2'";
        if (in_array("plugin_resources_functions_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo ResourceFunction::getTypeName(1);
        echo "</div>";
        echo "<div " . $tohide['plugin_resources_functions_id'] . " class='col-md-3 mb-2' id='show_roles'>";
        $option = [
            'name' => "plugin_resources_functions_id",
            'value' => $options["plugin_resources_functions_id"],
            'entity' => $_SESSION['glpiactiveentities'],
        ];
        if (in_array("plugin_resources_functions_id", $readonly)) {
            $option['readonly'] = true;
        }
        Dropdown::show(
            ResourceFunction::class,
            $option
        );

        echo "</div>";
        echo "<div " . $tohide['plugin_resources_teams_id'] . " class='col-md-3 mb-2'";
        if (in_array("plugin_resources_teams_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo Team::getTypeName(1);
        echo "</div>";
        echo "<div " . $tohide['plugin_resources_teams_id'] . " class='col-md-3 mb-2' id='show_roles'>";
        $option = [
            'name' => "plugin_resources_teams_id",
            'value' => $options["plugin_resources_teams_id"],
            'entity' => $_SESSION['glpiactiveentities'],
        ];
        if (in_array("plugin_resources_teams_id", $readonly)) {
            $option['readonly'] = true;
        }
        Dropdown::show(
            Team::class,
            $option
        );

        echo "</div>";
        echo "</div>";


        echo "<div class='row'>";

        echo "<div " . $tohide['date_begin'] . " class='col-md-3 mb-2'";
        if (in_array("date_begin", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Arrival date', 'resources');
        echo "</div>";
        echo "<div " . $tohide['date_begin'] . " class='col-md-3 mb-2'>";
        $option = ['value' => $options["date_begin"]];
        if (in_array("date_begin", $readonly)) {
            $option['readonly'] = true;
        }
        Html::showDateField("date_begin", $option);
        echo "</div>";

        echo "<div " . $tohide['date_end'] . " class='col-md-3 mb-2'";
        if (in_array("date_end", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Departure date', 'resources') . "&nbsp;";
        if (!in_array("date_end", $required)) {
            Html::showToolTip(nl2br(__('Empty for non defined', 'resources')));
        }
        echo "</div>";
        echo "<div " . $tohide['date_end'] . " class='col-md-3 mb-2'>";
        $option = ['value' => $options["date_end"]];
        if (in_array("date_end", $readonly)) {
            $option['readonly'] = true;
        }
        Html::showDateField("date_end", $option);
        echo "</div>";

        echo "</div>";

        echo "</div>";

        $config = new Config();

        if ($config->useSecurity()) {
            echo "<div class='plugin_resources_wizard_margin card-text'>";
            echo "<div class='row'>";

            echo "<div class='col-md-3 mb-2'>";
            echo __('Sensitized to security', 'resources');
            echo "</div>";
            echo "<div class='col-md-3 mb-2'>";
            $checked = '';
            if (isset($options['sensitize_security']) && $options['sensitize_security']) {
                $checked = "checked = true";
            }
            echo "<input type='checkbox' name='sensitize_security' $checked value='1'>";
            echo "</div>";

            echo "<div class='col-md-3 mb-2'>";
            echo __('Reading the security charter', 'resources');
            echo "</div>";
            echo "<div class='col-md-3 mb-2'>";
            $checked = '';
            if (isset($options['read_chart']) && $options['read_chart']) {
                $checked = "checked = true";
            }
            echo "<input type='checkbox' name='read_chart' $checked value='1'>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }

        echo "<div class='plugin_resources_wizard_margin card-text'>";

        echo "<div>";

        echo "<div " . $tohide['comment'] . " class='row'>";

        echo "<div>";
        echo __('Description');
        echo "</div>";

        echo "</div>";

        echo "<div " . $tohide['comment'] . " class='row'>";
        echo "<div>";
        echo Html::textarea([
            'name' => 'comment',
            'value' => $options["comment"],
            'cols' => '95',
            'rows' => '6',
            'display' => false,
        ]);
        echo "</div>";
        echo "</div>";


        echo "<div class='row'>";
        echo "<div ";
        if ($config->fields['automatic_notification_declare_arrival_form']) {
            echo "hidden ";
        }
        echo ">";
        echo __('Send a notification');
        echo "&nbsp;<input type='checkbox' name='send_notification' checked = true";
        if (Session::getCurrentInterface() != 'central') {
            echo " disabled='true' ";
        }
        echo " value='1'>";
        if (Session::getCurrentInterface() != 'central') {
            echo Html::hidden('send_notification', ['value' => 1]);
        }
        echo "</div>";
        echo "</div>";

        if (!empty($required)) {
            echo "<div class='row'>";
            echo "<div style='color:red;'>";
            echo __('The fields in red must be completed', 'resources');
            echo "</div>";
            echo "</div>";
        }

        echo "</div>";
        echo "</div>";

        $contract = $resource->fields["plugin_resources_contracttypes_id"];
        if ($empty == 1) {
            $contract = $input['plugin_resources_contracttypes_id'];
        }
        echo Html::hidden('plugin_resources_contracttypes_id', ['value' => $contract]);
        echo Html::hidden(
            'plugin_resources_resourcestates_id',
            ['value' => $resource->fields["plugin_resources_resourcestates_id"]]
        );
        echo Html::hidden('withtemplate', ['value' => $options['withtemplate']]);
        echo Html::hidden('date_declaration', ['value' => date('Y-m-d')]);
        echo Html::hidden('users_id_recipient', ['value' => Session::getLoginUserID()]);

        echo Html::hidden('plugin_resources_leavingreasons_id', ['value' => 0]);

        if ($resource->canCreate() && (empty($ID) || $options['withtemplate'] == 2)) {
            echo "<div class='row'>";
            echo "<div>";
            echo "<div class='preview'>";
            echo Html::submit(
                "< " . _sx('button', 'Previous', 'resources'),
                ['name' => 'undo_second_step', 'class' => 'btn btn-primary']
            );
            echo "</div>";
            echo "<div class='next'>";
            echo Html::submit(
                _sx('button', 'Next', 'resources') . " >",
                ['name' => 'third_step', 'class' => 'btn btn-success']
            );
            echo "</div>";
            echo "</div>";
            echo "</div>";
        } elseif ($resource->canCreate() && !empty($ID) && $options["new"] != 1) {
            echo "<div class='row'>";
            echo "<div>";
            echo "<div class='preview'>";
            echo Html::submit(
                "< " . _sx('button', 'Previous', 'resources'),
                ['name' => 'undo_second_step', 'class' => 'btn btn-primary']
            );
            echo "</div>";
            echo "<div class='next'>";
            echo Html::submit(
                _sx('button', 'Next', 'resources') . " >",
                ['name' => 'third_step', 'class' => 'btn btn-success']
            );
            echo Html::hidden('plugin_resources_resources_id', ['value' => $resource->fields["id"]]);
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
     * Wizard for Employer and Client association with the resource
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardThirdStep($plugin_resources_resources_id)
    {
        global $CFG_GLPI;

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

        if ($employee_spotted && $plugin_resources_resources_id) {

            echo "<div class='card container' style='min-width: 80%;'>";

            $title = __('Enter employer information about the resource', 'resources');
            $img = PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png";
            self::WizardHeader($title, $img);

            echo "<div class='card-body'>";

            $target = Toolbox::getItemTypeFormURL(Wizard::class);
            echo "<form action='$target' method='post'>";

            $entity = $resource->fields["entities_id"];

            echo "<div class='row'>";
            echo "<div class='col-md-3 mb-2'>";
            echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
            echo Employer::getTypeName(1);
            echo "</div>";
            echo "<div class='col-md-3 mb-2'>";
            Dropdown::show(Employer::class, [
                'name' => "plugin_resources_employers_id",
                'value' => $employee->fields["plugin_resources_employers_id"],
                'entity' => $entity,
            ]);
            echo "</div>";
            echo "<div class='col-md-3 mb-2'>";
            echo Client::getTypeName(1);
            echo "</div>";
            echo "<div class='col-md-3 mb-2'>";
            Dropdown::show(Client::class, [
                'name' => "plugin_resources_clients_id",
                'value' => $employee->fields["plugin_resources_clients_id"],
                'entity' => $entity,
                'on_change' => "plugin_resources_security_compliance(\"" . $CFG_GLPI['root_doc'] . "\", this.value);",
            ]);

            echo "<div style='color: green;' id='security_compliance'>";
            if (Client::isSecurityCompliance($employee->fields["plugin_resources_clients_id"])) {
                echo __('Security compliance', 'resources') . "&nbsp;";
                echo "<i style='color:green' class='ti ti-circle-check'></i>";
            }
            echo "</div>";
            echo "</div>";
            echo "</div>";
            if ($employee->canCreate()) {
                echo "<div class='row'>";
                echo "<div>";
                echo "<div class='preview'>";
                echo Html::hidden('id', ['value' => $ID]);
                echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                echo Html::hidden('withtemplate', ['value' => 0]);
                echo Html::submit(
                    "< " . _sx('button', 'Previous', 'resources'),
                    ['name' => 'undo_third_step', 'class' => 'btn btn-primary']
                );
                echo "</div>";
                echo "<div class='next'>";
                echo Html::submit(
                    _sx('button', 'Next', 'resources') . " >",
                    ['name' => 'four_step', 'class' => 'btn btn-success']
                );
                echo "</div>";
                echo "</div>";
                echo "</div>";
            }

            Html::closeForm();

            echo "</div>";
            echo "</div>";
        }
        return true;
    }

    /**
     * Wizard for Choice association with the resource
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardFourStep($plugin_resources_resources_id)
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

        if ($spotted && $plugin_resources_resources_id) {

            echo "<div class='card container' style='min-width: 80%;'>";

            $title = __('Enter the computing needs of the resource', 'resources');
            $img = PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png";
            self::WizardHeader($title, $img);

            echo "<div class='card-body'>";

            $target = Toolbox::getItemTypeFormURL(Wizard::class);
            echo "<form action='$target' name=\"choice\" method='post'>";

            $restrict = ["plugin_resources_resources_id" => $plugin_resources_resources_id];
            $choices = $dbu->getAllDataFromTable($choice->getTable(), $restrict);

            echo "<div class='row'>";
            echo "<div>";
            echo "<h5 class=\"bt-title-divider\">";
            echo __('Add a need', 'resources');
            echo "</h5>";
            $used = [];

            if ($choice->canCreate()) {
                if (!empty($choices)) {
                    foreach ($choices as $choice) {
                        $used[] = $choice["plugin_resources_choiceitems_id"];
                    }
                }

                echo "&nbsp;";
                echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                Dropdown::show(
                    ChoiceItem::class,
                    [
                        'name' => 'plugin_resources_choiceitems_id',
                        'entity' => $_SESSION['glpiactive_entity'],
                        'condition' => ['is_helpdesk_visible' => 1],
                        'used' => $used,
                    ]
                );
                echo "&nbsp;";
                echo Html::submit(_sx('button', 'Add'), ['name' => 'addchoice', 'class' => 'btn btn-primary']);
                echo "<br><br>";
            }
            echo "</div>";
            echo "</div>";

            echo "<div class='row'>";
            echo "<div>";
            echo "<h5 class=\"bt-title-divider\">";
            echo __('IT needs identified', 'resources');
            echo "</h5>";

            if (!empty($choices)) {
                foreach ($choices as $choice) {
                    $used[] = $choice["plugin_resources_choiceitems_id"];

                    echo "<div class='row' style='border:#CCC;border-style: dashed;'>";

                    $items = Dropdown::getDropdownName(
                        "glpi_plugin_resources_choiceitems",
                        $choice["plugin_resources_choiceitems_id"],
                        1
                    );

                    echo "<br><div class='col-md-3 mb-2'>";
                    echo $items["name"];
                    echo "</div>";
                    echo "<div class='col-md-3 mb-2'>";
                    echo nl2br($items["comment"]);
                    echo "</div>";
                    echo "<div class='col-md-4 center'>";
                    $items_id = $choice["id"];
                    $rand = mt_rand();
                    if (!empty($choice["comment"])) {
                        Choice::showModifyCommentFrom($choice, $rand);
                    } else {
                        Choice::showAddCommentForm($choice, $rand);
                    }
                    echo "</div>";
                    if ($choice->canCreate()) {
                        echo "<div class='col-md-2'>";
                        Html::showSimpleForm(
                            PLUGIN_RESOURCES_WEBDIR . '/front/wizard.form.php',
                            'deletechoice',
                            _x('button', 'Delete permanently'),
                            ['id' => $choice["id"], 'plugin_resources_resources_id' => $plugin_resources_resources_id]
                        );

                        echo "</div>";
                    }
                    echo "</div><br><br>";
                }
            } else {
                echo "<div class='row'>";
                echo "<div>";
                echo __('None');
                echo "</div>";
                echo "</div>";
            }

            if ($choice->canCreate()) {
                $rand = mt_rand();
                echo "<div class='row'>";
                echo "<div  style='border-top: #CCC;border-top-style: dashed;'>";
                //            echo "<a href=\"javascript:showHideDiv('view_comment','commentimg$rand','" .
                //                 $CFG_GLPI["root_doc"] . "/pics/deplier_down.png','" .
                //                 $CFG_GLPI["root_doc"] . "/pics/deplier_up.png');\">";
                //            echo "<img alt='' name='commentimg$rand' src=\"" .
                //                 $CFG_GLPI["root_doc"] . "/pics/deplier_down.png\">&nbsp;";
                echo "<h5 class=\"bt-title-divider\">";
                echo __('Others needs', 'resources') . "&nbsp;";
                Html::showToolTip(__('Will be added to the resource comment area', 'resources'), []);
                echo "</h5>";
                echo "</div>";
                echo "</div>";

                //            echo "<div class='center' style='display:none;' id='view_comment'>";
                echo "<div class='row'>";
                echo "<div style='margin-bottom: 5px;'>";
                $comment = "";
                //            if (isset($_SESSION['plugin_ressources_' . $plugin_resources_resources_id . '_comment'])) {

                if (!empty($resource->fields['comment'])) {
                    $comment = $resource->fields['comment'];
                }
                $comment = (isset($_SESSION['plugin_ressources_' . $plugin_resources_resources_id . '_comment'])) ? $_SESSION['plugin_ressources_' . $plugin_resources_resources_id . '_comment'] : $comment;

                echo "<br>";
                echo Html::textarea([
                    'name' => 'comment',
                    'value' => $comment,
                    'cols' => '80',
                    'rows' => '6',
                    'display' => false,
                ]);
                echo "<br>";
                if (isset($_SESSION['plugin_ressources_' . $plugin_resources_resources_id . '_comment'])) {
                    echo Html::submit(
                        _sx('button', 'Update'),
                        ['name' => 'updatecomment', 'class' => 'btn btn-primary']
                    );
                } else {
                    echo Html::submit(_sx('button', 'Add'), ['name' => 'addcomment', 'class' => 'btn btn-primary']);
                }
                //            }
                //            echo "</div>";
                echo "</div>";
                echo "</div>";
            }

            if ($choice->canCreate()) {
                echo "<div class='row'>";
                echo "<div>";
                echo "<div class='preview'>";
                echo Html::submit(
                    "< " . _sx('button', 'Previous', 'resources'),
                    ['name' => 'undo_four_step', 'class' => 'btn btn-primary']
                );
                echo "</div>";
                echo "<div class='next'>";
                echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                echo Html::submit(
                    _sx('button', 'Next', 'resources') . " >",
                    ['name' => 'five_step', 'class' => 'btn btn-success']
                );
                echo "</div>";
                echo "</div></div>";
            }

            Html::closeForm();

            echo "</div>";
            echo "</div>";
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
    public function wizardFiveStep($ID, $options = [])
    {
        $ressource = new Resource();
        if ($ID > 0) {
            $ressource->check($ID, READ);
        }

        echo "<div class='card container' style='min-width: 80%;'>";

        $title = __('Add the photo of the resource', 'resources');
        $img = PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png";
        self::WizardHeader($title, $img);

        echo "<div class='card-body'>";

        $target = Toolbox::getItemTypeFormURL(Wizard::class);
        echo "<form action='$target' enctype='multipart/form-data' method='post'>";

        if (!$ressource->canView()) {
            return false;
        }

        echo "<div class='row'>";
        echo "<div>";

        if (isset($ressource->fields["picture"])) {
            $path = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $ressource->fields["picture"];
            if (file_exists($path)) {
                echo "<object data='" . PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $ressource->fields["picture"] . "'>
            </object> ";
            } else {
                echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png'>";
            }
        } else {
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png'>";
        }
        echo "</div></div>";

        echo "<div class='row'>";
        echo "<div style='margin-bottom: 5px;'>";

        echo __('Photo format : JPG', 'resources') . "<br>";
        //      echo Html::file(['name' => 'picture', 'display' => false, 'onlyimages' => true]); //'value' => $ressource->fields["picture"],
        echo "<input class='form-control' type='file' name='picture'>";
        echo "&nbsp;";
        echo "(" . Document::getMaxUploadSize() . ")&nbsp;";

        echo "</div></div>";

        echo "<div class='row'>";
        echo "<div>";
        echo Html::submit(_sx('button', 'Add'), ['name' => 'upload_five_step', 'class' => 'btn btn-success']);
        echo Html::hidden('plugin_resources_resources_id', ['value' => $ressource->fields["id"]]);
        echo "</div></div>";

        if ($ressource->canCreate() && (!empty($ID))) {
            echo "<div class='row'>";
            echo "<div>";
            echo "<div class='preview'>";
            echo Html::submit(
                "< " . _sx('button', 'Previous', 'resources'),
                ['name' => 'undo_five_step', 'class' => 'btn btn-primary']
            );
            echo "</div>";
            echo "<div class='next'>";
            echo Html::submit(
                _sx('button', 'Next', 'resources') . " >",
                ['name' => 'six_step', 'class' => 'btn btn-success']
            );
            echo Html::hidden('plugin_resources_resources_id', ['value' => $ressource->fields["id"]]);
            echo "</div>";
            echo "</div></div>";
        }

        Html::closeForm();

        echo "</div>";
        echo "</div>";

        return true;
    }

    /**
     * Wizard for habilitations
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardSixStep($plugin_resources_resources_id)
    {
        global $DB;

        $ressourcehabilitation = new ResourceHabilitation();

        if (!$ressourcehabilitation->canView()) {
            return false;
        }

        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);

        if ($plugin_resources_resources_id) {
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

            echo "<div class='card container' style='min-width: 80%;'>";

            $title = __('Enter habilitations about the resource', 'resources');
            $img = PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png";
            self::WizardHeader($title, $img);

            echo "<div class='card-body'>";

            $target = Toolbox::getItemTypeFormURL(Wizard::class);
            echo "<form action='$target' method='post'>";

            if (count($levels) > 0) {
                $cpt = 1;
                //One line per level
                foreach ($levels as $level) {
                    echo "<div class='row'>";
                    echo "<div>";

                    if ($habilitation_level->getFromDB($level['id'])) {
                        $mandatory = "";
                        if ($habilitation_level->getField('is_mandatory_creating_resource')) {
                            $mandatory = "style='color:red;'";
                        }
                        //list of habilitations according to level
                        $habilitations = $habilitation->getHabilitationsWithLevel(
                            $habilitation_level,
                            $resource->fields["entities_id"]
                        );

                        // check if habilitation is already set for this level
                        $query_habilitations = "SELECT `glpi_plugin_resources_habilitations` .*
                              FROM `glpi_plugin_resources_resourcehabilitations`
                              LEFT JOIN `glpi_plugin_resources_habilitations`
                              ON `glpi_plugin_resources_habilitations`.id = `glpi_plugin_resources_resourcehabilitations`.`plugin_resources_habilitations_id`
                              WHERE `plugin_resources_resources_id` = $plugin_resources_resources_id
                              AND `plugin_resources_habilitationlevels_id` = $cpt";
                        $result_habilitations = $DB->doQuery($query_habilitations);
                        while ($data_habilitation = $DB->fetchAssoc($result_habilitations)) {
                            if (!is_null($data_habilitation)) {
                                $value = $data_habilitation['name'];
                                $id = $data_habilitation['id'];
                                if (!empty($data_habilitation["comment"])) {
                                    $value .= " - " . $data_habilitation["comment"];
                                }
                            }
                        }
                        if (isset($value) && isset($id)) {
                            $key = array_search($value, $habilitations);
                            // Cleaning to avoid duplicate
                            $cleaning_query = "DELETE FROM glpi_plugin_resources_resourcehabilitations
                                        WHERE plugin_resources_resources_id= $plugin_resources_resources_id
                                        AND plugin_resources_habilitations_id= $id";
                            $DB->doQuery($cleaning_query);
                        }
                        $cpt++;

                        echo "<div class='row'>";
                        echo "<div class='col-md-4 mb-2' $mandatory>";
                        echo $habilitation_level->getName();
                        echo "</div>";
                        echo "<div class='col-md-4 mb-2'>";
                        if ($habilitation_level->getField('number')) {
                            Dropdown::showFromArray(
                                str_replace(
                                    " ",
                                    "_",
                                    $habilitation_level->getName()
                                ) . "__" . $habilitation_level->getID(),
                                $habilitations,
                                [
                                    'multiple' => true,
                                    'width' => 200,
                                ]
                            );
                        } else {
                            if (isset($key)) {
                                Dropdown::showFromArray(
                                    str_replace(
                                        " ",
                                        "_",
                                        $habilitation_level->getName()
                                    ) . "__" . $habilitation_level->getID(),
                                    $habilitations,
                                    ['value' => $key]
                                );
                            } else {
                                Dropdown::showFromArray(
                                    str_replace(
                                        " ",
                                        "_",
                                        $habilitation_level->getName()
                                    ) . "__" . $habilitation_level->getID(),
                                    $habilitations
                                );
                            }
                        }
                        echo "</div></div>";
                    }
                    echo "</div></div>";
                }
            } else {
                //No level of habilitations no addition of authorizations to the resource
                echo "<div class='row'>";
                echo "<div>";
                echo __('No habilitation level, you cannot add habilitation for this resource.', 'resources');
                echo "</div></div>";
            }

            if ($ressourcehabilitation->canCreate()) {
                echo "<div class='row'>";
                echo "<div>";
                echo "<div class='preview'>";
                echo Html::submit(
                    "< " . _sx('button', 'Previous', 'resources'),
                    ['name' => 'undo_six_step', 'class' => 'btn btn-primary']
                );
                echo "</div>";
                echo "<div class='next'>";
                echo Html::submit(
                    _sx('button', 'Next', 'resources') . " >",
                    ['name' => 'seven_step', 'class' => 'btn btn-success']
                );
                echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                echo "</div>";
                echo "</div>";
                echo "</div>";
            }

            Html::closeForm();
            echo "</div></div>";
        }
        return true;
    }

    /**
     * Wizard for documents association with the resource
     *
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    public function wizardSevenStep($ID, $options = [])
    {
        $ressource = new Resource();
        if ($ID > 0) {
            $ressource->check($ID, READ);
        }

        $ressource->getFromDB($ID);

        $entities = "";
        $entity = $_SESSION["glpiactive_entity"];

        $doc_item = new Document_Item();
        $used_found = $doc_item->find([
            'items_id' => $ressource->getID(),
            'itemtype' => $ressource->getType(),
        ]);
        $used = array_keys($used_found);
        $used = array_combine($used, $used);

        if ($ressource->isEntityAssign()) {
            /// Case of personal items : entity = -1 : create on active entity (Reminder case))
            if ($ressource->getEntityID() >= 0) {
                $entity = $ressource->getEntityID();
            }

            if ($ressource->isRecursive()) {
                $entities = getSonsOf('glpi_entities', $entity);
            } else {
                $entities = $entity;
            }
        }

        echo "<div class='card container' style='min-width: 80%;'>";

        $title = __('Add documents to the resource', 'resources');
        $img = PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png";
        self::WizardHeader($title, $img);

        echo "<div class='card-body'>";

        $target = Toolbox::getItemTypeFormURL(Wizard::class);
        echo "<form action='$target' enctype='multipart/form-data' method='post'>";

        if (!$ressource->canView()) {
            return false;
        }

        echo "<div class='row'>";
        echo "<div>";

        Resource::showAddFormForItem($ressource);

        echo "</div></div>";

        echo "<div class='row'>";
        echo "<div>";

        Document_Item::showListForItem($ressource, 99); // With template 99 to disable massive action

        echo "</div></div>";

        echo Html::hidden('plugin_resources_resources_id', ['value' => $ressource->fields["id"]]);

        if ($ressource->canCreate() && (!empty($ID))) {
            echo "<div class='row'>";
            echo "<div class='col-md-11 mb-2'>";
            echo "<div class='preview'>";
            echo Html::submit(
                "< " . _sx('button', 'Previous', 'resources'),
                ['name' => 'undo_seven_step', 'class' => 'btn btn-primary']
            );
            echo "</div>";
            echo "<div class='next'>";
            echo Html::submit(
                _sx('button', 'Next', 'resources') . " >",
                ['name' => 'eight_step', 'class' => 'btn btn-success']
            );
            echo Html::hidden('plugin_resources_resources_id', ['value' => $ressource->fields["id"]]);
            echo "</div>";
            echo "</div></div>";
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
        $tt = [];
        $mandatory = [];
        $ressource = new Resource();
        $ressource->initForm($ID, $options);
        $ressource->getFromDB($ID);
        $input['plugin_resources_contracttypes_id'] = $ressource->fields['plugin_resources_contracttypes_id'];
        $input['entities_id'] = $ressource->fields['entities_id'];
        $input['more_information'] = 1;
        $mandatory = $ressource->checkRequiredFields($input);
        $mandatory = array_flip($mandatory);
        $hidden = $ressource->getHiddenFields($input);
        $hidden = array_flip($hidden);
        $readonly = $ressource->getReadonlyFields($input);
        $readonly = array_flip($readonly);
        if (isset($options['target']) && $options['target'] == 'item') {
            $target = null;
        } else {
            $target = PLUGIN_RESOURCES_WEBDIR . "/front/wizard.form.php";
        }

        echo "<div class='card container' style='min-width: 80%;'>";

        $title = __('Add recruiting informations to the resource', 'resources');
        $img = PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png";
        self::WizardHeader($title, $img);

        echo "<div class='card-body'>";

        TemplateRenderer::getInstance()->display('@resources/recruitinginformation.html.twig', [
            'item' => $ressource,
            'params' => [
                'plugin_resources_resources_id' => $ID,
                'hidden_fields' => $hidden,
                'mandatory_fields' => $mandatory,
                'readonly_fields' => $readonly,
                'target' => $target,
                'default_button' => $options['default_button'] ?? false,
                'candel' => false,
            ],
        ]);

        echo "</div>";
        echo "</div>";

        return true;
    }
}
