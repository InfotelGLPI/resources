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

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginResourcesResource
 */
class PluginResourcesResource extends CommonDBTM {

    static $rightname = 'plugin_resources';

    static $types = [
        Computer::class,
        Monitor::class,
        NetworkEquipment::class,
        Peripheral::class,
        Phone::class,
        Printer::class,
        Software::class,
        ConsumableItem::class,
        User::class,
        Appliance::class,
        ComputerType::class,
        PhoneType::class
    ];

    protected $usenotepad = true;

    public $dohistory = true;

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @return string
     **/
    static function getTypeName($nb = 0) {

        return _n('Human resource', 'Human resources', $nb, 'resources');
    }

    static function getIcon() {
        return "fas fa-user-friends";
    }

    /**
     * @return array
     */
    static function getDataNames() {
        return [
            __("Firstname", "resources"),
            __("Lastname", "resources"),
            __("ContractType", "resources"),
            __("Associed User", "resources"),
            __("Location", "resources"),
            __("Resource manager", "resources"),
            __("Department", "resources"),
            __("Arrival date", "resources"),
            __("Departure date", "resources"),
            __("Sales manager", "resources"),
            __("Other", "resources"),
            PluginResourcesTeam::getTypeName(0),
        ];
    }

    /**
     * @param $dataNameID
     *
     * @return string|null
     */
    static function getResourceColumnNameFromDataNameID($dataNameID) {

        $dataNames = [
            //         "id",
            "firstname",
            "name",
            "plugin_resources_contracttypes_id",
            "users_id_recipient",
            "locations_id",
            "users_id",
            "plugin_resources_departments_id",
            "date_begin",
            "date_end",
            "users_id_sales",
            "other",
            "plugin_resources_teams_id",
        ];

        if (!array_key_exists($dataNameID, $dataNames)) {
            Html::displayErrorAndDie(__("Resource column $dataNameID not found", "resources"));
            return null;
        }
        return $dataNames[$dataNameID];
    }

    /**
     * @return string[]
     */
    static function getDataTypes() {

        $dataTypes = [
            "String",
            "String",
            "PluginResourcesContractType",
            "User",
            "Location",
            "User",
            "PluginResourcesDepartment",
            "Date",
            "Date",
            "User",
            "String",
            "PluginResourcesTeam"
        ];

        return $dataTypes;
    }

    static function getDataType($dataNameId) {

        $dataTypes = self::getDataTypes();

        if (!array_key_exists($dataNameId, $dataTypes)) {
            Html::displayErrorAndDie(__("Data Type not found", "resources"));
            return null;
        }
        return $dataTypes[$dataNameId];
    }

    static function getColumnName($dataNameId) {

        $columnNames = [
            "firstname",
            "name",
            "plugin_resources_contracttypes_id",
            "users_id",
            "locations_id",
            "users_id_recipient",
            "plugin_resources_departments_id",
            "date_begin",
            "date_end",
            "users_id_sales",
            "others",
            "plugin_resources_teams_id"
        ];

        if (!array_key_exists($dataNameId, $columnNames)) {
            Html::displayErrorAndDie(__("Resource column name not found", "resources"));
            return null;
        }

        return $columnNames[$dataNameId];
    }

    /**
     * For other plugins, add a type to the linkable types
     *
     *
     * @param $type string class name
     **/
    static function registerType($type) {
        if (!in_array($type, self::$types)) {
            self::$types[] = $type;
        }
    }

    /**
     * Type than could be linked to a Resource
     *
     * @param $all boolean, all type, or only allowed ones
     *
     * @return array of types
     **/
    static function getTypes($all = false) {

        if ($all) {
            return self::$types;
        }

        // Only allowed types
        $types = self::$types;

        foreach ($types as $key => $type) {
            if (!class_exists($type)) {
                continue;
            }

            $item = new $type();
            if (!$item->canView()) {
                unset($types[$key]);
            }
        }
        return $types;
    }

    /**
     * Actions done when item is deleted from the database
     *
     * @return nothing
     **/
    function cleanDBonPurge() {

        $temp = new PluginResourcesResource_Item();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new PluginResourcesChoice();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new PluginResourcesTask();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']], 1);

        $temp = new PluginResourcesEmployee();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new PluginResourcesReportConfig();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new PluginResourcesChecklist();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new PluginResourcesResourceResting();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new PluginResourcesResourceHoliday();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new PluginResourcesResourceHabilitation();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);
    }

    /**
     * Hook called After an item is purge
     *
     * @param CommonDBTM $item
     */
    static function cleanForItem(CommonDBTM $item) {

        $type = get_class($item);
        $temp = new PluginResourcesResource_Item();
        $temp->deleteByCriteria(['itemtype' => $type,
                                 'items_id' => $item->getField('id')]);

        $task = new PluginResourcesTask_Item();
        $task->deleteByCriteria(['itemtype' => $type,
                                 'items_id' => $item->getField('id')]);
    }

    /**
     * Get Tab Name used for itemtype
     *
     * NB : Only called for existing object
     *      Must check right on what will be displayed + template
     *
     * @param CommonGLPI $item Item on which the tab need to be displayed
     * @param boolean    $withtemplate is a template object ? (default 0)
     *
     * @return string tab name
     **@since 0.83
     *
     */
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

        if ($item->getType() == PluginResourcesClient::class
            && $this->canView()) {
            return self::getTypeName(2);
        }
        return '';
    }


    /**
     * show Tab content
     *
     * @param CommonGLPI $item Item on which the tab need to be displayed
     * @param integer    $tabnum tab number (default 1)
     * @param boolean    $withtemplate is a template object ? (default 0)
     *
     * @return boolean
     **@since 0.83
     *
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

        if ($item->getType() == PluginResourcesClient::class) {
            $self = new self();
            $self->showListResourcesForClient($item->getField('id'));
        }
        return true;
    }

    /**
     * Get the Search options for the given Type
     *
     * This should be overloaded in Class
     *
     * @return an array of search options
     * More information on https://forge.indepnet.net/wiki/glpi/SearchEngine
     **/
    function rawSearchOptions() {

        $tab = parent::rawSearchOptions();

        unset($tab[1]);
        $tab[] = [
            'id'            => '1',
            'table'         => $this->getTable(),
            'field'         => 'name',
            'name'          => __('Surname'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'    => '2',
            'table' => $this->getTable(),
            'field' => 'firstname',
            'name'  => __('First name'),
        ];

        $tab[] = [
            'id'       => '37',
            'table'    => 'glpi_plugin_resources_contracttypes',
            'field'    => 'name',
            'name'     => PluginResourcesContractType::getTypeName(1),
            'datatype' => 'dropdown'
        ];

        $tab[] = [
            'id'       => '4',
            'table'    => 'glpi_users',
            'field'    => 'name',
            'name'     => __('Resource manager', 'resources'),
            'datatype' => 'dropdown',
            'right'    => 'all'
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[4] += ['searchtype' => 'contains'];
        }

        $tab[] = [
            'id'       => '5',
            'table'    => $this->getTable(),
            'field'    => 'date_begin',
            'name'     => __('Arrival date', 'resources'),
            'datatype' => 'date'
        ];
        $tab[] = [
            'id'       => '6',
            'table'    => $this->getTable(),
            'field'    => 'date_end',
            'name'     => __('Departure date', 'resources'),
            'datatype' => 'date'
        ];
        $tab[] = [
            'id'       => '7',
            'table'    => $this->getTable(),
            'field'    => 'comment',
            'name'     => __('Description'),
            'datatype' => 'text'
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
                'id'            => '8',
                'table'         => 'glpi_plugin_resources_resources_items',
                'field'         => 'items_id',
                'name'          => _n('Associated item', 'Associated items', 2),
                'massiveaction' => false,
                'forcegroupby'  => false,
                'nosearch'      => false,
                'joinparams'    => ['jointype' => 'child']
            ];
        }
        $tab[] = [
            'id'            => '9',
            'table'         => $this->getTable(),
            'field'         => 'date_declaration',
            'name'          => __('Request date'),
            'datatype'      => 'date',
            'massiveaction' => false
        ];
        $tab[] = [
            'id'            => '10',
            'table'         => 'glpi_users',
            'field'         => 'name',
            'linkfield'     => 'users_id_recipient',
            'name'          => __('Requester'),
            'datatype'      => 'dropdown',
            'right'         => 'all',
            'massiveaction' => false
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[10] += ['searchtype' => 'contains'];
        }
        $tab[] = [
            'id'       => '11',
            'table'    => 'glpi_plugin_resources_departments',
            'field'    => 'name',
            'name'     => PluginResourcesDepartment::getTypeName(1),
            'datatype' => 'dropdown'
        ];
        $tab   = array_merge($tab, Location::rawSearchOptionsToAdd());
        $tab[] = [
            'id'       => '36',
            'table'    => $this->getTable(),
            'field'    => 'is_leaving',
            'name'     => __('Declared as leaving', 'resources'),
            'datatype' => 'bool'
        ];
        $tab[] = [
            'id'            => '14',
            'table'         => 'glpi_users',
            'field'         => 'name',
            'linkfield'     => 'users_id_recipient_leaving',
            'name'          => __('Informant of leaving', 'resources'),
            'datatype'      => 'dropdown',
            'right'         => 'all',
            'massiveaction' => false
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[2] += ['searchtype' => 'contains'];
        }

        $tab[] = [
            'id'       => '15',
            'table'    => $this->getTable(),
            'field'    => 'is_helpdesk_visible',
            'name'     => __('Associable to a ticket'),
            'datatype' => 'bool'
        ];
        $tab[] = [
            'id'            => '16',
            'table'         => $this->getTable(),
            'field'         => 'date_mod',
            'name'          => __('Last update'),
            'datatype'      => 'datetime',
            'massiveaction' => false
        ];
        $tab[] = [
            'id'       => '17',
            'table'    => 'glpi_plugin_resources_resourcestates',
            'field'    => 'name',
            'name'     => PluginResourcesResourceState::getTypeName(1),
            'datatype' => 'dropdown'
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
                'id'            => '18',
                'table'         => $this->getTable(),
                'field'         => 'picture',
                'name'          => __('Photo', 'resources'),
                'massiveaction' => false
            ];
            $tab[] = [
                'id'            => '19',
                'table'         => $this->getTable(),
                'field'         => 'is_recursive',
                'name'          => __('Child entities'),
                'datatype'      => 'bool',
                'massiveaction' => false
            ];
        }
        $tab[] = [
            'id'       => '20',
            'table'    => $this->getTable(),
            'field'    => 'quota',
            'name'     => __('Quota', 'resources'),
            'datatype' => 'decimal'
        ];
        //To have Field in dataInjection
        //      if (Session::getCurrentInterface() != 'central') {

        $tab[] = [
            'id'            => '21',
            'table'         => 'glpi_plugin_resources_resourcesituations',
            'field'         => 'name',
            'name'          => PluginResourcesResourceSituation::getTypeName(1),
            'massiveaction' => false,
            'datatype'      => 'dropdown'
        ];
        $tab[] = [
            'id'            => '22',
            'table'         => 'glpi_plugin_resources_contractnatures',
            'field'         => 'name',
            'name'          => PluginResourcesContractNature::getTypeName(1),
            'massiveaction' => false,
            'datatype'      => 'dropdown'
        ];
        $tab[] = [
            'id'            => '23',
            'table'         => 'glpi_plugin_resources_ranks',
            'field'         => 'name',
            'name'          => PluginResourcesRank::getTypeName(1),
            'massiveaction' => false,
            'datatype'      => 'dropdown'
        ];
        $tab[] = [
            'id'            => '24',
            'table'         => 'glpi_plugin_resources_resourcespecialities',
            'field'         => 'name',
            'name'          => PluginResourcesResourceSpeciality::getTypeName(1),
            'massiveaction' => false,
            'datatype'      => 'dropdown'
        ];
        //      }

        $tab[] = [
            'id'       => '25',
            'table'    => 'glpi_plugin_resources_leavingreasons',
            'field'    => 'name',
            'name'     => PluginResourcesLeavingReason::getTypeName(1),
            'datatype' => 'dropdown'
        ];
        $tab[] = [
            'id'        => '27',
            'table'     => 'glpi_users',
            'field'     => 'name',
            'linkfield' => 'users_id_sales',
            'name'      => __('Sales manager', 'resources'),
            'datatype'  => 'dropdown',
            'right'     => 'all'
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[27] += ['searchtype' => 'contains'];
        }
        $tab[] = [
            'id'            => '28',
            'table'         => $this->getTable(),
            'field'         => 'date_declaration_leaving',
            'name'          => __('Declaration of departure date', 'resources'),
            'datatype'      => 'datetime',
            'massiveaction' => false
        ];

        $config = new PluginResourcesConfig();
        if ($config->useSecurity()) {
            $tab[] = [
                'id'            => '29',
                'table'         => $this->getTable(),
                'field'         => 'read_chart',
                'name'          => __('Reading the security charter', 'resources'),
                'datatype'      => 'bool',
                'massiveaction' => true
            ];
            $tab[] = [
                'id'            => '30',
                'table'         => $this->getTable(),
                'field'         => 'sensitize_security',
                'name'          => __('Sensitized to security', 'resources'),
                'datatype'      => 'bool',
                'massiveaction' => true
            ];
        }

        $tab[] = [
            'id'            => '32',
            'table'         => 'glpi_plugin_resources_habilitations',
            'field'         => 'name',
            'name'          => PluginResourcesHabilitation::getTypeName(),
            'datatype'      => 'itemlink',
            'forcegroupby'  => true,
            'massiveaction' => false,
            'joinparams'    => ['beforejoin'
                                => ['table'      => 'glpi_plugin_resources_resourcehabilitations',
                                    'joinparams' => ['jointype' => 'child']]]
        ];
        $tab[] = [
            'id'            => '33',
            'table'         => 'glpi_plugin_resources_employers',
            'field'         => 'name',
            'name'          => PluginResourcesEmployer::getTypeName(),
            'datatype'      => 'itemlink',
            'forcegroupby'  => false,
            'massiveaction' => false,
            'joinparams'    => ['join'
                                => ['table'      => 'glpi_plugin_resources_employees',
                                    'joinparams' => ['jointype' => 'child']]]
        ];
        $tab[] = [
            'id'            => '34',
            'table'         => 'glpi_plugin_resources_clients',
            'field'         => 'name',
            'name'          => PluginResourcesClient::getTypeName(),
            'datatype'      => 'itemlink',
            'forcegroupby'  => false,
            'massiveaction' => false,
            'joinparams'    => ['join'
                                => ['table'      => 'glpi_plugin_resources_employees',
                                    'joinparams' => ['jointype' => 'child']]]
        ];
        if ($config->useSecurityCompliance()) {
            $tab[] = [
                'id'            => '35',
                'table'         => 'glpi_plugin_resources_employers',
                'field'         => 'id',
                'name'          => __('Client Sensitized to security', 'resources'),
                'datatype'      => 'specific',
                'massiveaction' => false,
                'joinparams'    => ['join'
                                    => ['table'      => 'glpi_plugin_resources_employees',
                                        'joinparams' => ['jointype' => 'child']]]
            ];
        }
        $tab[] = [
            'id'            => '31',
            'table'         => $this->getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'massiveaction' => false,
            'datatype'      => 'number'
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[] = [
                'id'       => '80',
                'table'    => 'glpi_entities',
                'field'    => 'completename',
                'name'     => __('Entity'),
                'datatype' => 'dropdown'
            ];
        }

        $tab[] = [
            'id'            => '38',
            'table'         => $this->getTable(),
            'field'         => 'sensitize_security',
            'name'          => __('Sensitized to security', 'resources'),
            'datatype'      => 'bool',
            'massiveaction' => true
        ];
        $tab[] = [
            'id'            => '39',
            'table'         => $this->getTable(),
            'field'         => 'matricule',
            'name'          => __('Matricule', 'resources'),
            'datatype'      => 'text',
            'massiveaction' => true
        ];

        $tab[] = [
            'id'            => '40',
            'table'         => PluginResourcesRole::getTable(),
            'field'         => 'name',
            'name'          => PluginResourcesRole::getTypeName(),
            'datatype'      => 'dropdown',
            'massiveaction' => true
        ];
        $tab[] = [
            'id'            => '41',
            'table'         => PluginResourcesService::getTable(),
            'field'         => 'name',
            'name'          => PluginResourcesService::getTypeName(),
            'datatype'      => 'dropdown',
            'massiveaction' => true
        ];

        $tab[] = [
            'id'            => '42',
            'table'         => PluginResourcesFunction::getTable(),
            'field'         => 'name',
            'name'          => PluginResourcesFunction::getTypeName(),
            'datatype'      => 'dropdown',
            'massiveaction' => true
        ];

        $tab[] = [
            'id'            => '43',
            'table'         => PluginResourcesTeam::getTable(),
            'field'         => 'name',
            'name'          => PluginResourcesTeam::getTypeName(),
            'datatype'      => 'dropdown',
            'massiveaction' => true
        ];

        $tab[] = [
            'id'    => '44',
            'table' => $this->getTable(),
            'field' => 'matricule_second',
            'name'  => __('Second matricule', 'resources'),
        ];
        //      $tab[] = [
        //         'id'    => '45',
        //         'table' => $this->getTable(),
        //         'field' => 'society',
        //         'name'  => __('Society', 'resources'),
        //      ];

        return $tab;
    }

    /**
     * Define tabs to display
     *
     * NB : Only called for existing object
     *
     * @param $options array
     *     - withtemplate is a template view ?
     *
     * @return array containing the onglets
     **/
    function defineTabs($options = []) {

        $ong = [];

        $this->addDefaultFormTab($ong);
        $this->addStandardTab(PluginResourcesResource_Item::class, $ong, $options);
        $resourceItem  = new PluginResourcesResource_Item();
        $resourceUsers = $resourceItem->find(['plugin_resources_resources_id' => $this->getID(),
                                              'itemtype'                      => 'User']);
        if (count($resourceUsers) > 0 && Session::getCurrentInterface() == 'central') {
            $this->addStandardTab(PluginResourcesUser::class, $ong, $options);
        }
        $this->addStandardTab(PluginResourcesChoice::class, $ong, $options);
        $this->addStandardTab(PluginResourcesResourceHabilitation::class, $ong, $options);
        $this->addStandardTab(PluginResourcesEmployment::class, $ong, $options);
        $this->addStandardTab(PluginResourcesEmployee::class, $ong, $options);
        $this->addStandardTab(PluginResourcesChecklist::class, $ong, $options);
        $this->addStandardTab(PluginResourcesTask::class, $ong, $options);

        if (Session::getCurrentInterface() == 'central') {
            $this->addStandardTab(PluginResourcesResourceImport::class, $ong, $options);
            $this->addStandardTab(PluginResourcesReportConfig::class, $ong, $options);
            $this->addStandardTab(Document_Item::class, $ong, $options);

            if (!isset($options['withtemplate']) || empty($options['withtemplate'])) {
                $this->addStandardTab(Ticket::class, $ong, $options);
                $this->addStandardTab(Item_Problem::class, $ong, $options);
            }

            $this->addStandardTab(Notepad::class, $ong, $options);
            $this->addStandardTab(Log::class, $ong, $options);
        }
        return $ong;
    }

    /**
     * @param $input
     *
     * @return array
     */
    function getHiddenFields($input) {

        $need           = [];
        $rulecollection = new PluginResourcesRuleContracttypeHiddenCollection($input['entities_id']);

        $fields = [];
        $fields = $rulecollection->processAllRules($input, $fields, []);


        $field = [];
        foreach ($fields as $key => $val) {
            $hidden = explode("hiddenfields_", $key);
            if (isset($hidden[1])) {
                $field[] = $hidden[1];
            }
        }


        return $field;
    }

    /**
     * @param $input
     *
     * @return array
     */
    function checkRequiredFields($input) {

        $need           = [];
        $rulecollection = new PluginResourcesRuleContracttypeCollection($input['entities_id']);

        $fields = [];
        $fields = $rulecollection->processAllRules($input, $fields, []);

        $rank = new PluginResourcesRank();

        $field = [];
        foreach ($fields as $key => $val) {
            $required = explode("requiredfields_", $key);
            if (isset($required[1])) {
                $field[] = $required[1];
            }
        }

        if (count($field) > 0) {
            foreach ($field as $key => $val) {
                if (!isset($input[$val])
                    || empty($input[$val])
                    || is_null($input[$val])
                    || $input[$val] == "NULL"
                ) {
                    if (!$rank->canCreate()
                        && in_array($val,
                                    ['plugin_resources_ranks_id', 'plugin_resources_resourcesituations_id'])
                    ) {
                    } else {
                        $need[] = $val;
                    }
                }
            }
        }

        return $need;
    }


    /**
     * Prepare input datas for adding the item
     *
     * @param $input datas used to add the item
     *
     * @return the modified $input array
     **/
    function prepareInputForAdd($input) {

        if (!isset ($input["is_template"])) {

            if (!isset($input['force'])) {
                $required = $this->checkRequiredFields($input);

                if (count($required) > 0) {
                    Session::addMessageAfterRedirect(__('Required fields are not filled. Please try again.', 'resources'), false, ERROR);
                    return [];
                }
            } else {
                unset($input['force']);
            }
        }

        if (isset($input['date_end'])
            && empty($input['date_end'])
        ) {
            $input['date_end'] = 'NULL';
        }

        if (!isset($input['sensitize_security'])) {
            $input['sensitize_security'] = 0;
        }
        if (!isset($input['read_chart'])) {
            $input['read_chart'] = 0;
        }

        if (!isset($input['plugin_resources_resourcestates_id'])
            || empty($input['plugin_resources_resourcestates_id'])
        ) {
            $input['plugin_resources_resourcestates_id'] = '0';
        }
        //Add picture of the resource
        $input['picture'] = "NULL";
        if (isset($_FILES) && isset($_FILES['picture']) && $_FILES['picture']['size'] > 0) {

            if ($_FILES['picture']['type'] == "image/jpeg"
                || $_FILES['picture']['type'] == "image/pjpeg"
            ) {
                $max_size = Toolbox::return_bytes_from_ini_vars(ini_get("upload_max_filesize"));
                if ($_FILES['picture']['size'] <= $max_size) {

                    if (is_writable(GLPI_PLUGIN_DOC_DIR . "/resources/pictures/")) {
                        $input['picture'] = $this->addPhoto($this);
                    }
                } else {
                    Session::addMessageAfterRedirect(__('Failed to send the file (probably too large)'), false, ERROR);
                }
            } else {
                Session::addMessageAfterRedirect(__('Invalid filename') . " : " . $_FILES['picture']['type'], false, ERROR);
            }
        }

        $template_resources = new Self();
        if (isset($this->input['id_template'])) {
            if ($template_resources->getFromDBByCrit(['id'          => $this->input['id_template'],
                                                      'is_template' => 1])) {
                $input["resources_oldID"] = $this->input['id_template'];
            }
        }

        return $input;
    }


    /**
     * Actions done after the ADD of the item in the database
     *
     * @return nothing
     **/
    function post_addItem() {
        global $CFG_GLPI;

        //      if ($this->fields['id'] == 0) {
        //         $this->getFromDBByCrit(
        //            [ 'name'      => $this->fields['name'],
        //               'firstname' => $this->fields['firstname']]);
        //      }
        //       Manage add from template

        if (isset($this->input["resources_oldID"])) {

            // ADD choices
            PluginResourcesChoice::cloneItem($this->input["resources_oldID"], $this->fields['id']);

            // ADD habilitations
            PluginResourcesResourceHabilitation::cloneItem($this->input["resources_oldID"], $this->fields['id']);

            // ADD items
            PluginResourcesResource_Item::cloneItem($this->input["resources_oldID"], $this->fields['id']);

            // ADD reports
            PluginResourcesReportConfig::cloneItem($this->input["resources_oldID"], $this->fields['id']);

            //manage template from helpdesk (no employee to add : resource.form.php)
            if (!isset($this->input["add_from_helpdesk"])) {
                PluginResourcesEmployee::cloneItem($this->input["resources_oldID"], $this->fields['id']);
            }
            // ADD Documents
            $document_items             = Document_Item::getItemsAssociatedTo($this->getType(), $this->fields['id']);
            $override_input['items_id'] = $this->getID();
            foreach ($document_items as $document_item) {
                $document_item->clone($override_input);
            }

            // ADD tasks
            PluginResourcesTask::cloneItem($this->input["resources_oldID"], $this->fields['id']);
        }

        //ADD Checklists from rules
        $PluginResourcesChecklistconfig = new PluginResourcesChecklistconfig();
        $PluginResourcesChecklistconfig->addChecklistsFromRules($this, PluginResourcesChecklist::RESOURCES_CHECKLIST_IN);
        $PluginResourcesChecklistconfig->addChecklistsFromRules($this, PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT);
        $PluginResourcesChecklistconfig->addChecklistsFromRules($this, PluginResourcesChecklist::RESOURCES_CHECKLIST_TRANSFER);

        //Launch notification

        if (isset($this->input['withtemplate'])
            && $this->input["withtemplate"] != 1
            && isset($this->input['send_notification'])
            && $this->input['send_notification'] == 1
        ) {
            if ($CFG_GLPI["notifications_mailing"]) {
                NotificationEvent::raiseEvent("new", $this);
            }
        }
    }


    function post_getFromDB() {
        $this->fields['states_id'] = 1;
    }

    /**
     * @param        $str
     * @param string $charset
     *
     * @return mixed|string
     */
    function replace_accents($str, $charset = 'utf-8') {
        $str = htmlentities($str, ENT_NOQUOTES, $charset);

        $str = preg_replace('#\&([A-za-z])(?:acute|cedil|circ|grave|ring|tilde|uml)\;#', '\1', $str);
        $str = preg_replace('#\&([A-za-z]{2})(?:lig)\;#', '\1', $str); // pour les ligatures e.g. '&oelig;'
        $str = preg_replace('#\&[^;]+\;#', '', $str); // supprime les autres caractères

        return $str;
    }

    /**
     * @param $class
     *
     * @return mixed|string
     */
    function addPhoto($class) {
        $uploadedfile = $_FILES['picture']['tmp_name'];
        $src          = imagecreatefromjpeg($uploadedfile);

        list($width, $height) = getimagesize($uploadedfile);

        $newwidth  = 75;
        $newheight = ($height / $width) * $newwidth;
        $tmp       = imagecreatetruecolor($newwidth, $newheight);

        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        $ext                 = strtolower(substr(strrchr($_FILES['picture']['name'], '.'), 1));
        $resources_name      = str_replace(" ", "", strtolower($class->fields["name"]));
        $resources_firstname = str_replace(" ", "", strtolower($class->fields["firstname"]));
        $name                = $resources_name . "_" . $resources_firstname . "." . $ext;

        $name = $this->replace_accents($name);

        $tmpfile  = GLPI_DOC_DIR . "/_uploads/" . $name;
        $filename = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $name;

        imagejpeg($tmp, $tmpfile, 100);

        rename($tmpfile, $filename);

        imagedestroy($src);
        imagedestroy($tmp);

        return $name;
    }

    /**
     * Prepare input datas for updating the item
     *
     * @param $input datas used to update the item
     *
     * @return the modified $input array
     **/
    function prepareInputForUpdate($input) {

        if (isset($input['date_begin'])
            && empty($input['date_begin'])
        ) {
            $input['date_begin'] = 'NULL';
        }
        if (isset($input['date_end'])
            && empty($input['date_end'])
        ) {
            $input['date_end'] = 'NULL';
        }

        $this->getFromDB($input["id"]);

        if (!isset($input['_UpdateFromUser_']) && isset($_FILES) && isset($_FILES['picture']) && $_FILES['picture']['size'] > 0) {

            if ($_FILES['picture']['type'] == "image/jpeg"
                || $_FILES['picture']['type'] == "image/pjpeg"

            ) {
                $max_size = Toolbox::return_bytes_from_ini_vars(ini_get("upload_max_filesize"));
                if ($_FILES['picture']['size'] <= $max_size) {

                    $input['picture'] = $this->addPhoto($this);

                } else {
                    Session::addMessageAfterRedirect(__('Failed to send the file (probably too large)'), false, ERROR);
                }
            } else {
                Session::addMessageAfterRedirect(__('Invalid filename'), false, ERROR);
            }
        }

        $input["_old_name"]                                     = $this->fields["name"];
        $input["_old_firstname"]                                = $this->fields["firstname"];
        $input["_old_plugin_resources_contracttypes_id"]        = $this->fields["plugin_resources_contracttypes_id"];
        $input["_old_users_id"]                                 = $this->fields["users_id"];
        $input["_old_users_id_sales"]                           = $this->fields["users_id_sales"];
        $input["_old_users_id_recipient"]                       = $this->fields["users_id_recipient"];
        $input["_old_date_declaration"]                         = $this->fields["date_declaration"];
        $input["_old_date_begin"]                               = $this->fields["date_begin"];
        $input["_old_date_end"]                                 = $this->fields["date_end"];
        $input["_old_quota"]                                    = $this->fields["quota"];
        $input["_old_plugin_resources_departments_id"]          = $this->fields["plugin_resources_departments_id"];
        $input["_old_plugin_resources_resourcestates_id"]       = $this->fields["plugin_resources_resourcestates_id"];
        $input["_old_plugin_resources_resourcesituations_id"]   = $this->fields["plugin_resources_resourcesituations_id"];
        $input["_old_plugin_resources_contractnatures_id"]      = $this->fields["plugin_resources_contractnatures_id"];
        $input["_old_plugin_resources_ranks_id"]                = $this->fields["plugin_resources_ranks_id"];
        $input["_old_plugin_resources_resourcespecialities_id"] = $this->fields["plugin_resources_resourcespecialities_id"];
        $input["_old_locations_id"]                             = $this->fields["locations_id"];
        $input["_old_is_leaving"]                               = $this->fields["is_leaving"];
        $input["_old_date_declaration_leaving"]                 = $this->fields["date_declaration_leaving"];
        $input["_old_plugin_resources_leavingreasons_id"]       = $this->fields["plugin_resources_leavingreasons_id"];
        $input["_old_comment"]                                  = $this->fields["comment"];
        $input["_old_sensitize_security"]                       = $this->fields["sensitize_security"];
        $input["_old_read_chart"]                               = $this->fields["read_chart"];

        return $input;
    }

    /**
     * Actions done before the UPDATE of the item in the database
     *
     * @return nothing
     **/
    function pre_updateInDB() {

        $PluginResourcesResource_Item = new PluginResourcesResource_Item();
        //if leaving field is updated  && isset($this->input["withtemplate"]) && $this->input["withtemplate"]!=1

        $this->input["checkbadge"] = 0;

        if (isset($this->input["is_leaving"])
            && $this->input["is_leaving"] == 1
            && in_array("is_leaving", $this->updates)) {

            if ((!(isset($this->input["date_end"]))
                 || $this->input["date_end"] == 'NULL')
                || (!(isset($this->fields["date_end"]))
                    || $this->fields["date_end"] == 'NULL')) {

                Session::addMessageAfterRedirect(__('End date was not completed. Please try again.', 'resources'), false, ERROR);
                Html::back();

            } else {
                $this->fields["users_id_recipient_leaving"] = Session::getLoginUserID();
                $this->fields["date_declaration_leaving"]   = date('Y-m-d H:i:s');
                $this->updates[]                            = "users_id_recipient_leaving";
                $this->updates[]                            = "date_declaration_leaving";

                $resources_checklist = PluginResourcesChecklist::checkIfChecklistExist($this->fields["id"], PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT);
                if (!$resources_checklist) {
                    $PluginResourcesChecklistconfig = new PluginResourcesChecklistconfig();
                    $PluginResourcesChecklistconfig->addChecklistsFromRules($this, PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT);
                }
            }
        } else if (isset($this->input["is_leaving"])
                   && $this->input["is_leaving"] == 0
                   && in_array("is_leaving", $this->updates)) {
            $this->fields["users_id_recipient_leaving"]         = 0;
            $this->fields["date_declaration_leaving"]           = 'NULL';
            $this->fields["date_end"]                           = 'NULL';
            $this->fields["plugin_resources_leavingreasons_id"] = 0;
            $this->updates[]                                    = "users_id_recipient_leaving";
            $this->updates[]                                    = "date_declaration_leaving";
            $this->updates[]                                    = "plugin_resources_leavingreasons_id";
            $this->updates[]                                    = "date_end";

        }

        //if location field is updated
        if (isset ($this->fields["locations_id"])
            && isset ($this->input["_old_locations_id"])
            && !isset ($this->input["_UpdateFromUser_"])
            && $this->fields["locations_id"] != $this->input["_old_locations_id"]) {

            $PluginResourcesResource_Item->updateLocation($this->fields, "PluginResourcesResource");
        }

        $this->input["addchecklist"] = 0;
        if (isset ($this->fields["plugin_resources_contracttypes_id"])
            && isset ($this->input["_old_plugin_resources_contracttypes_id"])
            && $this->fields["plugin_resources_contracttypes_id"] != $this->input["_old_plugin_resources_contracttypes_id"]
        ) {
            $config = new PluginResourcesConfig();
            $config->getFromDB(1);
            if ($config->fields["reaffect_checklist_change"] == 1) {
                $this->input["addchecklist"] = 1;
            }
        }

        if (isset($this->input['plugin_resources_departments_id']) && isset($this->oldvalues['plugin_resources_departments_id'])
            && $this->input['plugin_resources_departments_id'] != $this->oldvalues['plugin_resources_departments_id']) {
            $plugin_resources_department_service = new PluginResourcesDepartment_Service();
            $service_id                          = $this->input['plugin_resources_services_id'] ?? $this->fields['plugin_resources_services_id'];
            if (!$plugin_resources_department_service->getFromDBByCrit(
                [
                    'plugin_resources_departments_id' => $this->input['plugin_resources_departments_id'],
                    'plugin_resources_services_id'    => $service_id
                ]
            )) {
                $this->fields["plugin_resources_services_id"] = 0;
                $this->updates[]                              = "plugin_resources_services_id";
                $this->fields["plugin_resources_roles_id"]    = 0;
                $this->updates[]                              = "plugin_resources_roles_id";
            }
        }

        if (isset($this->input['plugin_resources_services_id']) && isset($this->oldvalues['plugin_resources_services_id'])
            && $this->input['plugin_resources_services_id'] != $this->oldvalues['plugin_resources_services_id']) {
            $plugin_resources_service_role = new PluginResourcesRole_Service();
            $role_id                       = $this->input['plugin_resources_roles_id'] ?? $this->fields['plugin_resources_roles_id'];
            if (!$plugin_resources_service_role->getFromDBByCrit(
                [
                    'plugin_resources_services_id' => $this->input['plugin_resources_services_id'],
                    'plugin_resources_roles_id'    => $role_id
                ]
            )) {
                $this->fields["plugin_resources_roles_id"] = 0;
                $this->updates[]                           = "plugin_resources_roles_id";
            }
        }

    }

    /**
     * Actions done after the UPDATE of the item in the database
     *
     * @param $history store changes history ? (default 1)
     *
     * @return nothing
     **/
    function post_updateItem($history = 1) {
        global $CFG_GLPI, $DB;

        $PluginResourcesChecklist = new PluginResourcesChecklist();
        $config                   = new PluginResourcesConfig();
        $config->getFromDB(1);
        if ($config->fields["mandatory_adcreation"] == 1) {
            if (isset ($this->input["addchecklist"])
                && $this->input["addchecklist"] == 1) {

                $PluginResourcesChecklist->deleteByCriteria(['plugin_resources_resources_id' => $this->fields["id"]]);

                $PluginResourcesChecklistconfig = new PluginResourcesChecklistconfig();
                $PluginResourcesChecklistconfig->addChecklistsFromRules($this,
                                                                        PluginResourcesChecklist::RESOURCES_CHECKLIST_IN);
                $PluginResourcesChecklistconfig->addChecklistsFromRules($this,
                                                                        PluginResourcesChecklist::RESOURCES_CHECKLIST_OUT);
                $PluginResourcesChecklistconfig->addChecklistsFromRules($this,
                                                                        PluginResourcesChecklist::RESOURCES_CHECKLIST_TRANSFER);
            }
        }
        $status = "update";
        if (isset($this->fields["is_leaving"])
            && !empty($this->fields["is_leaving"])) {
            $status                       = "LeavingResource";
            $PluginResourcesResource_Item = new PluginResourcesResource_Item();
            $badge                        = $PluginResourcesResource_Item->searchAssociatedBadge($this->fields["id"]);
            if ($badge) {
                $this->input["checkbadge"] = 1;
            }

            //when a resource is leaving, current employment get default state
            if (isset($this->input['date_end'])) {
                $PluginResourcesEmployment = new PluginResourcesEmployment();
                $default                   = PluginResourcesEmploymentState::getDefault();
                // only current employment
                $restrict = "`plugin_resources_resources_id` = '" . $this->input["id"] . "'
                        AND ((`begin_date` < '" . $this->input['date_end'] . "'
                              OR `begin_date` IS NULL)
                              AND (`end_date` > '" . $this->input['date_end'] . "'
                                    OR `end_date` IS NULL)) ";

                $iterator = $DB->request("glpi_plugin_resources_employments", $restrict);
                foreach ($iterator as $employment) {
                    $values = ['plugin_resources_employmentstates_id' => $default,
                               'end_date'                             => $this->input['date_end'],
                               'id'                                   => $employment['id']
                    ];
                    $PluginResourcesEmployment->update($values);
                }
            }
        }

        $picture = [0 => "picture", 1 => "date_mod"];
        if (count($this->updates)
            && array_diff($this->updates, $picture)
            && isset($this->input["withtemplate"])
            && $this->input["withtemplate"] != 1
        ) {

            if ($CFG_GLPI["notifications_mailing"]
                && isset($this->input['send_notification'])
                && $this->input['send_notification'] == 1
            ) {
                NotificationEvent::raiseEvent($status, $this);
            }
        }
    }

    /**
     * Actions done before the DELETE of the item in the database /
     * Maybe used to add another check for deletion
     *
     * @return bool : true if item need to be deleted else false
     **/
    function pre_deleteItem() {
        global $CFG_GLPI;

        if (isset($this->input['picture']) && $this->input['picture'] != "" && $this->input['picture'] != "null" && $this->input['picture'] != "NULL") {
            $filename = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $this->input['picture'];
            unlink($filename);
        }
        if ($CFG_GLPI["notifications_mailing"]
            && $this->fields["is_template"] != 1
            && isset($this->input['_delete'])
            && isset($this->input['send_notification'])
            && $this->input['send_notification'] == 1
        ) {
            NotificationEvent::raiseEvent("delete", $this);
        }

        return true;
    }

    /**
     * @param     $name
     * @param int $value
     *
     * @return int|string
     */
    function dropdownTemplate($name, $value = 0, $skip_profiles = false) {
        $dbu = new DbUtils();

        $restrict = ["is_template" => 1] +
                    $dbu->getEntitiesRestrictCriteria($this->getTable(), '', '', $this->maybeRecursive()) +
                    ["ORDER" => "template_name"] +
                    ["GROUPBY" => "template_name"];

        $dbu       = new DbUtils();
        $templates = $dbu->getAllDataFromTable($this->getTable(), $restrict);

        $config = new PluginResourcesConfig();
        $config->getFromDB(1);
        $option = [];
        if ($config->fields['allow_without_contract'] == 0) {
            $option[-1] = __('Without contract', 'resources');
        }
        if ($value == 0) {
            $value = $config->fields['plugin_resources_resourcetemplates_id'];
        }
        $available_contracttype = false;
        $contracttypeprofile    = new PluginResourcesContracttypeprofile();
        if ($contracttypeprofile->getFromDBByCrit(['profiles_id' => $_SESSION['glpiactiveprofile']['id']])) {
            $available_contracttype = json_decode($contracttypeprofile->fields['plugin_resources_contracttypes_id']);
        }
        $skip = false;

        if ($skip_profiles == true || $available_contracttype === false || !is_array($available_contracttype)) {
            $skip = true;
        }
        if (!empty($templates)) {
            foreach ($templates as $template) {
                if ($skip == false) {
                    if (!in_array($template['plugin_resources_contracttypes_id'], $available_contracttype)) {
                        continue;
                    }
                }
                $id_display = "";
                if ($_SESSION["glpiis_ids_visible"] || empty($template["template_name"])) {
                    $id_display = " (" . $template["id"] . ")";
                }
                $option[$template["id"]] = $template["template_name"] . $id_display;
            }
        }
        return Dropdown::showFromArray($name, $option, ['value' => $value]);
    }

    /**
     * Return the SQL command to retrieve linked object
     *
     * @return a SQL command which return a set of (itemtype, items_id)
     */
    function getSelectLinkedItem() {
        return "SELECT `itemtype`, `items_id`
              FROM `glpi_plugin_resources_resources_items`
              WHERE `plugin_resources_resources_id`='" . $this->fields['id'] . "'";
    }

    /**
     * @param       $ID
     * @param array $options
     *
     * @return bool
     */
    function showForm($ID, $options = []) {

        $this->initForm($ID, $options);
        $options['formoptions'] = " enctype='multipart/form-data'";
        $this->showFormHeader($options);

        if (isset($this->fields["entities_id"])) {
            $input['entities_id'] = $this->fields["entities_id"];
        } else {
            $input['entities_id'] = $_SESSION['glpiactive_entity'];
        }
        $input['plugin_resources_contracttypes_id'] = $this->fields["plugin_resources_contracttypes_id"];
        $hidden                                     = $this->getHiddenFields($input);
        $required                                   = $this->checkRequiredFields($input);
        $alert                                      = " style='color:red' ";

        $tohide = [];
        foreach ($this->fields as $k => $f) {
            $tohide[$k] = "";
            if (in_array($k, $hidden)) {
                $tohide[$k] = "hidden";
            }
        }
        $tohide['plugin_resources_employers_id'] = "";
        if (in_array('plugin_resources_employers_id', $hidden)) {
            $tohide['plugin_resources_employers_id'] = "hidden";
        }

        $config = new PluginResourcesConfig();
        if ($config->useSecurity()) {
            $tohide["security"] = "";
            $tohide["charter"]  = "";
            if (in_array("security", $hidden)) {
                $tohide["security"] = "hidden";
            }
            if (in_array("charter", $hidden)) {
                $tohide["charter"] = "hidden";
            }

        }


        echo "<tr class='tab_bg_1'>";
        echo "<td " . $tohide['gender'] . "";
        if (in_array("gender", $required)) {
            echo $alert;
        }
        echo ">";
        echo __('Gender', 'resources') . "</td>";
        echo "<td " . $tohide['gender'] . ">";
        $genders = $this->getGenders();
        $option  = ['value' => isset($this->fields["gender"]) ? $this->fields["gender"] : 0];
        Dropdown::showFromArray('gender', $genders, $option);
        echo "</td>";
        echo "</tr>";

        echo "<tr  class='tab_bg_1'>";

        echo "<td " . $tohide['name'] . "";
        if (in_array("name", $required)) {
            echo $alert;
        }
        echo ">";
        echo __('Surname') . "</td>";
        echo "<td " . $tohide['name'] . ">";
        $option = ['value' => $this->fields['name'], 'onchange' => "javascript:this.value=this.value.toUpperCase();"];
        echo Html::input('name', $option);
        echo "</td>";

        if ($tohide["name"] == "hidden") {
            echo "<td colspan='2'></td>";
        }

        echo "<td rowspan='6' colspan='2' align='center'>";
        if (isset($this->fields["picture"]) && !empty($this->fields["picture"])) {
            $path = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $this->fields["picture"];
            if (file_exists($path)) {
                echo "<object data='" . PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $this->fields["picture"] . "'>
             <param name='src' value='" . PLUGIN_RESOURCES_WEBDIR .
                     "/front/picture.send.php?file=" . $this->fields["picture"] . "'>
            </object> ";
                echo Html::hidden('picture', ['value' => $this->fields["picture"]]);
            } else {
                echo "<img src='../pics/nobody.png'>";
            }
        } else {
            echo "<img src='../pics/nobody.png'>";
        }

        echo "<br>" . __('Photo', 'resources') . "<br>";
        //      echo Html::file(['name' => 'picture', 'display' => false, 'onlyimages' => true]); //'value' => $this->fields["picture"],
        echo "<input class='form-control' type='file' name='picture'>";
        echo "&nbsp;";
        echo "(" . Document::getMaxUploadSize() . ")&nbsp;";
        if (isset($this->fields["picture"]) && !empty($this->fields["picture"])) {
            Html::showSimpleForm(PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php",
                                 'delete_picture',
                                 _x('button', 'Delete permanently'),
                                 ['id'      => $ID,
                                  'picture' => $this->fields["picture"]],
                                 'fa-times-circle');
        }
        echo "</td></tr>";

        echo "<tr " . $tohide['firstname'] . " class='tab_bg_1'>";
        echo "<td";
        if (in_array("firstname", $required)) {
            echo $alert;
        }
        echo ">";
        echo __('First name') . "</td>";
        echo "<td>";
        $option = ['value' => $this->fields['firstname'], 'onchange' => "First2UpperCase(this.value);' style='text-transform:capitalize;'"];
        echo Html::input('firstname', $option);
        echo "</td></tr>";

        echo "<tr " . $tohide['matricule'] . " class='tab_bg_1'>";
        echo "<td";
        if (in_array("matricule", $required)) {
            echo $alert;
        }
        echo ">";
        echo __('Matricule', 'resources') . "</td>";
        echo "<td>";
        $option = ['value' => $this->fields['matricule']];
        echo Html::input('matricule', $option);
        echo "</td>";
        echo "</tr>";
        $contractType     = new PluginResourcesContractType();
        $second_matricule = false;
        if ($contractType->getFromDB($this->fields["plugin_resources_contracttypes_id"])) {
            if ($contractType->fields["use_second_matricule"] > 0) {
                $second_matricule = true;
            }

        }
        if ($second_matricule === true) {
            echo "<tr " . $tohide['matricule_second'] . " class='tab_bg_1'>";
            echo "<td";
            if (in_array("matricule_second", $required)) {
                echo $alert;
            }
            echo ">";
            echo __('Second matricule', 'resources') . "</td>";
            echo "<td>";
            $option = ['value' => $this->fields['matricule']];
            echo Html::input('matricule_second', $option);
            echo "</td>";
            echo "</tr>";
        }


        echo "<tr class='tab_bg_1'><td>" . PluginResourcesResourceState::getTypeName(1) . "</td>";
        echo "<td>";
        if (Session::getCurrentInterface() == 'central') {
            Dropdown::show(PluginResourcesResourceState::class,
                           ['value'  => $this->fields["plugin_resources_resourcestates_id"],
                            'entity' => $this->fields["entities_id"]]);
        } else {
            echo Dropdown::getDropdownName("glpi_plugin_resources_resourcestates", $this->fields["plugin_resources_resourcestates_id"]);
        }
        echo "</td></tr>";

        echo "<tr  " . $tohide['plugin_resources_contracttypes_id'] . " class='tab_bg_1'><td>" . PluginResourcesContractType::getTypeName(1) . "</td>";
        echo "<td>";
        Dropdown::show(PluginResourcesContractType::class,
                       ['value'  => $this->fields["plugin_resources_contracttypes_id"],
                        'entity' => $this->fields["entities_id"]]);
        echo "</td></tr>";

        echo "<tr " . $tohide['quota'] . " class='tab_bg_1'>";
        echo "<td";
        if (in_array("quota", $required)) {
            echo $alert;
        }
        echo ">";
        echo __('Quota', 'resources') . "</td>";
        echo "<td>";
        echo Html::input('quota', ['value' => Html::formatNumber($this->fields["quota"], true, 4), 'size' => 14]);
        echo "</td>";
        echo "</tr>";

        echo "</table><table class='tab_cadre_fixe'>";
        $rank = new PluginResourcesRank();
        if ($rank->canView()) {
            echo "<tr class='tab_bg_1'>";
            echo "<td " . $tohide['plugin_resources_resourcesituations_id'] . " ";
            if (in_array("plugin_resources_resourcesituations_id", $required)) {
                echo $alert;
            }
            echo ">";
            echo PluginResourcesResourceSituation::getTypeName(1) . "</td>";
            echo "<td " . $tohide['plugin_resources_resourcesituations_id'] . ">";

            $params = ['name'   => 'plugin_resources_resourcesituations_id',
                       'value'  => $this->fields['plugin_resources_resourcesituations_id'],
                       'entity' => $this->fields["entities_id"],
                       'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownContractnature.php",
                       'span'   => 'span_contractnature'
            ];
            self::showGenericDropdown(PluginResourcesResourceSituation::class, $params);
            echo '</td>';
            if ($tohide['plugin_resources_resourcesituations_id'] == "hidden") {
                echo "<td colspan='2'></td>";
            }
            echo "<td  " . $tohide['plugin_resources_contractnatures_id'] . "";
            if (in_array("plugin_resources_contractnatures_id", $required)) {
                echo $alert;
            }
            echo ">";

            echo PluginResourcesContractNature::getTypeName(1) . "</td>";


            echo "<td " . $tohide['plugin_resources_contractnatures_id'] . ">";
            echo "<span id='span_contractnature' name='span_contractnature'>";
            if ($this->fields["plugin_resources_contractnatures_id"] > 0) {
                echo Dropdown::getDropdownName('glpi_plugin_resources_contractnatures',
                                               $this->fields["plugin_resources_contractnatures_id"]);
            } else {
                echo __('None');
            }
            echo "</span>";
            echo "</td>";
            if ($tohide['plugin_resources_contractnatures_id'] == "hidden") {
                echo "<td colspan='2'></td>";
            }
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td " . $tohide['plugin_resources_ranks_id'] . "";
            if (in_array("plugin_resources_ranks_id", $required)) {
                echo $alert;
            }
            echo ">";
            echo PluginResourcesRank::getTypeName(1) . "</td>";
            echo "<td " . $tohide['plugin_resources_ranks_id'] . " >";

            $params = ['name'   => 'plugin_resources_ranks_id',
                       'value'  => $this->fields['plugin_resources_ranks_id'],
                       'entity' => $this->fields["entities_id"],
                       'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownSpeciality.php",
                       'span'   => 'span_speciality'
            ];
            self::showGenericDropdown(PluginResourcesRank::class, $params);
            echo "</td>";

            if ($tohide['plugin_resources_ranks_id'] == "hidden") {
                echo "<td colspan='2'></td>";
            }
            echo "<td " . $tohide['plugin_resources_resourcespecialities_id'] . " ";
            if (in_array("plugin_resources_resourcespecialities_id", $required)) {
                echo $alert;
            }
            echo ">";
            echo PluginResourcesResourceSpeciality::getTypeName(1) . "</td>";
            echo "<td " . $tohide['plugin_resources_resourcespecialities_id'] . " >";
            echo "<span id='span_speciality' name='span_speciality'>";
            if ($this->fields["plugin_resources_resourcespecialities_id"] > 0) {
                echo Dropdown::getDropdownName('glpi_plugin_resources_resourcespecialities',
                                               $this->fields["plugin_resources_resourcespecialities_id"]);
            } else {
                echo __('None');
            }
            echo "</span>";
            echo "</td>";
            if ($tohide['plugin_resources_resourcespecialities_id'] == "hidden") {
                echo "<td colspan='2'></td>";
            }
            echo "</tr>";
            echo "</table><table class='tab_cadre_fixe'>";

        }

        echo "<tr class='tab_bg_1'>";
        echo "<td " . $tohide['locations_id'] . " ";
        if (in_array("locations_id", $required)) {
            echo $alert;
        }
        echo ">";
        echo __('Location') . "</td>";
        echo "<td " . $tohide['locations_id'] . " >";
        Dropdown::show('Location',
                       ['value'  => $this->fields["locations_id"],
                        'entity' => $this->fields["entities_id"]]);
        echo "</td>";
        if ($tohide['locations_id'] == "hidden") {
            echo "<td colspan='2'></td>";
        }
        echo "<td " . $tohide['plugin_resources_departments_id'] . " ";
        if (in_array("plugin_resources_departments_id", $required)) {
            echo $alert;
        }
        echo ">";
        echo PluginResourcesDepartment::getTypeName(1) . "</td>";
        echo "<td " . $tohide['plugin_resources_departments_id'] . ">";
        $rand = mt_rand();

        if ($config->useServiceDepartmentAD()) {
            UserTitle::dropdown(['name' => "plugin_resources_departments_id", 'value' => $this->fields["plugin_resources_departments_id"], 'rand' => $rand]);
        } else {
            Dropdown::show(PluginResourcesDepartment::class,
                           ['value'  => $this->fields["plugin_resources_departments_id"],
                            'entity' => $this->fields["entities_id"],
                            'rand'   => $rand]);
        }
        echo "</td>";
        if ($tohide['plugin_resources_departments_id'] == "hidden") {
            echo "<td colspan='2'></td>";
        }
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";

        echo "<td " . $tohide['plugin_resources_services_id'] . " ";
        if (in_array("plugin_resources_services_id", $required)) {
            echo $alert;
        }
        echo ">";
        echo PluginResourcesService::getTypeName(0) . "</td>";

        echo "<td " . $tohide['plugin_resources_services_id'] . " >";
        echo "<div id='show_services'>";
        if ($config->useServiceDepartmentAD()) {
            UserCategory::dropdown(['name' => "plugin_resources_services_id", 'value' => $this->fields["plugin_resources_services_id"], 'rand' => $rand]);
        } else {
            PluginResourcesService::dropdownFromDepart($this->fields["plugin_resources_departments_id"], ['name'   => "plugin_resources_services_id",
                                                                                                          'value'  => $this->fields["plugin_resources_services_id"],
                                                                                                          'entity' => $_SESSION['glpiactiveentities'],
                                                                                                          'rand'   => $rand]);
            $params = ['plugin_resources_services_id' => '__VALUE__',
                       'rand'                         => $rand,
            ];
            Ajax::updateItemOnSelectEvent("dropdown_plugin_resources_services_id$rand", "show_roles", "../ajax/dropdownRole.php", $params);
        }
        echo "</div>";
        echo "</td>";
        $params = ['plugin_resources_departments_id' => '__VALUE__',
                   'rand'                            => $rand,
        ];
        Ajax::updateItemOnSelectEvent("dropdown_plugin_resources_departments_id$rand", "show_services", "../ajax/dropdownService.php", $params);
        echo "<td " . $tohide['plugin_resources_roles_id'] . " ";
        if (in_array("plugin_resources_roles_id", $required)) {
            echo $alert;
        }
        echo ">";
        echo PluginResourcesRole::getTypeName(0) . "</td>";
        echo "<td " . $tohide['plugin_resources_roles_id'] . " >";
        echo "<div id='show_roles'>";
        //      Dropdown::show('PluginResourcesRole',
        //                     ['value'  => $this->fields["plugin_resources_roles_id"],
        //                      'entity' => $this->fields["entities_id"]]);
        PluginResourcesRole::dropdownFromService($this->fields['plugin_resources_services_id'], ['name'   => "plugin_resources_roles_id",
                                                                                                 'value'  => $this->fields["plugin_resources_roles_id"],
                                                                                                 'entity' => $_SESSION['glpiactiveentities'],
                                                                                                 'rand'   => $rand]);
        echo "</div>";
        echo "</td>";


        if ($config->useSecondaryService() && $config->useServiceDepartmentAD()) {
            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            echo __('Secondaries services', 'resources');
            echo "</td>";
            echo "<td>";

            $services = [];
            $userCat  = new UserCategory();
            $usersCat = $userCat->find();
            foreach ($usersCat as $res) {
                $services[$res['id']] = $res['name'];
            }

            Dropdown::showFromArray("secondary_services",
                                    $services,
                                    ['values'   => !empty($this->fields['secondary_services']) ? json_decode($this->fields['secondary_services'], true) : [],
                                     'multiple' => true]);
            echo "</td>";
            echo "</tr>";

        }

        echo "</tr>";

        echo "<tr class='tab_bg_1'>";

        echo "<td " . $tohide['plugin_resources_functions_id'] . " ";
        if (in_array("plugin_resources_functions_id", $required)) {
            echo $alert;
        }
        echo ">";
        echo PluginResourcesFunction::getTypeName(0) . "</td>";
        echo "<td " . $tohide['plugin_resources_functions_id'] . " >";
        Dropdown::show('PluginResourcesFunction',
                       ['value'  => $this->fields["plugin_resources_functions_id"],
                        'entity' => $this->fields["entities_id"]]);
        echo "</td>";

        echo "<td " . $tohide['plugin_resources_teams_id'] . " ";
        if (in_array("plugin_resources_teams_id", $required)) {
            echo $alert;
        }
        echo ">";
        echo PluginResourcesTeam::getTypeName(0) . "</td>";
        echo "<td " . $tohide['plugin_resources_teams_id'] . " >";
        Dropdown::show('PluginResourcesTeam',
                       ['value'  => $this->fields["plugin_resources_teams_id"],
                        'entity' => $this->fields["entities_id"]]);


        echo "</tr>";

        echo "<tr class='tab_bg_1'>";

        $contractType     = new PluginResourcesContractType();
        $display_employee = false;
        $condition_emp    = ['second_list' => 0];
        if ($contractType->getFromDB($this->fields["plugin_resources_contracttypes_id"])) {
            if ($contractType->fields["use_employee_wizard"] > 0) {
                $display_employee = true;
            }
            if ($contractType->fields["use_second_list_employer"] > 0) {
                $condition_emp = ['second_list' => 1];
            }

        }

        if (Session::haveRight('plugin_resources_employee_core_form', READ) && !$display_employee) {
            $employee                                      = new PluginResourcesEmployee();
            $this->fields["plugin_resources_employers_id"] = 0;
            if ($employee->getFromDBByCrit(['plugin_resources_resources_id' => $this->getID()])) {
                $this->fields["plugin_resources_employers_id"] = $employee->fields['plugin_resources_employers_id'];
            }
            echo "<td " . $tohide['plugin_resources_employers_id'] . " ";
            if (in_array("plugin_resources_employers_id", $required)) {
                echo $alert;
            }
            echo ">";
            echo PluginResourcesEmployer::getTypeName(1) . "</td>";
            echo "<td " . $tohide['plugin_resources_employers_id'] . " >";
            Dropdown::show(PluginResourcesEmployer::getType(),
                           ['value'     => $this->fields["plugin_resources_employers_id"],
                            'entity'    => $this->fields["entities_id"],
                            'condition' => $condition_emp
                           ]);
            echo "</td>";


        } else {
            echo "<td colspan='2'></td>";
        }

        echo "<td colspan='2'></td>";


        echo "</tr>";


        echo "<tr class='tab_bg_1'>";
        echo "<td " . $tohide['users_id'] . " ";
        if (in_array("users_id", $required)) {
            echo $alert;
        }
        echo ">";
        echo __('Resource manager', 'resources') . "</td>";
        echo "<td " . $tohide['users_id'] . ">";
        $config = new PluginResourcesConfig();
        if ($config->getField('resource_manager') != "") {

            $tableProfileUser = Profile_User::getTable();
            $tableUser        = User::getTable();
            $profile_User     = new  Profile_User();
            $prof             = [];
            foreach (json_decode($config->getField('resource_manager')) as $profs) {
                $prof[$profs] = $profs;
            }
            $ids           = join("','", $prof);
            $restrict      = getEntitiesRestrictCriteria($tableProfileUser, 'entities_id', $this->fields["entities_id"], true);
            $restrict      = array_merge([$tableProfileUser . ".profiles_id" => [$ids]], $restrict);
            $profiles_User = $profile_User->find($restrict);
            $used          = [];
            foreach ($profiles_User as $profileUser) {
                $user = new User();
                $user->getFromDB($profileUser["users_id"]);
                $used[$profileUser["users_id"]] = $user->getFriendlyName();
            }


            Dropdown::showFromArray("users_id", $used, ['value' => $this->fields["users_id"], 'display_emptychoice' => true]);

        } else {
            User::dropdown(['value'       => $this->fields["users_id"],
                            'name'        => "users_id",
                            'entity'      => $this->fields["entities_id"],
                            'entity_sons' => true,
                            'right'       => 'all']);
        }

        echo "</td>";
        if ($tohide['users_id'] == "hidden") {
            echo "<td colspan='2'></td>";
        }
        echo "<td " . $tohide['date_begin'] . " ";
        if (in_array("date_begin", $required)) {
            echo $alert;
        }
        echo ">";
        echo __('Arrival date', 'resources') . "</td>";
        echo "<td " . $tohide['date_begin'] . " >";
        Html::showDateField("date_begin", ['value' => $this->fields["date_begin"]]);
        echo "</td>";
        if ($tohide['date_begin'] == "hidden") {
            echo "<td colspan='2'></td>";
        }
        echo "</tr>";

        echo "<tr " . $tohide['users_id_sales'] . " class='tab_bg_1'>";
        echo "<td";
        if (in_array("users_id_sales", $required)) {
            echo $alert;
        }
        echo ">";
        echo __('Sales manager', 'resources') . "</td>";
        echo "<td>";
        $config = new PluginResourcesConfig();
        if (($config->getField('sales_manager') != "")) {

            echo "<div class=\"bt-feature col-md-3\">";
            $tableProfileUser = Profile_User::getTable();
            $tableUser        = User::getTable();
            $profile_User     = new  Profile_User();
            $prof             = [];
            foreach (json_decode($config->getField('sales_manager')) as $profs) {
                $prof[$profs] = $profs;
            }

            $ids           = join("','", $prof);
            $restrict      = getEntitiesRestrictCriteria($tableProfileUser, 'entities_id', $this->fields["entities_id"], true);
            $restrict      = array_merge([$tableProfileUser . ".profiles_id" => [$ids]], $restrict);
            $profiles_User = $profile_User->find($restrict);
            $used          = [];
            foreach ($profiles_User as $profileUser) {
                $user = new User();
                $user->getFromDB($profileUser["users_id"]);
                $used[$profileUser["users_id"]] = $user->getFriendlyName();
            }

            Dropdown::showFromArray("users_id_sales", $used, ['value' => $this->fields["users_id_sales"], 'display_emptychoice' => true]);;
        } else {
            User::dropdown(['value'       => $this->fields["users_id_sales"],
                            'name'        => "users_id_sales",
                            'entity'      => $this->fields["entities_id"],
                            'entity_sons' => true,
                            'right'       => 'all']);
        }

        echo "</td>";
        if ($tohide['users_id_sales'] == "hidden") {
            echo "<td colspan='2'></td>";
        }
        echo "<td colspan='2'>";
        echo "</td>";
        echo "</tr>";

        echo "<tr " . $tohide['comment'] . " class='tab_bg_1'><td colspan='4'>" . __('Description') . "</td></tr>";

        echo "<tr " . $tohide['comment'] . " class='tab_bg_1'><td colspan='4'>";
        echo Html::textarea([
                                'name'    => 'comment',
                                'value'   => $this->fields["comment"],
                                'cols'    => '130',
                                'rows'    => '4',
                                'display' => false,
                            ]);
        echo Html::hidden('withtemplate', ['value' => $options['withtemplate']]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2'>";
        if ($ID && $options['withtemplate'] < 2) {
            echo __('Request date') . " : ";
            echo Html::convDate($this->fields["date_declaration"]);
            echo "&nbsp;" . __('By') . "&nbsp;";
            $users_id_recipient = new User();
            $users_id_recipient->getFromDB($this->fields["users_id_recipient"]);
            if ($this->canCreate() && Session::getCurrentInterface() == 'central') {

                User::dropdown(['value'       => $this->fields["users_id_recipient"],
                                'name'        => "users_id_recipient",
                                'entity'      => $this->fields["entities_id"],
                                'entity_sons' => true,
                                'right'       => 'all']);
            } else {
                echo $users_id_recipient->getName();
            }
        } else {
            echo Html::hidden('users_id_recipient', ['value' => Session::getLoginUserID()]);
            echo Html::hidden('date_declaration', ['value' => date('Y-m-d')]);
        }
        echo "</td>";

        echo "<td>" . __('Associable to a ticket') . "</td><td>";

        if (Session::getCurrentInterface() == 'central') {
            Dropdown::showYesNo('is_helpdesk_visible', $this->fields['is_helpdesk_visible']);
        } else {
            echo Dropdown::getDropdownName($this->getTable(), $this->fields["is_helpdesk_visible"]);
        }
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td></td><td></td><td>" . __('Send a notification') . "</td><td>";
        echo "<input type='checkbox' name='send_notification' checked = true";
        if (Session::getCurrentInterface() != 'central'
            || (isset($options['withtemplate']) && $options['withtemplate'])
        ) {
            echo " disabled='true' ";
        }
        echo " value='1'>";
        if (Session::getCurrentInterface() != 'central') {
            echo Html::hidden('send_notification', ['value' => 1]);
        }
        echo "</td>";
        echo "</tr>";

        echo "</table><table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'>";

        echo "<td>" . __('Declared as leaving', 'resources') . "</td><td>";
        Dropdown::showYesNo("is_leaving", $this->fields["is_leaving"]);

        if ($ID != -1 && $options['withtemplate'] != 1 && $this->fields["is_leaving"] == 1
            && isset($this->fields["users_id_recipient_leaving"])
        ) {
            echo "&nbsp;" . __('By') . "&nbsp;";
            $users_id_recipient_leaving = new User();
            if ($users_id_recipient_leaving->getFromDB($this->fields["users_id_recipient_leaving"])) {
                echo $users_id_recipient_leaving->getName();
            }

            if (isset($this->fields["date_declaration_leaving"])
                && $this->fields["date_declaration_leaving"] != null
            ) {
                echo "&nbsp;-&nbsp;";
                echo Html::convDateTime($this->fields["date_declaration_leaving"]);
            }
        }

        echo "</td>";
        if (countDistinctElementsInTable(PluginResourcesLeavingReason::getTable(), 'id')) {
            echo "<td";
            if (in_array("plugin_resources_leavingreasons_id", $required)) {
                echo $alert;
            }
            echo ">";
            echo PluginResourcesLeavingReason::getTypeName(1) . "</td>";
            echo "<td>";
            Dropdown::show(PluginResourcesLeavingReason::class,
                           ['value'  => $this->fields["plugin_resources_leavingreasons_id"],
                            'entity' => $this->fields["entities_id"]]);
            echo "</td>";
        }

        echo "<td " . $tohide['date_end'] . " ";
        if (in_array("date_end", $required)) {
            echo $alert;
        }
        echo ">";
        echo __('Departure date', 'resources') . "&nbsp;";
        if (!in_array("date_end", $required)) {
            Html::showToolTip(nl2br(__('Empty for non defined', 'resources')));
        }
        echo "</td>";
        echo "<td " . $tohide['date_end'] . ">";
        Html::showDateField("date_end", ['value' => $this->fields["date_end"]]);
        echo "</td>";
        if ($tohide['date_end'] == "hidden") {
            echo "<td colspan='2'></td>";
        }
        if (!countDistinctElementsInTable(PluginResourcesLeavingReason::getTable(), 'id')) {
            echo "<td colspan='2'></td>";
        }
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='6'>";
        if (isset($options['withtemplate']) && $options['withtemplate']) {
            //TRANS: %s is the datetime of insertion
            printf(__('Created on %s'), Html::convDateTime($_SESSION["glpi_currenttime"]));
        }
        echo "</td></tr>\n";

        $config = new PluginResourcesConfig();
        if ($config->useSecurity()) {
            echo "<tr class='tab_bg_1'>";
            echo "<td " . $tohide['security'] . ">" . __('Sensitized to security', 'resources') . "</td>";
            echo "<td " . $tohide['security'] . ">";
            $checked = '';
            if ($this->fields['sensitize_security']) {
                $checked = "checked = true";
            }
            echo "<input type='checkbox' name='sensitize_security' $checked value='1'>";
            echo "</td>";
            if ($tohide['security'] == "hidden") {
                echo "<td colspan='2'></td>";
            }
            echo "<td " . $tohide['charter'] . ">" . __('Reading the security charter', 'resources') . "</td><td " . $tohide['charter'] . ">";
            $checked = '';
            if ($this->fields['read_chart']) {
                $checked = "checked = true";
            }
            echo "<input type='checkbox' name='read_chart' $checked value='1'>";
            $name = 'is_checked' . $ID;
            echo Html::hidden($name, ['value' => (($this->fields['read_chart'] > 0) ? 0 : 1)]);

            echo "</td>";
            if ($tohide['charter'] == "hidden") {
                echo "<td colspan='2'></td>";
            }
            echo "<td colspan='2'></td>";
            echo "</tr>";
        }

        echo "</table><table class='tab_cadre_fixe'>";

        if (Session::getCurrentInterface() != 'central') {
            $options['candel'] = false;
        }
        $options['colspan'] = 6;
        $this->showFormButtons($options);

        return true;
    }

    /**
     * @param $options
     *
     * @return bool
     */
    function sendReport($options) {
        global $CFG_GLPI;

        if (!$this->getFromDB($options["id"])) {
            return false;
        }

        if ($CFG_GLPI["notifications_mailing"]) {
            $report = new PluginResourcesReportConfig();
            $report->getFromDB($options["reports_id"]);

            if ($report->fields['send_report_notif']) {
                $notification = new PluginResourcesNotification();
                $notification->add(['users_id'                      => Session::getLoginUserID(),
                                    'plugin_resources_resources_id' => $options["id"],
                                    'type'                          => 'report']);
                NotificationEvent::raiseEvent('report', $this, ['reports_id' => $options["reports_id"]]);
            }

            if ($report->fields['send_other_notif']) {
                $notification = new PluginResourcesNotification();
                $notification->add(['users_id'                      => Session::getLoginUserID(),
                                    'plugin_resources_resources_id' => $options["id"],
                                    'type'                          => 'other']);
                NotificationEvent::raiseEvent('other', $this, ['reports_id' => $options["reports_id"]]);
            }
        }
    }

    /**
     * @param $options
     *
     * @return bool
     */
    function reSendResourceCreation($options) {
        global $CFG_GLPI;

        if (!$this->getFromDB($options["id"])) {
            return false;
        }

        if ($CFG_GLPI["notifications_mailing"]) {
            $status = "new";
            NotificationEvent::raiseEvent($status, $this);
        }
    }

    /**
     * @param $options
     */
    static function showReportForm($options) {

        $reportconfig = new PluginResourcesReportConfig();
        $reportconfig->getFromDBByResource($options['id']);

        if ($reportconfig->fields['send_report_notif'] || $reportconfig->fields['send_other_notif']) {
            echo "<div align='center'>";
            echo "<form action='" . $options['target'] . "' method='post'>";
            echo "<table class='tab_cadre_fixe' width='50%'>";
            echo "<tr><th colspan='4'>" . PluginResourcesReportConfig::getTypeName(2) . "</th></tr>";
            echo "<tr class='tab_bg_2 center'>";
            echo "<td colspan='4'>";
            echo Html::submit(__s('Send a notification'), ['name' => 'report', 'class' => 'btn btn-primary']);
            echo Html::hidden('id', ['value' => $options['id']]);
            echo Html::hidden('reports_id', ['value' => $reportconfig->fields["id"]]);
            echo "</td></tr></table>";
            Html::closeForm();
            echo "</div>";
        }

        $notification = new PluginResourcesNotification();
        $notification->listItems($options['id']);
    }

    function wizardFirstForm() {
        global $CFG_GLPI;

        echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_main.css");
        echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_ticket.css");

        echo "<h3><div class='alert alert-secondary' role='alert' style='margin-top: 10px;'>";
        echo "<i class='" . self::getIcon() . "'></i>&nbsp;";
        echo __('Resources management', 'resources');
        echo "</div></h3>";

        echo "<div id ='content'>";
        echo "<div class='bt-container resources_wizard_resp'> ";
        echo "<div class='bt-block bt-features' > ";

        echo "<form action='" . PLUGIN_RESOURCES_WEBDIR . "/front/wizard.form.php' method='post'>";

        echo "<div class=\"form-row plugin_resources_wizard_margin \">";
        echo "<div class=\"bt-feature col-md-12 \" >";
        echo "<h4 class=\"bt-title-divider\">";
        echo "<img class='resources_wizard_resp_img' src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png' alt='newresource'/>&nbsp;";
        echo __('Welcome to the wizard resource', 'resources');
        echo "</h4></div></div>";

        echo "<div class=\"form-row\">";
        echo "<div class=\"bt-feature col-md-12 \">";
        echo __('This wizard lets you create new resources in GLPI', 'resources');
        echo "<br /><br />";
        echo __('To begin, select type of contract', 'resources');
        echo "<br /><br />";

        $this->dropdownTemplate("template");

        echo "</div></div>";

        echo "<div class=\"form-row\">";
        echo "<div class=\"bt-feature col-md-12 \">";
        echo "<div class='next'>";
        echo Html::hidden('withtemplate', ['value' => 2]);
        echo Html::submit(_sx('button', 'Next >', 'resources'), ['name' => 'first_step', 'class' => 'btn btn-success']);
        echo "</div>";
        echo "</div></div>";

        Html::closeForm();

        echo "</div>";
        echo "</div>";
        echo "</div>";
    }

    /**
     * @param       $ID
     * @param array $options
     */
    function wizardSecondForm($ID, $options = []) {
        global $CFG_GLPI;

        $empty = 0;
        if ($ID > 0) {
            $this->check($ID, READ);
        } else {
            // Create item
            $this->check(-1, UPDATE);
            $this->getEmpty();
            $empty = 1;
        }

        $rank = new PluginResourcesRank();

        if (!isset($options["requiredfields"])) {
            $options["requiredfields"] = 0;
        }
        if (($options['withtemplate'] == 2 || $options["new"] != 1) && $options["requiredfields"] != 1) {

            $options["gender"]                                   = $this->fields["gender"];
            $options["name"]                                     = $this->fields["name"];
            $options["firstname"]                                = $this->fields["firstname"];
            $options["locations_id"]                             = $this->fields["locations_id"];
            $options["users_id"]                                 = $this->fields["users_id"];
            $options["users_id_sales"]                           = $this->fields["users_id_sales"];
            $options["plugin_resources_departments_id"]          = $this->fields["plugin_resources_departments_id"];
            $options["plugin_resources_services_id"]             = $this->fields["plugin_resources_services_id"];
            $options["plugin_resources_functions_id"]            = $this->fields["plugin_resources_functions_id"];
            $options["plugin_resources_teams_id"]                = $this->fields["plugin_resources_teams_id"];
            $options["date_begin"]                               = $this->fields["date_begin"];
            $options["date_end"]                                 = $this->fields["date_end"];
            $options["comment"]                                  = $this->fields["comment"];
            $options["quota"]                                    = $this->fields["quota"];
            $options["plugin_resources_resourcesituations_id"]   = $this->fields["plugin_resources_resourcesituations_id"];
            $options["plugin_resources_contractnatures_id"]      = $this->fields["plugin_resources_contractnatures_id"];
            $options["plugin_resources_ranks_id"]                = $this->fields["plugin_resources_ranks_id"];
            $options["plugin_resources_resourcespecialities_id"] = $this->fields["plugin_resources_resourcespecialities_id"];
            $options["plugin_resources_leavingreasons_id"]       = $this->fields["plugin_resources_leavingreasons_id"];
            $options["sensitize_security"]                       = $this->fields["sensitize_security"];
            $options["read_chart"]                               = $this->fields["read_chart"];
            $options["plugin_resources_roles_id"]                = $this->fields["plugin_resources_roles_id"];
            $options["matricule"]                                = $this->fields["matricule"];
            $options["matricule_second"]                         = $this->fields["matricule_second"];
        }
        $options["plugin_resources_employers_id"] = 0;

        echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_main.css");
        echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_ticket.css");

        echo "<h3><div class='alert alert-secondary' role='alert' >";
        echo "<i class='" . self::getIcon() . "'></i>&nbsp;";
        echo __('Resources management', 'resources');
        echo "</div></h3>";

        echo "<div id ='content'>";
        echo "<div class='bt-container resources_wizard_resp'>";
        echo "<div class='bt-block bt-features' >";

        echo "<form action='" . $options['target'] . "' method='post'>";

        echo "<div class=\"form-row plugin_resources_wizard_margin \">";
        echo "<div class=\"bt-feature col-md-12 \" >";
        echo "<h4 class=\"bt-title-divider\">";
        echo "<img class='resources_wizard_resp_img' src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png' alt='newresource'/>&nbsp;";
        echo __('Enter general information about the resource', 'resources');
        echo "</h4></div></div>";

        if (!$this->canView()) {
            return false;
        }

        $input = [];
        if (isset($this->fields["entities_id"]) || $empty == 1) {
            if ($empty == 1) {
                $input['plugin_resources_contracttypes_id'] = 0;
                $input['entities_id']                       = $_SESSION['glpiactive_entity'];
                echo Html::hidden('entities_id', ['value' => $_SESSION["glpiactive_entity"]]);
            } else {
                $input['plugin_resources_contracttypes_id'] = $this->fields["plugin_resources_contracttypes_id"];
                if (isset($options['withtemplate']) && $options['withtemplate'] == 2) {
                    $input['entities_id'] = $_SESSION['glpiactive_entity'];
                    echo Html::hidden('id_template', ['value' => $ID]);
                    echo Html::hidden('entities_id', ['value' => $_SESSION["glpiactive_entity"]]);
                } else {
                    $input['entities_id'] = $this->fields["entities_id"];
                    echo Html::hidden('entities_id', ['value' => $this->fields["entities_id"]]);
                }
            }
        }
        $required = $this->checkRequiredFields($input);
        $hidden   = $this->getHiddenFields($input);
        $tohide   = [];
        foreach ($this->fields as $k => $f) {
            $tohide[$k] = "";
            if (in_array($k, $hidden)) {
                $tohide[$k] = "hidden";
            }
        }
        $tohide['plugin_resources_employers_id'] = "";
        if (in_array('plugin_resources_employers_id', $hidden)) {
            $tohide['plugin_resources_employers_id'] = "hidden";
        }

        echo "<div class=\"form-row plugin_resources_wizard_margin \">";
        echo "<div class=\"bt-feature col-md-12 \" >";

        echo "<div class=\"form-row\">";

        echo "<div class=\"bt-feature col-md-4 \">";
        echo "<span class='b'>";
        echo PluginResourcesContractType::getTypeName(1);
        echo "</span>&nbsp;";
        if ($this->fields["plugin_resources_contracttypes_id"]) {
            echo Dropdown::getDropdownName("glpi_plugin_resources_contracttypes",
                                           $this->fields["plugin_resources_contracttypes_id"]);
        } else {
            echo __('Without contract', 'resources');
        }
        echo "</div>";
        if (Session::isMultiEntitiesMode()) {
            echo "<div class=\"bt-feature col-md-4 \">";
            echo "<span class='b'>";
            echo __('Entity');
            echo "</span>&nbsp;";
            echo Dropdown::getDropdownName("glpi_entities", $input['entities_id']);
            echo "</div>";
        }
        if ($this->fields["plugin_resources_resourcestates_id"]) {
            echo "<div class=\"bt-feature col-md-4 \">";
            echo "<span class='b'>";
            echo PluginResourcesResourceState::getTypeName(1);
            echo "</span>&nbsp;";
            echo Dropdown::getDropdownName("glpi_plugin_resources_resourcestates",
                                           $this->fields["plugin_resources_resourcestates_id"]);
            echo "</div>";
        }
        echo "</div>";

        echo "</div>";
        echo "</div>";

        echo "<div class=\"form-row plugin_resources_wizard_margin\">";
        echo "<div class=\"bt-feature col-md-12\" >";

        echo "<div  class=\"form-row\">";
        echo "<div " . $tohide['gender'] . " class=\"bt-feature col-md-3\"";
        if (in_array("gender", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Gender', 'resources');
        echo "</div>";

        echo "<div " . $tohide['gender'] . " class=\"bt-feature col-md-3\">";
        $genders = $this->getGenders();
        $option  = ['value' => $options["gender"]];
        Dropdown::showFromArray('gender', $genders, $option);
        echo "</div>";
        echo "</div>";

        echo "<div  class=\"form-row\">";

        echo "<div " . $tohide['name'] . " class=\"bt-feature col-md-3\"";
        if (in_array("name", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Surname');
        echo "</div>";
        echo "<div " . $tohide['name'] . " class=\"bt-feature col-md-3\">";
        $option = ['value'    => $options["name"],
                   'size'     => 30,
                   'onchange' => "javascript:this.value=this.value.toUpperCase();"];
        echo Html::input('name', $option);
        echo "<br><span class='plugin_resources_wizard_comment' style='color:red;'>";
        echo __("Thank you for paying attention to the spelling of the name and the firstname of the resource. For compound firstnames, separate them with a dash \"-\".", "resources");
        echo "</span>";
        echo "</div>";

        echo "<div " . $tohide['firstname'] . " class=\"bt-feature col-md-3\"";
        if (in_array("firstname", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('First name');
        echo "</div>";

        echo "<div " . $tohide['firstname'] . " class=\"bt-feature col-md-3\">";

        $option = ['value'    => $options["firstname"],
                   'size'     => 30,
                   'onChange' => "javascript:this.value=First2UpperCase(this.value);style='text-transform:capitalize;'"];
        echo Html::input('firstname', $option);
        echo "</div>";

        echo "</div>";
        echo "<div class=\"form-row\">";

        echo "<div " . $tohide['matricule'] . " class=\"bt-feature col-md-3\"";
        if (in_array("matricule", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Matricule', 'resources') . "</td>";
        echo "</div>";
        echo "<div " . $tohide['matricule'] . " class=\"bt-feature col-md-3\">";
        $option = ['value' => $options['matricule']];
        echo Html::input('matricule', $option);
        echo "</div>";
        $contractType     = new PluginResourcesContractType();
        $second_matricule = false;
        if ($contractType->getFromDB($this->fields["plugin_resources_contracttypes_id"])) {

            if ($contractType->fields["use_second_matricule"] > 0) {
                $second_matricule = true;
            }
        }

        if ($second_matricule === true) {
            echo "<div " . $tohide['matricule_second'] . " class=\"bt-feature col-md-3\"";
            if (in_array("matricule_second", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo __('Second matricule', 'resources') . "</td>";
            echo "</div>";
            echo "<div " . $tohide['matricule_second'] . " class=\"bt-feature col-md-3\">";
            $option = ['value' => $options['matricule_second']];
            echo Html::input('matricule_second', $option);
            echo "</div>";
        }


        echo "</div>";


        echo "<div class=\"form-row\">";

        echo "<div " . $tohide['locations_id'] . " class=\"bt-feature col-md-3\"";
        if (in_array("locations_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Location');
        echo "</div>";
        echo "<div " . $tohide['locations_id'] . " class=\"bt-feature col-md-3\">";
        Dropdown::show('Location', ['name' => "locations_id", 'value' => $options["locations_id"]]);
        echo "</div>";

        echo "<div " . $tohide['quota'] . " class=\"bt-feature col-md-3\">";
        if (in_array("quota", $required)) {
            echo "<span class='red'>*</span>";
        }
        echo __('Quota', 'resources');
        echo "</div>";
        echo "<div " . $tohide['quota'] . " class=\"bt-feature col-md-3\">";
        echo Html::input('quota', ['value' => Html::formatNumber($options["quota"], true, 4), 'size' => 14]);
        echo "</div>";

        echo "</div>";

        echo "</div>";
        echo "</div>";

        if ($rank->canView()) {

            echo "<div class=\"form-row plugin_resources_wizard_margin\">";
            echo "<div class=\"bt-feature col-md-12  \" >";

            echo "<div class=\"form-row\">";

            echo "<div " . $tohide['plugin_resources_resourcesituations_id'] . " class=\"bt-feature col-md-3\"";
            if (in_array("plugin_resources_resourcesituations_id", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo PluginResourcesResourceSituation::getTypeName(1);
            echo "</div>";
            echo "<div " . $tohide['plugin_resources_resourcesituations_id'] . " class=\"bt-feature col-md-3\">";
            $params = ['name'   => 'plugin_resources_resourcesituations_id',
                       'value'  => $options['plugin_resources_resourcesituations_id'],
                       'entity' => $this->fields["entities_id"],
                       'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownContractnature.php",
                       'span'   => 'span_contractnature'
            ];
            self::showGenericDropdown(PluginResourcesResourceSituation::class, $params);
            echo "</div>";

            echo "<div " . $tohide['plugin_resources_contractnatures_id'] . " class=\"bt-feature col-md-3\"";
            if (in_array("plugin_resources_contractnatures_id", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo PluginResourcesContractNature::getTypeName(1);
            echo "</div>";
            echo "<div " . $tohide['plugin_resources_contractnatures_id'] . " class=\"bt-feature col-md-3\">";
            echo "<span id='span_contractnature' name='span_contractnature'>";
            if ($options["plugin_resources_contractnatures_id"] > 0) {
                echo Dropdown::getDropdownName('glpi_plugin_resources_contractnatures',
                                               $options["plugin_resources_contractnatures_id"]);
            } else {
                echo Html::hidden('plugin_resources_contractnatures_id', ['value' => 0]);
                echo __('None');
            }
            echo "</span>";
            echo "</div>";

            echo "</div>";

            echo "<div class=\"form-row\">";

            echo "<div " . $tohide['plugin_resources_ranks_id'] . " class=\"bt-feature col-md-3\"";
            if (in_array("plugin_resources_ranks_id", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo PluginResourcesRank::getTypeName(1);
            echo "</div>";
            echo "<div " . $tohide['plugin_resources_ranks_id'] . " class=\"bt-feature col-md-3\">";
            $params = ['name'   => 'plugin_resources_ranks_id',
                       'value'  => $options['plugin_resources_ranks_id'],
                       'entity' => $this->fields["entities_id"],
                       'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownSpeciality.php",
                       'span'   => 'span_speciality'
            ];
            self::showGenericDropdown(PluginResourcesRank::class, $params);

            echo "</div>";

            echo "<div " . $tohide['plugin_resources_resourcespecialities_id'] . " class=\"bt-feature col-md-3\"";
            if (in_array("plugin_resources_resourcespecialities_id", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo PluginResourcesResourceSpeciality::getTypeName(1);
            echo "</div>";
            echo "<div " . $tohide['plugin_resources_resourcespecialities_id'] . " class=\"bt-feature col-md-3\">";
            echo "<span id='span_speciality' name='span_speciality'>";
            if ($options["plugin_resources_resourcespecialities_id"] > 0) {
                echo Dropdown::getDropdownName('glpi_plugin_resources_resourcespecialities',
                                               $options["plugin_resources_resourcespecialities_id"]);
            } else {
                echo Html::hidden('plugin_resources_resourcespecialities_id', ['value' => 0]);
                echo __('None');
            }
            echo "</div>";
            echo "</div>";

            echo "</div>";
            echo "</div>";

        } else {

            echo Html::hidden('plugin_resources_resourcesituations_id', ['value' => 0]);
            echo Html::hidden('plugin_resources_contractnatures_id', ['value' => 0]);
            echo Html::hidden('plugin_resources_ranks_id', ['value' => 0]);
            echo Html::hidden('plugin_resources_resourcespecialities_id', ['value' => 0]);

        }

        echo "<div class=\"form-row plugin_resources_wizard_margin \">";
        echo "<div class=\"bt-feature col-md-12 \" >";

        echo "<div class=\"form-row\">";

        echo "<div " . $tohide['users_id'] . " class=\"bt-feature col-md-3\"";
        if (in_array("users_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Resource manager', 'resources');
        echo "</div>";
        $config = new PluginResourcesConfig();
        if ($config->getField('resource_manager') != "") {
            echo "<div " . $tohide['users_id'] . " class=\"bt-feature col-md-3\">";


            $tableProfileUser = Profile_User::getTable();
            $tableUser        = User::getTable();
            $profile_User     = new  Profile_User();
            $prof             = [];
            foreach (json_decode($config->getField('resource_manager')) as $profs) {
                $prof[$profs] = $profs;
            }
            $ids           = join("','", $prof);
            $restrict      = getEntitiesRestrictCriteria($tableProfileUser, 'entities_id', $_SESSION['glpiactive_entity'], true);
            $restrict      = array_merge([$tableProfileUser . ".profiles_id" => [$ids]], $restrict);
            $profiles_User = $profile_User->find($restrict);
            $used          = [];
            foreach ($profiles_User as $profileUser) {
                $user = new User();
                $user->getFromDB($profileUser["users_id"]);
                $used[$profileUser["users_id"]] = $user->getFriendlyName();
            }


            Dropdown::showFromArray("users_id", $used, ['value' => $options["users_id"], 'display_emptychoice' => true]);
            echo "</div>";
        } else {
            echo "<div " . $tohide['users_id'] . " class=\"bt-feature col-md-3\">";

            User::dropdown(['value'       => $options["users_id"],
                            'name'        => "users_id",
                            'entity'      => $input['entities_id'],
                            'entity_sons' => true,
                            'right'       => 'all',
                           ]);
            echo "</div>";


        }
        echo "<div " . $tohide['users_id_sales'] . " class=\"bt-feature col-md-3\"";
        if (in_array("users_id_sales", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Sales manager', 'resources');
        echo "</div>";

        if (($config->getField('sales_manager') != "")) {

            echo "<div " . $tohide['users_id_sales'] . " class=\"bt-feature col-md-3\">";
            $tableProfileUser = Profile_User::getTable();
            $tableUser        = User::getTable();
            $profile_User     = new  Profile_User();
            $prof             = [];
            foreach (json_decode($config->getField('sales_manager')) as $profs) {
                $prof[$profs] = $profs;
            }

            $ids      = join("','", $prof);
            $restrict = getEntitiesRestrictCriteria($tableProfileUser, 'entities_id', $input['entities_id'], true);
            $restrict = array_merge([$tableProfileUser . ".profiles_id" => [$ids]], $restrict);
            //         $profiles_User = $profile_User->find([$tableProfileUser . ".profiles_id" => [$ids], "entities_id" => $input['entities_id']]);
            $profiles_User = $profile_User->find($restrict);
            $used          = [];
            foreach ($profiles_User as $profileUser) {
                $user = new User();
                $user->getFromDB($profileUser["users_id"]);
                $used[$profileUser["users_id"]] = $user->getFriendlyName();
            }

            Dropdown::showFromArray("users_id_sales", $used, ['value' => $options["users_id_sales"], 'display_emptychoice' => true]);
            //         Dropdown::show(User::getType(), ['value' => $options["users_id_sales"],
            //            'name' => "users_id_sales",
            //            'entity' => $input['entities_id'],
            //            'right' => 'all',
            //            'condition' => [$tableUser . ".id" => [$ids]]]);
            echo "</div>";
        } else {

            echo "<div " . $tohide['users_id_sales'] . " class=\"bt-feature col-md-3\">";
            User::dropdown(['value'       => $options["users_id_sales"],
                            'name'        => "users_id_sales",
                            'entity'      => $input['entities_id'],
                            'entity_sons' => true,
                            'right'       => 'all',
                           ]);
            echo "</div>";
        }
        echo "</div>";

        $contractType     = new PluginResourcesContractType();
        $display_employee = false;

        $condition_emp = ['second_list' => 0];
        if ($contractType->getFromDB($this->fields["plugin_resources_contracttypes_id"])) {
            if ($contractType->fields["use_employee_wizard"] > 0) {
                $display_employee = true;
            }
            if ($contractType->fields["use_second_list_employer"] > 0) {
                $condition_emp = ['second_list' => 1];
            }
        }

        if (Session::haveRight('plugin_resources_employee_core_form', READ) && !$display_employee) {

            echo "<div class=\"form-row\">";

            echo "<div " . $tohide['plugin_resources_employers_id'] . " class=\"bt-feature col-md-3\"";
            if (in_array("plugin_resources_employers_id", $required)) {
                echo " style='color:red;'";
            }
            echo ">";
            echo PluginResourcesEmployer::getTypeName(1);
            echo "</div>";
            echo "<div " . $tohide['plugin_resources_employers_id'] . " class=\"bt-feature col-md-3\">";

            Dropdown::show(PluginResourcesEmployer::class,
                           [
                               'name'      => "plugin_resources_employers_id",
                               'value'     => $options["plugin_resources_employers_id"],
                               'entity'    => $_SESSION['glpiactiveentities'],
                               'condition' => $condition_emp
                           ]);
            echo "</div>";


            echo "<div>";
            echo "</div>";
            echo "<div>";
            echo "</div>";

            echo "</div>";
        }

        echo "<div class=\"form-row\">";

        echo "<div " . $tohide['plugin_resources_departments_id'] . " class=\"bt-feature col-md-3\"";
        if (in_array("plugin_resources_departments_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo PluginResourcesDepartment::getTypeName(1);
        echo "</div>";
        $rand = mt_rand();
        echo "<div " . $tohide['plugin_resources_departments_id'] . " class=\"bt-feature col-md-3\">";
        if ($config->useServiceDepartmentAD()) {
            UserTitle::dropdown(['name' => "plugin_resources_departments_id", 'value' => $this->fields["plugin_resources_departments_id"], 'rand' => $rand]);
        } else {
            Dropdown::show(PluginResourcesDepartment::class,
                           ['name'   => "plugin_resources_departments_id",
                            'value'  => $options["plugin_resources_departments_id"],
                            'entity' => $_SESSION['glpiactiveentities'],
                            'rand'   => $rand]);
        }
        echo "</div>";

        echo "<div " . $tohide['plugin_resources_services_id'] . " class=\"bt-feature col-md-3\"";
        if (in_array("plugin_resources_services_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo PluginResourcesService::getTypeName(1);
        echo "</div>";
        echo "<div " . $tohide['plugin_resources_services_id'] . " class=\"bt-feature col-md-3\" id='show_services'>";
        if ($config->useServiceDepartmentAD()) {
            UserCategory::dropdown(['name' => "plugin_resources_services_id", 'value' => $this->fields["plugin_resources_services_id"], 'rand' => $rand]);
        } else {
            //      Dropdown::show(PluginResourcesService::class,
            //                     ['name'   => "plugin_resources_services_id",
            //                      'value'  => $options["plugin_resources_services_id"],
            //                      'entity' => $_SESSION['glpiactiveentities']]);
            PluginResourcesService::dropdownFromDepart($options["plugin_resources_departments_id"], ['name'   => "plugin_resources_services_id",
                                                                                                     'value'  => $options["plugin_resources_services_id"],
                                                                                                     'entity' => $_SESSION['glpiactiveentities'],
                                                                                                     'rand'   => $rand]);
            $params = ['plugin_resources_services_id' => '__VALUE__',
                       'rand'                         => $rand,
            ];
            Ajax::updateItemOnSelectEvent("dropdown_plugin_resources_services_id$rand", "show_roles", "../ajax/dropdownRole.php", $params);
        }
        echo "</div>";
        $params = ['plugin_resources_departments_id' => '__VALUE__',
                   'rand'                            => $rand,
        ];
        Ajax::updateItemOnSelectEvent("dropdown_plugin_resources_departments_id$rand", "show_services", "../ajax/dropdownService.php", $params);
        echo "<div " . $tohide['plugin_resources_roles_id'] . " class=\"bt-feature col-md-3\"";
        if (in_array("plugin_resources_roles_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo PluginResourcesRole::getTypeName(1);
        echo "</div>";
        echo "<div " . $tohide['plugin_resources_roles_id'] . " class=\"bt-feature col-md-3\" id='show_roles'>";

        PluginResourcesRole::dropdownFromService($options['plugin_resources_services_id'], ['name'   => "plugin_resources_roles_id",
                                                                                            'value'  => $options["plugin_resources_roles_id"],
                                                                                            'entity' => $_SESSION['glpiactiveentities'],
                                                                                            'rand'   => $rand]);

        echo "</div>";


        if ($config->useSecondaryService() && $config->useServiceDepartmentAD()) {
            echo "<div class=\"bt-feature col-md-3\">";
            echo __('Secondaries services', 'resources');

            $services = [];
            $userCat  = new UserCategory();
            $usersCat = $userCat->find();
            foreach ($usersCat as $cat) {
                $services[$cat['id']] = $cat['name'];
            }
            echo "</div>";
            echo "<div class=\"bt-feature col-md-3\" id='show_secondary_services'>";
            Dropdown::showFromArray("secondary_services",
                                    $services,
                                    ['values'   => !empty($this->fields['secondary_services']) ? json_decode($this->fields['secondary_services'], true) : [],
                                     'multiple' => true]);
            echo "</div>";

        }

        echo "<div " . $tohide['plugin_resources_functions_id'] . " class=\"bt-feature col-md-3\"";
        if (in_array("plugin_resources_functions_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo PluginResourcesFunction::getTypeName(1);
        echo "</div>";
        echo "<div " . $tohide['plugin_resources_functions_id'] . " class=\"bt-feature col-md-3\" id='show_roles'>";

        Dropdown::show(PluginResourcesFunction::class,
                       ['name'   => "plugin_resources_functions_id",
                        'value'  => $options["plugin_resources_functions_id"],
                        'entity' => $_SESSION['glpiactiveentities']]);

        echo "</div>";
        echo "<div " . $tohide['plugin_resources_teams_id'] . " class=\"bt-feature col-md-3\"";
        if (in_array("plugin_resources_teams_id", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo PluginResourcesTeam::getTypeName(1);
        echo "</div>";
        echo "<div " . $tohide['plugin_resources_teams_id'] . " class=\"bt-feature col-md-3\" id='show_roles'>";

        Dropdown::show(PluginResourcesTeam::class,
                       ['name'   => "plugin_resources_teams_id",
                        'value'  => $options["plugin_resources_teams_id"],
                        'entity' => $_SESSION['glpiactiveentities']]);

        echo "</div>";
        echo "</div>";


        echo "<div class=\"form-row\">";

        echo "<div " . $tohide['date_begin'] . " class=\"bt-feature col-md-3\"";
        if (in_array("date_begin", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Arrival date', 'resources');
        echo "</div>";
        echo "<div " . $tohide['date_begin'] . " class=\"bt-feature col-md-3\">";
        Html::showDateField("date_begin", ['value' => $options["date_begin"]]);
        echo "</div>";

        echo "<div " . $tohide['date_end'] . " class=\"bt-feature col-md-3\"";
        if (in_array("date_end", $required)) {
            echo " style='color:red;'";
        }
        echo ">";
        echo __('Departure date', 'resources') . "&nbsp;";
        if (!in_array("date_end", $required)) {
            Html::showToolTip(nl2br(__('Empty for non defined', 'resources')));
        }
        echo "</div>";
        echo "<div " . $tohide['date_end'] . " class=\"bt-feature col-md-3\">";
        Html::showDateField("date_end", ['value' => $options["date_end"]]);
        echo "</div>";

        echo "</div>";

        echo "</div>";
        echo "</div>";

        $config = new PluginResourcesConfig();

        echo "<div class=\"form-row plugin_resources_wizard_margin\">";
        echo "<div class=\"bt-feature col-md-12  \" >";

        if ($config->useSecurity()) {

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-3\">";
            echo __('Sensitized to security', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-3\">";
            $checked = '';
            if (isset($options['sensitize_security']) && $options['sensitize_security']) {
                $checked = "checked = true";
            }
            echo "<input type='checkbox' name='sensitize_security' $checked value='1'>";
            echo "</div>";

            echo "<div class=\"bt-feature col-md-3\">";
            echo __('Reading the security charter', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-3\">";
            $checked = '';
            if (isset($options['read_chart']) && $options['read_chart']) {
                $checked = "checked = true";
            }
            echo "<input type='checkbox' name='read_chart' $checked value='1'>";
            echo "</div>";
            echo "</div>";

            echo "</div>";
            echo "</div>";
        }

        echo "<div class=\"form-row plugin_resources_wizard_margin\">";

        echo "<div class=\"bt-feature col-md-12  \" >";

        echo "<div " . $tohide['comment'] . " class=\"form-row\">";

        echo "<div class=\"bt-feature col-md-12\">";
        echo __('Description');
        echo "</div>";
        echo "</div>";

        echo "<div " . $tohide['comment'] . " class=\"form-row\">";
        echo "<div class=\"bt-feature col-md-12\">";
        echo Html::textarea([
                                'name'    => 'comment',
                                'value'   => $options["comment"],
                                'cols'    => '95',
                                'rows'    => '6',
                                'display' => false,
                            ]);
        echo "</div>";
        echo "</div>";


        echo "<div class=\"form-row\">";
        echo "<div class=\"bt-feature col-md-12\">";
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
            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-12\" style='color:red;'>";
            echo __('The fields in red must be completed', 'resources');
            echo "</div>";
            echo "</div>";
        }

        echo "</div>";
        echo "</div>";

        $contract = $this->fields["plugin_resources_contracttypes_id"];
        if ($empty == 1) {
            $contract = $input['plugin_resources_contracttypes_id'];
        }
        echo Html::hidden('plugin_resources_contracttypes_id', ['value' => $contract]);
        echo Html::hidden('plugin_resources_resourcestates_id', ['value' => $this->fields["plugin_resources_resourcestates_id"]]);
        echo Html::hidden('withtemplate', ['value' => $options['withtemplate']]);
        echo Html::hidden('date_declaration', ['value' => date('Y-m-d')]);
        echo Html::hidden('users_id_recipient', ['value' => Session::getLoginUserID()]);

        echo Html::hidden('plugin_resources_leavingreasons_id', ['value' => 0]);

        if ($this->canCreate() && (empty($ID) || $options['withtemplate'] == 2)) {
            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-12 \">";
            echo "<div class='preview'>";
            echo Html::submit(_sx('button', '< Previous', 'resources'), ['name' => 'undo_first_step', 'class' => 'btn btn-primary']);
            echo "</div>";
            echo "<div class='next'>";
            echo Html::submit(_sx('button', 'Next >', 'resources'), ['name' => 'second_step', 'class' => 'btn btn-success']);
            echo "</div>";
            echo "</div>";
            echo "</div>";
        } else if ($this->canCreate() && !empty($ID) && $options["new"] != 1) {

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-12 \">";
            echo "<div class='preview'>";
            echo Html::submit(_sx('button', '< Previous', 'resources'), ['name' => 'undo_first_step', 'class' => 'btn btn-primary']);
            echo "</div>";
            echo "<div class='next'>";
            echo Html::submit(_sx('button', 'Next >', 'resources'), ['name' => 'second_step_update', 'class' => 'btn btn-success']);
            echo Html::hidden('plugin_resources_resources_id', ['value' => $this->fields["id"]]);
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }

        Html::closeForm();
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }

    /**
     * @param       $ID
     * @param array $options
     *
     * @return bool
     */
    function wizardFiveForm($ID, $options = []) {
        global $CFG_GLPI;

        if ($ID > 0) {
            $this->check($ID, READ);
        }
        echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_main.css");
        echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_ticket.css");

        echo "<h3><div class='alert alert-secondary' role='alert' >";
        echo "<i class='" . self::getIcon() . "'></i>&nbsp;";
        echo __('Resources management', 'resources');
        echo "</div></h3>";

        echo "<div id ='content'>";
        echo "<div class='bt-container resources_wizard_resp'> ";
        echo "<div class='bt-block bt-features' > ";

        echo "<form action='" . $options['target'] . "' enctype='multipart/form-data' method='post'>";

        echo "<div class=\"form-row plugin_resources_wizard_margin \">";
        echo "<div class=\"bt-feature col-md-12 \" >";
        echo "<h4 class=\"bt-title-divider\">";
        echo "<img class='resources_wizard_resp_img' src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/newresource.png' alt='newresource'/>&nbsp;";
        echo __('Add the photo of the resource', 'resources');
        echo "</h4></div></div>";

        if (!$this->canView()) {
            return false;
        }

        echo "<div class=\"form-row\">";
        echo "<div class=\"bt-feature col-md-12 \">";

        if (isset($this->fields["picture"])) {
            $path = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $this->fields["picture"];
            if (file_exists($path)) {
                echo "<object data='" . PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $this->fields["picture"] . "'>
             <param name='src' value='" . PLUGIN_RESOURCES_WEBDIR .
                     "/front/picture.send.php?file=" . $this->fields["picture"] . "'>
            </object> ";
            } else {
                echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png'>";
            }
        } else {
            echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png'>";
        }
        echo "</div></div>";

        echo "<div class=\"form-row\">";
        echo "<div class=\"bt-feature col-md-12 \">";

        echo __('Photo format : JPG', 'resources') . "<br>";
        //      echo Html::file(['name' => 'picture', 'display' => false, 'onlyimages' => true]); //'value' => $this->fields["picture"],
        echo "<input class='form-control' type='file' name='picture'>";
        echo "&nbsp;";
        echo "(" . Document::getMaxUploadSize() . ")&nbsp;";

        echo "</div></div>";

        echo "<div class=\"form-row\">";
        echo "<div class=\"bt-feature col-md-12 \">";
        echo Html::submit(_sx('button', 'Add'), ['name' => 'upload_five_step', 'class' => 'btn btn-success']);
        echo Html::hidden('plugin_resources_resources_id', ['value' => $this->fields["id"]]);
        echo "</div></div>";

        if ($this->canCreate() && (!empty($ID))) {
            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-12 \">";
            echo "<div class='preview'>";
            echo Html::submit(_sx('button', '< Previous', 'resources'), ['name' => 'undo_five_step', 'class' => 'btn btn-primary']);
            echo "</div>";
            echo "<div class='next'>";
            echo Html::submit(_sx('button', 'Next >', 'resources'), ['name' => 'five_step', 'class' => 'btn btn-success']);
            echo Html::hidden('plugin_resources_resources_id', ['value' => $this->fields["id"]]);
            echo "</div>";
            echo "</div></div>";
        }

        Html::closeForm();

        echo "</div>";
        echo "</div>";
        echo "</div>";
    }

    function widgetSevenForm($ID, $options = []) {

        if ($ID > 0) {
            $this->check($ID, READ);
        }

        $self = new self();
        $self->getFromDB($ID);

        $entities = "";
        $entity   = $_SESSION["glpiactive_entity"];

        $doc_item   = new Document_Item();
        $used_found = $doc_item->find([
                                          'items_id' => $self->getID(),
                                          'itemtype' => $self->getType()
                                      ]);
        $used       = array_keys($used_found);
        $used       = array_combine($used, $used);

        if ($self->isEntityAssign()) {
            /// Case of personal items : entity = -1 : create on active entity (Reminder case))
            if ($self->getEntityID() >= 0) {
                $entity = $self->getEntityID();
            }

            if ($self->isRecursive()) {
                $entities = getSonsOf('glpi_entities', $entity);
            } else {
                $entities = $entity;
            }
        }

        echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_main.css");
        echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_ticket.css");

        echo "<h3><div class='alert alert-secondary' role='alert' >";
        echo "<i class='" . self::getIcon() . "'></i>&nbsp;";
        echo __('Resources management', 'resources');
        echo "</div></h3>";

        echo "<div id ='content'>";
        echo "<div class='bt-container resources_wizard_resp'> ";
        echo "<div class='bt-block bt-features' > ";

        echo "<form action='" . $options['target'] . "' enctype='multipart/form-data' method='post'>";

        echo "<div class=\"form-row plugin_resources_wizard_margin \">";
        echo "<div class=\"bt-feature col-md-12 \" >";
        echo "<h4 class=\"bt-title-divider\">";
        echo __('Add documents to the resource', 'resources');
        echo "</h4></div></div>";

        if (!$this->canView()) {
            return false;
        }

        echo "<div class=\"form-row\">";
        echo "<div class=\"bt-feature col-md-12 \">";

        $this->displayResourceDocumentForm($ID);

        echo "</div></div>";

        echo "<div class=\"form-row\">";
        echo "<div class=\"bt-feature col-md-12\">";

        Document_item::showListForItem($self, 99); // With template 99 to disable massive action

        echo "</div></div>";

        echo Html::hidden('plugin_resources_resources_id', ['value' => $this->fields["id"]]);

        if ($this->canCreate() && (!empty($ID))) {
            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-11\">";
            echo "<div class='preview'>";
            echo Html::submit(_sx('button', '< Previous', 'resources'), ['name' => 'undo_seven_step', 'class' => 'btn btn-primary']);
            echo "</div>";
            echo "<div class='next'>";
            echo Html::submit(_sx('button', 'Next >', 'resources'), ['name' => 'seven_step', 'class' => 'btn btn-success']);
            echo Html::hidden('plugin_resources_resources_id', ['value' => $this->fields["id"]]);
            echo "</div>";
            echo "</div></div>";
        }

        Html::closeForm();

        echo "</div>";
        echo "</div>";
        echo "</div>";
    }

    public function displayResourceDocumentForm($ID) {

        $item = new self();
        $item->getFromDB($ID);

        $rand = mt_rand();
        ob_start();
        Document_item::showAddFormForItem($item, 0, ['rand' => $rand]);
        $extraction = ob_get_contents();
        ob_end_clean();

        // Remove form brackets
        // First form start
        $beginStartFormPos = strpos($extraction, '<form');
        $endStartFormPos   = strpos($extraction, ">", $beginStartFormPos);

        $extraction2 = substr($extraction, 0, $beginStartFormPos);
        $extraction2 .= substr($extraction, $endStartFormPos + 1, strlen($extraction));

        // Second form start
        $beginStartFormPos = strpos($extraction2, '<form');
        $endStartFormPos   = strpos($extraction2, ">", $beginStartFormPos);

        $extraction3 = substr($extraction2, 0, $beginStartFormPos);
        $extraction3 .= substr($extraction2, $endStartFormPos + 1, strlen($extraction2));

        $extraction4 = str_replace("</form>", "", $extraction3);
        //      $finalExtraction = str_replace("name='add'", "name='upload_seven_step'", $extraction3);

        // Replace name of input type submit

        $stringToFind = "name='add'";

        //      $existingDocPos = strrpos($extraction4, $stringToFind);
        //      $extraction4    = substr_replace($extraction4, "name='add_doc_seven_step'", $existingDocPos, strlen($stringToFind));
        //
        $addNewFilePos = strrpos($extraction4, $stringToFind);
        $extraction4   = substr_replace($extraction4, "name='upload_seven_step'", $addNewFilePos, strlen($stringToFind));

        echo $extraction4;
    }

    /**
     * @param     $ID
     * @param int $link
     *
     * @return array|string
     */
    static function getResourceName($ID, $link = 0) {
        global $DB, $CFG_GLPI;

        $user = "";
        if ($link == 2) {
            $user = ["name"    => "",
                     "link"    => "",
                     "comment" => ""];
        }

        if ($ID) {
            $query  = "SELECT `glpi_plugin_resources_resources`.*,
                          `glpi_users`.`registration_number`,
                          `glpi_users`.`name` AS username
                   FROM `glpi_plugin_resources_resources`
                      LEFT JOIN `glpi_plugin_resources_resources_items`
                        ON (`glpi_plugin_resources_resources_items`.`plugin_resources_resources_id`
                            = `glpi_plugin_resources_resources`.`id`)
                      LEFT JOIN `glpi_users`
                        ON (`glpi_users`.`id` = `glpi_plugin_resources_resources_items`.`items_id`
                            AND `glpi_plugin_resources_resources_items`.`itemtype` = 'User')
                   WHERE `glpi_plugin_resources_resources`.`id` = '$ID' 
                   GROUP BY `glpi_plugin_resources_resources`.`id`";
            $result = $DB->query($query);

            if ($link == 2) {
                $user = ["name"    => "",
                         "comment" => "",
                         "link"    => ""];
            }

            $dbu = new DbUtils();

            if ($DB->numrows($result) == 1) {
                $data     = $DB->fetchAssoc($result);
                $username = $dbu->formatUserName($data["id"], $data["username"], $data["name"],
                                                 $data["firstname"], $link);

                if ($link == 2) {
                    $user["name"]    = $username;
                    $user["link"]    = PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php?id=" . $ID;
                    $user["comment"] = "";

                    if (isset($data["picture"]) && !empty($data["picture"])) {
                        $path = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $data["picture"];
                        if (file_exists($path)) {
                            $user["comment"] .= "<object data='" . PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $data["picture"] . "'>
                      <param name='src' value='" . PLUGIN_RESOURCES_WEBDIR .
                                                "/front/picture.send.php?file=" . $data["picture"] . "'>
                     </object><br> ";

                        } else {
                            $user["comment"] .= "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png'><br>";
                        }
                    } else {
                        $user["comment"] .= "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png'><br>";
                    }

                    $user["comment"] .= __('Name') . "&nbsp;: " . $username . "<br>";

                    if ($data["plugin_resources_ranks_id"] > 0) {
                        $user["comment"] .= PluginResourcesRank::getTypeName(1) . "&nbsp;: " .
                                            Dropdown::getDropdownName("glpi_plugin_resources_ranks",
                                                                      $data["plugin_resources_ranks_id"]) . "<br>";
                    }

                    if ($data["locations_id"] > 0) {
                        $user["comment"] .= __('Location') . "&nbsp;: " .
                                            Dropdown::getDropdownName("glpi_locations",
                                                                      $data["locations_id"]) . "<br>";
                    }

                    if ($data["registration_number"] > 0) {
                        $user["comment"] .= _x('user', 'Administrative number') . "&nbsp;: " .
                                            $data["registration_number"] . "<br>";
                    }

                } else {
                    $user = $username;
                }
            }
        }
        return $user;
    }

    /**
     * Permet l'affichage dynamique des ressources avec info bulle
     *
     * @static
     *
     * @param array ($myname,$value,$entity_restrict)
     */

    static function dropdown($options = []) {
        global $CFG_GLPI;

        $params['value']             = 0;
        $params['valuename']         = Dropdown::EMPTY_VALUE;
        $params['customcomments']    = true;
        $params['comments']          = false;
        $params['entity']            = $_SESSION['glpiactive_entity'];
        $params['name']              = 'plugin_resources_resources_id';
        $params['addUnlinkedUsers']  = false;
        $params['rand']              = mt_rand();
        $params['display']           = false;
        $params['showHabilitations'] = false;
        if (!empty($options)) {
            foreach ($options as $key => $val) {
                $params[$key] = $val;
            }
        }

        $params['value2'] = $params['value'];
        $user             = self::getResourceName($params['value'], 2);
        if (!empty($params['value'])) {
            //         $params['valuename'] = Dropdown::getDropdownName(self::getTable(), $params['value']);
            $params['valuename'] = $user['name'];
        }

        $field_id = Html::cleanId("dropdown_" . $params['name'] . $params['rand']);

        $item   = new self();
        $output = "<span class='no-wrap'>";
        $output .= Html::jsAjaxDropdown($params['name'], $field_id,
                                        PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownResources.php",
                                        $params);
        if (class_exists('PluginPositionsPosition')) {
            $output .= PluginPositionsPosition::showGeolocLink(PluginResourcesResource::class, $params['value']);
        }
        // Display comment
        if ($params['customcomments']) {
            $table = $item->getTable();
            $user  = self::getResourceName($params['value'], 2);

            $comment_id = Html::cleanId("comment_" . $params['name'] . $params['rand']);
            $link_id    = Html::cleanId("comment_link_" . $params['name'] . $params['rand']);

            if (empty($user["link"])) {
                $user["link"] = PLUGIN_RESOURCES_WEBDIR . "/front/resource.php";
            }

            $output .= "&nbsp;" . Html::showToolTip($user["comment"],
                                                    ['contentid'  => $comment_id,
                                                     'link'       => $user["link"],
                                                     'linkid'     => $link_id,
                                                     'linktarget' => '_blank',
                                                     'display'    => false]);

            $paramscomment = ['value' => '__VALUE__',
                              'table' => $table];
            if ($item->canView()) {
                $paramscomment['withlink'] = $link_id;
            }

            $output .= Ajax::updateItemOnSelectEvent($field_id, $comment_id,
                                                     PLUGIN_RESOURCES_WEBDIR . "/ajax/comments.php",
                                                     $paramscomment, false);

        }
        $config = new PluginResourcesConfig();
        if ($params['showHabilitations'] && $config->getField('display_habilitations_txt')) {
            $output .= "<p id='habilitationsTxt'></p>";
            $output .= Ajax::updateItemOnSelectEvent($field_id, 'habilitationsTxt',
                PLUGIN_RESOURCES_WEBDIR . "/ajax/showHabilitations.php",
                ['value' => '__VALUE__', 'metademands_id'=> $_GET['metademands_id']??0], false);
        }
        $output .= Ajax::commonDropdownUpdateItem($params, false);
        $output .= "</span>";
        if ($params['display']) {
            echo $output;
            return $params['rand'];
        }
        return $output;
    }

    static function fastResourceAddForm() {

        echo "<table class='tab_cadre'>";
        // ContractType
        echo "<tr>";
        echo "<td>" . __("Contract type") . "</td>";
        echo "<td>";

        PluginResourcesDepartment::getTypeName(1) . "</td><td>";
        Dropdown::show(PluginResourcesContractType::class,
                       ['name' => "plugin_resources_contracttypes_id"]);

        echo "</td>";
        echo "</tr>";
        echo "<tr>";

        // Recipient
        echo "<td>";
        echo __('Resource manager', 'resources') . "</td>";
        echo "<td width='70%'>";
        User::dropdown(['name'        => "users_id_recipient",
                        'entity'      => $_SESSION['glpiactive_entity'],
                        'entity_sons' => true,
                        'right'       => 'all']);
        echo "<td>";
        echo "</tr>";

        // Department
        echo "<tr>";
        echo "<td>";
        echo PluginResourcesDepartment::getTypeName(1) . "</td><td>";
        Dropdown::show(PluginResourcesDepartment::class,
                       ['name' => "plugin_resources_departments_id"]);

        echo '<input type="hidden" name="itemtype" value="User">';
        echo "</td>";
        echo "</tr>";
        echo "</table>";
    }

    /**
     * @param $userId
     * @param $options
     *
     * @return array
     */
    static function fastResourceAdd($userId, $options) {
        global $DB;

        $params['plugin_resources_contracttypes_id'] = 0;
        $params['plugin_resources_departments_id']   = 0;
        $params['users_id_recipient']                = 0;
        $params['itemtype']                          = 'User';
        $params['entities_id']                       = $_SESSION['glpiactive_entity'];

        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }

        $message        = null;
        $idResource     = 0;
        $error['right'] = 0;
        $error['error'] = 0;

        $user = new User();
        if ($user->getFromDB($userId)) {
            $resource = new PluginResourcesResource();
            $resource->getFromDBByCrit(['name'       => $user->fields['realname'],
                                        'firstname'  => $user->fields['firstname'],
                                        'is_deleted' => 0]);

            if (!isset($resource->fields['id']) || $resource->fields['id'] <= 0) {
                $resource->fields['entities_id'] = $params['entities_id'];
                $resource->fields['name']        = isset($user->fields['realname']) ? $user->fields['realname'] : '';
                $resource->fields['firstname']   = isset($user->fields['firstname']) ? $user->fields['firstname'] : '';

                $resource->fields['plugin_resources_contracttypes_id'] = $params['plugin_resources_contracttypes_id'];
                $resource->fields['users_id_recipient']                = Session::getLoginUserID();
                $resource->fields['users_id']                          = $params["users_id_recipient"];
                $resource->fields['users_id_sales']                    = 0;

                $resource->fields['date_declaration'] = date('Y-m-d');
                $resource->fields['date_begin']       = null;
                $resource->fields['date_end']         = null;

                $resource->fields['plugin_resources_departments_id'] = $params['plugin_resources_departments_id'];
                $resource->fields['locations_id']                    = 0;
                $resource->fields['is_leaving']                      = 0;
                $resource->fields['users_id_recipient_leaving']      = 0;
                $resource->fields['comment']                         = '';
                $resource->fields['notepad']                         = '';
                $resource->fields['is_template']                     = 0;
                $resource->fields['template_name']                   = '';
                $resource->fields['is_deleted']                      = 0;
                $resource->fields['is_helpdesk_visible']             = 1;
                $resource->fields['date_mod']                        = date('Y-m-d');

                $resource->fields['plugin_resources_resourcestates_id']       = 0;
                $resource->fields['picture']                                  = null;
                $resource->fields['is_recursive']                             = 0;
                $resource->fields['quota']                                    = 1;
                $resource->fields['plugin_resources_resourcesituations_id']   = 0;
                $resource->fields['plugin_resources_contractnatures_id']      = 0;
                $resource->fields['plugin_resources_ranks_id']                = 0;
                $resource->fields['plugin_resources_resourcespecialities_id'] = 0;
                $resource->fields['plugin_resources_leavingreasons_id']       = 0;
                $resource->fields['sensitize_security']                       = 0;
                $resource->fields['read_chart']                               = 0;

                $resourceItem = new PluginResourcesResource_Item();
                if ($resourceItem->can(-1, UPDATE, $resource)) {
                    $idResource = $resource->add($resource->fields);
                    if ($idResource) {
                        $resource->fields['id'] = $idResource;
                        if (isset($resourceItem->fields['id'])) {
                            unset($resourceItem->fields['id']);
                        }

                        $resourceItem->fields['plugin_resources_resources_id'] = $idResource;
                        $resourceItem->fields['items_id']                      = $user->fields['id'];
                        $resourceItem->fields['itemtype']                      = $params['itemtype'];
                        $resourceItem->fields['comment']                       = null;

                        $idResourceItem = $resourceItem->add($resourceItem->fields);
                        if ($idResourceItem) {
                            // Cochage des checklist en mode "JOB DONE"
                            $pChecklist = new PluginResourcesChecklist();

                            $query = "UPDATE " . $pChecklist->getTable() . " SET `is_checked`=1 WHERE `plugin_resources_resources_id`=" . $idResource;
                            if ($DB->query($query)) {
                                $message = $user->fields['realname'] . " " . $user->fields['firstname'] . "<br/>";
                            }
                        } else {
                            $error['error'] = 1;
                            $message        = $user->fields['realname'] . " " . $user->fields['firstname'] . "<br/>";
                            $resource->delete($resource->fields, 1);
                        }
                    } else {
                        $error['error'] = 1;
                    }
                } else {
                    $error['right'] = 1;
                }
            } else {
                $error['error'] = 1;
                $message        = $user->fields['realname'] . " " . $user->fields['firstname'] . "<br/>";
            }
        } else {
            $error['error'] = 1;
        }

        return [$idResource, $error, $message];
    }

    /**
     * @param bool   $count
     * @param int    $entity_restrict
     * @param int    $value
     * @param array  $used
     * @param string $search
     * @param bool   $showOnlyLinkedResources
     *
     * @return bool|\mysqli_result
     */
    static function getSqlSearchResult($count = true, $entity_restrict = -1, $value = 0, $used = [], $search = '', $showOnlyLinkedResources = false) {
        global $DB, $CFG_GLPI;

        // No entity define : use active ones
        if ($entity_restrict < 0) {
            $entity_restrict = $_SESSION["glpiactiveentities"];
        }

        $dbu = new DbUtils();

        $where = " `glpi_plugin_resources_resources`.`is_deleted` = 0
                  AND `glpi_plugin_resources_resources`.`is_leaving` = 0
                  AND `glpi_plugin_resources_resources`.`is_template` = 0 ";

        $where .= $dbu->getEntitiesRestrictRequest('AND', 'glpi_plugin_resources_resources', '', $entity_restrict, true);
        if ((is_numeric($value) && $value)
            || count($used)
        ) {

            $where .= " AND `glpi_plugin_resources_resources`.`id` NOT IN (0";
            if (is_numeric($value)) {
                $first = false;
                $where .= $value;
            } else {
                $first = true;
            }
            if (is_array($used)) {
                foreach ($used as $val) {
                    if ($first) {
                        $first = false;
                    } else {
                        $where .= ",";
                    }
                    $where .= $val;
                }
            }
            $where .= ")";
        }

        if ($count) {
            $query = "SELECT COUNT(DISTINCT `glpi_plugin_resources_resources`.`id` ) AS cpt
                   FROM `glpi_plugin_resources_resources` ";
        } else {
            $query = "SELECT DISTINCT `glpi_plugin_resources_resources`.*,
                          `glpi_users`.`registration_number`,
                          `glpi_users`.`name` AS username,
                          `glpi_users`.`id` AS userid
                   FROM `glpi_plugin_resources_resources`";
            if ($showOnlyLinkedResources) {
                $query .= "INNER JOIN `glpi_plugin_resources_resources_items`
                        ON (`glpi_plugin_resources_resources_items`.`plugin_resources_resources_id`
                            = `glpi_plugin_resources_resources`.`id`  
                            AND `glpi_plugin_resources_resources_items`.`itemtype` = 'User')
                      INNER JOIN `glpi_users`
                        ON (`glpi_users`.`id` = `glpi_plugin_resources_resources_items`.`items_id`
                              AND `glpi_plugin_resources_resources_items`.`itemtype` = 'User') ";
            } else {
                $query .= "LEFT JOIN `glpi_plugin_resources_resources_items`
                        ON (`glpi_plugin_resources_resources_items`.`plugin_resources_resources_id`
                            = `glpi_plugin_resources_resources`.`id` 
                             AND `glpi_plugin_resources_resources_items`.`itemtype` = 'User')
                      LEFT JOIN `glpi_users`
                        ON (`glpi_users`.`id` = `glpi_plugin_resources_resources_items`.`items_id`
                              AND `glpi_plugin_resources_resources_items`.`itemtype` = 'User') ";
            }
        }

        if (!Session::haveRight("plugin_resources_all", READ)) {
            $who   = Session::getLoginUserID();
            $where .= " AND (`glpi_plugin_resources_resources`.`users_id_recipient` = '$who' OR `glpi_plugin_resources_resources`.`users_id` = '$who') ";
        }

        if ($count) {
            $query .= " WHERE $where ";
        } else {
            $contracttypeprofile = new PluginResourcesContracttypeprofile();
            if ($contracttypeprofile->getFromDBByCrit(["profiles_id" => $_SESSION['glpiactiveprofile']['id']])) {
                $contracttypeprofiles = json_decode($contracttypeprofile->fields['plugin_resources_contracttypes_id']);
                if ($contracttypeprofiles !== false && is_array($contracttypeprofiles) && !empty($contracttypeprofiles)) {
                    $where .= "AND (`glpi_plugin_resources_resources`.`plugin_resources_contracttypes_id` IN (" . implode(', ', $contracttypeprofiles) . "))";
                }
            }
            if (strlen($search) > 0 && $search != $CFG_GLPI["ajax_wildcard"]) {

                $where .= " AND (`glpi_plugin_resources_resources`.`name` " . Search::makeTextSearch($search) . "
                             OR `glpi_plugin_resources_resources`.`firstname` " . Search::makeTextSearch($search) . "
                             OR `glpi_users`.`registration_number` " . Search::makeTextSearch($search) . "
                             OR `glpi_users`.`name` " . Search::makeTextSearch($search) . "
                             OR CONCAT(`glpi_plugin_resources_resources`.`name`,' ',`glpi_plugin_resources_resources`.`firstname`,' ',`glpi_users`.`registration_number`,' ',`glpi_users`.`name`) " .
                          Search::makeTextSearch($search) . ")";
            }
            $query .= " WHERE $where ";

            if ($_SESSION["glpinames_format"] == User::FIRSTNAME_BEFORE) {
                $query .= " ORDER BY `glpi_plugin_resources_resources`.`firstname`,
                               `glpi_plugin_resources_resources`.`name` ";
            } else {
                $query .= " ORDER BY `glpi_plugin_resources_resources`.`firstname`,
                               `glpi_plugin_resources_resources`.`name` ";
            }

            if ($search != $CFG_GLPI["ajax_wildcard"]) {
                $query .= " LIMIT 0," . $CFG_GLPI["dropdown_max"];
            }
        }
        return $DB->query($query);
    }


    /**
     * @param     $target
     * @param int $add
     */
    function listOfTemplates($target, $add = 0) {
        $dbu = new DbUtils();

        $restrict = ["is_template" => 1] +
                    $dbu->getEntitiesRestrictCriteria($this->getTable(), '', '', $this->maybeRecursive()) +
                    ["ORDER" => "name"];

        $templates = $dbu->getAllDataFromTable($this->getTable(), $restrict);

        if (Session::isMultiEntitiesMode()) {
            $colsup = 1;
        } else {
            $colsup = 0;
        }

        echo "<div align='center'><table class='tab_cadre_fixe'>";
        if ($add) {
            echo "<tr><th colspan='" . (2 + $colsup) . "'>" . __('Choose a template') . " - " . self::getTypeName(2) . "</th>";
        } else {
            echo "<tr><th colspan='" . (2 + $colsup) . "'>" . __('Templates') . " - " . self::getTypeName(2) . "</th>";
        }

        echo "</tr>";
        if ($add) {

            echo "<tr>";
            echo "<td colspan='" . (2 + $colsup) . "' class='center tab_bg_1'>";
            echo "<a href=\"$target?id=-1&amp;withtemplate=2\">&nbsp;&nbsp;&nbsp;" . __('Blank Template') . "&nbsp;&nbsp;&nbsp;</a></td>";
            echo "</tr>";
        }

        foreach ($templates as $template) {

            $templname = $template["template_name"];
            if ($_SESSION["glpiis_ids_visible"] || empty($template["template_name"])) {
                $templname .= "(" . $template["id"] . ")";
            }

            echo "<tr>";
            echo "<td class='center tab_bg_1'>";
            if (!$add) {
                echo "<a href=\"$target?id=" . $template["id"] . "&amp;withtemplate=1\">&nbsp;&nbsp;&nbsp;$templname&nbsp;&nbsp;&nbsp;</a></td>";

                if (Session::isMultiEntitiesMode()) {
                    echo "<td class='center tab_bg_2'>";
                    echo Dropdown::getDropdownName("glpi_entities", $template['entities_id']);
                    echo "</td>";
                }
                echo "<td class='center tab_bg_2'>";
                Html::showSimpleForm($target,
                                     'purge',
                                     _x('button', 'Delete permanently'),
                                     ['id' => $template["id"], 'withtemplate' => 1]);
                echo "</td>";

            } else {
                echo "<a href=\"$target?id=" . $template["id"] . "&amp;withtemplate=2\">&nbsp;&nbsp;&nbsp;$templname&nbsp;&nbsp;&nbsp;</a></td>";

                if (Session::isMultiEntitiesMode()) {
                    echo "<td class='center tab_bg_2'>";
                    echo Dropdown::getDropdownName("glpi_entities", $template['entities_id']);
                    echo "</td>";
                }
            }
            echo "</tr>";
        }
        if (!$add) {
            echo "<tr>";
            echo "<td colspan='" . (2 + $colsup) . "' class='tab_bg_2 center'>";
            echo "<b><a href=\"$target?withtemplate=1\">" . __('Add a template...') . "</a></b>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table></div>";
    }

    //Show form from heelpdesk to remove a resource
    function showResourcesToRemove() {
        global $CFG_GLPI;

        $dbu = new DbUtils();

        if ($dbu->countElementsInTable($this->getTable()) > 0) {
            echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_main.css");
            echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_ticket.css");

            echo "<h3><div class='alert alert-secondary' role='alert' >";
            echo "<i class='" . self::getIcon() . "'></i>&nbsp;";
            echo __('Resources management', 'resources');
            echo "</div></h3>";

            echo "<div id ='content'>";
            echo "<div class='bt-container resources_wizard_resp'>";
            echo "<div class='bt-block bt-features' >";

            echo "<form method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR . "/front/resource.remove.php\">";

            echo "<div class=\"form-row plugin_resources_wizard_margin \">";
            echo "<div class=\"bt-feature col-md-12 \" >";
            echo "<h4 class=\"bt-title-divider\">";
            echo "<img class='resources_wizard_resp_img' src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/removeresource.png' alt='removeresource'/>&nbsp;";
            echo __('Declare a departure', 'resources');
            echo "</h4></div></div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo self::getTypeName(1);
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $available_contracttype = false;
            $contracttypeprofile    = new PluginResourcesContracttypeprofile();
            if ($contracttypeprofile->getFromDBByCrit(['profiles_id' => $_SESSION['glpiactiveprofile']['id']])) {
                $available_contracttype = json_decode($contracttypeprofile->fields['plugin_resources_contracttypes_id']);
            }

            $cond = [];

            if ($available_contracttype !== false && is_array($available_contracttype)) {
                $available_contracttype[] = 0;
                $cond                     = ['plugin_resources_contracttypes_id' => $available_contracttype];
            }

            self::dropdown(['name'      => 'plugin_resources_resources_id',
                            'display'   => true,
                            'entity'    => $_SESSION['glpiactiveentities'],
                            'condition' => $cond,
                            'on_change' => "plugin_resources_pdf_resource(\"" . PLUGIN_RESOURCES_WEBDIR . "\", this.value);"]);

            echo "</div>";
            echo "</div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('Departure date', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            Html::showDateField("date_end", ['value' => $_POST["date_end"]]);
            echo "</div>";
            echo "</div>";


            if (countDistinctElementsInTable(PluginResourcesLeavingReason::getTable(), 'id')) {
                echo "<div class=\"form-row\">";
                echo "<div class=\"bt-feature col-md-4 \">";
                echo PluginResourcesLeavingReason::getTypeName(1);
                echo "</div>";
                echo "<div class=\"bt-feature col-md-4 \">";
                Dropdown::show(PluginResourcesLeavingReason::class,
                               ['entity' => $_SESSION['glpiactiveentities']]);
                echo "</div>";
                echo "</div>";
            }


            echo "<div class='center' id='resource_pdf' colspan='2'></div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-12 \">";
            echo "<div class='next'>";
            echo Html::submit(__s('Declare a departure', 'resources'), ['name' => 'removeresources', 'class' => 'btn btn-success']);
            echo "</div>";
            echo "</div></div>";

            Html::closeForm();
            echo "</div>";
            echo "</div>";
            echo "</div>";

        } else {
            echo "<div align='center'>" . __('No item found') . "</div>";
        }
    }

    /**
     * Show form from helpdesk to change a resource
     *
     * @param array $options
     */
    function showResourcesToChange($options = []) {
        global $CFG_GLPI;

        $dbu = new DbUtils();

        if ($dbu->countElementsInTable($this->getTable()) > 0) {
            echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_main.css");
            echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_ticket.css");

            echo "<h3><div class='alert alert-secondary' role='alert' >";
            echo "<i class='" . self::getIcon() . "'></i>&nbsp;";
            echo __('Resources management', 'resources');
            echo "</div></h3>";

            echo "<div id ='content'>";
            echo "<div class='bt-container resources_wizard_resp'> ";
            echo "<div class='bt-block bt-features' > ";

            echo "<form method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR . "/front/resource.change.php\">";

            echo "<div class=\"form-row plugin_resources_wizard_margin \">";
            echo "<div class=\"bt-feature col-md-12 \" >";
            echo "<h4 class=\"bt-title-divider\">";
            echo "<img class='resources_wizard_resp_img' src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/recap.png' alt='changeresource'/>&nbsp;";
            echo __('Declare a change', 'resources');
            echo "</h4></div></div>";

            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo self::getTypeName(1);
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            self::dropdown(['name'      => 'plugin_resources_resources_id',
                            'display'   => true,
                            'entity'    => $_SESSION['glpiactiveentities'],
                            'on_change' => "plugin_resources_change_resource(\"" . PLUGIN_RESOURCES_WEBDIR . "\", this.value);"]);

            echo "</div>";
            echo "</div>";

            //choose actions
            echo "<div class=\"form-row\">";
            echo "<div class=\"bt-feature col-md-4 \">";
            echo __('Actions to be taken', 'resources');
            echo "</div>";
            echo "<div class=\"bt-feature col-md-4 \">";
            $actions       = PluginResourcesResource_Change::getAllActions();
            $actionProfile = new PluginResourcesActionprofile();
            if ($actionProfile->getFromDBByCrit(['profiles_id' => $_SESSION['glpiactiveprofile']['id']])) {
                $available_action = json_decode($actionProfile->fields['actions_id']);
            }
            if (isset($available_action) && !empty($available_action)) {
                foreach ($actions as $id => $action) {
                    if (!in_array($id, $available_action)) {
                        unset($actions[$id]);
                    }
                }

            }
            Dropdown::showFromArray('change_action',
                                    $actions,
                                    ['on_change' => "plugin_resources_change_action(\"" . PLUGIN_RESOURCES_WEBDIR . "\", this.value);"]);
            echo "</div>";
            echo "</div>";

            echo "<div id='plugin_resources_actions'>";
            $msg = [];
            if (isset($options['plugin_resources_resources_id']) && $options['plugin_resources_resources_id'] == 0) {
                $msg[] = self::getTypeName(1);
            }
            if (isset($options['change_action']) && $options['change_action'] == 0) {
                $msg[] = __('Actions to taken');
            }

            if (count($msg) > 0) {
                echo "<span class='red'>" . sprintf(__("Please correct: %s", 'resources'), implode(', ', $msg)) . "</span>";
            }
            echo "</div>";

            echo "<div colspan='2' id='plugin_resources_buttonchangeresources'></div>";

            Html::closeForm();
            echo "</div>";
            echo "</div>";
            echo "</div>";

        } else {
            echo "<div align='center'>" . __('No item found') . "</div>";
        }
    }

    /**
     * Show form from heelpdesk to transfer a resource
     *
     * @param $plugin_resources_resources_id
     */
    function showResourcesToTransfer($plugin_resources_resources_id) {
        global $CFG_GLPI;

        $dbu = new DbUtils();

        if ($dbu->countElementsInTable($this->getTable()) > 0) {
            echo "<div align='center'>";
            echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_main.css");
            echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/css/style_bootstrap_ticket.css");

            echo "<h3><div class='alert alert-secondary' role='alert' >";
            echo "<i class='" . self::getIcon() . "'></i>&nbsp;";
            echo __('Resources management', 'resources');
            echo "</div></h3>";

            echo "<div id ='content'>";
            echo "<div class='bt-container resources_wizard_resp'>";
            echo "<div class='bt-block bt-features' >";

            echo "<form method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR . "/front/resource.transfer.php\">";

            if (isset($plugin_resources_resources_id)) {
                $resource = new PluginResourcesResource();
                if ($resource->getFromDB($plugin_resources_resources_id)) {

                    echo "<div class=\"form-row plugin_resources_wizard_margin \">";
                    echo "<div class=\"bt-feature col-md-12 \" >";
                    echo "<h4 class=\"bt-title-divider\">";
                    echo "<img class='resources_wizard_resp_img' src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/transferresource.png' alt='transferresource'/>&nbsp;";
                    echo __('Declare a transfer', 'resources');
                    echo "</h4></div></div>";

                    echo "<div class=\"form-row\">";
                    echo "<div class=\"bt-feature col-md-4 \">";
                    echo self::getTypeName(1);
                    echo "</div>";
                    echo "<div class=\"bt-feature col-md-4 \">";
                    echo self::getResourceName($plugin_resources_resources_id);
                    echo "</div>";
                    echo "</div>";

                    echo "<div class=\"form-row\">";
                    echo "<div class=\"bt-feature col-md-4 \">";
                    echo __('Current entity', 'resources');
                    echo "</div>";
                    echo "<div class=\"bt-feature col-md-4 \">";
                    echo Dropdown::getDropdownName('glpi_entities', $resource->fields['entities_id']);
                    echo "</div>";
                    echo "</div>";

                    echo "<div class=\"form-row\">";
                    echo "<div class=\"bt-feature col-md-4 \">";
                    echo __('Target entity', 'resources') . " <span class='red'>*</span>";
                    echo "</div>";
                    echo "<div class=\"bt-feature col-md-4 \">";
                    $transferentity = new PluginResourcesTransferEntity();
                    $data           = $transferentity->find();
                    $elements       = [Dropdown::EMPTY_VALUE];
                    foreach ($data as $val) {
                        $elements[$val['entities_id']] = Dropdown::getDropdownName("glpi_entities", $val['entities_id']);
                    }
                    Dropdown::showFromArray("entities_id", $elements);
                    echo "</div>";
                    echo "</div>";

                    echo "<div class=\"form-row\">";
                    echo "<div class=\"bt-feature col-md-12 \">";
                    echo "<div class='next'>";
                    echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                    echo Html::submit(__s('Declare a transfer', 'resources'), ['name' => 'transferresources', 'class' => 'btn btn-success']);
                    echo "</div>";
                    echo "</div></div>";

                    Html::closeForm();
                    echo "</div>";
                    echo "</div>";
                    echo "</div>";
                }
            }
        } else {
            echo "<div align='center'>" . __('No item found') . "</div>";
        }
    }

    /**
     * Massive actions to be added
     *
     * @param type $type
     *
     * @return $action
     */
    function massiveActions($type) {

        $action = [];
        $prefix = $this->getType() . MassiveAction::CLASS_ACTION_SEPARATOR;
        if (Session::haveRightsOr('plugin_resources', [CREATE, UPDATE])) {
            $action[$prefix . "plugin_resources_add_item"] = __('Associate a resource', 'resources');
        }

        if ($type == "User") {
            $action[$prefix . "plugin_resources_generate_resources"] = __('Generate resources', 'resources');
            $action[$prefix . "plugin_resources_add_habilitation"]   = __('Add habiliation', 'resources');
        }
        return $action;
    }

    /**
     * Get the specific massive actions
     *
     * @param $checkitem link item to check right   (default NULL)
     *
     * @return an array of massive actions
     * *@since version 0.84
     *
     */
    function getSpecificMassiveActions($checkitem = null) {
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if ($isadmin && Session::getCurrentInterface() == 'central') {
            $actions[PluginResourcesResource::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'Install']    = _x('button', 'Associate');
            $actions[PluginResourcesResource::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'Desinstall'] = _x('button', 'Dissociate');

            if (Session::haveRight('transfer', READ)
                && Session::isMultiEntitiesMode()
            ) {
                $actions[PluginResourcesResource::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'Transfert'] = __('Transfer');
            }
            $actions[PluginResourcesResource::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'AddHabilitation'] = __('Add additional habilitation', 'resources');
            $actions[PluginResourcesResource::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'Send']            = __('Send a notification');
        }
        return $actions;
    }

    /**
     * Class-specific method used to show the fields to specify the massive action
     *
     * @param MassiveAction $ma the current massive action object
     *
     * @return boolean false if parameters displayed ?
     **@since 0.85
     *
     */
    static function showMassiveActionsSubForm(MassiveAction $ma) {
        $itemtype = $ma->getItemtype(false);
        switch ($ma->getAction()) {
            case "Install" :
                Dropdown::showSelectItemFromItemtypes(['items_id_name' => "item_item",
                                                       'itemtypes'     => self::getTypes()]);
                break;
            case "Desinstall" :
                Dropdown::showSelectItemFromItemtypes(['items_id_name' => "item_item",
                                                       'itemtypes'     => self::getTypes()]);
                break;
            case "Transfert" :
                Dropdown::show('Entity');
                break;
            case "plugin_resources_add_item":
                echo Html::hidden('itemtype', ['value' => $itemtype]);
                self::dropdown(['display' => true]);
                break;
            case "plugin_resources_generate_resources":
                echo Html::hidden('itemtype', ['value' => $itemtype]);
                self::fastResourceAddForm();
                break;
            case "AddHabilitation":
            case "plugin_resources_add_habilitation":
                Dropdown::show(PluginResourcesHabilitation::class,
                               ['entity' => $_SESSION['glpiactiveentities']]);
                break;
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     * */
    static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {

        $input         = $ma->getInput();
        $resource_item = new PluginResourcesResource_Item();
        $resource      = new PluginResourcesResource();
        $itemtype      = $ma->getItemtype(false);

        switch ($ma->getAction()) {
            case "Transfert" :
                if ($itemtype == PluginResourcesResource::class) {
                    foreach ($ids as $key => $val) {
                        if ($item->transferResource($key, $input['entities_id'])) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    }
                }
                break;

            case "Install" :
                foreach ($ids as $key => $val) {
                    if ($item->can($key, UPDATE)) {
                        $values = ['plugin_resources_resources_id' => $key,
                                   'items_id'                      => $input["item_item"],
                                   'itemtype'                      => $input['itemtype']];
                        if ($resource_item->add($values)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                }
                break;

            case "Desinstall" :
                foreach ($ids as $key => $val) {
                    if ($resource_item->deleteItemByResourcesAndItem($key, $input['item_item'], $input['itemtype'])) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                    }
                }
                break;

            case "Send" :
                if ($resource->sendEmail($ids)) {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_OK);
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
                }
                break;

            case "plugin_resources_add_item":
                $messages = [];
                foreach ($ids as $key => $val) {
                    if ($item->can($key, UPDATE)) {
                        $input = ['plugin_resources_resources_id' => $input['plugin_resources_resources_id'],
                                  'items_id'                      => $key,
                                  'itemtype'                      => $input['itemtype']];

                        if ($resource_item->add($input)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                            $messages[] = _n("This resource has been added", "These resources have been added", 2, "resources");
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            $messages[] = _n("This resource aldready exists", "These resources aldready exist", 2, "resources");
                        }
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        $messages[] = $item->getErrorMessage(ERROR_RIGHT);
                    }
                }
                $ma->addMessage(implode("<br>", array_unique($messages)));
                break;

            case "plugin_resources_generate_resources":
                $messages = [];
                if (sizeof($input['itemtype']) > 0) {
                    foreach ($ids as $key => $val) {
                        list($id, $error, $message) = self::fastResourceAdd($key, $input);
                        if ($error['right']) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            $messages[] = $item->getErrorMessage(ERROR_RIGHT);
                        } else if ($error['error']) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            $messages[] = _n("This resource aldready exists", "These resources aldready exist", 2, "resources") . "<br>" . $message;
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                            $messages[] = _n("This resource has been added", "These resources have been added", 2, "resources") . "<br>" . $message;
                        }
                    }
                }
                $ma->addMessage(implode("<br>", array_unique($messages)));
                break;

            case "AddHabilitation":
                $habilitation = new PluginResourcesResourceHabilitation();
                foreach ($ids as $key => $val) {
                    if ($item->can($key, UPDATE)) {

                        //check if habilitation already added
                        if (!$habilitation->getFromDBByCrit(['plugin_resources_resources_id'     => $key,
                                                             'plugin_resources_habilitations_id' => $input['plugin_resources_habilitations_id']])) {
                            if ($resource->getFromDB($key)) {
                                //TODO add verification entities
                                $values = ['plugin_resources_resources_id'     => $key,
                                           'plugin_resources_habilitations_id' => $input["plugin_resources_habilitations_id"]];
                                if ($habilitation->add($values)) {
                                    $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                                } else {
                                    $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                                }
                            } else {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            }
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                }
                break;
            case "plugin_resources_add_habilitation":
                $habilitation = new PluginResourcesResourceHabilitation();
                foreach ($ids as $key => $val) {
                    if ($item->can($key, UPDATE)) {
                        $resource_item = new PluginResourcesResource_Item();
                        if ($resource_item->getFromDBByCrit(['items_id' => $key, 'itemtype' => User::getType()])) {


                            $resource_id = $resource_item->getField('plugin_resources_resources_id');
                            //check if habilitation already added
                            if (!$habilitation->getFromDBByCrit(['plugin_resources_resources_id'     => $resource_id,
                                                                 'plugin_resources_habilitations_id' => $input['plugin_resources_habilitations_id']])) {
                                if ($resource->getFromDB($resource_id)) {
                                    //TODO add verification entities
                                    $values = ['plugin_resources_resources_id'     => $resource_id,
                                               'plugin_resources_habilitations_id' => $input["plugin_resources_habilitations_id"]];
                                    if ($habilitation->add($values)) {
                                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                                    } else {
                                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                                    }
                                } else {
                                    $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                                }
                            } else {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            }
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        }
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                    }
                }
                break;

            default :
                return parent::doSpecificMassiveActions($input);
        }
    }

    /**
     * Transfer resource
     *
     * @param type $resources_id
     * @param type $entities_id
     *
     * @return boolean
     */
    function transferResource($resources_id, $entities_id, $options = []) {
        global $DB;

        $params['users_id']          = 0;
        $params['itemtype']          = 'User';
        $params['link_resources_id'] = 0;

        $dbu = new DbUtils();

        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }

        $resource_item = new PluginResourcesResource_Item();

        if (strstr($resources_id, 'users')) {
            list($tag, $users_id) = explode('-', $resources_id);
        }

        $resourceOk    = $this->getFromDB($resources_id);
        $source_entity = $this->fields['entities_id'];

        if (!$resourceOk) {
            // Link user to resource
            if (!empty($params['link_resources_id'])) {
                $input = ['plugin_resources_resources_id' => $params['link_resources_id'],
                          'items_id'                      => $users_id,
                          'itemtype'                      => $params['itemtype']];
                if ($resource_item->can(-1, 'w', $input)) {
                    $resourceOk = $resource_item->add($input);
                }
                $resources_id = $params['link_resources_id'];
                $resourceOk   = $this->getFromDB($resources_id);

                // Add resource
            } else {
                list($resources_id, $error, $message) = self::fastResourceAdd($users_id, $params);
                if ($error['error'] || $error['right']) {
                    $resourceOk = false;
                } else {
                    $resourceOk = $this->getFromDB($resources_id);
                }
            }
        }

        if ($resourceOk) {
            // Link to a user if needed
            if (!empty($params['users_id'])) {
                $input = ['plugin_resources_resources_id' => $resources_id,
                          'items_id'                      => $params['users_id'],
                          'itemtype'                      => $params['itemtype']];
                if ($resource_item->can(-1, 'w', $input)) {
                    $resource_item->add($input);
                }
            }

            $contracttype = PluginResourcesContractType::transfer($this->fields["plugin_resources_contracttypes_id"], $entities_id);
            if ($contracttype > 0) {
                $values["id"]                                = $resources_id;
                $values["plugin_resources_contracttypes_id"] = $contracttype;
                $this->update($values);
            }

            unset($values);

            $resourcestate = PluginResourcesResourceState::transfer($this->fields["plugin_resources_resourcestates_id"], $entities_id);
            if ($resourcestate > 0) {
                $values["id"]                                 = $resources_id;
                $values["plugin_resources_resourcestates_id"] = $resourcestate;
                $this->update($values);
            }

            unset($values);

            $department = PluginResourcesDepartment::transfer($this->fields["plugin_resources_departments_id"], $entities_id);
            if ($department > 0) {
                $values["id"]                              = $resources_id;
                $values["plugin_resources_departments_id"] = $department;
                $this->update($values);
            }

            unset($values);

            $situation = PluginResourcesResourceSituation::transfer($this->fields["plugin_resources_resourcesituations_id"], $entities_id);
            if ($situation > 0) {
                $values["id"]                                     = $resources_id;
                $values["plugin_resources_resourcesituations_id"] = $situation;
                $this->update($values);
            }

            unset($values);

            $contractnature = PluginResourcesContractNature::transfer($this->fields["plugin_resources_contractnatures_id"], $entities_id);
            if ($contractnature > 0) {
                $values["id"]                                  = $resources_id;
                $values["plugin_resources_contractnatures_id"] = $contractnature;
                $this->update($values);
            }
            unset($values);

            $rank = PluginResourcesRank::transfer($this->fields["plugin_resources_ranks_id"], $entities_id);
            if ($rank > 0) {
                $values["id"]                        = $resources_id;
                $values["plugin_resources_ranks_id"] = $rank;
                $this->update($values);
            }

            unset($values);

            $speciality = PluginResourcesResourceSpeciality::transfer($this->fields["plugin_resources_resourcespecialities_id"], $entities_id);
            if ($speciality > 0) {
                $values["id"]                                       = $resources_id;
                $values["plugin_resources_resourcespecialities_id"] = $speciality;
                $this->update($values);
            }
            unset($values);

            $PluginResourcesTask = new PluginResourcesTask();
            $restrict            = ["plugin_resources_resources_id" => $resources_id];
            $tasks               = $dbu->getAllDataFromTable("glpi_plugin_resources_tasks", $restrict);
            if (!empty($tasks)) {
                foreach ($tasks as $task) {
                    $PluginResourcesTask->getFromDB($task["id"]);
                    $tasktype = PluginResourcesTaskType::transfer($PluginResourcesTask->fields["plugin_resources_tasktypes_id"], $entities_id);
                    if ($tasktype > 0) {
                        $values["id"]                            = $task["id"];
                        $values["plugin_resources_tasktypes_id"] = $tasktype;
                        $PluginResourcesTask->update($values);
                    }
                    $values["id"]          = $task["id"];
                    $values["entities_id"] = $entities_id;
                    $PluginResourcesTask->update($values);
                }
            }

            unset($values);

            $PluginResourcesEmployment = new PluginResourcesEmployment();
            $restrict                  = ["plugin_resources_resources_id" => $resources_id];
            $employments               = $dbu->getAllDataFromTable("glpi_plugin_resources_employments", $restrict);
            if (!empty($employments)) {
                foreach ($employments as $employment) {
                    $PluginResourcesEmployment->getFromDB($employment["id"]);
                    $rank = PluginResourcesRank::transfer($PluginResourcesEmployment->fields["plugin_resources_ranks_id"], $entities_id);
                    if ($rank > 0) {
                        $values["id"]                        = $employment["id"];
                        $values["plugin_resources_ranks_id"] = $rank;
                        $PluginResourcesEmployment->update($values);
                    }
                    $PluginResourcesEmployment->getFromDB($employment["id"]);
                    $profession = PluginResourcesProfession::transfer($PluginResourcesEmployment->fields["plugin_resources_professions_id"], $entities_id);
                    if ($profession > 0) {
                        $values["id"]                              = $employment["id"];
                        $values["plugin_resources_professions_id"] = $profession;
                        $PluginResourcesEmployment->update($values);
                    }
                    $values["id"]          = $employment["id"];
                    $values["entities_id"] = $entities_id;
                    $PluginResourcesEmployment->update($values);
                }
            }

            unset($values);

            $PluginResourcesEmployee = new PluginResourcesEmployee();

            $restrict  = ["plugin_resources_resources_id" => $resources_id];
            $employees = $dbu->getAllDataFromTable("glpi_plugin_resources_employees", $restrict);
            if (!empty($employees)) {
                foreach ($employees as $employee) {
                    $employer = PluginResourcesEmployer::transfer($employee["plugin_resources_employers_id"], $entities_id);
                    if ($employer > 0) {
                        $values["id"]                            = $employee["id"];
                        $values["plugin_resources_employers_id"] = $employer;
                        $PluginResourcesEmployee->update($values);
                    }

                    $client = PluginResourcesClient::transfer($employee["plugin_resources_clients_id"], $entities_id);
                    if ($client > 0) {
                        $values["id"]                          = $employee["id"];
                        $values["plugin_resources_clients_id"] = $client;
                        $PluginResourcesEmployee->update($values);
                    }
                }
            }

            unset($values);

            $values["id"]          = $resources_id;
            $values["entities_id"] = $entities_id;
            if ($this->update($values)) {
                // Check list
                $checklist_exist = PluginResourcesChecklist::checkIfChecklistExist($resources_id, PluginResourcesChecklist::RESOURCES_CHECKLIST_TRANSFER);
                $checklistconfig = new PluginResourcesChecklistconfig();
                if ($checklist_exist) {
                    $checklist = new PluginResourcesChecklist();
                    $checklist->deleteByCriteria(['plugin_resources_resources_id' => $resources_id,
                                                  'checklist_type'                => PluginResourcesChecklist::RESOURCES_CHECKLIST_TRANSFER]);
                    $query = "UPDATE `glpi_plugin_resources_checklists`
                         SET `entities_id` = '" . $entities_id . "'
                         WHERE `plugin_resources_resources_id` ='$resources_id'";
                    $DB->query($query);
                }
                $checklistconfig->addChecklistsFromRules($this, PluginResourcesChecklist::RESOURCES_CHECKLIST_TRANSFER);

                // Notification
                $restrict = ["itemtype"                      => 'User',
                             "plugin_resources_resources_id" => $resources_id];

                $data = $dbu->getAllDataFromTable('glpi_plugin_resources_resources_items', $restrict);

                if (!empty($data)) {
                    $linkeduser = [];
                    foreach ($data as $val) {
                        $linkeduser[$val['items_id']] = $val['items_id'];
                    }
                    $reportconfig = new PluginResourcesReportConfig();
                    if ($reportconfig->getFromDBByResource($resources_id)) {
                        if ($reportconfig->fields['send_other_notif']) {
                            NotificationEvent::raiseEvent('other', $this, ['reports_id' => $reportconfig->fields['id']]);
                        }
                        if ($reportconfig->fields['send_transfer_notif']) {
                            NotificationEvent::raiseEvent('transfer', $this, ['reports_id' => $reportconfig->fields['id'], 'users_id' => $linkeduser, 'source_entity' => $source_entity, 'target_entity' => $entities_id]);
                        }
                    }
                } else {
                    Session::addMessageAfterRedirect(__('The notification is not sent because the resource is not linked with a user', 'resources'), true, ERROR);
                }

                Session::addMessageAfterRedirect(__('Declaration of resource transfer OK', 'resources'), true);
                return true;
            }
        }

        return false;
    }


    // Cron action

    /**
     * @param $name
     *
     * @return array
     */
    static function cronInfo($name) {

        switch ($name) {
            case 'Resources':
                return [
                    'description' => __('Resources not declaring leaving', 'resources')];   // Optional
                break;
            case 'AlertCommercialManager':
                return [
                    'description' => __('Resources list of commercial manager', 'resources')];   // Optional
                break;
            case 'UpdateResourcesState':
                return [
                    'description' => __('Update Resources state', 'resources')];   // Optional
                break;
        }
        return [];
    }

    /**
     * @return string
     */
    function queryAlert() {

        $first = false;
        $date  = date("Y-m-d H:i:s");
        $query = "SELECT *
            FROM `" . $this->getTable() . "`
            WHERE `date_end` IS NOT NULL
            AND `date_end` <= '" . $date . "'
            AND `is_leaving` != 1";

        // Add Restrict templates
        if ($this->maybeTemplate()) {
            $LINK = " AND ";
            if ($first) {
                $LINK  = " ";
                $first = false;
            }
            $query .= $LINK . "`" . $this->getTable() . "`.`is_template` = 0 ";
        }
        // Add is_deleted if item have it
        if ($this->maybeDeleted()) {
            $LINK = " AND ";
            if ($first) {
                $LINK  = " ";
                $first = false;
            }
            $query .= $LINK . "`" . $this->getTable() . "`.`is_deleted` = 0 ";
        }

        return $query;

    }

    /**
     * Cron action on tasks : LeavingResources
     *
     * @param $task for log, if NULL display
     *
     **/
    static function cronResources($task = null) {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["notifications_mailing"]) {
            return 0;
        }

        $message     = [];
        $cron_status = 0;

        $resource      = new self();
        $query_expired = $resource->queryAlert();

        $querys = [Alert::END => $query_expired];

        $task_infos    = [];
        $task_messages = [];

        foreach ($querys as $type => $query) {
            $task_infos[$type] = [];
            foreach ($DB->request($query) as $data) {
                $entity                       = $data['entities_id'];
                $message                      = $data["name"] . " " . $data["firstname"] . " : " .
                                                Html::convDate($data["date_end"]) . "<br>\n";
                $task_infos[$type][$entity][] = $data;

                if (!isset($task_messages[$type][$entity])) {
                    $task_messages[$type][$entity] = __('These resources have normally left the company', 'resources') . "<br />";
                }
                $task_messages[$type][$entity] .= $message;
            }
        }

        foreach ($querys as $type => $query) {

            foreach ($task_infos[$type] as $entity => $resources) {
                Plugin::loadLang('resources');

                if (NotificationEvent::raiseEvent("AlertLeavingResources",
                                                  new PluginResourcesResource(),
                                                  ['entities_id' => $entity,
                                                   'resources'   => $resources])
                ) {
                    $message     = $task_messages[$type][$entity];
                    $cron_status = 1;
                    if ($task) {
                        $task->log(Dropdown::getDropdownName("glpi_entities",
                                                             $entity) . ":  $message\n");
                        $task->addVolume(1);
                    } else {
                        Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities",
                                                                                   $entity) . ":  $message");
                    }

                } else {
                    if ($task) {
                        $task->log(Dropdown::getDropdownName("glpi_entities", $entity) .
                                   ":  Send leaving resources alert failed\n");
                    } else {
                        Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities", $entity) .
                                                         ":  Send leaving resources alert failed", false, ERROR);
                    }
                }
            }
        }

        return $cron_status;
    }

    /**
     * Cron action on tasks : AlertCommercialManager
     *
     * @param $task for log, if NULL display
     *
     **/
    static function cronAlertCommercialManager($task = null) {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["notifications_mailing"]) {
            return 0;
        }

        $message     = [];
        $cron_status = 0;

        $query_commercial = $query = "SELECT DISTINCT(`users_id_sales`) 
                                     FROM `glpi_plugin_resources_resources` 
                                     WHERE `users_id_sales` != 0
                                     AND `is_deleted` = 0";

        foreach ($DB->request($query_commercial) as $commercial) {
            $query = "SELECT * 
                  FROM `glpi_plugin_resources_resources` 
                  WHERE `users_id_sales` = " . $commercial['users_id_sales'] . " 
                  AND `is_deleted` = 0";

            $resources = [];
            foreach ($DB->request($query) as $data) {
                $resources[] = $data;
            }
            $resource               = new PluginResourcesResource();
            $resource->fields['id'] = isset($resources[0]['id']) ? $resources[0]['id'] : 0;

            $dbu = new DbUtils();

            if (count($resources) > 0 && NotificationEvent::raiseEvent("AlertCommercialManager",
                                                                       $resource,
                                                                       ['resources'      => $resources,
                                                                        'users_id_sales' => $commercial['users_id_sales']])
            ) {
                $cron_status = 1;
                if ($task) {
                    $task->log($dbu->getUserName($commercial['users_id_sales']) . ": " .
                               __('Send alert to the commercial manager', 'resources') . "\n");
                    $task->addVolume(1);
                } else {
                    Session::addMessageAfterRedirect(getUserName($commercial['users_id_sales']) . ": " .
                                                     __('Send alert to the commercial manager', 'resources') . "\n");
                }

            } else {
                if ($task) {
                    $task->log($dbu->getUserName($commercial['users_id_sales']) . ": " .
                               __('Failed to Send alert to the commercial manager', 'resources') . "\n");
                } else {
                    Session::addMessageAfterRedirect(getUserName($commercial['users_id_sales']) . ": " .
                                                     __('Failed to Send alert to the commercial manager', 'resources') . "\n");
                }
            }
        }

        return $cron_status;
    }

    /**
     * Cron action on tasks : UpdateResourcesState
     *
     * @param $task for log, if NULL display
     *
     **/
    static function cronUpdateResourcesState($task = null) {
        global $DB, $CFG_GLPI;

        $resource = new PluginResourcesResource();
        $config   = new PluginResourcesConfig();
        $config->getFromDB(1);


        $message     = [];
        $cron_status = 1;

        $query_arrival = $query = "SELECT * 
                                     FROM `glpi_plugin_resources_resources` 
                                     WHERE `date_begin` IS NOT NULL 
                                     AND `date_begin` <= NOW()
                                     AND (`date_end` IS NULL OR `date_end` > NOW())
                                     AND `is_deleted` = 0";

        foreach ($DB->request($query_arrival) as $resourceD) {


            if ($resourceD['plugin_resources_resourcestates_id'] != $config->fields['plugin_resources_resourcestates_id_arrival']) {
                $input                                       = [];
                $input['id']                                 = $resourceD['id'];
                $input["plugin_resources_resourcestates_id"] = $config->fields['plugin_resources_resourcestates_id_arrival'];
                $resource->update($input);
                $task->addVolume(1);
            }

        }

        $query_departure = $query = "SELECT * 
                                     FROM `glpi_plugin_resources_resources` 
                                     WHERE `date_begin` IS NOT NULL 
                                     AND `date_begin` <= NOW()
                                     AND `date_end` < NOW()
                                     AND `is_deleted` = 0";

        foreach ($DB->request($query_departure) as $resourceD) {


            if ($resourceD['plugin_resources_resourcestates_id'] != $config->fields['plugin_resources_resourcestates_id_departure']) {
                $input                                       = [];
                $input['id']                                 = $resourceD['id'];
                $input["plugin_resources_resourcestates_id"] = $config->fields['plugin_resources_resourcestates_id_departure'];
                $resource->update($input);
                $task->addVolume(1);
            }

        }

        return $cron_status;
    }

    /**
     * Display entities of the loaded profile
     *
     * @param $myname select name
     * @param $target target for entity change action
     */
    static function showSelector($target) {
        global $CFG_GLPI;

        $rand = mt_rand();
        Plugin::loadLang('resources');
        echo Html::css("/public/lib/base.css");
        echo Html::script("public/lib/base.js");
        echo Html::css(PLUGIN_RESOURCES_NOTFULL_DIR . "/lib/jstree/themes/default/style.min.css");

        echo "<div class='alert alert-important alert-info d-flex'>" . __('Select the contract type', 'resources') . "</div><br>";
        echo "<a href='" . $target . "?reset=reset' target='_blank' title=\"" .
             __s('Show all') . "\">" . str_replace(" ", "&nbsp;", __('Show all')) . "</a>";

        echo "<div class='left' style='width:100%'>";
        $root_doc = PLUGIN_RESOURCES_WEBDIR;
        $js       = "   $(function() {
                  $.getScript('$root_doc/lib/jstree/jstree.min.js', function(data, textStatus, jqxhr) {
                     $('#tree_resourcetypes$rand').jstree({
                        // the `plugins` array allows you to configure the active plugins on this instance
                        'plugins' : ['search', 'qload'],
                        'search': {
                           'case_insensitive': true,
                           'show_only_matches': true,
                           'ajax': {
                              'type': 'POST',
                              'url': '" . PLUGIN_RESOURCES_WEBDIR . "/ajax/resourcetreetypes.php'
                           }
                        },
                        'qload': {
                           'prevLimit': 50,
                           'nextLimit': 30,
                           'moreText': '" . __s('Load more...') . "'
                        },
                        'core': {
//                           'themes': {
//                              'name': 'glpi'
//                           },
                           'animation': 0,
                           'data': {
                              'url': function(node) {
                                 return node.id === '#' ?
                                    '" . PLUGIN_RESOURCES_WEBDIR . "/ajax/resourcetreetypes.php?node=-1' :
                                    '" . PLUGIN_RESOURCES_WEBDIR . "/ajax/resourcetreetypes.php?node='+node.id;
                              }
                           }
                        }
                     });
                  });
               });";
        echo Html::scriptBlock($js);
        echo "<div class='left' style='width:100%'>";
        echo "<div id='tree_resourcetypes$rand'></div>";
        echo "</div>";
    }

    /**
     * @param $items
     *
     * @return bool
     */
    function sendEmail($items) {

        $users = [];
        foreach ($items as $key => $val) {
            $restrict  = ["itemtype"                      => 'User',
                          "plugin_resources_resources_id" => $key];
            $dbu       = new DbUtils();
            $resources = $dbu->getAllDataFromTable("glpi_plugin_resources_resources_items", $restrict);

            if (!empty($resources)) {
                foreach ($resources as $resource) {
                    $users[] = $resource["items_id"];
                }
            }
        }

        $User  = new User();
        $mail  = "";
        $first = true;
        foreach ($users as $key => $val) {
            if ($User->getFromDB($val)) {
                $email = $User->getDefaultEmail();
                if (!empty($email)) {
                    if (!$first) {
                        $mail .= ";";
                    } else {
                        $first = false;
                    }
                    $mail .= $email;
                }
            }
        }

        $send = "<a href='mailto:$mail'>" . __('Click here to send your email', 'resources') . "</a>";
        Session::addMessageAfterRedirect($send);

        return true;
    }

    /**
     * Send a file (not a document) to the navigator
     * See Document->send();
     *
     * @param $file string: storage filename
     * @param $filename string: file title
     *
     * @return nothing
     **/
    static function sendFile($file, $filename) {

        // Test securite : document in DOC_DIR
        $tmpfile = str_replace(GLPI_PLUGIN_DOC_DIR . "/resources/pictures/", "", $file);

        if (strstr($tmpfile, "../") || strstr($tmpfile, "..\\")) {
            \Glpi\Event::log($file, "sendFile", 1, "security",
                             $_SESSION["glpiname"] . " try to get a non standard file.");
            die("Security attack !!!");
        }

        if (!file_exists($file)) {
            die("Error file $file does not exist");
        }

        $splitter = explode("/", $file);
        $mime     = "application/octet-stream";

        if (preg_match('/\.(....?)$/', $file, $regs)) {
            switch ($regs[1]) {
                case "jpeg" :
                    $mime = "image/jpeg";
                    break;

                case "jpg" :
                    $mime = "image/jpeg";
                    break;
            }
        }
        //print_r($file);

        // Now send the file with header() magic
        header("Expires: Mon, 26 Nov 1962 00:00:00 GMT");
        header('Pragma: private'); /// IE BUG + SSL
        header('Cache-control: private, must-revalidate'); /// IE BUG + SSL
        header("Content-disposition: filename=\"$filename\"");
        header("Content-type: " . $mime);

        readfile($file) or die ("Error opening file $file");
    }


    /**
     * Permet l'affichage dynamique d'une liste déroulante imbriquee
     *
     * @static
     *
     * @param array ($itemtype,$myname,$value,$entity_restrict,$action,$span)
     */
    static function showGenericDropdown($itemtype, $options = []) {

        if (isset($options['name'])) {
            // Set dropdown
            $options['on_change'] = "update" . $options['name'] . "();";
            $options['entity']    = $_SESSION['glpiactive_entity'];
            $options['addicon']   = true;
            $rand                 = Dropdown::show($itemtype, $options);

            // Set ajax load if needed
            if (isset($options['action']) && isset($options['span'])) {
                $options[$options['name']]  = "__VALUE__";
                $options['entity_restrict'] = $_SESSION['glpiactive_entity'];
                $options['rand']            = $rand;
                $script                     = "function update" . $options['name'] . "(){";
                $script                     .= Ajax::updateItemJsCode($options['span'], $options['action'], $options, 'dropdown_' . $options['name'] . $rand, false);
                $script                     .= "}";
                echo Html::scriptBlock($script);
            }
        }
    }

    /**
     * Display information on treeview plugin
     *
     * @params itemtype, id, pic, url, name
     *
     * @return params
     **/
    static function showResourceTreeview($params) {
        global $CFG_GLPI;

        if ($params['itemtype'] == "PluginResourcesResource") {

            $params['pic'] = "../resources/pics/miniresources.png";

            $item = new $params['itemtype']();
            if ($item->getFromDB($params['id'])) {
                $params['name'] = self::getResourceName($params['id']);

                if (isset($item->fields["picture"])) {
                    $params['pic'] = PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $item->fields["picture"];
                }
            }
        }
        return $params;
    }

    /**
     * @param $input
     *
     * @return bool
     */
    function checkTransferMandatoryFields($input) {
        $msg     = [];
        $checkKo = false;

        $mandatory_fields = ['entities_id' => __('Entity'), 'plugin_resources_resources_id' => self::getTypeName(1)];

        foreach ($input as $key => $value) {
            if (array_key_exists($key, $mandatory_fields)) {
                if (empty($value)) {
                    $msg[]   = $mandatory_fields[$key];
                    $checkKo = true;
                }
            }
        }

        if ($checkKo) {
            Session::addMessageAfterRedirect(sprintf(__("Mandatory fields are not filled. Please correct: %s"), implode(', ', $msg)), false, ERROR);
            return false;
        }
        return true;
    }

    /**
     * Get picture URL from picture field
     *
     * @param $picture picture field
     *
     * @return string URL to show picture
     **@since version 0.85
     *
     */
    static function getThumbnailURLForPicture($picture) {
        global $CFG_GLPI;

        if (!empty($picture)) {
            $tmp = explode(".", $picture);
            if (count($tmp) == 2) {
                return PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $tmp[0] . '.' . $tmp[1];
            }
            return PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png";
        }
        return PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png";

    }

    /**
     * List of resources for a client
     *
     * @param $client_id
     */
    function showListResourcesForClient($client_id) {
        global $DB;

        //Retrieving resource ids for this client
        $query  = "SELECT *  
                FROM `glpi_plugin_resources_resources` 
                LEFT JOIN `glpi_plugin_resources_employees` 
                  ON `glpi_plugin_resources_resources`.`id` = `glpi_plugin_resources_employees`.`plugin_resources_resources_id`
                WHERE `glpi_plugin_resources_employees`.`plugin_resources_clients_id` = $client_id
                AND `glpi_plugin_resources_resources`.`is_deleted` = 0";
        $result = $DB->query($query);

        echo "<div align='center'>";

        if ($DB->numrows($result) == 0) {
            echo __('No item to display');
        } else {

            echo "<table class='tab_cadre_fixe'>";
            echo "<tr><th colspan='5'>" . __('Resources list', 'resources') . "</th></tr>";
            echo "<tr><th>" . __('Surname') . "</th>";
            echo "<th>" . __('First name') . "</th>";
            echo "<th>" . PluginResourcesResourceState::getTypeName(1) . "</th>";
            echo "<th>" . __('Location') . "</th>";
            echo "<th>" . PluginResourcesDepartment::getTypeName(1) . "</th>";
            echo "</tr>";

            $resource = new PluginResourcesResource();
            $dbu      = new DbUtils();

            foreach ($DB->request($query) as $employee) {
                if ($resource->getFromDB($employee['plugin_resources_resources_id'])) {
                    if (!$resource->fields['is_deleted']) {
                        echo "<tr class='tab_bg_1'>";
                        echo "<td>" . $resource->getLink() . "</td>";
                        echo "<td>" . $resource->fields['firstname'] . "</td>";
                        echo "<td>" . Dropdown::getDropdownName($dbu->getTableForItemType(PluginResourcesResourceState::class),
                                                                $resource->fields['plugin_resources_resourcestates_id']) . "</td>";
                        echo "<td>" . Dropdown::getDropdownName($dbu->getTableForItemType('Location'),
                                                                $resource->fields['locations_id']) . "</td>";
                        echo "<td>" . Dropdown::getDropdownName($dbu->getTableForItemType(PluginResourcesDepartment::class),
                                                                $resource->fields['plugin_resources_departments_id']) . "</td>";
                        echo "</tr>";
                    }
                }
            }
            echo "</table>";

        }
        echo "</div>";

    }

    /**
     * Each identifiers must be formatted as follow:
     * - name
     * - value
     * - type
     * - resource_column
     *
     * @param array $identifiers
     *
     * @return |null
     * @throws GlpitestSQLError
     */
    function isExistingResourceByIdentifier($identifiers = []) {

        global $DB;

        $tableResourceCriterias       = [];
        $tableResourceImportCriterias = [];

        $query = "SELECT r.*";
        $from  = "FROM " . self::getTable() . " as r";
        $join  = "";
        $where = 'WHERE 1=1';

        foreach ($identifiers as $identifier) {

            if (is_string($identifier['value']) && empty($identifier['value'])) {
                $identifier['value'] = null;
            }

            switch ($identifier['resource_column']) {
                case 10:
                    $tableResourceImportCriterias[] = [
                        'name'  => $identifier['name'],
                        'value' => $identifier['value'],
                        'type'  => $identifier['type']
                    ];
                    break;
                default:
                    $tableResourceCriterias[] = [
                        'name'  => $this->getColumnName($identifier['resource_column']),
                        'value' => $identifier['value'],
                        'type'  => $identifier['type']
                    ];
                    break;
            }
        }

        if (count($tableResourceImportCriterias) > 0) {
            $join .= " INNER JOIN " . PluginResourcesResourceImport::getTable() . " as ri";
            $join .= " ON ri.plugin_resources_resources_id = r.id";

            foreach ($tableResourceImportCriterias as $tableResourceImportCriteria) {

                $where .= " AND ri.name = '" . addslashes($tableResourceImportCriteria['name']) . "'";
                $where .= " AND ri.value = ";
                if (is_string($tableResourceImportCriteria['value'])) {
                    $where .= "'" . addslashes($tableResourceImportCriteria['value']) . "'";
                } else {
                    $where = $tableResourceImportCriteria['value'];
                }
            }
        }

        if (count($tableResourceCriterias) > 0) {
            foreach ($tableResourceCriterias as $tableResourceCriteria) {

                $where .= " AND r." . addslashes($tableResourceCriteria['name']) . " = ";

                if (is_string($tableResourceCriteria['value'])) {
                    $where .= "'" . addslashes($tableResourceCriteria['value']) . "'";
                } else {
                    $where = $tableResourceCriteria['value'];
                }
            }
        }

        $query .= " " . $from;
        $query .= " " . $join;
        $query .= " " . $where;

        $results = $DB->query($query);

        while ($data = $results->fetchArray()) {
            return $data['id'];
        }
        return null;
    }

    /**
     * Test if a resource exist in database by testing (1st and 2nd level) identifiers of importResource
     *
     * @param $importResourceID
     *
     * @return bool|null
     * @throws GlpitestSQLError
     */
    function isExistingResourceByImportResourceID($importResourceID) {

        $pluginResourcesImportResourceData = new PluginResourcesImportResourceData();

        // First level identifier
        $firstLevelIdentifiers = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel($importResourceID, 1);

        $resourceID = $this->isExistingResourceByIdentifier($firstLevelIdentifiers);

        if (!is_null($resourceID)) {
            return $resourceID;
        }

        // Second level identifier
        $secondLevelIdentifiers = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel($importResourceID, 2);

        $resourceID = $this->isExistingResourceByIdentifier($secondLevelIdentifiers);

        if (!is_null($resourceID)) {
            return $resourceID;
        }

        return false;
    }

    /**
     * Test if datas from csv file are different to resource field and resourceimports
     *
     * @param $resourceID
     * @param $datas
     *
     * @return bool
     */
    function isDifferentFromImportResourceDatas($resourceID, $datas) {
        foreach ($datas as $data) {
            if (self::isDifferentFromImportResourceData($resourceID, $data)) {
                return true;
            }
        }
        return false;
    }

    function isDifferentFromImportResourceData($resourceID, $data) {

        $result = self::hasDifferenciesWithValueByDataNameID(
            $resourceID,
            $data['resource_column'],
            $data['name'],
            $data['value']
        );

        return $result;
    }

    /**
     * Test if resource and importresources are differents
     *
     * @param $resourceID
     * @param $importResourceID
     *
     * @return bool
     */
    function isDifferentFromImportResource($resourceID, $importResourceID) {

        $pluginResourcesResource = new self();
        if (!$pluginResourcesResource->getFromDB($resourceID)) {
            Html::displayErrorAndDie("No resource for id " . $resourceID);
        }

        $pluginResourcesImportResource = new PluginResourcesImportResource();
        if (!$pluginResourcesImportResource->getFromDB($importResourceID)) {
            Html::displayErrorAndDie("No importResource for id " . $importResourceID);
        }

        $pluginResourcesImportResourceData = new PluginResourcesImportResourceData();

        // Get all import data
        $datas = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel($importResourceID, null, ['resource_column']);

        return $this->isDifferentFromImportResourceDatas($resourceID, $datas);
    }

    /**
     * Get resourceimports value by name
     *
     * @param $resourceID
     * @param $name
     *
     * @return mixed|string
     */
    function getResourceImportValueByName($resourceID, $name) {

        $pluginResourcesResource = new self();
        $pluginResourcesResource->getFromDB($resourceID);

        $pluginResourcesResourceImport = new PluginResourcesResourceImport();

        $crit = [
            PluginResourcesResourceImport::$items_id => $pluginResourcesResource->getID(),
            'name'                                   => $name
        ];

        if (!$pluginResourcesResourceImport->getFromDBByCrit($crit)) {
            return "";
        }

        return $pluginResourcesResourceImport->getField('value');
    }

    /**
     * Get resource field matching dataNameID
     *
     * @param $dataNameID
     *
     * @return mixed|null
     */
    function getFieldByDataNameID($dataNameID) {
        if (is_null($dataNameID)) {
            return null;
        }

        $resourceFieldName = $this->getResourceColumnNameFromDataNameID($dataNameID);

        return $this->getField($resourceFieldName);
    }

    /**
     * Test if value in resource fields or resourceimports have differences
     * with passed pair of name and value
     *
     * @param $resourceID
     * @param $dataNameID
     * @param $name
     * @param $value
     *
     * @return bool
     */
    function hasDifferenciesWithValueByDataNameID($resourceID, $dataNameID, $name, $value) {

        $pluginResourcesResource = new self();
        if (!$pluginResourcesResource->getFromDB($resourceID)) {
            Html::displayErrorAndDie("No resource for id " . $resourceID);
        }

        switch ($dataNameID) {
            case 10:
                // Find in Resource Import
                $pluginResourcesResourceImport = new PluginResourcesResourceImport();

                $crit = [
                    PluginResourcesResourceImport::$items_id => $resourceID,
                    'name'                                   => $name
                ];

                // Resource doesn't have the data
                if (!$pluginResourcesResourceImport->getFromDBByCrit($crit)) {
                    return true;
                }

                // Data are different
                if ($pluginResourcesResourceImport->getField('value') != $value) {
                    return true;
                }
                break;
            default:
                // Find in Resource Fields
                $resourceFieldName = $pluginResourcesResource->getResourceColumnNameFromDataNameID($dataNameID);
                $resourceValue     = $pluginResourcesResource->getField($resourceFieldName);

                // When firstname and lastname
                if ($dataNameID == 0 || $dataNameID == 1) {

                    $result = strcasecmp($resourceValue, $value) == 0;

                    return !$result;
                } else {
                    return $resourceValue != $value;
                }

                break;
        }
        return false;
    }

    function getGenders() {
        return [Dropdown::EMPTY_VALUE,
                __('Male', 'resources'),
                __('Female', 'resources')];
    }

    //   public function getCloneRelations(): array {
    //      return [
    ////         PluginResourcesTask::class,
    ////         PluginResourcesEmployee::class,
    ////         PluginResourcesReportConfig::class,
    ////         PluginResourcesResource_Item::class,
    ////         PluginResourcesResourceHabilitation::class,
    ////         PluginResourcesChoice::class,
    //         Document_Item::class,
    //         Notepad::class
    //      ];
    //   }
}
