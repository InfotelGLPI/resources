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

namespace GlpiPlugin\Resources;
use RuleCollection;
use Session;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}


/**
 * Class RuleContracttypeReadonlyCollection
 */
class RuleContracttypeReadonlyCollection extends RuleCollection {

    static $rightname = 'plugin_resources';

    // From RuleCollection
    public $stop_on_first_match=true;
    public $menu_option='contracttypereadonlys';

    /**
     * Get title used in list of rules
     *
     * @return Title of the rule collection
     **/
    function getTitle() {

        return __('Assignment rule of read only fields to a contract type', 'resources');
    }

    /**
     * PluginResourcesRuleContracttypeCollection constructor.
     *
     * @param int $entity
     */
    function __construct($entity = 0) {
        $this->entity = $entity;
    }

    /**
     * @return bool
     */
    function showInheritedTab() {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]) && ($this->entity);
    }

    /**
     * @return bool
     */
    function showChildrensTab() {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]) && (count($_SESSION['glpiactiveentities']) > 1);
    }

    /**
     * Process all the rules collection
     *
     * @param input the input data used to check criterias
     * @param output the initial ouput array used to be manipulate by actions
     * @param params parameters for all internal functions
     *
     * @return the output array updated by actions
     **/
    function processAllRules($input = [], $output = [], $params = [],
                             $force_no_cache = false) {

        // Get Collection datas
        $this->getCollectionDatas(1, 1);
        $input = $this->prepareInputDataForProcess($input, $params);
        $output["_no_rule_matches"] = true;
        $checklists = [];

        if (count($this->RuleList->list)) {
            foreach ($this->RuleList->list as $rule) {
                //If the rule is active, process it

                if ($rule->fields["is_active"]) {
                    $output["_rule_process"] = false;
                    if (isset($input['plugin_resources_users_id_reel'])) {
                        foreach ($rule->criterias as $id => $criterion) {
                            if ($criterion->fields["criteria"] == "plugin_resources_users_id") {
                                $rule->criterias[$id]->fields['pattern'] = $input['plugin_resources_users_id_reel'];
                            }
                        }
                    }

                    $rule->process($input, $output, $params);

                    if (
                        (isset($output['_stop_rules_processing']) && (int) $output['_stop_rules_processing'] === 1)
                        || ($output["_rule_process"] && $this->stop_on_first_match)
                    ) {
                        unset($output["_stop_rules_processing"], $output["_rule_process"]);
                        $output["_ruleid"] = $rule->fields["id"];
                        return $output;
                    }

                }

                if ($this->use_output_rule_process_as_next_input) {
                    $output = $this->prepareInputDataForProcessWithPlugins($output, $params);
                    $input  = $output;
                }
            }
        }

        return $output;
    }
}

