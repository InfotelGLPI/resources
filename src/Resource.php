<?php

/**
 * -------------------------------------------------------------------------
 * resources plugin for GLPI
 * Copyright (C) 2015-2026 by the resources Development Team.
 *
 * https://github.com/InfotelGLPI/resources
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of resources.
 *
 * resources is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * resources is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with resources. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Resources;

use Ajax;
use Alert;
use Appliance;
use CommonDBTM;
use CommonGLPI;
use CommonITILActor;
use Computer;
use ComputerType;
use ConsumableItem;
use DateTime;
use DBConnection;
use DbUtils;
use Document;
use Document_Item;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryFunction;
use Glpi\Event;
use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Positions\Position;
use Group_Ticket;
use Html;
use Item_Problem;
use Item_Ticket;
use Location;
use Log;
use MassiveAction;
use Migration;
use Monitor;
use NetworkEquipment;
use Notepad;
use NotificationEvent;
use Peripheral;
use Phone;
use PhoneType;
use Plugin;
use Printer;
use Profile_User;
use Search;
use Session;
use Software;
use Ticket;
use Toolbox;
use UserCategory;
use UserTitle;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Resource
 */
class Resource extends CommonDBTM
{
    public static $rightname = 'plugin_resources';

    public static $types = [
        Computer::class,
        Monitor::class,
        NetworkEquipment::class,
        Peripheral::class,
        Phone::class,
        Printer::class,
        Software::class,
        ConsumableItem::class,
        \User::class,
        Appliance::class,
        ComputerType::class,
        PhoneType::class,
    ];

    public static $itemtype = Resource::class;

    protected $usenotepad = true;

    public $dohistory = true;

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return _n('Human resource', 'Human resources', $nb, 'resources');
    }

    public static function getIcon()
    {
        return "ti ti-friends";
    }

    /**
     * Guard a mutation performed on a row owned by a Resource.
     *
     * Several child itemtypes of this plugin (LinkAd, ResourceHabilitation,
     * ResourceHoliday, ResourceResting, Employee, ...) carry no entities_id column of
     * their own. On those, CommonDBTM::check()/can() cannot enforce any entity boundary:
     * checkEntity() short-circuits to true as soon as isEntityAssign() is false, so a
     * check() called directly on the child degrades to a plain global right test and lets
     * a user of entity A mutate rows belonging to entity B. The boundary only exists on
     * the owning Resource, so it has to be checked there.
     *
     * @param int|string $resources_id id of the owning Resource
     * @param int        $right        right to require on that Resource
     */
    public static function checkOwnership($resources_id, int $right = UPDATE): void
    {
        $resource = new self();
        $resource->check((int) $resources_id, $right);
    }

    /**
     * Same guard, for a mutation targeting an existing child row.
     *
     * The owning Resource is resolved from the child row read back from the database,
     * never from a parent id posted alongside it: the two are not correlated, so trusting
     * the posted parent would let a caller pass a Resource they own while naming a child
     * id belonging to another one (identifier substitution).
     *
     * @param CommonDBTM $child    empty instance of the child itemtype
     * @param int|string $child_id id of the child row being mutated
     * @param int        $right    right to require on the owning Resource
     */
    public static function checkChildOwnership(CommonDBTM $child, $child_id, int $right = UPDATE): void
    {
        if (!$child->getFromDB((int) $child_id)) {
            throw new BadRequestHttpException();
        }

        self::checkOwnership($child->fields['plugin_resources_resources_id'] ?? 0, $right);
    }

    /**
     * @return array
     */
    public static function getDataNames()
    {
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
            Team::getTypeName(0),
        ];
    }

    /**
     * @param $dataNameID
     *
     * @return string|null
     */
    public static function getResourceColumnNameFromDataNameID($dataNameID)
    {
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
            throw new BadRequestHttpException(__("Resource column $dataNameID not found", "resources"));
            return null;
        }
        return $dataNames[$dataNameID];
    }

    /**
     * @return string[]
     */
    public static function getDataTypes()
    {
        $dataTypes = [
            "String",
            "String",
            "ContractType",
            "User",
            "Location",
            "User",
            "Department",
            "Date",
            "Date",
            "User",
            "String",
            "Team",
        ];

        return $dataTypes;
    }

    public static function getDataType($dataNameId)
    {
        $dataTypes = self::getDataTypes();

        if (!array_key_exists($dataNameId, $dataTypes)) {
            throw new BadRequestHttpException(__("Data Type not found", "resources"));
            return null;
        }
        return $dataTypes[$dataNameId];
    }

    public static function getColumnName($dataNameId)
    {
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
            "plugin_resources_teams_id",
        ];

        if (!array_key_exists($dataNameId, $columnNames)) {
            throw new BadRequestHttpException(__("Resource column name not found", "resources"));
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
    public static function registerType($type)
    {
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
    public static function getTypes($all = false)
    {
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
    public function cleanDBonPurge()
    {
        $temp = new Resource_Item();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new Choice();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new Task();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']], 1);

        $temp = new Employee();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new ReportConfig();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new Checklist();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new ResourceResting();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new ResourceHoliday();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new ResourceHabilitation();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);
    }

    /**
     * Hook called After an item is purge
     *
     * @param CommonDBTM $item
     */
    public static function cleanForItem(CommonDBTM $item)
    {
        $type = get_class($item);
        $temp = new Resource_Item();
        $temp->deleteByCriteria([
            'itemtype' => $type,
            'items_id' => $item->getField('id'),
        ]);

        $task = new Task_Item();
        $task->deleteByCriteria([
            'itemtype' => $type,
            'items_id' => $item->getField('id'),
        ]);
    }

    public function playnotification($resource)
    {
        NotificationEvent::raiseEvent("AlertLeavingRessourceManager", $resource);
    }

    /**
     * Get Tab Name used for itemtype
     *
     * NB : Only called for existing object
     *      Must check right on what will be displayed + template
     *
     * @param CommonGLPI $item Item on which the tab need to be displayed
     * @param bool $withtemplate is a template object ? (default 0)
     *
     * @return string tab name
     **@since 0.83
     *
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == Client::class
            && $this->canView()) {
            return self::createTabEntry(self::getTypeName(2));
        }

        if ($item->getType() == self::class
            && $this->canView()) {
            return self::createTabEntry(__('Recruiting information', 'resources'));
        }
        return '';
    }

    /**
     * show Tab content
     *
     * @param CommonGLPI $item Item on which the tab need to be displayed
     * @param int $tabnum tab number (default 1)
     * @param bool $withtemplate is a template object ? (default 0)
     *
     * @return bool
     **@since 0.83
     *
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Client::class) {
            $self = new self();
            $self->showListResourcesForClient($item->getField('id'));
        }
        if ($item->getType() == self::class) {
            $wizard = new Wizard();
            $wizard->wizardEightStep($item->getField('id'), ['default_button' => true, 'target' => 'item']);
        }
        return true;
    }

    public static function getDefaultSearchRequest()
    {

        $config = new Config();
        $search = ['sort'  => $config->fields['order_column'],
            'order' => $config->fields['order_order'],];

        return $search;
    }

    /**
     * Get the Search options for the given Type
     *
     * This should be overloaded in Class
     *
     * @return an array of search options
     * More information on https://forge.indepnet.net/wiki/glpi/SearchEngine
     **/
    public function rawSearchOptions()
    {

        $tab[] = [
            'id' => 'common',
            'name' => self::getTypeName(2),
        ];

        $tab[] = [
            'id' => '1',
            'table' => $this->getTable(),
            'field' => 'name',
            'name' => __('Surname'),
            'datatype' => 'itemlink',
            'itemlink_type' => $this->getType(),
        ];

        $tab[] = [
            'id' => '2',
            'table' => $this->getTable(),
            'field' => 'firstname',
            'name' => __('First name'),
        ];

        $tab[] = [
            'id' => '37',
            'table' => 'glpi_plugin_resources_contracttypes',
            'field' => 'name',
            'name' => ContractType::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4',
            'table' => 'glpi_users',
            'field' => 'name',
            'name' => __('Resource manager', 'resources'),
            'datatype' => 'dropdown',
            'right' => 'all',
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[4] += ['searchtype' => 'contains'];
        }

        $tab[] = [
            'id' => '5',
            'table' => $this->getTable(),
            'field' => 'date_begin',
            'name' => __('Arrival date', 'resources'),
            'datatype' => 'date',
        ];
        $tab[] = [
            'id' => '6',
            'table' => $this->getTable(),
            'field' => 'date_end',
            'name' => __('Departure date', 'resources'),
            'datatype' => 'date',
        ];
        $tab[] = [
            'id' => '7',
            'table' => $this->getTable(),
            'field' => 'comment',
            'name' => __('Description'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id' => '8',
            'table' => 'glpi_plugin_resources_resources_items',
            'field' => 'items_id',
            'name' => _n('Associated item', 'Associated items', 2),
            'massiveaction' => false,
            'forcegroupby' => false,
            'nosearch' => false,
            'joinparams' => ['jointype' => 'child'],
        ];

        $tab[] = [
            'id' => '9',
            'table' => $this->getTable(),
            'field' => 'date_declaration',
            'name' => __('Request date'),
            'datatype' => 'date',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '10',
            'table' => 'glpi_users',
            'field' => 'name',
            'linkfield' => 'users_id_recipient',
            'name' => __('Requester'),
            'datatype' => 'dropdown',
            'right' => 'all',
            'massiveaction' => false,
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[10] += ['searchtype' => 'contains'];
        }
        $tab[] = [
            'id' => '11',
            'table' => 'glpi_plugin_resources_departments',
            'field' => 'name',
            'name' => Department::getTypeName(1),
            'datatype' => 'dropdown',
        ];
        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());
        $tab[] = [
            'id' => '36',
            'table' => $this->getTable(),
            'field' => 'is_leaving',
            'name' => __('Declared as leaving', 'resources'),
            'datatype' => 'bool',
        ];
        $tab[] = [
            'id' => '14',
            'table' => 'glpi_users',
            'field' => 'name',
            'linkfield' => 'users_id_recipient_leaving',
            'name' => __('Informant of leaving', 'resources'),
            'datatype' => 'dropdown',
            'right' => 'all',
            'massiveaction' => false,
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[2] += ['searchtype' => 'contains'];
        }

        $tab[] = [
            'id' => '15',
            'table' => $this->getTable(),
            'field' => 'is_helpdesk_visible',
            'name' => __('Associable to a ticket'),
            'datatype' => 'bool',
        ];
        $tab[] = [
            'id' => '16',
            'table' => $this->getTable(),
            'field' => 'date_mod',
            'name' => __('Last update'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '17',
            'table' => 'glpi_plugin_resources_resourcestates',
            'field' => 'name',
            'name' => ResourceState::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '18',
            'table' => $this->getTable(),
            'field' => 'picture',
            'name' => __('Photo', 'resources'),
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '19',
            'table' => $this->getTable(),
            'field' => 'is_recursive',
            'name' => __('Child entities'),
            'datatype' => 'bool',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '20',
            'table' => $this->getTable(),
            'field' => 'quota',
            'name' => __('Quota', 'resources'),
            'datatype' => 'decimal',
        ];
        //To have Field in dataInjection
        //      if (Session::getCurrentInterface() != 'central') {

        $tab[] = [
            'id' => '21',
            'table' => 'glpi_plugin_resources_resourcesituations',
            'field' => 'name',
            'name' => ResourceSituation::getTypeName(1),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '22',
            'table' => 'glpi_plugin_resources_contractnatures',
            'field' => 'name',
            'name' => ContractNature::getTypeName(1),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '23',
            'table' => 'glpi_plugin_resources_ranks',
            'field' => 'name',
            'name' => Rank::getTypeName(1),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '24',
            'table' => 'glpi_plugin_resources_resourcespecialities',
            'field' => 'name',
            'name' => ResourceSpeciality::getTypeName(1),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];
        //      }

        $tab[] = [
            'id' => '25',
            'table' => 'glpi_plugin_resources_leavingreasons',
            'field' => 'name',
            'name' => LeavingReason::getTypeName(1),
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '27',
            'table' => 'glpi_users',
            'field' => 'name',
            'linkfield' => 'users_id_sales',
            'name' => __('Sales manager', 'resources'),
            'datatype' => 'dropdown',
            'right' => 'all',
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[27] += ['searchtype' => 'contains'];
        }
        $tab[] = [
            'id' => '28',
            'table' => $this->getTable(),
            'field' => 'date_declaration_leaving',
            'name' => __('Declaration of departure date', 'resources'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];

        $config = new Config();
        if ($config->useSecurity()) {
            $tab[] = [
                'id' => '29',
                'table' => $this->getTable(),
                'field' => 'read_chart',
                'name' => __('Reading the security charter', 'resources'),
                'datatype' => 'bool',
                'massiveaction' => true,
            ];
            $tab[] = [
                'id' => '30',
                'table' => $this->getTable(),
                'field' => 'sensitize_security',
                'name' => __('Sensitized to security', 'resources'),
                'datatype' => 'bool',
                'massiveaction' => true,
            ];
        }

        $tab[] = [
            'id' => '32',
            'table' => 'glpi_plugin_resources_habilitations',
            'field' => 'name',
            'name' => Habilitation::getTypeName(),
            'datatype' => 'itemlink',
            'forcegroupby' => true,
            'massiveaction' => false,
            'joinparams' => [
                'beforejoin'
                => [
                    'table' => 'glpi_plugin_resources_resourcehabilitations',
                    'joinparams' => ['jointype' => 'child'],
                ],
            ],
        ];
        $tab[] = [
            'id' => '33',
            'table' => 'glpi_plugin_resources_employers',
            'field' => 'name',
            'name' => Employer::getTypeName(),
            'datatype' => 'itemlink',
            'forcegroupby' => false,
            'massiveaction' => false,
            'joinparams' => [
                'join'
                => [
                    'table' => 'glpi_plugin_resources_employees',
                    'joinparams' => ['jointype' => 'child'],
                ],
            ],
        ];
        $tab[] = [
            'id' => '34',
            'table' => 'glpi_plugin_resources_clients',
            'field' => 'name',
            'name' => Client::getTypeName(),
            'datatype' => 'itemlink',
            'forcegroupby' => false,
            'massiveaction' => false,
            'joinparams' => [
                'join'
                => [
                    'table' => 'glpi_plugin_resources_employees',
                    'joinparams' => ['jointype' => 'child'],
                ],
            ],
        ];
        if ($config->useSecurityCompliance()) {
            $tab[] = [
                'id' => '35',
                'table' => 'glpi_plugin_resources_employers',
                'field' => 'id',
                'name' => __('Client Sensitized to security', 'resources'),
                'datatype' => 'specific',
                'massiveaction' => false,
                'joinparams' => [
                    'join'
                    => [
                        'table' => 'glpi_plugin_resources_employees',
                        'joinparams' => ['jointype' => 'child'],
                    ],
                ],
            ];
        }
        $tab[] = [
            'id' => '31',
            'table' => $this->getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'massiveaction' => false,
            'datatype' => 'number',
        ];

        $tab[] = [
            'id' => '80',
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __('Entity'),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '38',
            'table' => $this->getTable(),
            'field' => 'sensitize_security',
            'name' => __('Sensitized to security', 'resources'),
            'datatype' => 'bool',
            'massiveaction' => true,
        ];
        $tab[] = [
            'id' => '39',
            'table' => $this->getTable(),
            'field' => 'matricule',
            'name' => __('Matricule', 'resources'),
            'datatype' => 'text',
            'massiveaction' => true,
        ];

        $tab[] = [
            'id' => '40',
            'table' => Role::getTable(),
            'field' => 'name',
            'name' => Role::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
        ];
        $tab[] = [
            'id' => '41',
            'table' => Service::getTable(),
            'field' => 'name',
            'name' => Service::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
        ];

        $tab[] = [
            'id' => '42',
            'table' => ResourceFunction::getTable(),
            'field' => 'name',
            'name' => ResourceFunction::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
        ];

        $tab[] = [
            'id' => '43',
            'table' => Team::getTable(),
            'field' => 'name',
            'name' => Team::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
            'nosort' => true,
        ];

        $tab[] = [
            'id' => '44',
            'table' => $this->getTable(),
            'field' => 'matricule_second',
            'name' => __('Second matricule', 'resources'),
        ];
        $tab[] = [
            'id' => '45',
            'table' => $this->getTable(),
            'field' => 'date_agreement_candidate',
            'name' => __('Date agreement candidate', 'resources'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '46',
            'table' => DegreeGroup::getTable(),
            'field' => 'name',
            'name' => DegreeGroup::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
        ];
        $tab[] = [
            'id' => '47',
            'table' => RecruitingSource::getTable(),
            'field' => 'name',
            'name' => RecruitingSource::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
        ];
        $tab[] = [
            'id' => '48',
            'table' => $this->getTable(),
            'field' => 'yearsexperience',
            'name' => __('Number of years experience', 'resources'),
            'massiveaction' => false,
            'datatype' => 'number',
        ];
        $tab[] = [
            'id' => '49',
            'table' => $this->getTable(),
            'field' => 'reconversion',
            'name' => __('Reconversion', 'resources'),
            'datatype' => 'bool',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '50',
            'table' => $this->getTable(),
            'field' => 'date_of_last_contract_type',
            'name' => __('Date of last contract type', 'resources'),
            'datatype' => 'date',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '54',
            'table' => 'glpi_plugin_resources_contracttypes',
            'field' => 'name',
            'linkfield' => 'last_contract_type',
            'name' => __('Last contract type', 'resources'),
            'datatype' => 'dropdown',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '52',
            'table' => $this->getTable(),
            'field' => 'date_of_last_location',
            'name' => __('Date of last location', 'resources'),
            'datatype' => 'date',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '53',
            'table' => Location::getTable(),
            'field' => 'name',
            'linkfield' => 'last_location',
            'name' => __('Last location', 'resources'),
            'datatype' => 'dropdown',
            'massiveaction' => false,
        ];

        //        $tab[] = [
        //            'id'        => '54',
        //            'table'     => 'glpi_plugin_resources_workprofiles',
        //            'field'     => 'name',
        //            'linkfield' => 'plugin_resources_workprofiles_id_entrance',
        //            'name'      => WorkProfile::getTypeName(),
        //            'datatype'  => 'dropdown',
        //            'right'     => 'all'
        //        ];
        $tab[] = [
            'id' => '55',
            'table' => 'glpi_plugin_resources_candidateorigins',
            'field' => 'name',
            'linkfield' => 'plugin_resources_candidateorigins_id',
            'name' => Candidateorigin::getTypeName(),
            'datatype' => 'dropdown',
            'right' => 'all',
        ];
        $tab[] = [
            'id' => '56',
            'table' => $this->getTable(),
            'field' => 'gender',
            'name' => __('Gender', 'resources'),
            'datatype' => 'specific',
            'searchtype' => ['equals', 'notequals'],
        ];
        //      $tab[] = [
        //         'id'    => '45',
        //         'table' => $this->getTable(),
        //         'field' => 'society',
        //         'name'  => __('Society', 'resources'),
        //      ];

        $tab = array_merge($tab, LeavingInformation::rawSearchOptionsToAdd(get_class($this)));
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
    public function defineTabs($options = [])
    {
        $ong = [];

        $config = new Config();

        $this->addDefaultFormTab($ong);
        $this->addStandardTab(Resource::class, $ong, $options);
        if (Session::getCurrentInterface() == 'central') {

            $this->addStandardTab(Resource_Item::class, $ong, $options);

            $resourceItem = new Resource_Item();
            $resourceUsers = $resourceItem->find([
                'plugin_resources_resources_id' => $this->getID(),
                'itemtype' => 'User',
            ]);

            if (is_array($resourceUsers)
                && count($resourceUsers) > 0) {
                $this->addStandardTab(User::class, $ong, $options);
            }
        }

        $this->addStandardTab(Choice::class, $ong, $options);
        $canViewSynchroADView = json_decode($config->fields['can_view_synchronisationAD']);
        $canViewSynchroADView = is_array($canViewSynchroADView) ? $canViewSynchroADView : [];

        if ($config->fields['use_module_validation'] && ((!$this->fields['valid_resource_information'] && Session::getLoginUserID() == $this->fields['users_id']) ||
            ($this->fields['valid_resource_information'] && in_array($_SESSION['glpiactiveprofile']['id'], $canViewSynchroADView)))
        ) {
            $this->addStandardTab(Resource_Validation::class, $ong, $options);
        }
        $this->addStandardTab(ResourceHabilitation::class, $ong, $options);
        $this->addStandardTab(Employment::class, $ong, $options);
        $this->addStandardTab(Employee::class, $ong, $options);
        $this->addStandardTab(LeavingInformation::class, $ong, $options);
        if ($config->fields['use_module_departure_instruction']) {
            $this->addStandardTab(Resource_Leaving::class, $ong, $options);
        }
        $this->addStandardTab(Checklist::class, $ong, $options);
        $this->addStandardTab(Task::class, $ong, $options);

        if (Session::getCurrentInterface() == 'central') {
            $this->addStandardTab(ResourceImport::class, $ong, $options);
            if ($config->fields['view_notification_tab']) {
                $this->addStandardTab(ReportConfig::class, $ong, $options);
            }
            $this->addStandardTab(Document_Item::class, $ong, $options);

            if (!isset($options['withtemplate']) || empty($options['withtemplate'])) {
                $this->addStandardTab(Item_Ticket::class, $ong, $options);
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
    public function getHiddenFields($input)
    {
        $need = [];
        $rulecollection = new RuleContracttypeHiddenCollection($input['entities_id']);

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

    public function getReadonlyFields($input)
    {

        $rulecollection = new RuleContracttypeReadonlyCollection($input['entities_id']);

        $fields = [];
        $fields = $rulecollection->processAllRules($input, $fields, []);

        $field = [];
        foreach ($fields as $key => $val) {
            $hidden = explode("readonlyfields_", $key);
            if (isset($hidden[1])) {
                $field[] = $hidden[1];
            }
        }

        return $field;
    }

    public function afterInsert(Item_Ticket $input)
    {
        if ($input->fields['itemtype'] === Resource::class) {
            $ticket = new Ticket();
            $ticket->getFromDB($input->fields['tickets_id']);
            $config = new Config();
            $config ->getFromDB(1);
            $date = new DateTime();
            $datecreation = new DateTime($ticket->fields['date_creation']);
            $seconds = $date->getTimestamp() - $datecreation->getTimestamp();
            if ($config->fields["default_assignment_group"] && $seconds < 30) {
                $groupticket = new Group_Ticket();
                $groupticket->fields['tickets_id'] = $input->fields['tickets_id'];
                $groupticket->fields['groups_id'] = $config->fields["default_assignment_group"];
                $groupticket->fields['type'] = CommonITILActor::ASSIGN;
                unset($groupticket->fields["id"]);
                $groupticket->add($groupticket->fields);
            }
        }
    }

    /**
     * @param $input
     *
     * @return array
     */
    public function checkRequiredFields($input)
    {
        $need = [];
        if (isset($input['entities_id'])) {
            $rulecollection = new RuleContracttypeCollection($input['entities_id']);

            $fields = [];
            $fields = $rulecollection->processAllRules($input, $fields, []);

            $rank = new Rank();

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
                        if (isset($input['more_information'])) {
                            if (!$rank->canCreate()
                                && !in_array(
                                    $val,
                                    [
                                        'date_agreement_candidate',
                                        'plugin_resources_degreegroups_id',
                                        'plugin_resources_recruitingsources_id',
                                        'yearsexperience',
                                        'reconversion',
                                        'interview_date',
                                        'plugin_resources_workprofiles_id',
                                        'plugin_resources_clients_id',
                                        'resignation_date',
                                        'wished_leaving_date',
                                        'effective_leaving_date',
                                        'plugin_resources_destinations_id',
                                        'plugin_resources_leavingreasons_id',
                                        'company_name',
                                        'pay_gap',
                                        'mission_lost',
                                    ],
                                )
                            ) {
                            } else {
                                $need[] = $val;
                            }
                        } else {
                            if ((!$rank->canCreate()
                                    && in_array(
                                        $val,
                                        [
                                            'plugin_resources_ranks_id',
                                            'plugin_resources_resourcesituations_id',
                                            'date_agreement_candidate',
                                            'plugin_resources_degreegroups_id',
                                            'plugin_resources_recruitingsources_id',
                                            'yearsexperience',
                                            'reconversion',
                                            'interview_date',
                                            'plugin_resources_workprofiles_id',
                                            'plugin_resources_clients_id',
                                            'resignation_date',
                                            'wished_leaving_date',
                                            'effective_leaving_date',
                                            'plugin_resources_destinations_id',
                                            'plugin_resources_leavingreasons_id',
                                            'company_name',
                                            'pay_gap',
                                            'mission_lost',
                                        ],
                                    )) || in_array(
                                        $val,
                                        [
                                            'date_agreement_candidate',
                                            'plugin_resources_degreegroups_id',
                                            'plugin_resources_recruitingsources_id',
                                            'yearsexperience',
                                            'reconversion',
                                            'interview_date',
                                            'plugin_resources_workprofiles_id',
                                            'plugin_resources_clients_id',
                                            'resignation_date',
                                            'wished_leaving_date',
                                            'effective_leaving_date',
                                            'plugin_resources_destinations_id',
                                            'plugin_resources_leavingreasons_id',
                                            'company_name',
                                            'pay_gap',
                                            'mission_lost',
                                        ],
                                    )
                            ) {
                            } else {
                                $need[] = $val;
                            }
                        }
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
    public function prepareInputForAdd($input)
    {
        if (!isset($input["is_template"])) {
            if (!isset($input['force'])) {
                $required = $this->checkRequiredFields($input);
                $input['plugin_resources_profiletypes_id'] = $_SESSION["glpiactiveprofile"]['id'];
                $input['plugin_resources_grouptypes_id'] = $_SESSION["glpigroups"];

                if (count($required) > 0) {
                    Session::addMessageAfterRedirect(
                        __('Required fields are not filled. Please try again.', 'resources'),
                        false,
                        ERROR,
                    );
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
        $uploadedfile = self::getUploadedPicturePath($input);
        if ($uploadedfile !== '') {
            if (exif_imagetype($uploadedfile) === IMAGETYPE_JPEG) {
                $max_size = Toolbox::return_bytes_from_ini_vars(ini_get("upload_max_filesize"));
                if (filesize($uploadedfile) <= $max_size) {
                    if (is_writable(GLPI_PLUGIN_DOC_DIR . "/resources/pictures/")) {
                        $input['picture'] = $this->addPhoto($this, $uploadedfile);
                    }
                } else {
                    Session::addMessageAfterRedirect(__('Failed to send the file (probably too large)'), false, ERROR);
                }
            } else {
                Session::addMessageAfterRedirect(__('Invalid filename'), false, ERROR);
            }
        }

        $template_resources = new Resource();
        if (isset($this->input['id_template'])) {
            if ($template_resources->getFromDBByCrit([
                'id' => $this->input['id_template'],
                'is_template' => 1,
            ])) {
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
    public function post_addItem()
    {
        global $CFG_GLPI;

        //      if ($this->fields['id'] == 0) {
        //         $this->getFromDBByCrit(
        //            [ 'name'      => $this->fields['name'],
        //               'firstname' => $this->fields['firstname']]);
        //      }
        //       Manage add from template

        if (isset($this->input["resources_oldID"])) {
            // ADD choices
            Choice::cloneItem($this->input["resources_oldID"], $this->fields['id']);

            // ADD habilitations
            ResourceHabilitation::cloneItem($this->input["resources_oldID"], $this->fields['id']);

            // ADD items
            Resource_Item::cloneItem($this->input["resources_oldID"], $this->fields['id']);

            // ADD reports
            ReportConfig::cloneItem($this->input["resources_oldID"], $this->fields['id']);

            //manage template from helpdesk (no employee to add : resource.form.php)
            if (!isset($this->input["add_from_helpdesk"])) {
                Employee::cloneItem($this->input["resources_oldID"], $this->fields['id']);
            }
            // ADD Documents
            $document_items = Document_Item::getItemsAssociatedTo($this->getType(), $this->fields['id']);
            $override_input['items_id'] = $this->getID();
            foreach ($document_items as $document_item) {
                $document_item->clone($override_input);
            }

            // ADD tasks
            Task::cloneItem($this->input["resources_oldID"], $this->fields['id']);
        }

        //ADD Checklists from rules
        $Checklistconfig = new Checklistconfig();
        $Checklistconfig->addChecklistsFromRules($this, Checklist::RESOURCES_CHECKLIST_IN);
        $Checklistconfig->addChecklistsFromRules($this, Checklist::RESOURCES_CHECKLIST_OUT);
        $Checklistconfig->addChecklistsFromRules($this, Checklist::RESOURCES_CHECKLIST_TRANSFER);

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

    public function post_getFromDB()
    {
        $this->fields['states_id'] = 1;
    }

    /**
     * @param        $str
     * @param string $charset
     *
     * @return mixed|string
     */
    public function replace_accents($str, $charset = 'utf-8')
    {
        $str = htmlentities($str, ENT_NOQUOTES, $charset);

        $str = preg_replace('#\&([A-za-z])(?:acute|cedil|circ|grave|ring|tilde|uml)\;#', '\1', $str);
        $str = preg_replace('#\&([A-za-z]{2})(?:lig)\;#', '\1', $str); // pour les ligatures e.g. '&oelig;'
        $str = preg_replace('#\&[^;]+\;#', '', $str); // supprime les autres caractères

        return $str;
    }

    /**
     * Resolve the picture sent by the asynchronous uploader of Html::file().
     *
     * jQuery File Upload replaces the file input as soon as it is filled, so $_FILES is
     * always empty when the form is finally submitted: the file has already been moved to
     * GLPI_TMP_DIR and only its name is posted in _picture[0]. Containment is enforced with
     * realpath() so a crafted name cannot escape the temporary directory.
     *
     * @param array $input
     *
     * @return string Absolute path of the temporary file, or an empty string when there is none
     */
    public static function getUploadedPicturePath(array $input): string
    {
        // The uploader posts _picture as an array, but a hand crafted request may send a scalar:
        // do not index it blindly, "abc"[0] would silently yield "a" instead of falling back.
        $picture = $input['_picture'] ?? '';
        if (is_array($picture)) {
            $picture = reset($picture);
        }
        $filename = is_string($picture) ? $picture : '';
        if ($filename === '') {
            return '';
        }

        $tmp_dir  = realpath(GLPI_TMP_DIR);
        $fullpath = realpath(GLPI_TMP_DIR . "/" . $filename);

        if ($tmp_dir === false
            || $fullpath === false
            || !str_starts_with($fullpath, $tmp_dir . DIRECTORY_SEPARATOR)) {
            return '';
        }

        return $fullpath;
    }

    /**
     * @param $class
     * @param $uploadedfile Absolute path of the uploaded temporary file
     *
     * @return mixed|string
     */
    public function addPhoto($class, $uploadedfile = null)
    {
        if ($uploadedfile === null) {
            $uploadedfile = self::getUploadedPicturePath(is_array($this->input) ? $this->input : []);
        }

        // Fail-closed defense in depth: never trust the caller's pre-checks. Only process a
        // file that GD confirms is a genuine JPEG, so a spoofed Content-Type or a forged
        // temp path cannot reach imagecreatefromjpeg(). Containment inside GLPI_TMP_DIR is
        // guaranteed by getUploadedPicturePath().
        if ($uploadedfile === '' || !is_file($uploadedfile)
            || exif_imagetype($uploadedfile) !== IMAGETYPE_JPEG) {
            return '';
        }

        $src = imagecreatefromjpeg($uploadedfile);

        [$width, $height] = getimagesize($uploadedfile);

        $newwidth = 75;
        $newheight = ($height / $width) * $newwidth;
        $tmp = imagecreatetruecolor($newwidth, $newheight);

        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        $ext = 'jpg';
        // The photo filename is derived from user-controlled resource fields (name/firstname).
        // replace_accents() only transliterates accents — it leaves path separators (/, \, ..)
        // untouched, so an unsanitized name lets the JPEG be written outside the pictures
        // directory (path traversal on imagejpeg()/rename()). Strip every character outside
        // [a-z0-9_-] from each component before building the path, mirroring the read-side
        // hardening already applied in picture.send.php.
        $resources_name = str_replace(" ", "", strtolower($class->fields["name"]));
        $resources_firstname = str_replace(" ", "", strtolower($class->fields["firstname"]));

        $resources_name = preg_replace('/[^a-z0-9_-]/', '', $this->replace_accents($resources_name));
        $resources_firstname = preg_replace('/[^a-z0-9_-]/', '', $this->replace_accents($resources_firstname));

        $name = $resources_name . "_" . $resources_firstname . "." . $ext;

        $tmpfile = GLPI_DOC_DIR . "/_uploads/" . $name;
        $filename = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $name;

        imagejpeg($tmp, $tmpfile, 100);

        rename($tmpfile, $filename);

        // The uploader left the source file in GLPI_TMP_DIR: the resized copy is stored, drop it.
        @unlink($uploadedfile);

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
    public function prepareInputForUpdate($input)
    {
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

        $uploadedfile = self::getUploadedPicturePath($input);
        if (!isset($input['_UpdateFromUser_']) && $uploadedfile !== '') {
            if (exif_imagetype($uploadedfile) === IMAGETYPE_JPEG) {
                $max_size = Toolbox::return_bytes_from_ini_vars(ini_get("upload_max_filesize"));
                if (filesize($uploadedfile) <= $max_size) {
                    $input['picture'] = $this->addPhoto($this, $uploadedfile);
                } else {
                    Session::addMessageAfterRedirect(__('Failed to send the file (probably too large)'), false, ERROR);
                }
            } else {
                Session::addMessageAfterRedirect(__('Invalid filename'), false, ERROR);
            }
        } elseif (isset($input['picture']) && $input['picture'] != "NULL") {
            // The posted "picture" field only feeds the delete_picture action of the front
            // controller, which sets it to NULL. Outside that case the stored file name is
            // never client supplied, so drop the value instead of writing it back.
            unset($input['picture']);
        }

        $input["_old_name"] = $this->fields["name"];
        $input["_old_firstname"] = $this->fields["firstname"];
        $input["_old_plugin_resources_contracttypes_id"] = $this->fields["plugin_resources_contracttypes_id"];
        $input["_old_users_id"] = $this->fields["users_id"];
        $input["_old_users_id_sales"] = $this->fields["users_id_sales"];
        $input["_old_users_id_recipient"] = $this->fields["users_id_recipient"];
        $input["_old_date_declaration"] = $this->fields["date_declaration"];
        $input["_old_date_begin"] = $this->fields["date_begin"];
        $input["_old_date_end"] = $this->fields["date_end"];
        $input["_old_quota"] = $this->fields["quota"];
        $input["_old_plugin_resources_departments_id"] = $this->fields["plugin_resources_departments_id"];
        $input["_old_plugin_resources_resourcestates_id"] = $this->fields["plugin_resources_resourcestates_id"];
        $input["_old_plugin_resources_resourcesituations_id"] = $this->fields["plugin_resources_resourcesituations_id"];
        $input["_old_plugin_resources_contractnatures_id"] = $this->fields["plugin_resources_contractnatures_id"];
        $input["_old_plugin_resources_ranks_id"] = $this->fields["plugin_resources_ranks_id"];
        $input["_old_plugin_resources_resourcespecialities_id"] = $this->fields["plugin_resources_resourcespecialities_id"];
        $input["_old_locations_id"] = $this->fields["locations_id"];
        $input["_old_is_leaving"] = $this->fields["is_leaving"];
        $input["_old_date_declaration_leaving"] = $this->fields["date_declaration_leaving"];
        $input["_old_plugin_resources_leavingreasons_id"] = $this->fields["plugin_resources_leavingreasons_id"];
        $input["_old_comment"] = $this->fields["comment"];
        $input["_old_sensitize_security"] = $this->fields["sensitize_security"];
        $input["_old_read_chart"] = $this->fields["read_chart"];

        return $input;
    }

    /**
     * Actions done before the UPDATE of the item in the database
     *
     * @return nothing
     **/
    public function pre_updateInDB()
    {
        $Resource_Item = new Resource_Item();
        //if leaving field is updated  && isset($this->input["withtemplate"]) && $this->input["withtemplate"]!=1

        $this->input["checkbadge"] = 0;

        if (isset($this->input["is_leaving"])
            && $this->input["is_leaving"] == 1
            && in_array("is_leaving", $this->updates)) {
            if ((!(isset($this->input["date_end"]))
                    || $this->input["date_end"] == 'NULL')
                || (!(isset($this->fields["date_end"]))
                    || $this->fields["date_end"] == 'NULL')) {
                Session::addMessageAfterRedirect(
                    __('End date was not completed. Please try again.', 'resources'),
                    false,
                    ERROR,
                );
                Html::back();
            } else {
                $this->fields["users_id_recipient_leaving"] = Session::getLoginUserID();
                $this->fields["date_declaration_leaving"] = date('Y-m-d H:i:s');
                $this->updates[] = "users_id_recipient_leaving";
                $this->updates[] = "date_declaration_leaving";

                $resources_checklist = Checklist::checkIfChecklistExist(
                    $this->fields["id"],
                    Checklist::RESOURCES_CHECKLIST_OUT,
                );
                if (!$resources_checklist) {
                    $Checklistconfig = new Checklistconfig();
                    $Checklistconfig->addChecklistsFromRules($this, Checklist::RESOURCES_CHECKLIST_OUT);
                }
            }
        } elseif (isset($this->input["is_leaving"])
            && $this->input["is_leaving"] == 0
            && in_array("is_leaving", $this->updates)) {
            $this->fields["users_id_recipient_leaving"] = 0;
            $this->fields["date_declaration_leaving"] = 'NULL';
            $this->fields["date_end"] = 'NULL';
            $this->fields["plugin_resources_leavingreasons_id"] = 0;
            $this->updates[] = "users_id_recipient_leaving";
            $this->updates[] = "date_declaration_leaving";
            $this->updates[] = "plugin_resources_leavingreasons_id";
            $this->updates[] = "date_end";
        }

        //if location field is updated
        if (isset($this->fields["locations_id"])
            && isset($this->input["_old_locations_id"])
            && !isset($this->input["_UpdateFromUser_"])
            && $this->fields["locations_id"] != $this->input["_old_locations_id"]) {
            $Resource_Item->updateLocation($this->fields, Resource::class);
        }

        $this->input["addchecklist"] = 0;
        if (isset($this->fields["plugin_resources_contracttypes_id"])
            && isset($this->input["_old_plugin_resources_contracttypes_id"])
            && $this->fields["plugin_resources_contracttypes_id"] != $this->input["_old_plugin_resources_contracttypes_id"]
        ) {
            $config = new Config();
            $config->getFromDB(1);
            if ($config->fields["reaffect_checklist_change"] == 1) {
                $this->input["addchecklist"] = 1;
            }
        }

        if (isset($this->input['plugin_resources_departments_id']) && isset($this->oldvalues['plugin_resources_departments_id'])
            && $this->input['plugin_resources_departments_id'] != $this->oldvalues['plugin_resources_departments_id']) {
            $plugin_resources_department_service = new Department_Service();
            $service_id = $this->input['plugin_resources_services_id'] ?? $this->fields['plugin_resources_services_id'];
            if (!$plugin_resources_department_service->getFromDBByCrit(
                [
                    'plugin_resources_departments_id' => $this->input['plugin_resources_departments_id'],
                    'plugin_resources_services_id' => $service_id,
                ],
            )) {
                $this->fields["plugin_resources_services_id"] = 0;
                $this->updates[] = "plugin_resources_services_id";
                $this->fields["plugin_resources_roles_id"] = 0;
                $this->updates[] = "plugin_resources_roles_id";
            }
        }

        if (isset($this->input['plugin_resources_services_id']) && isset($this->oldvalues['plugin_resources_services_id'])
            && $this->input['plugin_resources_services_id'] != $this->oldvalues['plugin_resources_services_id']) {
            $plugin_resources_service_role = new Role_Service();
            $role_id = $this->input['plugin_resources_roles_id'] ?? $this->fields['plugin_resources_roles_id'];
            if (!$plugin_resources_service_role->getFromDBByCrit(
                [
                    'plugin_resources_services_id' => $this->input['plugin_resources_services_id'],
                    'plugin_resources_roles_id' => $role_id,
                ],
            )) {
                $this->fields["plugin_resources_roles_id"] = 0;
                $this->updates[] = "plugin_resources_roles_id";
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
    public function post_updateItem($history = 1)
    {
        global $CFG_GLPI, $DB;

        $Checklist = new Checklist();
        $config = new Config();
        $config->getFromDB(1);
        if ($config->fields["mandatory_adcreation"] == 1) {
            if (isset($this->input["addchecklist"])
                && $this->input["addchecklist"] == 1) {
                $Checklist->deleteByCriteria(['plugin_resources_resources_id' => $this->fields["id"]]);

                $Checklistconfig = new Checklistconfig();
                $Checklistconfig->addChecklistsFromRules(
                    $this,
                    Checklist::RESOURCES_CHECKLIST_IN,
                );
                $Checklistconfig->addChecklistsFromRules(
                    $this,
                    Checklist::RESOURCES_CHECKLIST_OUT,
                );
                $Checklistconfig->addChecklistsFromRules(
                    $this,
                    Checklist::RESOURCES_CHECKLIST_TRANSFER,
                );
            }
        }
        $status = "update";
        if (isset($this->fields["is_leaving"])
            && !empty($this->fields["is_leaving"])) {
            $status = "LeavingResource";
            $Resource_Item = new Resource_Item();
            $badge = $Resource_Item->searchAssociatedBadge($this->fields["id"]);
            if ($badge) {
                $this->input["checkbadge"] = 1;
            }

            //when a resource is leaving, current employment get default state
            if (isset($this->input['date_end'])) {
                $Employment = new Employment();
                $default = EmploymentState::getDefault();
                // only current employment
                //                $restrict = "`plugin_resources_resources_id` = '" . $this->input["id"] . "'
                //                        AND ((`begin_date` < '" . $this->input['date_end'] . "'
                //                              OR `begin_date` IS NULL)
                //                              AND (`end_date` > '" . $this->input['date_end'] . "'
                //                                    OR `end_date` IS NULL)) ";
                //

                $criteria = [
                    'SELECT' => ['glpi_plugin_resources_employments.*'],
                    'FROM' => 'glpi_plugin_resources_employments',
                    'WHERE' => [
                        'plugin_resources_resources_id' => $this->input["id"],
                        'begin_date' => ['<', $this->input['date_end']],
                        [
                            "OR" => [
                                ['begin_date' => null],
                            ],
                        ],
                        'end_date' => ['>', $this->input['date_end']],
                        [
                            "OR" => [
                                ['end_date' => null],
                            ],
                        ],
                    ],
                ];

                $iterator = $DB->request($criteria);

                foreach ($iterator as $employment) {
                    $values = [
                        'plugin_resources_employmentstates_id' => $default,
                        'end_date' => $this->input['date_end'],
                        'id' => $employment['id'],
                    ];
                    $Employment->update($values);
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
    public function pre_deleteItem()
    {
        global $CFG_GLPI;

        if (isset($this->input['picture']) && $this->input['picture'] != "" && $this->input['picture'] != "null" && $this->input['picture'] != "NULL") {
            // 'picture' is a flat filename (name_firstname.jpg); apply basename() so a crafted
            // input (e.g. picture=../../config/glpicrypt.key posted on delete) cannot make
            // unlink() remove an arbitrary file outside the pictures directory.
            $filename = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . basename((string) $this->input['picture']);
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
    public static function dropdownTemplate($name, $value = 0, $skip_profiles = false)
    {
        $dbu = new DbUtils();
        $self = new self();
        $restrict = ["is_template" => 1]
            + $dbu->getEntitiesRestrictCriteria($self->getTable(), '', '', $self->maybeRecursive())
            + ["ORDER" => "template_name"]
            + ["GROUPBY" => "template_name"];

        $dbu = new DbUtils();
        $templates = $dbu->getAllDataFromTable($self->getTable(), $restrict);

        $config = new Config();
        $config->getFromDB(1);
        $option = [];
        if ($config->fields['allow_without_contract'] == 0) {
            $option[-1] = __('Without contract', 'resources');
        }
        if ($value == 0) {
            $value = $config->fields['plugin_resources_resourcetemplates_id'];
        }
        $available_contracttype = false;
        $contracttypeprofile = new Contracttypeprofile();
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
        Dropdown::showFromArray($name, $option, ['value' => $value]);
    }

    /**
     * Return the SQL command to retrieve linked object
     *
     * @return a SQL command which return a set of (itemtype, items_id)
     */
    public function getSelectLinkedItem()
    {
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
    public function showForm($ID, $options = [])
    {
        $this->initForm($ID, $options);

        $config = new Config();

        $input = [
            'entities_id'                       => $this->fields["entities_id"] ?? $_SESSION['glpiactive_entity'],
            'plugin_resources_contracttypes_id' => $this->fields["plugin_resources_contracttypes_id"],
            'plugin_resources_profiletypes_id'  => $_SESSION["glpiactiveprofile"]['id'],
            'plugin_resources_grouptypes_id'    => $_SESSION["glpigroups"],
            'plugin_resources_users_id'         => Session::getLoginUserID(),
            'plugin_resources_users_id_reel'    => $this->fields['users_id'],
        ];

        // The rule collections return flat lists of field names. Flipping them into maps lets the
        // template test them with "hidden.<field> is defined", the way the wizard already does.
        $hidden    = array_flip($this->getHiddenFields($input));
        $readonly  = array_flip($this->getReadonlyFields($input));
        $mandatory = array_flip($this->checkRequiredFields($input));

        $is_central   = Session::getCurrentInterface() == 'central';
        $withtemplate = $options['withtemplate'] ?? 0;

        $contracttype     = new ContractType();
        $second_matricule = false;
        $display_employee = false;
        $condition_emp    = ['second_list' => 0];
        if ($contracttype->getFromDB($this->fields["plugin_resources_contracttypes_id"])) {
            $second_matricule = $contracttype->fields["use_second_matricule"] > 0;
            $display_employee = $contracttype->fields["use_employee_wizard"] > 0;
            if ($contracttype->fields["use_second_list_employer"] > 0) {
                $condition_emp = ['second_list' => 1];
            }
        }

        // The employer is not a resource column: it is carried by the Employee relation.
        $employers_id      = 0;
        $can_read_employee = Session::haveRight('plugin_resources_employee_core_form', READ) && !$display_employee;
        if ($can_read_employee) {
            $employee = new Employee();
            if ($employee->getFromDBByCrit(['plugin_resources_resources_id' => $this->getID()])) {
                $employers_id = $employee->fields['plugin_resources_employers_id'];
            }
        }

        $use_secondary_services = $config->useSecondaryService() && $config->useServiceDepartmentAD();
        $secondary_services     = [];
        $secondary_values       = [];
        if ($use_secondary_services) {
            $usercategory = new UserCategory();
            foreach ($usercategory->find() as $category) {
                $secondary_services[$category['id']] = $category['name'];
            }
            $decoded          = json_decode($this->fields['secondary_services'] ?? '', true);
            $secondary_values = is_array($decoded) ? $decoded : [];
        }

        // Shared by the whole department/service/role chain: the AJAX handlers target
        // "dropdown_<name><rand>", so the PHP dropdowns and the template must agree on it.
        $rand = mt_rand();

        $rank                = new Rank();
        $can_view_rank       = $rank->canView();
        $situation_dropdown  = '';
        $contractnature_html = '';
        $rank_dropdown       = '';
        $speciality_html     = '';
        if ($can_view_rank) {
            $situation = self::buildChainedDropdownPair(
                ResourceSituation::class,
                'plugin_resources_resourcesituations_id',
                $this->fields['plugin_resources_resourcesituations_id'],
                $this->fields["entities_id"],
                'dropdownContractnature.php',
                'span_contractnature',
                'glpi_plugin_resources_contractnatures',
                $this->fields["plugin_resources_contractnatures_id"],
            );
            $situation_dropdown  = $situation['dropdown'];
            $contractnature_html = $situation['span'];

            $rank_pair = self::buildChainedDropdownPair(
                Rank::class,
                'plugin_resources_ranks_id',
                $this->fields['plugin_resources_ranks_id'],
                $this->fields["entities_id"],
                'dropdownSpeciality.php',
                'span_speciality',
                'glpi_plugin_resources_resourcespecialities',
                $this->fields["plugin_resources_resourcespecialities_id"],
            );
            $rank_dropdown   = $rank_pair['dropdown'];
            $speciality_html = $rank_pair['span'];
        }

        $organisation = $this->buildOrganisationFields($readonly, $rand, (bool) $config->useServiceDepartmentAD());

        $resource_managers = null;
        if ($config->getField('resource_manager') != "") {
            $resource_managers = self::getManagerDropdownValues(
                $config->getField('resource_manager'),
                $this->fields["entities_id"],
            );
        }
        $sales_managers = null;
        if ($config->getField('sales_manager') != "") {
            $sales_managers = self::getManagerDropdownValues(
                $config->getField('sales_manager'),
                $this->fields["entities_id"],
            );
        }

        $picture_url = '';
        if (!empty($this->fields["picture"])
            && file_exists(GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $this->fields["picture"])
        ) {
            $picture_url = PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $this->fields["picture"];
        }

        // "By <user> - <date>", appended to the "Declared as leaving" field
        $leaving_by = '';
        if ($ID != -1 && $withtemplate != 1 && $this->fields["is_leaving"] == 1
            && isset($this->fields["users_id_recipient_leaving"])
        ) {
            $leaving_by = htmlescape(
                __('By') . " " . getUserName($this->fields["users_id_recipient_leaving"]),
            );
            if (!empty($this->fields["date_declaration_leaving"])) {
                $leaving_by .= " - " . htmlescape(Html::convDateTime($this->fields["date_declaration_leaving"]));
            }
        }

        $params = $options;
        if (!$is_central) {
            $params['candel'] = false;
        }
        $params['hidden_fields']    = $hidden;
        $params['readonly_fields']  = $readonly;
        $params['mandatory_fields'] = $mandatory;

        TemplateRenderer::getInstance()->display('@resources/resource_form.html.twig', [
            'item'                       => $this,
            'params'                     => $params,
            'is_central'                 => $is_central,
            'plugin_rand'                => $rand,
            'root_resources'             => PLUGIN_RESOURCES_WEBDIR,
            'user_class'                 => \User::class,
            'location_class'             => Location::class,
            'resourcestate_class'        => ResourceState::class,
            'contracttype_class'         => ContractType::class,
            'resourcefunction_class'     => ResourceFunction::class,
            'team_class'                 => Team::class,
            'employer_class'             => Employer::class,
            'leavingreason_class'        => LeavingReason::class,
            'genders'                    => self::getGenders(),
            'quota_value'                => Html::formatNumber($this->fields["quota"], true, 4),
            'can_view_rank'              => $can_view_rank,
            'can_read_employee'          => $can_read_employee,
            'can_set_recipient'          => $this->canCreate() && $is_central,
            'second_matricule'           => $second_matricule,
            'use_security'               => (bool) $config->useSecurity(),
            'use_services_deparments_ad' => (bool) $config->useServiceDepartmentAD(),
            'use_secondary_services'     => $use_secondary_services,
            'secondary_services'         => $secondary_services,
            'secondary_values'           => $secondary_values,
            'condition_emp'              => $condition_emp,
            'employers_id'               => $employers_id,
            'resource_managers'          => $resource_managers,
            'sales_managers'             => $sales_managers,
            'has_leavingreasons'         => countDistinctElementsInTable(LeavingReason::getTable(), 'id') > 0,
            'picture_url'                => $picture_url,
            'empty_picture'              => PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png",
            'max_upload_size'            => Document::getMaxUploadSize(),
            'situation_dropdown'         => $situation_dropdown,
            'contractnature_html'        => $contractnature_html,
            'rank_dropdown'              => $rank_dropdown,
            'speciality_html'            => $speciality_html,
            'department_dropdown'        => $organisation['department'],
            'service_dropdown'           => $organisation['service'],
            'role_dropdown'              => $organisation['role'],
            'organisation_script'        => $organisation['script'],
            'declaration_date'           => Html::convDate($this->fields["date_declaration"]),
            'recipient_name'             => getUserName($this->fields["users_id_recipient"]),
            'date_end_display'           => Html::convDate($this->fields["date_end"]),
            'leaving_by'                 => $leaving_by,
            'remove_url'                 => PLUGIN_RESOURCES_WEBDIR . "/front/resource.remove.php?resource_id=" . (int) $ID,
            'created_on'                 => sprintf(__('Created on %s'), Html::convDateTime($_SESSION["glpi_currenttime"])),
        ]);

        return true;
    }

    /**
     * Build the users list of a "manager" dropdown, restricted to the profiles selected in the
     * plugin configuration and to the entities the given entity gives access to.
     *
     * @param string $profiles_json Raw configuration value, a JSON encoded list of profile IDs
     * @param int    $entities_id   Entity the resource belongs to
     *
     * @return array<int, string> Users indexed by their ID
     */
    public static function getManagerDropdownValues($profiles_json, $entities_id): array
    {
        $table    = Profile_User::getTable();
        $decoded  = json_decode($profiles_json, true);
        $profiles = [];
        foreach (is_array($decoded) ? $decoded : [] as $profile) {
            $profiles[$profile] = $profile;
        }

        $restrict = getEntitiesRestrictCriteria($table, 'entities_id', $entities_id, true);
        $restrict = array_merge([$table . ".profiles_id" => [join("','", $profiles)]], $restrict);

        $profile_user = new Profile_User();
        $managers     = [];
        foreach ($profile_user->find($restrict) as $line) {
            $user = new \User();
            if ($user->getFromDB($line["users_id"])) {
                $managers[$line["users_id"]] = $user->getFriendlyName();
            }
        }

        return $managers;
    }

    /**
     * Build a "chained dropdown + target span" pair, as used by the situation/contract nature and
     * the rank/speciality couples. showGenericDropdown() echoes both the dropdown and its wiring
     * script, so its output has to be captured to be handed over to fields.htmlField().
     *
     * @param string $itemtype   Dropdown itemtype
     * @param string $name       Input name of the dropdown
     * @param int    $value      Current dropdown value
     * @param int    $entity     Entity restriction
     * @param string $ajax_file  File name, inside the plugin ajax/ directory, feeding the span
     * @param string $span_id    DOM id of the span refreshed on change
     * @param string $span_table Table the span label is read from
     * @param int    $span_value Current value of the field displayed in the span
     *
     * @return array{dropdown: string, span: string}
     */
    private static function buildChainedDropdownPair(
        $itemtype,
        $name,
        $value,
        $entity,
        $ajax_file,
        $span_id,
        $span_table,
        $span_value,
    ): array {
        ob_start();
        self::showGenericDropdown($itemtype, [
            'name'   => $name,
            'value'  => $value,
            'entity' => $entity,
            'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/" . $ajax_file,
            'span'   => $span_id,
        ]);
        $dropdown = (string) ob_get_clean();

        $label = htmlescape($span_value > 0
            ? Dropdown::getDropdownName($span_table, $span_value)
            : __('None'));

        return [
            'dropdown' => $dropdown,
            'span'     => "<span id='" . $span_id . "' name='" . $span_id . "'>" . $label . "</span>",
        ];
    }

    /**
     * Build the department / service / role dropdowns and the scripts chaining them.
     *
     * When the departments and services are read from the directory, they are plain UserTitle and
     * UserCategory dropdowns and only the department drives a refresh; otherwise the plugin
     * dropdowns are used and the service drives the role list as well.
     *
     * @param array $readonly Readonly fields, as a map of field names
     * @param int   $rand     Random suffix shared by the whole chain
     * @param bool  $from_ad  Whether departments and services come from the directory
     *
     * @return array{department: string, service: string, role: string, script: string}
     */
    private function buildOrganisationFields(array $readonly, $rand, bool $from_ad): array
    {
        $department_options = [
            'name'    => "plugin_resources_departments_id",
            'value'   => $this->fields["plugin_resources_departments_id"],
            'rand'    => $rand,
            'display' => false,
        ];
        $service_options = [
            'name'    => "plugin_resources_services_id",
            'value'   => $this->fields["plugin_resources_services_id"],
            'rand'    => $rand,
            'display' => false,
        ];
        if (isset($readonly['plugin_resources_departments_id'])) {
            $department_options['readonly'] = true;
        }
        if (isset($readonly['plugin_resources_services_id'])) {
            $service_options['readonly'] = true;
        }

        $script = '';
        if ($from_ad) {
            $department = (string) UserTitle::dropdown($department_options);
            $service    = (string) UserCategory::dropdown($service_options);
        } else {
            $department_options['entity'] = $this->fields["entities_id"];
            $department                   = (string) Dropdown::show(Department::class, $department_options);

            $service_options['entity'] = $_SESSION['glpiactiveentities'];
            $service                   = (string) Service::dropdownFromDepart(
                $this->fields["plugin_resources_departments_id"],
                $service_options,
            );

            ob_start();
            Ajax::updateItemOnSelectEvent(
                "dropdown_plugin_resources_services_id" . $rand,
                "show_roles",
                PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownRole.php",
                ['plugin_resources_services_id' => '__VALUE__', 'rand' => $rand],
            );
            $script .= (string) ob_get_clean();
        }

        $role_options = [
            'name'    => "plugin_resources_roles_id",
            'value'   => $this->fields["plugin_resources_roles_id"],
            'entity'  => $_SESSION['glpiactiveentities'],
            'rand'    => $rand,
            'display' => false,
        ];
        if (isset($readonly['plugin_resources_roles_id'])) {
            $role_options['readonly'] = true;
        }
        $role = (string) Role::dropdownFromService(
            $this->fields['plugin_resources_services_id'],
            $role_options,
        );

        ob_start();
        Ajax::updateItemOnSelectEvent(
            "dropdown_plugin_resources_departments_id" . $rand,
            "show_services",
            PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownService.php",
            ['plugin_resources_departments_id' => '__VALUE__', 'rand' => $rand],
        );
        $script .= (string) ob_get_clean();

        return [
            'department' => $department,
            'service'    => $service,
            'role'       => $role,
            'script'     => $script,
        ];
    }

    /**
     * @param $options
     *
     * @return bool
     */
    public function sendReport($options)
    {
        global $CFG_GLPI;

        if (!$this->getFromDB($options["id"])) {
            return false;
        }

        if ($CFG_GLPI["notifications_mailing"]) {
            $report = new ReportConfig();
            $report->getFromDB($options["reports_id"]);

            if ($report->fields['send_report_notif']) {
                $notification = new Notification();
                $notification->add([
                    'users_id' => Session::getLoginUserID(),
                    'plugin_resources_resources_id' => $options["id"],
                    'type' => 'report',
                ]);
                NotificationEvent::raiseEvent('report', $this, ['reports_id' => $options["reports_id"]]);
            }

            if ($report->fields['send_other_notif']) {
                $notification = new Notification();
                $notification->add([
                    'users_id' => Session::getLoginUserID(),
                    'plugin_resources_resources_id' => $options["id"],
                    'type' => 'other',
                ]);
                NotificationEvent::raiseEvent('other', $this, ['reports_id' => $options["reports_id"]]);
            }
        }
    }

    /**
     * @param $options
     *
     * @return bool
     */
    public function reSendResourceCreation($options)
    {
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
    public static function showReportForm($options)
    {
        $reportconfig = new ReportConfig();
        $reportconfig->getFromDBByResource($options['id']);

        if ($reportconfig->fields['send_report_notif'] || $reportconfig->fields['send_other_notif']) {
            TemplateRenderer::getInstance()->display('@resources/resource_report_form.html.twig', [
                'form_action' => $options['target'],
                'title'       => ReportConfig::getTypeName(2),
                'label_send'  => __('Send a notification'),
                'id'          => (int) $options['id'],
                'reports_id'  => (int) $reportconfig->fields["id"],
            ]);
        }

        $notification = new Notification();
        $notification->listItems($options['id']);
    }

    public static function showAddFormForItem(CommonDBTM $item, $withtemplate = 0, $options = [])
    {
        global $DB;

        //default options
        $params['rand'] = mt_rand();
        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $params[$key] = $val;
            }
        }

        if (!$item->can($item->fields['id'], READ)) {
            return false;
        }

        if (empty($withtemplate)) {
            $withtemplate = 0;
        }

        // find documents already associated to the item
        $doc_item   = new Document_Item();
        $used_found = $doc_item->find([
            'items_id'  => $item->getID(),
            'itemtype'  => $item::class,
        ]);
        $used       = array_keys($used_found);
        $used       = array_combine($used, $used);

        if (
            $item->canAddItem('Document')
            && $withtemplate < 2
        ) {
            // Restrict entity for knowbase
            $entities = "";
            $entity   = $_SESSION["glpiactive_entity"];

            if ($item->isEntityAssign()) {
                // Case of personal items : entity = -1 : create on active entity (Reminder case))
                if ($item->getEntityID() >= 0) {
                    $entity = $item->getEntityID();
                }

                if ($item->isRecursive()) {
                    $entities = getSonsOf('glpi_entities', $entity);
                } else {
                    $entities = $entity;
                }
            }

            $count = $DB->request([
                'COUNT'     => 'cpt',
                'FROM'      => 'glpi_documents',
                'WHERE'     => [
                    'is_deleted' => 0,
                ] + getEntitiesRestrictCriteria('glpi_documents', '', $entities, true),
            ])->current();
            $nb = $count['cpt'];

            if ($item::class === Document::class) {
                $used[$item->getID()] = $item->getID();
            }

            $target = PLUGIN_RESOURCES_WEBDIR . "/front/wizard.form.php";

            TemplateRenderer::getInstance()->display('@resources/document_item.html.twig', [
                'canview' => Document::canView(),
                'item' => $item,
                'used' => $used,
                'entity' => $entity,
                'entities' => $entities,
                'nb' => $nb,
                'target' => $target,
                'rand' => mt_rand(),
            ]);
        }

        return true;
    }

    /**
     * @param     $ID
     * @param int $link
     *
     * @return array|string
     */
    public static function getResourceName($ID, $link = 0)
    {
        global $DB, $CFG_GLPI;

        $user = "";
        if ($link == 2) {
            $user = [
                "name" => "",
                "link" => "",
                "comment" => "",
            ];
        }

        if ($ID) {

            $criteria = [
                'SELECT' => ['glpi_plugin_resources_resources.*',
                    'glpi_users.registration_number',
                    'glpi_users.name AS username'],
                'FROM' => 'glpi_plugin_resources_resources',
                'LEFT JOIN'       => [
                    'glpi_plugin_resources_resources_items' => [
                        'ON' => [
                            'glpi_plugin_resources_resources_items' => 'plugin_resources_resources_id',
                            'glpi_plugin_resources_resources'          => 'id',
                        ],
                    ],
                    'glpi_users' => [
                        'ON' => [
                            'glpi_users' => 'id',
                            'glpi_plugin_resources_resources_items'                  => 'items_id', [
                                'AND' => [
                                    'glpi_plugin_resources_resources_items.itemtype' => 'User',
                                ],
                            ],
                        ],
                    ],
                ],
                'WHERE' => [
                    'glpi_plugin_resources_resources.id' => $ID,
                ],
                'GROUPBY'   => 'glpi_plugin_resources_resources.id',
            ];

            $iterator = $DB->request($criteria);

            if ($link == 2) {
                $user = [
                    "name" => "",
                    "comment" => "",
                    "link" => "",
                ];
            }

            $dbu = new DbUtils();

            if (count($iterator) == 1) {
                foreach ($iterator as $data) {

                    $username = $dbu->formatUserName(
                        $data["id"],
                        $data["username"],
                        $data["name"],
                        $data["firstname"],
                    );

                    if ($link == 2) {
                        $user["name"] = $username;
                        $user["link"] = PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php?id=" . $ID;
                        $user["comment"] = "";

                        if (isset($data["picture"]) && !empty($data["picture"])) {
                            $path = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $data["picture"];
                            if (file_exists($path)) {
                                $user["comment"] .= "<object data='" . PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $data["picture"] . "'>
                      <param name='src' value='" . PLUGIN_RESOURCES_WEBDIR
                                    . "/front/picture.send.php?file=" . $data["picture"] . "'>
                     </object><br> ";
                            } else {
                                $user["comment"] .= "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png'><br>";
                            }
                        } else {
                            $user["comment"] .= "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png'><br>";
                        }

                        $user["comment"] .= __('Name') . "&nbsp;: " . $username . "<br>";

                        if ($data["plugin_resources_ranks_id"] > 0) {
                            $user["comment"] .= Rank::getTypeName(1) . "&nbsp;: "
                                . Dropdown::getDropdownName(
                                    "glpi_plugin_resources_ranks",
                                    $data["plugin_resources_ranks_id"],
                                ) . "<br>";
                        }

                        if ($data["locations_id"] > 0) {
                            $user["comment"] .= __('Location') . "&nbsp;: "
                                . Dropdown::getDropdownName(
                                    "glpi_locations",
                                    $data["locations_id"],
                                ) . "<br>";
                        }

                        if ($data["registration_number"] > 0) {
                            $user["comment"] .= _x('user', 'Administrative number') . "&nbsp;: "
                                . $data["registration_number"] . "<br>";
                        }
                    } else {
                        $user = $username;
                    }
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

    public static function dropdown($options = [])
    {
        global $CFG_GLPI;

        $params['value'] = 0;
        $params['valuename'] = Dropdown::EMPTY_VALUE;
        $params['customcomments'] = true;
        $params['comments'] = false;
        $params['entity'] = $_SESSION['glpiactive_entity'];
        $params['name'] = 'plugin_resources_resources_id';
        $params['addUnlinkedUsers'] = false;
        $params['rand'] = mt_rand();
        $params['display'] = false;
        $params['showHabilitations'] = false;
        if (!empty($options)) {
            foreach ($options as $key => $val) {
                $params[$key] = $val;
            }
        }

        $params['value2'] = $params['value'];
        $user = self::getResourceName($params['value'], 2);
        if (!empty($params['value'])) {
            //         $params['valuename'] = Dropdown::getDropdownName(self::getTable(), $params['value']);
            $params['valuename'] = $user['name'];
        }

        $field_id = Html::cleanId("dropdown_" . $params['name'] . $params['rand']);

        $item = new self();
        $output = "<span class='no-wrap'>";
        $output .= Html::jsAjaxDropdown(
            $params['name'],
            $field_id,
            PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownResources.php",
            $params,
        );
        if (class_exists(Position::class)) {
            $output .= Position::showGeolocLink(Resource::class, $params['value']);
        }
        // Display comment
        if ($params['customcomments']) {
            $table = $item->getTable();
            $user = self::getResourceName($params['value'], 2);

            $comment_id = Html::cleanId("comment_" . $params['name'] . $params['rand']);
            $link_id = Html::cleanId("comment_link_" . $params['name'] . $params['rand']);

            if (empty($user["link"])) {
                $user["link"] = PLUGIN_RESOURCES_WEBDIR . "/front/resource.php";
            }

            $output .= "&nbsp;" . Html::showToolTip(
                $user["comment"],
                [
                    'contentid' => $comment_id,
                    'link' => $user["link"],
                    'linkid' => $link_id,
                    'linktarget' => '_blank',
                    'display' => false,
                ],
            );

            $paramscomment = [
                'value' => '__VALUE__',
                'table' => $table,
            ];
            if ($item->canView()) {
                $paramscomment['withlink'] = $link_id;
            }

            $output .= Ajax::updateItemOnSelectEvent(
                $field_id,
                $comment_id,
                PLUGIN_RESOURCES_WEBDIR . "/ajax/comments.php",
                $paramscomment,
                false,
            );
        }
        $config = new Config();
        if ($params['showHabilitations'] && $config->getField('display_habilitations_txt')) {
            $output .= "<p id='habilitationsTxt'></p>";
            $output .= Ajax::updateItemOnSelectEvent(
                $field_id,
                'habilitationsTxt',
                PLUGIN_RESOURCES_WEBDIR . "/ajax/showHabilitations.php",
                ['value' => '__VALUE__', 'metademands_id' => $_GET['metademands_id'] ?? 0],
                false,
            );
        }
        $output .= Ajax::commonDropdownUpdateItem($params, false);
        $output .= "</span>";
        if ($params['display']) {
            echo $output;
            return $params['rand'];
        }
        return $output;
    }

    /**
     * Massive action sub form of "Generate resources": collects the few fields
     * fastResourceAdd() needs to build a resource out of each selected user.
     *
     * @return void
     */
    public static function fastResourceAddForm()
    {
        // The dropdowns echo their markup and return their rand by default: ask for the
        // string instead, so the template stays in charge of the layout.
        $contracttype_dropdown = (string) Dropdown::show(ContractType::class, [
            'name'    => 'plugin_resources_contracttypes_id',
            'display' => false,
        ]);

        $recipient_dropdown = (string) \User::dropdown([
            'name'        => 'users_id_recipient',
            'entity'      => $_SESSION['glpiactive_entity'],
            'entity_sons' => true,
            'right'       => 'all',
            'display'     => false,
        ]);

        $department_dropdown = (string) Dropdown::show(Department::class, [
            'name'    => 'plugin_resources_departments_id',
            'display' => false,
        ]);

        TemplateRenderer::getInstance()->display('@resources/resource_fast_add_form.html.twig', [
            'contracttype_label'    => __('Contract type'),
            'contracttype_dropdown' => $contracttype_dropdown,
            'recipient_label'       => __('Resource manager', 'resources'),
            'recipient_dropdown'    => $recipient_dropdown,
            'department_label'      => Department::getTypeName(1),
            'department_dropdown'   => $department_dropdown,
        ]);
    }

    /**
     * @param $userId
     * @param $options
     *
     * @return array
     */
    public static function fastResourceAdd($userId, $options)
    {
        global $DB;

        $params['plugin_resources_contracttypes_id'] = 0;
        $params['plugin_resources_departments_id'] = 0;
        $params['users_id_recipient'] = 0;
        $params['itemtype'] = 'User';
        $params['entities_id'] = $_SESSION['glpiactive_entity'];

        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }

        $message = null;
        $idResource = 0;
        $error['right'] = 0;
        $error['error'] = 0;

        $user = new \User();
        if ($user->getFromDB($userId)) {
            $resource = new Resource();
            $resource->getFromDBByCrit([
                'name' => $user->fields['realname'],
                'firstname' => $user->fields['firstname'],
                'is_deleted' => 0,
            ]);

            if (!isset($resource->fields['id']) || $resource->fields['id'] <= 0) {
                $resource->fields['entities_id'] = $params['entities_id'];
                $resource->fields['name'] = $user->fields['realname'] ?? '';
                $resource->fields['firstname'] = $user->fields['firstname'] ?? '';

                $resource->fields['plugin_resources_contracttypes_id'] = $params['plugin_resources_contracttypes_id'];
                $resource->fields['users_id_recipient'] = Session::getLoginUserID();
                $resource->fields['users_id'] = $params["users_id_recipient"];
                $resource->fields['users_id_sales'] = 0;

                $resource->fields['date_declaration'] = date('Y-m-d');
                $resource->fields['date_begin'] = null;
                $resource->fields['date_end'] = null;

                $resource->fields['plugin_resources_departments_id'] = $params['plugin_resources_departments_id'];
                $resource->fields['locations_id'] = 0;
                $resource->fields['is_leaving'] = 0;
                $resource->fields['users_id_recipient_leaving'] = 0;
                $resource->fields['comment'] = '';
                $resource->fields['notepad'] = '';
                $resource->fields['is_template'] = 0;
                $resource->fields['template_name'] = '';
                $resource->fields['is_deleted'] = 0;
                $resource->fields['is_helpdesk_visible'] = 1;
                $resource->fields['date_mod'] = date('Y-m-d');

                $resource->fields['plugin_resources_resourcestates_id'] = 0;
                $resource->fields['picture'] = null;
                $resource->fields['is_recursive'] = 0;
                $resource->fields['quota'] = 1;
                $resource->fields['plugin_resources_resourcesituations_id'] = 0;
                $resource->fields['plugin_resources_contractnatures_id'] = 0;
                $resource->fields['plugin_resources_ranks_id'] = 0;
                $resource->fields['plugin_resources_resourcespecialities_id'] = 0;
                $resource->fields['plugin_resources_leavingreasons_id'] = 0;
                $resource->fields['sensitize_security'] = 0;
                $resource->fields['read_chart'] = 0;

                $resourceItem = new Resource_Item();
                if ($resourceItem->can(-1, UPDATE, $resource)) {
                    $idResource = $resource->add($resource->fields);
                    if ($idResource) {
                        $resource->fields['id'] = $idResource;
                        if (isset($resourceItem->fields['id'])) {
                            unset($resourceItem->fields['id']);
                        }

                        $resourceItem->fields['plugin_resources_resources_id'] = $idResource;
                        $resourceItem->fields['items_id'] = $user->fields['id'];
                        $resourceItem->fields['itemtype'] = $params['itemtype'];
                        $resourceItem->fields['comment'] = null;

                        $idResourceItem = $resourceItem->add($resourceItem->fields);
                        if ($idResourceItem) {
                            // Cochage des checklist en mode "JOB DONE"
                            $pChecklist = new Checklist();

                            if ($DB->update(
                                $pChecklist->getTable(),
                                ['is_checked' => 1],
                                ['plugin_resources_resources_id' => (int) $idResource],
                            )) {
                                $message = $user->fields['realname'] . " " . $user->fields['firstname'] . "<br/>";
                            }
                        } else {
                            $error['error'] = 1;
                            $message = $user->fields['realname'] . " " . $user->fields['firstname'] . "<br/>";
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
                $message = $user->fields['realname'] . " " . $user->fields['firstname'] . "<br/>";
            }
        } else {
            $error['error'] = 1;
        }

        return [$idResource, $error, $message];
    }

    /**
     * @param bool $count
     * @param int $entity_restrict
     * @param int $value
     * @param array $used
     * @param string $search
     * @param bool $showOnlyLinkedResources
     *
     * @return \Glpi\DBAL\DBmysqlIterator
     */
    public static function getSqlSearchResult(
        $count = true,
        $entity_restrict = -1,
        $value = 0,
        $used = [],
        $search = '',
        $showOnlyLinkedResources = false,
        $isNotLeavingOnly = false
    ) {
        global $DB, $CFG_GLPI;

        // No entity define : use active ones
        if ($entity_restrict < 0) {
            $entity_restrict = $_SESSION["glpiactiveentities"];
        }

        $dbu = new DbUtils();

        $where = [
            'glpi_plugin_resources_resources.is_deleted' => 0,
        ];
        if (!$isNotLeavingOnly) {
            $where[] = [
                'OR' => [
                    'glpi_plugin_resources_resources.is_leaving' => 0,
                    'glpi_plugin_resources_resources.date_end'   => ['>', QueryFunction::now()],
                ],
            ];
        } else {
            $where['glpi_plugin_resources_resources.is_leaving'] = 0;
        }

        $where['glpi_plugin_resources_resources.is_template'] = 0;

        $entities_crit = $dbu->getEntitiesRestrictCriteria(
            'glpi_plugin_resources_resources',
            '',
            $entity_restrict,
            true,
        );
        if (count($entities_crit)) {
            $where[] = $entities_crit;
        }

        if ((is_numeric($value) && $value)
            || count($used)
        ) {
            $exclude = [0];
            if (is_numeric($value)) {
                $exclude[] = (int) $value;
            }
            if (is_array($used)) {
                foreach ($used as $val) {
                    $exclude[] = (int) $val;
                }
            }
            $where[] = ['NOT' => ['glpi_plugin_resources_resources.id' => $exclude]];
        }

        if (!Session::haveRight("plugin_resources_all", READ)) {
            $who = (int) Session::getLoginUserID();
            $where[] = [
                'OR' => [
                    'glpi_plugin_resources_resources.users_id_recipient' => $who,
                    'glpi_plugin_resources_resources.users_id'           => $who,
                ],
            ];
        }

        if ($count) {
            $criteria = [
                'SELECT' => new QueryExpression(
                    'COUNT(DISTINCT ' . $DB->quoteName('glpi_plugin_resources_resources.id')
                    . ') AS ' . $DB->quoteName('cpt'),
                ),
                'FROM'   => 'glpi_plugin_resources_resources',
                'WHERE'  => $where,
            ];
        } else {
            $contracttypeprofile = new Contracttypeprofile();
            if ($contracttypeprofile->getFromDBByCrit(["profiles_id" => $_SESSION['glpiactiveprofile']['id']])) {
                $contracttypeprofiles = json_decode($contracttypeprofile->fields['plugin_resources_contracttypes_id']);
                if ($contracttypeprofiles !== false && is_array(
                    $contracttypeprofiles,
                ) && !empty($contracttypeprofiles)) {
                    $where['glpi_plugin_resources_resources.plugin_resources_contracttypes_id']
                        = array_map('intval', $contracttypeprofiles);
                }
            }
            if (strlen($search) > 0 && $search != $CFG_GLPI["ajax_wildcard"]) {
                $search_sql = Search::makeTextSearch($search);
                $where[] = [
                    'OR' => [
                        new QueryExpression(
                            $DB->quoteName('glpi_plugin_resources_resources.name') . ' ' . $search_sql,
                        ),
                        new QueryExpression(
                            $DB->quoteName('glpi_plugin_resources_resources.firstname') . ' ' . $search_sql,
                        ),
                        new QueryExpression(
                            $DB->quoteName('glpi_users.registration_number') . ' ' . $search_sql,
                        ),
                        new QueryExpression(
                            $DB->quoteName('glpi_users.name') . ' ' . $search_sql,
                        ),
                        new QueryExpression(
                            'CONCAT(' . $DB->quoteName('glpi_plugin_resources_resources.name') . ', '
                            . $DB->quoteValue(' ') . ', '
                            . $DB->quoteName('glpi_plugin_resources_resources.firstname') . ', '
                            . $DB->quoteValue(' ') . ', '
                            . $DB->quoteName('glpi_users.registration_number') . ', '
                            . $DB->quoteValue(' ') . ', '
                            . $DB->quoteName('glpi_users.name') . ') ' . $search_sql,
                        ),
                    ],
                ];
            }

            $join = [
                'glpi_plugin_resources_resources_items' => [
                    'ON' => [
                        'glpi_plugin_resources_resources_items' => 'plugin_resources_resources_id',
                        'glpi_plugin_resources_resources'       => 'id',
                        [
                            'AND' => [
                                'glpi_plugin_resources_resources_items.itemtype' => 'User',
                            ],
                        ],
                    ],
                ],
                'glpi_users' => [
                    'ON' => [
                        'glpi_users'                            => 'id',
                        'glpi_plugin_resources_resources_items' => 'items_id',
                        [
                            'AND' => [
                                'glpi_plugin_resources_resources_items.itemtype' => 'User',
                            ],
                        ],
                    ],
                ],
            ];

            $criteria = [
                'SELECT'   => [
                    'glpi_plugin_resources_resources.*',
                    'glpi_users.registration_number',
                    'glpi_users.name AS username',
                    'glpi_users.id AS userid',
                ],
                'DISTINCT' => true,
                'FROM'     => 'glpi_plugin_resources_resources',
                ($showOnlyLinkedResources ? 'INNER JOIN' : 'LEFT JOIN') => $join,
                'WHERE'    => $where,
                'ORDER'    => [
                    'glpi_plugin_resources_resources.firstname',
                    'glpi_plugin_resources_resources.name',
                ],
            ];

            if ($search != $CFG_GLPI["ajax_wildcard"]) {
                $criteria['START'] = 0;
                $criteria['LIMIT'] = (int) $CFG_GLPI["dropdown_max"];
            }
        }
        return $DB->request($criteria);
    }

    /**
     * List the resource templates.
     *
     * Rendering is delegated to the core template list view, which already brings the
     * multi entity column, the purge button and its CSRF token. Template names are
     * escaped here: they are free text and used to be echoed raw inside the link.
     *
     * @param string $target
     * @param int    $add
     */
    public function listOfTemplates($target, $add = 0)
    {
        $dbu = new DbUtils();

        $restrict = ["is_template" => 1]
            + $dbu->getEntitiesRestrictCriteria($this->getTable(), '', '', $this->maybeRecursive())
            + ["ORDER" => "name"];

        $templates = $dbu->getAllDataFromTable($this->getTable(), $restrict);

        $can_purge = self::canPurge();
        $entries   = [];

        if ($add) {
            $entries[] = [
                'name' => '<a href="' . htmlescape($target . '?id=-1&withtemplate=2') . '">'
                    . __s('Blank Template') . '</a>',
            ];
        }

        // Picking a template creates a resource out of it (withtemplate=2), while the
        // management screen edits the template itself (withtemplate=1).
        $withtemplate = $add ? 2 : 1;

        foreach ($templates as $template) {
            $templname = $template["template_name"];
            if ($_SESSION["glpiis_ids_visible"] || empty($template["template_name"])) {
                $templname = sprintf(__('%1$s (%2$s)'), $templname, $template["id"]);
            }

            $url = $target . '?id=' . $template["id"] . '&withtemplate=' . $withtemplate;

            $entry = [
                'id'   => $template["id"],
                'name' => '<a href="' . htmlescape($url) . '">' . htmlescape($templname) . '</a>',
            ];

            if (!$add) {
                if (Session::isMultiEntitiesMode()) {
                    $entry['entity'] = Dropdown::getDropdownName("glpi_entities", $template['entities_id']);
                }
                $entry['can_delete'] = $can_purge && $this->can($template["id"], PURGE);
            }

            $entries[] = $entry;
        }

        TemplateRenderer::getInstance()->display('pages/assets/template_list.html.twig', [
            'add_mode'      => (bool) $add,
            'templates'     => $entries,
            'target'        => $target,
            'can_delete'    => $can_purge,
            'add_template'  => !$add && self::canCreate(),
            'target_create' => $target . '?id=-1&withtemplate=1',
        ]);
    }

    /**
     * Show the helpdesk form used to declare the departure of a resource.
     *
     * @return void
     */
    public function showResourcesToRemove()
    {
        $dbu = new DbUtils();

        if ($dbu->countElementsInTable($this->getTable()) == 0) {
            TemplateRenderer::getInstance()->display('@resources/resource_remove_form.html.twig', [
                'has_resources' => false,
            ]);
            return;
        }

        $preselected_id = isset($_GET['resource_id']) ? (int) $_GET['resource_id'] : 0;

        // Only the contract types granted to the current profile can be declared as leaving.
        // Resources with no contract type at all (0) stay reachable.
        $available_contracttype = false;
        $contracttypeprofile    = new Contracttypeprofile();
        if ($contracttypeprofile->getFromDBByCrit(['profiles_id' => $_SESSION['glpiactiveprofile']['id']])) {
            $available_contracttype = json_decode(
                $contracttypeprofile->fields['plugin_resources_contracttypes_id'],
            );
        }

        $cond = ['is_not_leaving_only' => 'is_not_leaving_only'];
        if (is_array($available_contracttype)) {
            $available_contracttype[] = 0;
            $cond['plugin_resources_contracttypes_id'] = $available_contracttype;
        }

        // The dropdowns echo their markup and return their rand by default: ask for the
        // string instead, so the template stays in charge of the layout.
        $rand = mt_rand();
        $resource_dropdown = (string) self::dropdown([
            'name'      => 'plugin_resources_resources_id',
            'display'   => false,
            'entity'    => $_SESSION['glpiactiveentities'],
            'condition' => $cond,
            'rand'      => $rand,
            'value'     => $preselected_id,
            'on_change' => "plugin_resources_pdf_resource(\"" . PLUGIN_RESOURCES_WEBDIR . "\", this.value);",
        ]);

        $leaving_url = "../ajax/leavingform.php";
        $scripts     = (string) Ajax::updateItemOnSelectEvent(
            "dropdown_plugin_resources_resources_id$rand",
            "leaving_input",
            $leaving_url,
            [
                'plugin_resources_resources_id' => '__VALUE__',
                'rand' => $rand,
            ],
            false,
        );

        // A pre-selected resource never fires a change event: fill the block right away.
        if ($preselected_id > 0) {
            $scripts .= (string) Ajax::updateItem(
                "leaving_input",
                $leaving_url,
                [
                    'plugin_resources_resources_id' => $preselected_id,
                    'rand' => $rand,
                ],
                "",
                false,
            );
        }

        $manager_dropdown = (string) User::dropdown([
            'name'    => 'remove_manager',
            'right'   => 'all',
            'display' => false,
        ]);

        TemplateRenderer::getInstance()->display('@resources/resource_remove_form.html.twig', [
            'has_resources'     => true,
            'header_title'      => __('Declare a departure', 'resources'),
            'header_img'        => PLUGIN_RESOURCES_WEBDIR . "/pics/removeresource.png",
            'form_action'       => PLUGIN_RESOURCES_WEBDIR . "/front/resource.remove.php",
            'resource_label'    => self::getTypeName(1),
            'resource_dropdown' => $resource_dropdown,
            'date_end'          => $_POST["date_end"] ?? '',
            'manager_dropdown'  => $manager_dropdown,
            'scripts'           => $scripts,
        ]);
    }

    /**
     * Show the helpdesk form used to declare a change on a resource.
     *
     * @param array $options previously posted values, used to report the empty ones
     *
     * @return void
     */
    public function showResourcesToChange($options = [])
    {
        $dbu = new DbUtils();

        if ($dbu->countElementsInTable($this->getTable()) == 0) {
            TemplateRenderer::getInstance()->display('@resources/resource_change_form.html.twig', [
                'has_resources' => false,
            ]);
            return;
        }

        // The dropdowns echo their markup and return their rand by default: ask for the
        // string instead, so the template stays in charge of the layout.
        $resource_dropdown = (string) self::dropdown([
            'name'      => 'plugin_resources_resources_id',
            'display'   => false,
            'entity'    => $_SESSION['glpiactiveentities'],
            'on_change' => "plugin_resources_change_resource(\"" . PLUGIN_RESOURCES_WEBDIR . "\", this.value);",
        ]);

        // Only offer the actions granted to the current profile.
        $actions       = Resource_Change::getAllActions();
        $actionProfile = new Actionprofile();
        if ($actionProfile->getFromDBByCrit(['profiles_id' => $_SESSION['glpiactiveprofile']['id']])) {
            $available_action = json_decode($actionProfile->fields['actions_id']);
            if (!empty($available_action)) {
                foreach ($actions as $id => $action) {
                    if (!in_array($id, $available_action)) {
                        unset($actions[$id]);
                    }
                }
            }
        }

        $action_dropdown = (string) Dropdown::showFromArray('change_action', $actions, [
            'display'   => false,
            'on_change' => "plugin_resources_change_action(\"" . PLUGIN_RESOURCES_WEBDIR . "\", this.value);",
        ]);

        // Fields the previous submit left empty, reported back above the action block.
        $msg = [];
        if (isset($options['plugin_resources_resources_id']) && $options['plugin_resources_resources_id'] == 0) {
            $msg[] = self::getTypeName(1);
        }
        if (isset($options['change_action']) && $options['change_action'] == 0) {
            $msg[] = __('Actions to taken');
        }

        TemplateRenderer::getInstance()->display('@resources/resource_change_form.html.twig', [
            'has_resources'     => true,
            'header_title'      => __('Declare a change', 'resources'),
            'header_img'        => PLUGIN_RESOURCES_WEBDIR . "/pics/recap.png",
            'form_action'       => PLUGIN_RESOURCES_WEBDIR . "/front/resource.change.php",
            'resource_label'    => self::getTypeName(1),
            'resource_dropdown' => $resource_dropdown,
            'action_label'      => __('Actions to be taken', 'resources'),
            'action_dropdown'   => $action_dropdown,
            'error_message'     => count($msg) > 0
                ? sprintf(__("Please correct: %s", 'resources'), implode(', ', $msg))
                : '',
        ]);
    }

    /**
     * Show the helpdesk form used to declare the transfer of a resource to another entity.
     *
     * @param int $plugin_resources_resources_id
     *
     * @return void
     */
    public function showResourcesToTransfer($plugin_resources_resources_id)
    {
        $resource = new self();

        // can() loads the row and enforces both the read right and the entity boundary,
        // which the bare getFromDB() it replaces did not: the name and the current entity
        // of any resource were readable from the id alone.
        if (!$resource->can((int) $plugin_resources_resources_id, READ)) {
            TemplateRenderer::getInstance()->display('@resources/resource_transfer_form.html.twig', [
                'has_resource' => false,
            ]);
            return;
        }

        // Only the entities opened to transfers, and only those the current user may reach:
        // front/resource.transfer.php checks the posted one again anyway.
        $elements = [Dropdown::EMPTY_VALUE];
        foreach ((new TransferEntity())->find() as $val) {
            if (!Session::haveAccessToEntity($val['entities_id'])) {
                continue;
            }
            $elements[$val['entities_id']] = Dropdown::getDropdownName('glpi_entities', $val['entities_id']);
        }

        TemplateRenderer::getInstance()->display('@resources/resource_transfer_form.html.twig', [
            'has_resource'                  => true,
            'header_title'                  => __('Declare a transfer', 'resources'),
            'header_img'                    => PLUGIN_RESOURCES_WEBDIR . "/pics/transferresource.png",
            'form_action'                   => PLUGIN_RESOURCES_WEBDIR . "/front/resource.transfer.php",
            'resource_label'                => self::getTypeName(1),
            'resource_name'                 => self::getResourceName($resource->getID()),
            'current_entity'                => Dropdown::getDropdownName(
                'glpi_entities',
                $resource->fields['entities_id'],
            ),
            'entity_dropdown'               => (string) Dropdown::showFromArray('entities_id', $elements, [
                'display' => false,
            ]),
            'plugin_resources_resources_id' => $resource->getID(),
        ]);
    }

    /**
     * Massive actions to be added
     *
     * @param $type
     *
     * @return $action
     */
    public function massiveActions($type)
    {
        $action = [];
        $prefix = $this->getType() . MassiveAction::CLASS_ACTION_SEPARATOR;
        if (Session::haveRightsOr('plugin_resources', [CREATE, UPDATE])) {
            $action[$prefix . "plugin_resources_add_item"] = __('Associate a resource', 'resources');
        }

        if ($type == "User") {
            $action[$prefix . "plugin_resources_generate_resources"] = __('Generate resources', 'resources');
            $action[$prefix . "plugin_resources_add_habilitation"] = __('Add habiliation', 'resources');
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
    public function getSpecificMassiveActions($checkitem = null)
    {
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if ($isadmin && Session::getCurrentInterface() == 'central') {
            $actions['GlpiPlugin\Resources\Resource' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Install'] = _x(
                'button',
                'Associate',
            );
            $actions['GlpiPlugin\Resources\Resource' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Desinstall'] = _x(
                'button',
                'Dissociate',
            );

            if (Session::haveRight('transfer', READ)
                && Session::isMultiEntitiesMode()
            ) {
                $actions['GlpiPlugin\Resources\Resource' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Transfert'] = __(
                    'Transfer',
                );
            }
            $actions['GlpiPlugin\Resources\Resource' . MassiveAction::CLASS_ACTION_SEPARATOR . 'AddHabilitation'] = __(
                'Add additional habilitation',
                'resources',
            );
            $actions['GlpiPlugin\Resources\Resource' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Send'] = __(
                'Send a notification',
            );
        }
        return $actions;
    }

    /**
     * Class-specific method used to show the fields to specify the massive action
     *
     * @param MassiveAction $ma the current massive action object
     *
     * @return bool false if parameters displayed ?
     **@since 0.85
     *
     */
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        $itemtype = $ma->getItemtype(false);
        switch ($ma->getAction()) {
            case "Install":
                Dropdown::showSelectItemFromItemtypes([
                    'items_id_name' => "item_item",
                    'itemtypes' => self::getTypes(),
                ]);
                break;
            case "Desinstall":
                Dropdown::showSelectItemFromItemtypes([
                    'items_id_name' => "item_item",
                    'itemtypes' => self::getTypes(),
                ]);
                break;
            case "Transfert":
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
                Dropdown::show(
                    Habilitation::class,
                    ['entity' => $_SESSION['glpiactiveentities']],
                );
                break;
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     * */
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        $input = $ma->getInput();
        $resource_item = new Resource_Item();
        $resource = new Resource();
        $itemtype = $ma->getItemtype(false);

        switch ($ma->getAction()) {
            case "Transfert":
                if ($itemtype == Resource::class) {
                    foreach ($ids as $key => $val) {
                        if ($item->transferResource($key, $input['entities_id'])) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    }
                }
                break;

            case "Install":
                foreach ($ids as $key => $val) {
                    if ($item->can($key, UPDATE)) {
                        $values = [
                            'plugin_resources_resources_id' => $key,
                            'items_id' => $input["item_item"],
                            'itemtype' => $input['itemtype'],
                        ];
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

            case "Desinstall":
                foreach ($ids as $key => $val) {
                    if ($resource_item->deleteItemByResourcesAndItem($key, $input['item_item'], $input['itemtype'])) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                    }
                }
                break;

            case "Send":
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
                        $input = [
                            'plugin_resources_resources_id' => $input['plugin_resources_resources_id'],
                            'items_id' => $key,
                            'itemtype' => $input['itemtype'],
                        ];

                        if ($resource_item->add($input)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                            $messages[] = _n(
                                "This resource has been added",
                                "These resources have been added",
                                2,
                                "resources",
                            );
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            $messages[] = _n(
                                "This resource aldready exists",
                                "These resources aldready exist",
                                2,
                                "resources",
                            );
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
                // The sub form posts itemtype as a scalar ("User"): sizeof() on a string is a
                // TypeError since PHP 8.0, so this guard used to abort the whole action. Only
                // check that the hidden field made it through.
                if (!empty($input['itemtype'])) {
                    foreach ($ids as $key => $val) {
                        [$id, $error, $message] = self::fastResourceAdd($key, $input);
                        if ($error['right']) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            $messages[] = $item->getErrorMessage(ERROR_RIGHT);
                        } elseif ($error['error']) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            $messages[] = _n(
                                "This resource aldready exists",
                                "These resources aldready exist",
                                2,
                                "resources",
                            ) . "<br>" . $message;
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                            $messages[] = _n(
                                "This resource has been added",
                                "These resources have been added",
                                2,
                                "resources",
                            ) . "<br>" . $message;
                        }
                    }
                }
                $ma->addMessage(implode("<br>", array_unique($messages)));
                break;

            case "AddHabilitation":
                $habilitation = new ResourceHabilitation();
                foreach ($ids as $key => $val) {
                    if ($item->can($key, UPDATE)) {
                        //check if habilitation already added
                        if (!$habilitation->getFromDBByCrit([
                            'plugin_resources_resources_id' => $key,
                            'plugin_resources_habilitations_id' => $input['plugin_resources_habilitations_id'],
                        ])) {
                            if ($resource->getFromDB($key)) {
                                //TODO add verification entities
                                $values = [
                                    'plugin_resources_resources_id' => $key,
                                    'plugin_resources_habilitations_id' => $input["plugin_resources_habilitations_id"],
                                ];
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
                $habilitation = new ResourceHabilitation();
                foreach ($ids as $key => $val) {
                    if ($item->can($key, UPDATE)) {
                        $resource_item = new Resource_Item();
                        if ($resource_item->getFromDBByCrit(['items_id' => $key, 'itemtype' => User::getType()])) {
                            $resource_id = $resource_item->getField('plugin_resources_resources_id');
                            //check if habilitation already added
                            if (!$habilitation->getFromDBByCrit([
                                'plugin_resources_resources_id' => $resource_id,
                                'plugin_resources_habilitations_id' => $input['plugin_resources_habilitations_id'],
                            ])) {
                                if ($resource->getFromDB($resource_id)) {
                                    //TODO add verification entities
                                    $values = [
                                        'plugin_resources_resources_id' => $resource_id,
                                        'plugin_resources_habilitations_id' => $input["plugin_resources_habilitations_id"],
                                    ];
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
        }
    }

    /**
     * Transfer resource
     *
     * @param  $resources_id
     * @param  $entities_id
     *
     * @return bool
     */
    public function transferResource($resources_id, $entities_id, $options = [])
    {
        global $DB;

        $params['users_id'] = 0;
        $params['itemtype'] = 'User';
        $params['link_resources_id'] = 0;

        $dbu = new DbUtils();

        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }

        $resource_item = new Resource_Item();

        if (strstr($resources_id, 'users')) {
            [$tag, $users_id] = explode('-', $resources_id);
        }

        $resourceOk = $this->getFromDB($resources_id);
        $source_entity = $this->fields['entities_id'];

        if (!$resourceOk) {
            // Link user to resource
            if (!empty($params['link_resources_id'])) {
                $input = [
                    'plugin_resources_resources_id' => $params['link_resources_id'],
                    'items_id' => $users_id,
                    'itemtype' => $params['itemtype'],
                ];
                if ($resource_item->can(-1, 'w', $input)) {
                    $resourceOk = $resource_item->add($input);
                }
                $resources_id = $params['link_resources_id'];
                $resourceOk = $this->getFromDB($resources_id);
                // Add resource
            } else {
                [$resources_id, $error, $message] = self::fastResourceAdd($users_id, $params);
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
                $input = [
                    'plugin_resources_resources_id' => $resources_id,
                    'items_id' => $params['users_id'],
                    'itemtype' => $params['itemtype'],
                ];
                if ($resource_item->can(-1, 'w', $input)) {
                    $resource_item->add($input);
                }
            }

            $contracttype = ContractType::transfer($this->fields["plugin_resources_contracttypes_id"], $entities_id);
            if ($contracttype > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_contracttypes_id"] = $contracttype;
                $this->update($values);
            }

            unset($values);

            $resourcestate = ResourceState::transfer($this->fields["plugin_resources_resourcestates_id"], $entities_id);
            if ($resourcestate > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_resourcestates_id"] = $resourcestate;
                $this->update($values);
            }

            unset($values);

            $department = Department::transfer($this->fields["plugin_resources_departments_id"], $entities_id);
            if ($department > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_departments_id"] = $department;
                $this->update($values);
            }

            unset($values);

            $situation = ResourceSituation::transfer(
                $this->fields["plugin_resources_resourcesituations_id"],
                $entities_id,
            );
            if ($situation > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_resourcesituations_id"] = $situation;
                $this->update($values);
            }

            unset($values);

            $contractnature = ContractNature::transfer(
                $this->fields["plugin_resources_contractnatures_id"],
                $entities_id,
            );
            if ($contractnature > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_contractnatures_id"] = $contractnature;
                $this->update($values);
            }
            unset($values);

            $rank = Rank::transfer($this->fields["plugin_resources_ranks_id"], $entities_id);
            if ($rank > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_ranks_id"] = $rank;
                $this->update($values);
            }

            unset($values);

            $speciality = ResourceSpeciality::transfer(
                $this->fields["plugin_resources_resourcespecialities_id"],
                $entities_id,
            );
            if ($speciality > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_resourcespecialities_id"] = $speciality;
                $this->update($values);
            }
            unset($values);

            $Task = new Task();
            $restrict = ["plugin_resources_resources_id" => $resources_id];
            $tasks = $dbu->getAllDataFromTable("glpi_plugin_resources_tasks", $restrict);
            if (!empty($tasks)) {
                foreach ($tasks as $task) {
                    $Task->getFromDB($task["id"]);
                    $tasktype = TaskType::transfer($Task->fields["plugin_resources_tasktypes_id"], $entities_id);
                    if ($tasktype > 0) {
                        $values["id"] = $task["id"];
                        $values["plugin_resources_tasktypes_id"] = $tasktype;
                        $Task->update($values);
                    }
                    $values["id"] = $task["id"];
                    $values["entities_id"] = $entities_id;
                    $Task->update($values);
                }
            }

            unset($values);

            $Employment = new Employment();
            $restrict = ["plugin_resources_resources_id" => $resources_id];
            $employments = $dbu->getAllDataFromTable("glpi_plugin_resources_employments", $restrict);
            if (!empty($employments)) {
                foreach ($employments as $employment) {
                    $Employment->getFromDB($employment["id"]);
                    $rank = Rank::transfer($Employment->fields["plugin_resources_ranks_id"], $entities_id);
                    if ($rank > 0) {
                        $values["id"] = $employment["id"];
                        $values["plugin_resources_ranks_id"] = $rank;
                        $Employment->update($values);
                    }
                    $Employment->getFromDB($employment["id"]);
                    $profession = Profession::transfer(
                        $Employment->fields["plugin_resources_professions_id"],
                        $entities_id,
                    );
                    if ($profession > 0) {
                        $values["id"] = $employment["id"];
                        $values["plugin_resources_professions_id"] = $profession;
                        $Employment->update($values);
                    }
                    $values["id"] = $employment["id"];
                    $values["entities_id"] = $entities_id;
                    $Employment->update($values);
                }
            }

            unset($values);

            $Employee = new Employee();

            $restrict = ["plugin_resources_resources_id" => $resources_id];
            $employees = $dbu->getAllDataFromTable("glpi_plugin_resources_employees", $restrict);
            if (!empty($employees)) {
                foreach ($employees as $employee) {
                    $employer = Employer::transfer($employee["plugin_resources_employers_id"], $entities_id);
                    if ($employer > 0) {
                        $values["id"] = $employee["id"];
                        $values["plugin_resources_employers_id"] = $employer;
                        $Employee->update($values);
                    }

                    $client = Client::transfer($employee["plugin_resources_clients_id"], $entities_id);
                    if ($client > 0) {
                        $values["id"] = $employee["id"];
                        $values["plugin_resources_clients_id"] = $client;
                        $Employee->update($values);
                    }
                }
            }

            unset($values);

            $values["id"] = $resources_id;
            $values["entities_id"] = $entities_id;
            if ($this->update($values)) {
                // Check list
                $checklist_exist = Checklist::checkIfChecklistExist(
                    $resources_id,
                    Checklist::RESOURCES_CHECKLIST_TRANSFER,
                );
                $checklistconfig = new Checklistconfig();
                if ($checklist_exist) {
                    $checklist = new Checklist();
                    $checklist->deleteByCriteria([
                        'plugin_resources_resources_id' => $resources_id,
                        'checklist_type' => Checklist::RESOURCES_CHECKLIST_TRANSFER,
                    ]);
                    $DB->update(
                        'glpi_plugin_resources_checklists',
                        ['entities_id' => (int) $entities_id],
                        ['plugin_resources_resources_id' => (int) $resources_id],
                    );
                }
                $checklistconfig->addChecklistsFromRules($this, Checklist::RESOURCES_CHECKLIST_TRANSFER);

                // Notification
                $restrict = [
                    "itemtype" => 'User',
                    "plugin_resources_resources_id" => $resources_id,
                ];

                $data = $dbu->getAllDataFromTable('glpi_plugin_resources_resources_items', $restrict);

                if (!empty($data)) {
                    $linkeduser = [];
                    foreach ($data as $val) {
                        $linkeduser[$val['items_id']] = $val['items_id'];
                    }
                    $reportconfig = new ReportConfig();
                    if ($reportconfig->getFromDBByResource($resources_id)) {
                        if ($reportconfig->fields['send_other_notif']) {
                            NotificationEvent::raiseEvent('other', $this, ['reports_id' => $reportconfig->fields['id']]);
                        }
                        if ($reportconfig->fields['send_transfer_notif']) {
                            NotificationEvent::raiseEvent(
                                'transfer',
                                $this,
                                [
                                    'reports_id' => $reportconfig->fields['id'],
                                    'users_id' => $linkeduser,
                                    'source_entity' => $source_entity,
                                    'target_entity' => $entities_id,
                                ],
                            );
                        }
                    }
                } else {
                    Session::addMessageAfterRedirect(
                        __('The notification is not sent because the resource is not linked with a user', 'resources'),
                        true,
                        ERROR,
                    );
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
    public static function cronInfo($name)
    {
        switch ($name) {
            case 'Resources':
                return [
                    'description' => __('Resources not declaring leaving', 'resources'),
                ];   // Optional
                break;
            case 'AlertCommercialManager':
                return [
                    'description' => __('Resources list of commercial manager', 'resources'),
                ];   // Optional
                break;
            case 'UpdateResourcesState':
                return [
                    'description' => __('Update Resources state', 'resources'),
                ];   // Optional
                break;
        }
        return [];
    }

    /**
     * @return array
     */
    public function queryAlert()
    {
        $date = date("Y-m-d H:i:s");
        $query
            = [
                'SELECT' => [
                    '*',
                ],
                'FROM' => $this->getTable(),
                'WHERE' => [
                    'NOT'       => ['date_end' => null, 'is_leaving' => 1],
                    'date_end'    => ['<=', $date],
                    'is_deleted'    => 0,
                    'is_template'    => 0,
                ],
            ];

        return $query;
    }

    /**
     * Cron action on tasks : LeavingResources
     *
     * @param $task for log, if NULL display
     *
     **/
    public static function cronResources($task = null)
    {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["notifications_mailing"]) {
            return 0;
        }

        $message = [];
        $cron_status = 0;

        $resource = new self();
        $query_expired = $resource->queryAlert();

        $querys = [Alert::END => $query_expired];

        $task_infos = [];
        $task_messages = [];

        foreach ($querys as $type => $query) {
            $task_infos[$type] = [];
            foreach ($DB->request($query) as $data) {
                $entity = $data['entities_id'];
                $message = $data["name"] . " " . $data["firstname"] . " : "
                    . Html::convDate($data["date_end"]) . "<br>\n";
                $task_infos[$type][$entity][] = $data;

                if (!isset($task_messages[$type][$entity])) {
                    $task_messages[$type][$entity] = __(
                        'These resources have normally left the company',
                        'resources',
                    ) . "<br />";
                }
                $task_messages[$type][$entity] .= $message;
            }
        }

        foreach ($querys as $type => $query) {
            foreach ($task_infos[$type] as $entity => $resources) {
                Plugin::loadLang('resources');

                if (NotificationEvent::raiseEvent(
                    "AlertLeavingResources",
                    new Resource(),
                    [
                        'entities_id' => $entity,
                        'resources' => $resources,
                    ],
                )
                ) {
                    $message = $task_messages[$type][$entity];
                    $cron_status = 1;
                    if ($task) {
                        $task->log(
                            Dropdown::getDropdownName(
                                "glpi_entities",
                                $entity,
                            ) . ":  $message\n",
                        );
                        $task->addVolume(1);
                    } else {
                        Session::addMessageAfterRedirect(
                            Dropdown::getDropdownName(
                                "glpi_entities",
                                $entity,
                            ) . ":  $message",
                        );
                    }
                } else {
                    if ($task) {
                        $task->log(
                            Dropdown::getDropdownName("glpi_entities", $entity)
                            . ":  Send leaving resources alert failed\n",
                        );
                    } else {
                        Session::addMessageAfterRedirect(
                            Dropdown::getDropdownName("glpi_entities", $entity)
                            . ":  Send leaving resources alert failed",
                            false,
                            ERROR,
                        );
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
    public static function cronAlertCommercialManager($task = null)
    {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["notifications_mailing"]) {
            return 0;
        }

        $message = [];
        $cron_status = 0;

        $query_commercial = [
            'SELECT' => [
                'users_id_sales',
            ],
            'DISTINCT' => true,
            'FROM' => 'glpi_plugin_resources_resources',
            'WHERE' => [
                'is_deleted' => 0,
                'users_id_sales' => ['<>', 0],
            ],
        ];

        foreach ($DB->request($query_commercial) as $commercial) {
            $query = [
                'SELECT' => [
                    '*',
                ],
                'FROM' => 'glpi_plugin_resources_resources',
                'WHERE' => [
                    'is_deleted' => 0,
                    'users_id_sales' => $commercial['users_id_sales'],
                ],
            ];

            $resources = [];
            foreach ($DB->request($query) as $data) {
                $resources[] = $data;
            }
            $resource = new Resource();
            $resource->fields['id'] = $resources[0]['id'] ?? 0;

            $dbu = new DbUtils();

            if (count($resources) > 0 && NotificationEvent::raiseEvent(
                "AlertCommercialManager",
                $resource,
                [
                    'resources' => $resources,
                    'users_id_sales' => $commercial['users_id_sales'],
                ],
            )
            ) {
                $cron_status = 1;
                if ($task) {
                    $task->log(
                        $dbu->getUserName($commercial['users_id_sales']) . ": "
                        . __('Send alert to the commercial manager', 'resources') . "\n",
                    );
                    $task->addVolume(1);
                } else {
                    Session::addMessageAfterRedirect(
                        getUserName($commercial['users_id_sales']) . ": "
                        . __('Send alert to the commercial manager', 'resources') . "\n",
                    );
                }
            } else {
                if ($task) {
                    $task->log(
                        $dbu->getUserName($commercial['users_id_sales']) . ": "
                        . __('Failed to Send alert to the commercial manager', 'resources') . "\n",
                    );
                } else {
                    Session::addMessageAfterRedirect(
                        getUserName($commercial['users_id_sales']) . ": "
                        . __('Failed to Send alert to the commercial manager', 'resources') . "\n",
                    );
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
    public static function cronUpdateResourcesState($task = null)
    {
        global $DB, $CFG_GLPI;

        $resource = new Resource();
        $config = new Config();
        $config->getFromDB(1);

        $message = [];
        $cron_status = 1;

        $query_arrival = [
            'SELECT' => [
                '*',
            ],
            'FROM' => 'glpi_plugin_resources_resources',
            'WHERE' => [
                'is_deleted' => 0,
                'NOT' => ['date_begin' => 'NULL'],
                'date_begin' => ['<=', QueryFunction::now()],
                [
                    'OR' => ['date_end' => 'NULL'],
                    'date_end' => ['>', QueryFunction::now()],
                ],
            ],
        ];

        foreach ($DB->request($query_arrival) as $resourceD) {
            if ($resourceD['plugin_resources_resourcestates_id'] != $config->fields['plugin_resources_resourcestates_id_arrival']) {
                $input = [];
                $input['id'] = $resourceD['id'];
                $input["plugin_resources_resourcestates_id"] = $config->fields['plugin_resources_resourcestates_id_arrival'];
                $resource->update($input);
                $task->addVolume(1);
            }
        }

        $query_departure = [
            'SELECT' => [
                '*',
            ],
            'FROM' => 'glpi_plugin_resources_resources',
            'WHERE' => [
                'is_deleted' => 0,
                'NOT' => ['date_begin' => 'NULL'],
                'date_begin' => ['<=', QueryFunction::now()],
                'date_end' => ['<', QueryFunction::now()],
            ],
        ];

        foreach ($DB->request($query_departure) as $resourceD) {
            if ($resourceD['plugin_resources_resourcestates_id'] != $config->fields['plugin_resources_resourcestates_id_departure']) {
                $input = [];
                $input['id'] = $resourceD['id'];
                $input["plugin_resources_resourcestates_id"] = $config->fields['plugin_resources_resourcestates_id_departure'];
                $resource->update($input);
                $task->addVolume(1);
            }
        }

        return $cron_status;
    }

    /**
     * Show the contract type tree used to filter the resource list.
     *
     * Rendered inside an iframe modal, in a page that goes through none of the usual
     * header: the stylesheets and the scripts it needs are pulled here.
     *
     * @param string $target front page the "Show all" link points back to
     *
     * @return void
     */
    public static function showSelector($target)
    {
        Plugin::loadLang('resources');

        $assets = Html::css("lib/base.css")
            . Html::script("lib/base.js")
            . Html::css(PLUGIN_RESOURCES_WEBDIR . "/lib/jstree/themes/default/style.min.css")
            . Html::css(PLUGIN_RESOURCES_WEBDIR . "/lib/jstree/jstree-glpi.css")
            . Html::script(PLUGIN_RESOURCES_WEBDIR . "/scripts/resourcetree.js", [], false);

        TemplateRenderer::getInstance()->display('@resources/resource_tree.html.twig', [
            'assets'    => $assets,
            'rand'      => mt_rand(),
            'target'    => $target,
            'root_doc'  => PLUGIN_RESOURCES_WEBDIR,
            'more_text' => __('Load more...'),
        ]);
    }

    /**
     * @param $items
     *
     * @return bool
     */
    public function sendEmail($items)
    {
        $users = [];
        foreach ($items as $key => $val) {
            $restrict = [
                "itemtype" => 'User',
                "plugin_resources_resources_id" => $key,
            ];
            $dbu = new DbUtils();
            $resources = $dbu->getAllDataFromTable("glpi_plugin_resources_resources_items", $restrict);

            if (!empty($resources)) {
                foreach ($resources as $resource) {
                    $users[] = $resource["items_id"];
                }
            }
        }

        $User = new \User();
        $mail = "";
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
    public static function sendFile($file, $filename)
    {
        // Test securite : document in DOC_DIR
        $tmpfile = str_replace(GLPI_PLUGIN_DOC_DIR . "/resources/pictures/", "", $file);

        if (strstr($tmpfile, "../") || strstr($tmpfile, "..\\")) {
            Event::log(
                $file,
                "sendFile",
                1,
                "security",
                $_SESSION["glpiname"] . " try to get a non standard file.",
            );
            die("Security attack !!!");
        }

        if (!file_exists($file)) {
            die("Error file $file does not exist");
        }

        $splitter = explode("/", $file);
        $mime = "application/octet-stream";

        if (preg_match('/\.(....?)$/', $file, $regs)) {
            switch ($regs[1]) {
                case "jpeg":
                    $mime = "image/jpeg";
                    break;

                case "jpg":
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

        readfile($file) or die("Error opening file $file");
    }

    /**
     * Permet l'affichage dynamique d'une liste déroulante imbriquee
     *
     * @static
     *
     * @param array ($itemtype,$myname,$value,$entity_restrict,$action,$span)
     */
    public static function showGenericDropdown($itemtype, $options = [])
    {

        if (isset($options['name'])) {
            // Set dropdown
            $options['on_change'] = "update" . $options['name'] . "();";
            $options['entity'] = $_SESSION['glpiactive_entity'];
            $options['addicon'] = true;
            $rand = Dropdown::show($itemtype, $options);

            // Set ajax load if needed
            if (isset($options['action']) && isset($options['span'])) {
                $options[$options['name']] = "__VALUE__";
                $options['entity_restrict'] = $_SESSION['glpiactive_entity'];
                $options['rand'] = $rand;
                $script = "function update" . $options['name'] . "(){";
                $script .= Ajax::updateItemJsCode(
                    $options['span'],
                    $options['action'],
                    $options,
                    'dropdown_' . $options['name'] . $rand,
                    false,
                );
                $script .= "}";
                echo Html::scriptBlock($script);
            }
        }
    }

    /**
     * Build the Profession/Rank pair shared by the Budget, Cost and Employment forms.
     *
     * The Profession dropdown wires an AJAX refresh of the rank span through
     * showGenericDropdown(), and Rank is displayed read-only because it follows the
     * selected profession. Neither fits a plain fields.dropdownField macro, so both
     * are returned as ready-to-render HTML for fields.htmlField().
     *
     * @param array $fields  Item fields, holding plugin_resources_professions_id,
     *                       plugin_resources_ranks_id and entities_id
     * @param bool  $sort    Whether the profession dropdown is sorted
     *
     * @return array{profession_dropdown: string, rank_html: string}
     */
    public static function getProfessionRankFields(array $fields, bool $sort = true): array
    {
        ob_start();
        self::showGenericDropdown(Profession::class, [
            'name'   => 'plugin_resources_professions_id',
            'value'  => $fields['plugin_resources_professions_id'],
            'entity' => $fields['entities_id'],
            'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownRank.php",
            'span'   => 'span_rank',
            'sort'   => $sort,
        ]);
        $profession_dropdown = (string) ob_get_clean();

        if ($fields['plugin_resources_ranks_id'] > 0) {
            $rank_label = Dropdown::getDropdownName(
                'glpi_plugin_resources_ranks',
                $fields['plugin_resources_ranks_id'],
            );
        } else {
            $rank_label = __('None');
        }

        return [
            'profession_dropdown' => $profession_dropdown,
            'rank_html'           => "<span id='span_rank' name='span_rank'>" . $rank_label . "</span>",
        ];
    }

    /**
     * Display information on treeview plugin
     *
     * @params itemtype, id, pic, url, name
     *
     * @return params
     **/
    public static function showResourceTreeview($params)
    {
        global $CFG_GLPI;

        if ($params['itemtype'] == Resource::class) {
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
    public function checkTransferMandatoryFields($input)
    {
        $msg = [];
        $checkKo = false;

        $mandatory_fields = ['entities_id' => __('Entity'), 'plugin_resources_resources_id' => self::getTypeName(1)];

        foreach ($input as $key => $value) {
            if (array_key_exists($key, $mandatory_fields)) {
                if (empty($value)) {
                    $msg[] = $mandatory_fields[$key];
                    $checkKo = true;
                }
            }
        }

        if ($checkKo) {
            Session::addMessageAfterRedirect(
                sprintf(__("Mandatory fields are not filled. Please correct: %s"), implode(', ', $msg)),
                false,
                ERROR,
            );
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
    public static function getThumbnailURLForPicture($picture)
    {
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
     * List, in the client tab, the resources whose employee record points at that client.
     *
     * @param int $client_id
     *
     * @return void
     */
    public function showListResourcesForClient($client_id)
    {
        global $DB;

        $dbu = new DbUtils();

        // The join is what filters on the client, so it must be an inner one. The entity
        // restriction is ours to add: the client tab does not scope the linked resources.
        $iterator = $DB->request([
            'SELECT'     => [self::getTable() . '.*'],
            'FROM'       => self::getTable(),
            'INNER JOIN' => [
                Employee::getTable() => [
                    'ON' => [
                        Employee::getTable() => 'plugin_resources_resources_id',
                        self::getTable()     => 'id',
                    ],
                ],
            ],
            'WHERE'      => [
                self::getTable() . '.is_deleted'                      => 0,
                Employee::getTable() . '.plugin_resources_clients_id' => (int) $client_id,
            ] + $dbu->getEntitiesRestrictCriteria(self::getTable(), '', '', $this->maybeRecursive()),
            'ORDER'      => self::getTable() . '.name',
        ]);

        $resource = new self();
        $entries  = [];
        foreach ($iterator as $data) {
            $resource->getFromResultSet($data);
            $entries[] = [
                'name'       => $resource->getLink(),
                'firstname'  => $data['firstname'],
                'state'      => Dropdown::getDropdownName(
                    ResourceState::getTable(),
                    $data['plugin_resources_resourcestates_id'],
                ),
                'location'   => Dropdown::getDropdownName('glpi_locations', $data['locations_id']),
                'department' => Dropdown::getDropdownName(
                    Department::getTable(),
                    $data['plugin_resources_departments_id'],
                ),
            ];
        }

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'is_tab'          => true,
            'nofilter'        => true,
            'nosort'          => true,
            'super_header'    => __('Resources list', 'resources'),
            'columns'         => [
                'name'       => __('Surname'),
                'firstname'  => __('First name'),
                'state'      => ResourceState::getTypeName(1),
                'location'   => __('Location'),
                'department' => Department::getTypeName(1),
            ],
            // getLink() returns an anchor, everything else is plain text Twig escapes.
            'formatters'      => ['name' => 'raw_html'],
            'entries'         => $entries,
            'total_number'    => count($entries),
            'filtered_number' => count($entries),
        ]);
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
     */
    public function isExistingResourceByIdentifier($identifiers = [])
    {
        global $DB;

        $tableResourceCriterias = [];
        $tableResourceImportCriterias = [];

        foreach ($identifiers as $identifier) {
            if (is_string($identifier['value']) && empty($identifier['value'])) {
                $identifier['value'] = null;
            }

            switch ($identifier['resource_column']) {
                case 10:
                    $tableResourceImportCriterias[] = [
                        'name' => $identifier['name'],
                        'value' => $identifier['value'],
                        'type' => $identifier['type'],
                    ];
                    break;
                default:
                    $tableResourceCriterias[] = [
                        'name' => $this->getColumnName($identifier['resource_column']),
                        'value' => $identifier['value'],
                        'type' => $identifier['type'],
                    ];
                    break;
            }
        }

        $criteria = [
            'SELECT' => 'r.*',
            'FROM'   => self::getTable() . ' AS r',
        ];
        $where = [];

        if (count($tableResourceImportCriterias) > 0) {
            $criteria['INNER JOIN'] = [
                ResourceImport::getTable() . ' AS ri' => [
                    'ON' => [
                        'ri' => 'plugin_resources_resources_id',
                        'r'  => 'id',
                    ],
                ],
            ];

            foreach ($tableResourceImportCriterias as $tableResourceImportCriteria) {
                $where[] = [
                    'ri.name'  => $tableResourceImportCriteria['name'],
                    'ri.value' => $tableResourceImportCriteria['value'],
                ];
            }
        }

        if (count($tableResourceCriterias) > 0) {
            foreach ($tableResourceCriterias as $tableResourceCriteria) {
                $where[] = ['r.' . $tableResourceCriteria['name'] => $tableResourceCriteria['value']];
            }
        }

        $criteria['WHERE'] = $where;

        $iterator = $DB->request($criteria);

        foreach ($iterator as $data) {
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
     */
    public function isExistingResourceByImportResourceID($importResourceID)
    {
        $pluginResourcesImportResourceData = new ImportResourceData();

        // First level identifier
        $firstLevelIdentifiers = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel(
            $importResourceID,
            1,
        );

        $resourceID = $this->isExistingResourceByIdentifier($firstLevelIdentifiers);

        if (!is_null($resourceID)) {
            return $resourceID;
        }

        // Second level identifier
        $secondLevelIdentifiers = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel(
            $importResourceID,
            2,
        );

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
    public function isDifferentFromImportResourceDatas($resourceID, $datas)
    {
        foreach ($datas as $data) {
            if (self::isDifferentFromImportResourceData($resourceID, $data)) {
                return true;
            }
        }
        return false;
    }

    public function isDifferentFromImportResourceData($resourceID, $data)
    {
        $result = self::hasDifferenciesWithValueByDataNameID(
            $resourceID,
            $data['resource_column'],
            $data['name'],
            $data['value'],
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
    public function isDifferentFromImportResource($resourceID, $importResourceID)
    {
        $pluginResourcesResource = new self();
        if (!$pluginResourcesResource->getFromDB($resourceID)) {
            throw new BadRequestHttpException("No resource for id " . $resourceID);
        }

        $pluginResourcesImportResource = new ImportResource();
        if (!$pluginResourcesImportResource->getFromDB($importResourceID)) {
            throw new BadRequestHttpException("No importResource for id " . $importResourceID);
        }

        $pluginResourcesImportResourceData = new ImportResourceData();

        // Get all import data
        $datas = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel(
            $importResourceID,
            null,
            ['resource_column'],
        );

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
    public function getResourceImportValueByName($resourceID, $name)
    {
        $pluginResourcesResource = new self();
        $pluginResourcesResource->getFromDB($resourceID);

        $pluginResourcesResourceImport = new ResourceImport();

        $crit = [
            ResourceImport::$items_id => $pluginResourcesResource->getID(),
            'name' => $name,
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
    public function getFieldByDataNameID($dataNameID)
    {
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
    public function hasDifferenciesWithValueByDataNameID($resourceID, $dataNameID, $name, $value)
    {
        $pluginResourcesResource = new self();
        if (!$pluginResourcesResource->getFromDB($resourceID)) {
            throw new BadRequestHttpException("No resource for id " . $resourceID);
        }

        switch ($dataNameID) {
            case 10:
                // Find in Resource Import
                $pluginResourcesResourceImport = new ResourceImport();

                $crit = [
                    ResourceImport::$items_id => $resourceID,
                    'name' => $name,
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
                $resourceValue = $pluginResourcesResource->getField($resourceFieldName);

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

    public static function getGenders()
    {
        return [
            Dropdown::EMPTY_VALUE,
            __('Male', 'resources'),
            __('Female', 'resources'),
            __('Other', 'resources'),
        ];
    }

    public static function getGenderByValue($value)
    {
        switch ($value) {
            case 1 :
                return __('Male', 'resources');
                break;
            case 2 :
                return __('Female', 'resources');
                break;
            case 3 :
                return __('Other', 'resources');
                break;
            default:
                return '';
        }
    }

    /**
     * @param $field
     * @param $name (DEFAULT '')
     * @param $values (DEFAULT '')
     * @param $options   array
     *
     * @return string
     **@since version 0.84
     *
     */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        switch ($field) {
            case 'gender':
                return Dropdown::showFromArray($name, self::getGenders(), ['display' => false]);
                break;
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * display a value according to a field
     *
     * @param $field     String         name of the field
     * @param $values    String / Array with the value to display
     * @param $options   Array          of option
     *
     * @return a string
     **@since version 0.83
     *
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'gender':
                if (empty($values[$field])) {
                    $values[$field] = 0;
                }
                $gender = self::getGenders();
                return $gender[$values[$field]];
                //            break;
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    //   public function getCloneRelations(): array {
    //      return [
    ////         Task::class,
    ////         Employee::class,
    ////         ReportConfig::class,
    ////         Resource_Item::class,
    ////         ResourceHabilitation::class,
    ////         Choice::class,
    //         Document_Item::class,
    //         Notepad::class
    //      ];
    //   }

    public static function jsGetElementbyID($id)
    {
        return "$('#$id')";
    }

    public static function joinDropdownTranslations($alias, $table, $itemtype, $field)
    {
        global $DB;
        return "LEFT JOIN " . $DB::quoteName('glpi_dropdowntranslations') . " AS " . $DB::quoteName($alias) . "
                  ON (" . $DB::quoteName($alias . '.itemtype') . " = " . $DB->quote($itemtype) . "
                    AND " . $DB::quoteName($alias . '.items_id') . " = " . $DB::quoteName($table . '.id') . "
                    AND " . $DB::quoteName($alias . '.language') . " = " . $DB->quote($_SESSION['glpilanguage']) . "
                    AND " . $DB::quoteName($alias . '.field') . " = " . $DB->quote($field) . ")";
    }

    public static function supportHelpdeskDisplayPreferences(): bool
    {
        return true;
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();
        $table  = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                        `id`                                        int {$default_key_sign} NOT NULL auto_increment,
                        `entities_id`                               int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `is_recursive`                              tinyint        NOT NULL                 DEFAULT '0',
                        `name`                                      varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `firstname`                                 varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `plugin_resources_contracttypes_id`         int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_contracttypes (id)',
                        `users_id`                                  int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_users (id)',
                        `users_id_sales`                            int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_users (id)',
                        `users_id_recipient`                        int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_users (id)',
                        `date_declaration`                          timestamp      NULL                     DEFAULT NULL,
                        `date_begin`                                timestamp      NULL                     DEFAULT NULL,
                        `date_end`                                  timestamp      NULL                     DEFAULT NULL,
                        `quota`                                     decimal(10, 4) NOT NULL                 DEFAULT '1.0000',
                        `plugin_resources_departments_id`           int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_departments (id)',
                        `plugin_resources_resourcestates_id`        int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_resourcestates (id)',
                        `plugin_resources_resourcesituations_id`    int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_contractnatures_id`       int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_ranks_id`                 int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_resourcespecialities_id`  int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `locations_id`                              int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_locations (id)',
                        `is_leaving`                                int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_workprofiles_id`          int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_leavingreasons_id`        int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `date_declaration_leaving`                  timestamp      NULL                     DEFAULT NULL,
                        `date_agreement_candidate`                  timestamp      NULL                     DEFAULT NULL,
                        `date_of_last_contract_type`                timestamp      NULL                     DEFAULT NULL,
                        `date_of_last_location`                     timestamp      NULL                     DEFAULT NULL,
                        `users_id_recipient_leaving`                int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_users (id)',
                        `picture`                                   varchar(100) COLLATE utf8mb4_unicode_ci default NULL,
                        `is_helpdesk_visible`                       int {$default_key_sign}   NOT NULL                 DEFAULT '1',
                        `date_mod`                                  timestamp      NULL                     DEFAULT NULL,
                        `comment`                                   TEXT COLLATE utf8mb4_unicode_ci,
                        `is_template`                               tinyint        NOT NULL                 DEFAULT '0',
                        `template_name`                             varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `is_deleted`                                tinyint        NOT NULL                 DEFAULT '0',
                        `sensitize_security`                        tinyint        NOT NULL                 DEFAULT '0',
                        `read_chart`                                tinyint        NOT NULL                 DEFAULT '0',
                        `contract_type_change`                      tinyint        NOT NULL                 DEFAULT '0',
                        `reconversion`                              tinyint        NOT NULL                 DEFAULT '0',
                        `plugin_resources_roles_id`                 int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_roles (id)',
                        `matricule`                                 varchar(255)   NOT NULL                 DEFAULT '',
                        `plugin_resources_functions_id`             int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_teams_id`                 int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_services_id`              int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_degreegroups_id`          int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_recruitingsources_id`     int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `last_contract_type`                        int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `last_location`                             int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `yearsexperience`                           int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_candidateorigins_id`      int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_workprofiles_id_entrance` int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `matricule_second`                          varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `secondary_services`                        varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `gender`                                    varchar(3) COLLATE utf8mb4_unicode_ci   DEFAULT NULL,
                        `phone`                                     varchar(20) COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
                        `cellphone`                                 varchar(20) COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
                        `remove_manager`                            int {$default_key_sign} NOT NULL DEFAULT '0',
                        `remove_order`                              TEXT COLLATE utf8mb4_unicode_ci,
                        `computer_phone_equipment`                  TEXT COLLATE utf8mb4_unicode_ci,
                        `softwares_requirements`                    TEXT COLLATE utf8mb4_unicode_ci,
                        `furnitures_needs`                          TEXT COLLATE utf8mb4_unicode_ci,
                        `other_needs`                               TEXT COLLATE utf8mb4_unicode_ci,
                        `valid_resource_information`                tinyint NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`),
                        KEY `name` (`name`),
                        KEY `entities_id` (`entities_id`),
                        KEY `is_recursive` (`is_recursive`),
                        KEY `users_id` (`users_id`),
                        KEY `users_id_sales` (`users_id_sales`),
                        KEY `users_id_recipient` (`users_id_recipient`),
                        KEY `locations_id` (`locations_id`),
                        KEY `is_leaving` (`is_leaving`),
                        KEY `users_id_recipient_leaving` (`users_id_recipient_leaving`),
                        KEY `date_mod` (`date_mod`),
                        KEY `is_helpdesk_visible` (`is_helpdesk_visible`),
                        KEY `is_deleted` (`is_deleted`),
                        KEY `is_template` (`is_template`),
                        KEY `plugin_resources_contracttypes_id` (`plugin_resources_contracttypes_id`),
                        KEY `plugin_resources_departments_id` (`plugin_resources_departments_id`),
                        KEY `plugin_resources_resourcestates_id` (`plugin_resources_resourcestates_id`),
                        KEY `plugin_resources_resourcesituations_id` (`plugin_resources_resourcesituations_id`),
                        KEY `plugin_resources_contractnatures_id` (`plugin_resources_contractnatures_id`),
                        KEY `plugin_resources_ranks_id` (`plugin_resources_ranks_id`),
                        KEY `plugin_resources_resourcespecialities_id` (`plugin_resources_resourcespecialities_id`),
                        KEY `plugin_resources_leavingreasons_id` (`plugin_resources_leavingreasons_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 2,
                    'rank' => 1,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 3,
                    'rank' => 2,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 4,
                    'rank' => 3,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 5,
                    'rank' => 4,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 6,
                    'rank' => 5,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 8,
                    'rank' => 6,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

        }
    }
}
