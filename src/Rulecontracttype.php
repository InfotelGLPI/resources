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

use Html;
use Rule;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}


/**
 * Rule class store all informations about a GLPI rule :
 *   - description
 *   - criterias
 *   - actions
 *
 **/
class RuleContracttype extends Rule
{
    public static $rightname = 'plugin_resources';

    public $can_sort = true;

    /**
     * Get title used in rule
     *
     * @return string of the rule
     **/
    public function getTitle()
    {
        return Resource::getTypeName(2) . " " . __('Required Fields', 'resources');
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
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return
     **/
    public static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
    }

    /**
     * @return bool
     */
    public function maybeRecursive()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isEntityAssign()
    {
        return true;
    }

    /**
     * Can I change recursive flag to false
     * check if there is "linked" object in another entity
     *
     * May be overloaded if needed
     *
     * @return
     **/
    public function canUnrecurs()
    {
        return true;
    }

    /**
     * @return int
     */
    public function maxCriteriasCount()
    {
        return 1;
    }

    /**
     * Get maximum number of Actions of the Rule (0 = unlimited)
     *
     * @return int maximum number of actions
     **/
    public function maxActionsCount()
    {
        return count($this->getActions());
    }

    /**
     * Function used to add specific params before rule processing
     *
     * @param $params
     **/
    public function addSpecificParamsForPreview($params)
    {
        if (!isset($params["entities_id"])) {
            $params["entities_id"] = $_SESSION["glpiactive_entity"];
        }
        return $params;
    }

    /**
     * Function used to display type specific criterias during rule's preview
     *
     * @param $fields fields values
     **/
    public function showSpecificCriteriasForPreview($fields)
    {
        $entity_as_criteria = false;
        foreach ($this->criterias as $criteria) {
            if ($criteria->fields['criteria'] == 'entities_id') {
                $entity_as_criteria = true;
                break;
            }
        }
        if (!$entity_as_criteria) {
            echo Html::hidden('entities_id', ['value' => $_SESSION["glpiactive_entity"]]);
        }
    }

    /**
     * @return array
     */
    public function getCriterias()
    {
        $criterias = [];

        $criterias['plugin_resources_contracttypes_id']['name'] = ContractType::getTypeName(1);
        $criterias['plugin_resources_contracttypes_id']['type'] = 'dropdownContractType';
        $criterias['plugin_resources_contracttypes_id']['allow_condition'] = [Rule::PATTERN_IS, Rule::PATTERN_IS_NOT];

        return $criterias;
    }

    /**
     * Display item used to select a pattern for a criteria
     *
     * @param $name      criteria name
     * @param $ID        the given criteria
     * @param $condition condition used
     * @param $value     the pattern (default '')
     * @param $test      Is to test rule ? (false by default)
     **/
    public function displayCriteriaSelectPattern($name, $ID, $condition, $value = "", $test = false)
    {
        $ContractType = new ContractType();

        $crit = $this->getCriteria($ID);
        $display = false;
        if (isset($crit['type'])
            && ($test || $condition == Rule::PATTERN_IS || $condition == Rule::PATTERN_IS_NOT)) {
            switch ($crit['type']) {
                case "dropdownContractType":
                    $ContractType->dropdownContractType($name);
                    $display = true;
                    break;
            }
        }
    }

    /**
     * Return a value associated with a pattern associated to a criteria to display it
     *
     * @param $ID the given criteria
     * @param $condition condition used
     * @param $pattern the pattern
     **/
    public function getCriteriaDisplayPattern($ID, $condition, $pattern)
    {
        if (($condition == Rule::PATTERN_IS || $condition == Rule::PATTERN_IS_NOT)) {
            $crit = $this->getCriteria($ID);
            if (isset($crit['type'])) {
                switch ($crit['type']) {
                    case "dropdownContractType":
                        $ContractType = new ContractType();
                        return $ContractType->getContractTypeName($pattern);
                }
            }
        }
        return $pattern;
    }

    /**
     * @return array
     */
    public function getActions()
    {
        $actions = [];

        $actions['requiredfields_name']['name'] = __('Surname');
        $actions['requiredfields_name']['type'] = "yesonly";
        $actions['requiredfields_name']['force_actions'] = ['assign'];
        $actions['requiredfields_name']['type'] = "yesonly";

        $actions['requiredfields_firstname']['name'] = __('First name');
        $actions['requiredfields_firstname']['type'] = "yesonly";
        $actions['requiredfields_firstname']['force_actions'] = ['assign'];

        $actions['requiredfields_locations_id']['name'] = __('Location');
        $actions['requiredfields_locations_id']['type'] = "yesonly";
        $actions['requiredfields_locations_id']['force_actions'] = ['assign'];

        $actions['requiredfields_users_id']['name'] = __('Resource manager', 'resources');
        $actions['requiredfields_users_id']['type'] = "yesonly";
        $actions['requiredfields_users_id']['force_actions'] = ['assign'];

        $actions['requiredfields_users_id_sales']['name'] = __('Sales manager', 'resources');
        $actions['requiredfields_users_id_sales']['type'] = "yesonly";
        $actions['requiredfields_users_id_sales']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_departments_id']['name'] = Department::getTypeName(1);
        $actions['requiredfields_plugin_resources_departments_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_departments_id']['force_actions'] = ['assign'];

        $actions['requiredfields_date_begin']['name'] = __('Arrival date', 'resources');
        $actions['requiredfields_date_begin']['type'] = "yesonly";
        $actions['requiredfields_date_begin']['force_actions'] = ['assign'];

        $actions['requiredfields_date_end']['name'] = __('Departure date', 'resources');
        $actions['requiredfields_date_end']['type'] = "yesonly";
        $actions['requiredfields_date_end']['force_actions'] = ['assign'];

        $actions['requiredfields_quota']['name'] = __('Quota', 'resources');
        $actions['requiredfields_quota']['type'] = "yesonly";
        $actions['requiredfields_quota']['force_actions'] = ['assign'];

        $actions['requiredfields_matricule']['name'] = __('Matricule', 'resources');
        $actions['requiredfields_matricule']['type'] = "yesonly";
        $actions['requiredfields_matricule']['force_actions'] = ['assign'];

        $actions['requiredfields_matricule_second']['name'] = __('Second matricule', 'resources');
        $actions['requiredfields_matricule_second']['type'] = "yesonly";
        $actions['requiredfields_matricule_second']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_roles_id']['name'] = __('Role', 'resources');
        $actions['requiredfields_plugin_resources_roles_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_roles_id']['force_actions'] = ['assign'];
        $actions['requiredfields_plugin_resources_employers_id']['name'] = Employer::getTypeName();
        $actions['requiredfields_plugin_resources_employers_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_employers_id']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_services_id']['name'] = Service::getTypeName(1);
        $actions['requiredfields_plugin_resources_services_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_services_id']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_teams_id']['name'] = Team::getTypeName(1);
        $actions['requiredfields_plugin_resources_teams_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_teams_id']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_functions_id']['name'] = ResourceFunction::getTypeName(1);
        $actions['requiredfields_plugin_resources_functions_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_functions_id']['force_actions'] = ['assign'];

        $actions['requiredfields_date_agreement_candidate']['name'] = __('Date agreement candidate', 'resources');
        $actions['requiredfields_date_agreement_candidate']['type'] = "yesonly";
        $actions['requiredfields_date_agreement_candidate']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_degreegroups_id']['name'] = DegreeGroup::getTypeName(1);
        $actions['requiredfields_plugin_resources_degreegroups_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_degreegroups_id']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_recruitingsources_id']['name'] = RecruitingSource::getTypeName(1);
        $actions['requiredfields_plugin_resources_recruitingsources_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_recruitingsources_id']['force_actions'] = ['assign'];

        $actions['requiredfields_yearsexperience']['name'] = __('Number of years experience', 'resources');
        $actions['requiredfields_yearsexperience']['type'] = "yesonly";
        $actions['requiredfields_yearsexperience']['force_actions'] = ['assign'];

        $actions['requiredfields_reconversion']['name'] = __('Reconversion', 'resources');
        $actions['requiredfields_reconversion']['type'] = "yesonly";
        $actions['requiredfields_reconversion']['force_actions'] = ['assign'];

        $actions['requiredfields_interview_date']['name'] = __('Interview date', 'resources');
        $actions['requiredfields_interview_date']['type'] = "yesonly";
        $actions['requiredfields_interview_date']['force_actions'] = ['assign'];

        //       $actions['requiredfields_users_id']['name']  = __('Sales manager', 'resources');
        //       $actions['requiredfields_users_id']['type']  = "yesonly";
        //       $actions['requiredfields_users_id']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_workprofiles_id']['name'] = WorkProfile::getTypeName(1);
        $actions['requiredfields_plugin_resources_workprofiles_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_workprofiles_id']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_clients_id']['name'] = Client::getTypeName(1);
        $actions['requiredfields_plugin_resources_clients_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_clients_id']['force_actions'] = ['assign'];

        $actions['requiredfields_resignation_date']['name'] = __('Resignation date', 'resources');
        $actions['requiredfields_resignation_date']['type'] = "yesonly";
        $actions['requiredfields_resignation_date']['force_actions'] = ['assign'];

        $actions['requiredfields_wished_leaving_date']['name'] = __('Wished leaving date', 'resources');
        $actions['requiredfields_wished_leaving_date']['type'] = "yesonly";
        $actions['requiredfields_wished_leaving_date']['force_actions'] = ['assign'];

        $actions['requiredfields_effective_leaving_date']['name'] = __('Effective leaving date', 'resources');
        $actions['requiredfields_effective_leaving_date']['type'] = "yesonly";
        $actions['requiredfields_effective_leaving_date']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_destinations_id']['name'] = Destination::getTypeName(1);
        $actions['requiredfields_plugin_resources_destinations_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_destinations_id']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_leavingreasons_id']['name'] = LeavingReason::getTypeName(1);
        $actions['requiredfields_plugin_resources_leavingreasons_id']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_leavingreasons_id']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_company_name']['name'] = __('Company name', 'resources');
        $actions['requiredfields_plugin_resources_company_name']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_company_name']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_pay_gap']['name'] = __('Pay gap', 'resources');
        $actions['requiredfields_plugin_resources_pay_gap']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_pay_gap']['force_actions'] = ['assign'];

        $actions['requiredfields_plugin_resources_mission_lost']['name'] = __('Mission lost', 'resources');
        $actions['requiredfields_plugin_resources_mission_lost']['type'] = "yesonly";
        $actions['requiredfields_plugin_resources_mission_lost']['force_actions'] = ['assign'];


        if (Session::haveRight('plugin_resources_dropdown_public', UPDATE)) {
            $actions['requiredfields_plugin_resources_resourcesituations_id']['name'] = ResourceSituation::getTypeName(
                1
            );
            $actions['requiredfields_plugin_resources_resourcesituations_id']['type'] = "yesonly";
            $actions['requiredfields_plugin_resources_resourcesituations_id']['force_actions'] = ['assign'];

            $actions['requiredfields_plugin_resources_ranks_id']['name'] = Rank::getTypeName(1);
            $actions['requiredfields_plugin_resources_ranks_id']['type'] = "yesonly";
            $actions['requiredfields_plugin_resources_ranks_id']['force_actions'] = ['assign'];
        }

        return $actions;
    }
}
