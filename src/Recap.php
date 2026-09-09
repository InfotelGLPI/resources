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

use CommonDBTM;
use Location;
use Migration;
use Search;
use Session;

/**
 * Recap Class
 * This class is used to generate report
 * */
class Recap extends CommonDBTM
{
    protected static $notable = true;
    private $table = "glpi_users";

    public static function getTable($classname = null)
    {
        return \User::getTable();
    }

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @param integer $nb Number of items
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return _n('List Employment / Resource', 'List Employments / Resources', $nb, 'resources');
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return
     **/
    public static function canCreate(): bool
    {
        if (Session::haveRight('plugin_resources_employment', UPDATE)) {
            return true;
        }
        return false;
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
        if (Session::haveRight('plugin_resources_employment', READ)) {
            return true;
        }
        return false;
    }

    /**
     * Provides search options configuration. Do not rely directly
     * on this, @return array a *not indexed* array of search options
     *
     * @since 9.3
     *
     * This should be overloaded in Class
     *
     * @see CommonDBTM::searchOptions instead.
     *
     * @see https://glpi-developer-documentation.rtfd.io/en/master/devapi/search.html
     **/
    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id' => 'common',
            'name' => self::getTypeName(2),
        ];

        $tab[] = [
            'id' => '1',
            'table' => $this->table,
            'field' => 'registration_number',
            'name' => _x('user', 'Administrative number'),
            'datatype' => 'string',
        ];

        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

        $tab[] = [
            'id' => '4350',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'name',
            'name' => __('Surname'),
            'datatype' => 'itemlink',
            'itemlink_type' => Resource::class,
        ];

        $tab[] = [
            'id' => '4351',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'firstname',
            'name' => __('First name'),
            'itemlink_type' => Resource::class,
        ];

        $tab[] = [
            'id' => '4352',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'quota',
            'name' => __('Quota', 'resources'),
            'datatype' => 'decimal',
        ];

        $tab[] = [
            'id' => '4353',
            'table' => 'glpi_plugin_resources_resourcesituations',
            'field' => 'name',
            'name' => ResourceSituation::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4354',
            'table' => 'glpi_plugin_resources_contractnatures',
            'field' => 'name',
            'name' => ContractNature::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4355',
            'table' => 'glpi_plugin_resources_contracttypes',
            'field' => 'name',
            'name' => ContractType::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4356',
            'table' => 'glpi_plugin_resources_resourcespecialities',
            'field' => 'name',
            'name' => ResourceSpeciality::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4357',
            'table' => 'glpi_plugin_resources_ranks',
            'field' => 'name',
            'name' => Rank::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4358',
            'table' => 'glpi_plugin_resources_professions',
            'field' => 'name',
            'name' => Profession::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4359',
            'table' => 'glpi_plugin_resources_professionlines',
            'field' => 'name',
            'name' => ProfessionLine::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4360',
            'table' => 'glpi_plugin_resources_professioncategories',
            'field' => 'name',
            'name' => ProfessionCategory::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4376',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'date_begin',
            'name' => __('Arrival date', 'resources'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '4377',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'date_end',
            'name' => __('Departure date', 'resources'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '4361',
            'table' => 'glpi_plugin_resources_employments',
            'field' => 'name',
            'name' => __('Name') . " - " . Employment::getTypeName(1),
            'forcegroupby' => true,
        ];

        $tab[] = [
            'id' => '4362',
            'table' => 'glpi_plugin_resources_employments',
            'field' => 'ratio_employment_budget',
            'name' => __('Ratio Employment / Budget', 'resources'),
            'datatype' => 'decimal',
        ];

        $tab[] = [
            'id' => '4363',
            'table' => 'glpi_plugin_resources_employmentranks',
            'field' => 'name',
            'name' => Employment::getTypeName(1) . " - " . Rank::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4364',
            'table' => 'glpi_plugin_resources_employmentprofessions',
            'field' => 'name',
            'name' => Employment::getTypeName(1) . " - " . Profession::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4365',
            'table' => 'glpi_plugin_resources_employmentprofessionlines',
            'field' => 'name',
            'name' => Employment::getTypeName(1) . " - " . ProfessionLine::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4366',
            'table' => 'glpi_plugin_resources_employmentprofessioncategories',
            'field' => 'name',
            'name' => Employment::getTypeName(1) . " - " . ProfessionCategory::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4367',
            'table' => 'glpi_plugin_resources_employments',
            'field' => 'begin_date',
            'name' => __('Begin date'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '4368',
            'table' => 'glpi_plugin_resources_employments',
            'field' => 'end_date',
            'name' => __('End date'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '4369',
            'table' => 'glpi_plugin_resources_employmentstates',
            'field' => 'name',
            'name' => EmploymentState::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4370',
            'table' => 'glpi_plugin_resources_employers',
            'field' => 'completename',
            'name' => Employer::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4372',
            'table' => 'glpi_plugin_resources_employmentranks',
            'field' => 'id',
            'name' => Employment::getTypeName(1) . " - " . Rank::getTypeName(1) . " - " . __('ID'),
        ];

        $tab[] = [
            'id' => '4373',
            'table' => 'glpi_plugin_resources_employmentprofessions',
            'field' => 'id',
            'name' => Employment::getTypeName(1) . " - " . Profession::getTypeName(1) . " - " . __('ID'),
        ];

        $tab[] = [
            'id' => '4374',
            'table' => 'glpi_plugin_resources_ranks',
            'field' => 'id',
            'name' => Resource::getTypeName(1) . " - " . Rank::getTypeName(1) . " - " . __('ID'),
        ];

        $tab[] = [
            'id' => '4375',
            'table' => 'glpi_plugin_resources_professions',
            'field' => 'id',
            'name' => Resource::getTypeName(1) . " - " . Profession::getTypeName(1) . " - " . __('ID'),
        ];

        return $tab;
    }

    /**
     * @since version 0.84
     **/
    public function getForbiddenStandardMassiveAction()
    {
        $forbidden = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        $forbidden[] = 'purge';
        return $forbidden;
    }

    /**
     * Display result table for search engine for an type
     *
     * @param $itemtype item type to manage
     * @param $params search params passed to prepareDatasForSearch function
     *
     * @return
     **/
    public static function showList($itemtype, $params)
    {
        $data = Search::prepareDatasForSearch($itemtype, $params);
        Search::constructSQL($data);
        Search::constructData($data);
        Search::displayData($data);
    }

    /**
     * Get the specific massive actions
     *
     * @param object $checkitem link item to check right (default NULL)
     *
     * @return array an array of massive actions
     **@since 0.84
     *
     * This should be overloaded in Class
     *
     */
    public function getSpecificMassiveActions($checkitem = null)
    {
        //To avoid masives action error as there is no table for recap.class.php
        return [];
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4350,
                'rank' => 1,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4351,
                'rank' => 2,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4352,
                'rank' => 3,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4353,
                'rank' => 4,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4354,
                'rank' => 5,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4355,
                'rank' => 6,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4356,
                'rank' => 7,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4357,
                'rank' => 8,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4358,
                'rank' => 9,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4359,
                'rank' => 10,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4360,
                'rank' => 11,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4361,
                'rank' => 12,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4362,
                'rank' => 13,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4363,
                'rank' => 14,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4364,
                'rank' => 15,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4365,
                'rank' => 16,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4366,
                'rank' => 17,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4367,
                'rank' => 18,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4368,
                'rank' => 19,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4369,
                'rank' => 20,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4370,
                'rank' => 21,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );
    }

}
