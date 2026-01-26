<?php
/*
 *
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
use CommonGLPI;
use Dropdown;
use GlpiPlugin\Metademands\Metademand;
use Group;
use Html;
use ITILCategory;
use Plugin;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Config
 */
class Config extends CommonDBTM
{

    static $rightname = 'plugin_resources';

    /**
     * functions mandatory
     * getTypeName(), canCreate(), canView()
     * */
    static function getTypeName($nb = 0)
    {
        return __('Setup');
    }

    /**
     * Have I the global right to "view" the Object
     *
     * Default is true and check entity if the objet is entity assign
     *
     * May be overloaded if needed
     *
     * @return
     **/
    static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return
     **/
    static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
    }

    /**
     * Config constructor.
     */
    function __construct()
    {
        global $DB;

        if ($DB->tableExists($this->getTable())) {
            $this->getFromDB(1);
        }
    }

    static function getIcon()
    {
        return "ti ti-settings";
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate) {
            switch ($item->getType()) {
                case __CLASS__:
                    $ong[1] = self::createTabEntry(__('Wizard', 'resources'));
                    $ong[2] = self::createTabEntry(__('Arrival / Departure workflow', 'resources'));
                    $ong[3] = self::createTabEntry(__('Other', 'resources'));
                    if (Plugin::isPluginActive('metademands')) {
                        $ong[4] = self::createTabEntry(__('Link with metademand', 'resources'));
                    }

                    return $ong;
            }
        }
    }

    public static function getAvailablevariable() {
        return [
            '##resource_gender##' => __('Gender', 'resources'),
            '##resource_name##' => __('Surname'),
            '##resource_firstname##' => __('First name'),
            '##resource_phone##' => __('Phone'),
            '##resource_cellphone##' => __('Mobile phone'),
            '##resource_locations_id##' => __('Location'),
            '##resource_users_id##' => __('Resource manager', 'resources'),
            '##resource_users_id_sales##' => __('Sales manager', 'resources'),
            '##resource_plugin_resources_departments_id##' => Department::getTypeName(1),
            '##resource_plugin_resources_services_id##' => Service::getTypeName(1),
            '##resource_plugin_resources_functions_id##' => ResourceFunction::getTypeName(1),
            '##resource_plugin_resources_teams_id##' => Team::getTypeName(1),
            '##resource_date_begin##' => __('Arrival date', 'resources'),
            '##resource_date_end##' => __('Departure date', 'resources'),
            '##resource_comment##' => __('Description', 'resources'),
            '##resource_quota##' => __('Quota', 'resources'),
            '##resource_plugin_resources_resourcesituations_id##' => ResourceSituation::getTypeName(1),
            '##resource_plugin_resources_contractnatures_id##' => ContractNature::getTypeName(1),
            '##resource_plugin_resources_ranks_id##' => Rank::getTypeName(1),
            '##resource_plugin_resources_resourcespecialities_id##' => ResourceSpeciality::getTypeName(1),
            '##resource_sensitize_security##' => __('Sensitized to security', 'resources'),
            '##resource_read_chart##' => __('Reading the security charter', 'resources'),
            '##resource_plugin_resources_roles_id##' => Role::getTypeName(1),
            '##resource_matricule##' => __('Matricule', 'resources'),
            '##resource_matricule_second##' => __('Second matricule', 'resources'),
        ];
    }

    public function getVariableToHide(){
        return [
            'gender' => __('Gender', 'resources'),
            'name' => __('Surname'),
            'firstname' => __('First name'),
            'matricule' => __('Matricule', 'resources'),
            'phone' => __('Phone'),
            'matricule_second' => __('Second matricule', 'resources'),
            'cellphone' => __('Mobile phone'),
            'locations_id' => __('Location'),
            'quota' => __('Quota', 'resources'),
        ];
    }
    /**
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item->getType() == __CLASS__) {
            switch ($tabnum) {
                case 1:
                    $item->showWizardForm();
                    break;

                case 2:
                    $item->showWorkflowForm();
                    break;

                case 3:
                    $item->showOtherForm();
                    break;

                case 4:
                    $item->showMetademandsForm();
                    break;
            }
        }
        return true;
    }

    /**
     * @param array $options
     *
     * @return array
     * @see CommonGLPI::defineTabs()
     */
    public function defineTabs($options = [])
    {
        $ong = [];

        $this->addStandardTab(__CLASS__, $ong, $options);
        $this->addStandardTab(Resource_Change::class, $ong, $options);
        $this->addStandardTab(Adconfig::class, $ong, $options);
        $this->addStandardTab(TicketCategory::class, $ong, $options);
        $this->addStandardTab(TransferEntity::class, $ong, $options);
        //badges
        if (Plugin::isPluginActive("badges")
            && Plugin::isPluginActive("metademands")) {
            $this->addStandardTab(ResourceBadge::class, $ong, $options);
        }
        if (Plugin::isPluginActive("metademands")) {
            $this->addStandardTab(ConfigHabilitation::class, $ong, $options);
        }

        return $ong;
    }

    function showWizardForm()
    {
        if (!$this->canView()) {
            return false;
        }
        if (!$this->canCreate()) {
            return false;
        }

        $canedit = true;

        if ($canedit) {
            $this->getFromDB(1);
            echo "<form name='form' method='post' action='" . $this->getFormURL() . "'>";

            echo "<div class='center'>";
            echo "<table class='tab_cadre_fixe'>";

            echo "<tr class='tab_bg_1'>";
            echo "<th colspan='3'>";
            echo __('Wizard', 'resources');
            echo "</th>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __("If other contract are available don't display without contract", 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo("allow_without_contract", $this->fields["allow_without_contract"]);
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Default contract template selected', 'resources');
            echo "</td>";
            echo "<td>";
            $resource = new Resource();
            $resource->dropdownTemplate(
                "plugin_resources_resourcetemplates_id",
                $this->fields["plugin_resources_resourcetemplates_id"],
                false
            );
//         Dropdown::showYesNo("plugin_resources_resourcestemplates_id",$this->fields["plugin_resources_resourcestemplates_id"]);

            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Displaying the security block on the resource', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo('security_display', $this->fields['security_display']);
            echo "</td>";
            echo "<td><span class='alert alert-warning'>" ;
            echo __('Display of two additional security fields on a resource', 'resources')
                . "</span>";
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Security compliance management', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo('security_compliance', $this->fields['security_compliance']);
            echo "</td>";
            echo "<td><span class='alert alert-warning'>" . sprintf(
                    __('%1$s <br> %2$s'),
                    __('Display of four additional security fields in the clients', 'resources'),
                    __('(If all four fields are enabled, the client is compliant with security)', 'resources')
                ) . "</span>";
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Resource managers selection : only these users with these profiles', 'resources');
            echo "</td>";
            echo "<td>";
            echo Html::hidden("resource_manager");
            $possible_values = [];
            $profileITIL = new \Profile();
            $profiles = $profileITIL->find([]);
            if (!empty($profiles)) {
                foreach ($profiles as $profile) {
                    $possible_values[$profile['id']] = $profile['name'];
                }
            }
            $values = json_decode($this->fields['resource_manager']);
            if (!is_array($values)) {
                $values = [];
            }
            Dropdown::showFromArray(
                "resource_manager",
                $possible_values,
                [
                    'values' => $values,
                    'multiple' => 'multiples'
                ]
            );

            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Sales managers selection : only these users with these profiles', 'resources');
            echo "</td>";
            echo "<td>";
            echo Html::hidden("sales_manager");


            $values = json_decode($this->fields['sales_manager']);
            if (!is_array($values)) {
                $values = [];
            }
            Dropdown::showFromArray(
                "sales_manager",
                $possible_values,
                [
                    'values' => $values,
                    'multiple' => 'multiples'
                ]
            );

            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Use service and departement from AD', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo('use_service_department_ad', $this->fields['use_service_department_ad']);
            echo "</td>";
            echo "</tr>";

            if ($this->useServiceDepartmentAD()) {
                echo "<tr class='tab_bg_1'>";
                echo "<td>";
                echo __('Use secondaries services', 'resources');
                echo "</td>";
                echo "<td>";
                Dropdown::showYesNo('use_secondary_service', $this->fields['use_secondary_service']);
                echo "</td>";
                echo "</tr>";
            }

            echo "<tr>";
            echo "<td class='tab_bg_2 center' colspan='3'>";
            echo Html::hidden('id', ['value' => 1]);
            echo Html::submit(_sx('button', 'Update'), ['name' => 'update_setup', 'class' => 'btn btn-primary']);
            echo "</td>";
            echo "</tr>";
            echo "</table></div>";
            Html::closeForm();
        }
    }

    /**
     * @return bool
     */
    function showWorkflowForm()
    {
        if (!$this->canView()) {
            return false;
        }
        if (!$this->canCreate()) {
            return false;
        }

        $canedit = true;

        if ($canedit) {
            $this->getFromDB(1);
            echo "<form name='form' method='post' action='" . $this->getFormURL() . "'>";

            echo "<div class='center'>";
            echo "<table class='tab_cadre_fixe'>";

            echo "<tr class='tab_bg_1'>";
            echo "<th colspan='3'>";
            echo __('Arrival / Departure workflow', 'resources');
            echo "</th>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Create a ticket with departure', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo("create_ticket_departure", $this->fields["create_ticket_departure"]);
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Create a ticket with departure and instructions', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo("create_ticket_departure_instructions",$this->fields["create_ticket_departure_instructions"]);
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Category of departure ticket', 'resources');
            echo "</td>";
            echo "<td>";
            ITILCategory::dropdown(["name" => "categories_id", "value" => $this->fields["categories_id"]]);
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('All checklist done is mandatory for arrival and departure to close ticket', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo("mandatory_checklist", $this->fields["mandatory_checklist"]);
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Create or delete user in ldap is mandatory to close ticket', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo("mandatory_adcreation", $this->fields["mandatory_adcreation"]);
            echo "</td>";
            echo "</tr>";


            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Resource state for working people', 'resources');
            echo "</td>";
            echo "<td>";
            ResourceState::dropdown(
                [
                    'name' => 'plugin_resources_resourcestates_id_arrival',
                    'value' => $this->fields['plugin_resources_resourcestates_id_arrival']
                ]
            );
            //         Dropdown::showYesNo("plugin_resources_resourcestemplates_id",$this->fields["plugin_resources_resourcestemplates_id"]);

            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Resource state for left people', 'resources');
            echo "</td>";
            echo "<td>";
            ResourceState::dropdown(
                [
                    'name' => 'plugin_resources_resourcestates_id_departure',
                    'value' => $this->fields['plugin_resources_resourcestates_id_departure']
                ]
            );
            //         Dropdown::showYesNo("plugin_resources_resourcestemplates_id",$this->fields["plugin_resources_resourcestemplates_id"]);

            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Change checklists for resources during a contract change', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo('reaffect_checklist_change', $this->fields['reaffect_checklist_change']);

            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Send an automatique notification on the "declare an arrival" form', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo('automatic_notification_declare_arrival_form',$this->fields['automatic_notification_declare_arrival_form']);
            echo "</td>";
            echo "</tr>";

            echo Ajax::createModalWindow(
                'popupAvailablevariable',
                PLUGIN_RESOURCES_WEBDIR . '/front/modalavailablevariable.php',
                [
                    'title' => __('Are you sure?', 'resources'),
                    'reloadonclose' => false,
                    'width' => 1180,
                    'height' => 500,
                ]
            );
            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Text in the resource creation ticket after validation', 'resources') . '<br>';
            Html::requireJs('tinymce');
            echo "<a class='' href='#' onclick='popupAvailablevariable.show()' title='" . __("See variable available", "resources") . "'>" . __("See variable available", "resources") . "</a>";
            echo "</td>";
            echo "<td>";
            Html::textarea(['name' => 'text_ticket_validation','value' => $this->fields['text_ticket_validation']]);
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Hide the following fields in the "Report an Arrival" form', 'resources');
            echo "</td>";
            echo "<td>";
            $values_used = [];
            if (!empty($this->fields['hide_fieds_arrival_form'])) {
                $values_used = json_decode($this->fields['hide_fieds_arrival_form']);
            }
            Dropdown::showFromArray('hide_fieds_arrival_form',$this->getVariableToHide(),[
                'values' => $values_used,
                'width' => '250px',
                'multiple' => true,
                'entity' => $_SESSION['glpiactiveentities']
            ]);
            echo "</td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td class='tab_bg_2 center' colspan='3'>";
            echo Html::hidden('id', ['value' => 1]);
            echo Html::submit(_sx('button', 'Update'), ['name' => 'update_setup', 'class' => 'btn btn-primary']);
            echo "</td>";
            echo "</tr>";
            echo "</table></div>";
            Html::closeForm();
        }
    }


    /**
     * @return bool
     */
    function showOtherForm()
    {
        if (!$this->canView()) {
            return false;
        }
        if (!$this->canCreate()) {
            return false;
        }

        $canedit = true;

        if ($canedit) {
            $this->getFromDB(1);
            echo "<form name='form' method='post' action='" . $this->getFormURL() . "'>";

            echo "<div class='center'>";
            echo "<table class='tab_cadre_fixe'>";

            echo "<tr class='tab_bg_1'>";
            echo "<th colspan='3'>";
            echo __('Other', 'resources');
            echo "</th>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Hide/Show elements', 'resources') . " : " . __('View my resources as a commercial', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo('hide_view_commercial_resource', $this->fields['hide_view_commercial_resource']);
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Default assignment group', 'resources');
            echo "</td>";
            echo "<td>";
            Group::dropdown(['name' => 'default_assignment_group','value' => $this->fields['default_assignment_group']]);
            echo "</td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td class='tab_bg_2 center' colspan='3'>";
            echo Html::hidden('id', ['value' => 1]);
            echo Html::submit(_sx('button', 'Update'), ['name' => 'update_setup', 'class' => 'btn btn-primary']);
            echo "</td>";
            echo "</tr>";
            echo "</table></div>";
            Html::closeForm();
        }
    }

    /**
     * @return bool
     */
    function showMetademandsForm()
    {
        if (!$this->canView()) {
            return false;
        }
        if (!$this->canCreate()) {
            return false;
        }

        $canedit = true;

        if ($canedit) {
            $this->getFromDB(1);
            echo "<form name='form' method='post' action='" . $this->getFormURL() . "'>";

            echo "<div class='center'>";
            echo "<table class='tab_cadre_fixe'>";

            echo "<tr class='tab_bg_1'>";
            echo "<th colspan='3'>";
            echo __('Link with metademand', 'resources');
            echo "</th>";
            echo "</tr>";


            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Use metademand for resources changes', 'resources');
            echo "</td>";
            echo "<td>";

            $meta = new Metademand();
            $options['empty_value'] = true;
            $data = $meta->listMetademands(true, $options);
            echo Dropdown::showFromArray(
                'use_meta_for_changes',
                $data,
                ['width' => 250, 'display' => false, 'value' => $this->fields['use_meta_for_changes']]
            );

            echo "</td>";
            echo "<td><span class='alert alert-warning'>" ;
            echo __('Replace change actions management', 'resources')
                . "</span>";
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Use metademand for leaving resources', 'resources');
            echo "</td>";
            echo "<td>";

            $meta = new Metademand();
            $options['empty_value'] = true;
            $data = $meta->listMetademands(true, $options);
            echo Dropdown::showFromArray(
                'use_meta_for_leave',
                $data,
                ['width' => 250, 'display' => false, 'value' => $this->fields['use_meta_for_leave']]
            );

            echo "</td>";
            echo "<td><span class='alert alert-warning'>" ;
            echo __('Replace default form for departure', 'resources')
                . "</span>";
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Remove habilitation when update resource', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo("remove_habilitation_on_update", $this->fields["remove_habilitation_on_update"]);
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Display habilitation resource with dropdown', 'resources');
            echo "</td>";
            echo "<td>";
            Dropdown::showYesNo("display_habilitations_txt", $this->fields["display_habilitations_txt"]);
            echo "</td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td class='tab_bg_2 center' colspan='3'>";
            echo Html::hidden('id', ['value' => 1]);
            echo Html::submit(_sx('button', 'Update'), ['name' => 'update_setup', 'class' => 'btn btn-primary']);
            echo "</td>";
            echo "</tr>";
            echo "</table></div>";
            Html::closeForm();
        }
    }

    /**
     * @return mixed
     */
    function useSecurity()
    {
        return $this->fields['security_display'];
    }

    /**
     * @return mixed
     */
    function useSecurityCompliance()
    {
        return $this->fields['security_compliance'];
    }


    /**
     * @param $input
     *
     * @return
     */
    function prepareInputForAdd($input)
    {
        return $this->encodeSubtypes($input);
    }

    /**
     * @param $input
     *
     * @return
     */
    function prepareInputForUpdate($input)
    {
        return $this->encodeSubtypes($input);
    }

    /**
     * Encode sub types
     *
     * @param  $input
     *
     * @return
     */
    function encodeSubtypes($input)
    {
        if (!empty($input['resource_manager'])) {
            $input['resource_manager'] = json_encode(array_values($input['resource_manager']));
        }
        if (!empty($input['sales_manager'])) {
            $input['sales_manager'] = json_encode(array_values($input['sales_manager']));
        }

        return $input;
    }

    /**
     * @return mixed
     */
    function useServiceDepartmentAD()
    {
        return $this->fields['use_service_department_ad'];
    }

    /**
     * @return mixed
     */
    function useSecondaryService()
    {
        return $this->fields['use_secondary_service'];
    }
}
