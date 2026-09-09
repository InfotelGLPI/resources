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
use DbUtils;
use Location;
use MassiveAction;
use Migration;
use Search;
use Session;

/**
 * Class Directory
 */
class Directory extends CommonDBTM
{
    public static $rightname = 'plugin_resources';
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
        return __('Resources directory', 'resources');
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

        $tab[] = [
            'id' => '2',
            'table' => $this->table,
            'field' => 'id',
            'name' => __('ID'),
            'massiveaction' => false,
            'datatype' => 'number',
        ];

        $tab[] = [
            'id' => '34',
            'table' => $this->table,
            'field' => 'realname',
            'name' => __('Surname'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id' => '9',
            'table' => $this->table,
            'field' => 'firstname',
            'name' => __('First name'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id' => '5',
            'table' => 'glpi_useremails',
            'field' => 'email',
            'name' => _n('Email', 'Emails', 2),
            'datatype' => 'email',
            'joinparams' => ['jointype' => 'child'],
            'forcegroupby' => true,
            'massiveaction' => false,
        ];

        //        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

        $tab[] = [
            'id' => '6',
            'table' => $this->table,
            'field' => 'phone',
            'name' => __('Phone'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id' => '10',
            'table' => $this->table,
            'field' => 'phone2',
            'name' => __('Phone 2'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id' => '11',
            'table' => $this->table,
            'field' => 'mobile',
            'name' => __('Mobile phone'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id' => '4313',
            'table' => 'glpi_plugin_resources_employers',
            'field' => 'completename',
            'name' => Employer::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4314',
            'table' => 'glpi_plugin_resources_clients',
            'field' => 'name',
            'name' => Client::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4315',
            'table' => 'glpi_plugin_resources_contracttypes',
            'field' => 'name',
            'name' => ContractType::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4316',
            'table' => 'glpi_plugin_resources_managers',
            'field' => 'name',
            'name' => __('Resource manager', 'resources'),
            'searchtype' => 'contains',
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4317',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'date_begin',
            'name' => __('Arrival date', 'resources'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '4318',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'date_end',
            'name' => __('Departure date', 'resources'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '4319',
            'table' => 'glpi_plugin_resources_departments',
            'field' => 'name',
            'name' => Department::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4320',
            'table' => 'glpi_plugin_resources_resourcestates',
            'field' => 'name',
            'name' => ResourceState::getTypeName(1),
            'datatype' => 'dropdown',
        ];
        return $tab;
    }

    /**
     * Display result table for search engine for an type
     *
     * @param $itemtype
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
     * @since version 0.84
     * */
    public function getForbiddenStandardMassiveAction()
    {
        $forbidden = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        $forbidden[] = 'purge';
        return $forbidden;
    }

    //Massive action

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
        $actions = [];
        if (Session::getCurrentInterface() == "central") {
            $actions['GlpiPlugin\Resources\Directory' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Send'] = __(
                'Send a notification',
            );
        }
        return $actions;
    }

    /**
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     * */
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        $input = $ma->getInput();

        switch ($ma->getAction()) {
            case "Send":
                if ($item->sendEmail($ids)) {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_OK);
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
                }
                break;

            default:
                return parent::doSpecificMassiveActions($input);
        }
    }

    /**
     * @param $items
     *
     * @return bool
     */
    public function sendEmail($items)
    {
        /** @var \DBmysql $DB */
        global $DB;

        // The ids come straight from the massive-action form. The directory list itself is
        // scoped through glpi_profiles_users, so replay that scope here: without it a forged
        // id list disclosed the mail address of any user of the instance.
        $allowed   = [];
        $requested = array_map('intval', array_keys($items));
        if (!empty($requested)) {
            $dbu      = new DbUtils();
            $iterator = $DB->request([
                'SELECT'   => 'users_id',
                'DISTINCT' => true,
                'FROM'     => 'glpi_profiles_users',
                'WHERE'    => ['users_id' => $requested]
                    + $dbu->getEntitiesRestrictCriteria('glpi_profiles_users', '', '', true),
            ]);
            foreach ($iterator as $row) {
                $allowed[(int) $row['users_id']] = true;
            }
        }

        $User = new User();
        $mail = "";
        $first = true;
        foreach ($items as $key => $val) {
            if (isset($allowed[(int) $key]) && $User->getFromDB($key)) {
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

        // addMessageAfterRedirect() is rendered with |raw by the core toast template, and
        // the address list is built from user-editable fields: escape it here, and quote the
        // attribute so a stray apostrophe cannot break out of it either.
        $send = '<a href="mailto:' . htmlspecialchars($mail, ENT_QUOTES, 'UTF-8') . '">'
            . __('Click here to send your email', 'resources') . '</a>';
        Session::addMessageAfterRedirect($send);

        return true;
    }

    public static function supportHelpdeskDisplayPreferences(): bool
    {
        return true;
    }


    public static function install(Migration $migration)
    {
        global $DB;

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 34,
                'rank' => 1,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 9,
                'rank' => 2,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4320,
                'rank' => 3,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 3,
                'rank' => 4,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 5,
                'rank' => 5,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 10,
                'rank' => 6,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 6,
                'rank' => 7,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 11,
                'rank' => 8,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4313,
                'rank' => 9,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4314,
                'rank' => 10,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );

        $DB->insert(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
                'num' => 4316,
                'rank' => 11,
                'users_id' => 0,
                'interface' => 'central',
            ],
        );
    }
}
