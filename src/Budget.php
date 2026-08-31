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
use DBConnection;
use Glpi\Application\View\TemplateRenderer;
use Migration;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Budget
 */
class Budget extends CommonDBTM
{
    public static $rightname = 'plugin_resources_budget';
    // From CommonDBTM
    public $dohistory = true;

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
        return _n('Budget', 'Budgets', $nb);
    }

    /**
     * Have I the global right to "view" the Object
     *
     * Default is true and check entity if the objet is entity assign
     *
     * May be overloaded if needed
     *
     * @return bool
     **/
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return bool
     **/
    public static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
    }

    /**
     * Display Tab for each budget
     *
     * @param array $options
     *
     * @return array
     */
    public function defineTabs($options = [])
    {
        $ong = [];

        $this->addDefaultFormTab($ong);
        $this->addStandardTab('Document', $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     * allow to control data before adding in bdd
     *
     * @param $input
     * @return array
     */
    public function prepareInputForAdd($input)
    {
        if (!isset($input["plugin_resources_professions_id"])
            || $input["plugin_resources_professions_id"] == '0') {
            Session::addMessageAfterRedirect(
                __('The profession for the budget must be filled', 'resources'),
                false,
                ERROR,
            );
            return [];
        }

        return $input;
    }

    /**
     * allow to control data before updating in bdd
     *
     * @param $input
     * @return array
     */
    public function prepareInputForUpdate($input)
    {
        if (!isset($input["plugin_resources_professions_id"])
            || $input["plugin_resources_professions_id"] == '0') {
            Session::addMessageAfterRedirect(
                __('The profession for the budget must be filled', 'resources'),
                false,
                ERROR,
            );
            return [];
        }

        return $input;
    }

    /**
     * allow search management
     */
    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id' => '2',
            'table' => $this->getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'datatype' => 'number',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '3',
            'table' => 'glpi_plugin_resources_ranks',
            'field' => 'name',
            'name' => __('Rank', 'resources'),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4',
            'table' => 'glpi_plugin_resources_professions',
            'field' => 'name',
            'name' => __('Profession', 'resources'),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '5',
            'table' => 'glpi_plugin_resources_budgettypes',
            'field' => 'name',
            'name' => __('Budget type', 'resources'),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '6',
            'table' => $this->getTable(),
            'field' => 'begin_date',
            'name' => __('Begin date'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '7',
            'table' => $this->getTable(),
            'field' => 'end_date',
            'name' => __('End date'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '8',
            'table' => $this->getTable(),
            'field' => 'volume',
            'name' => __('Budget volume', 'resources'),
        ];

        $tab[] = [
            'id' => '9',
            'table' => 'glpi_plugin_resources_budgetvolumes',
            'field' => 'name',
            'name' => __('Type of budget volume', 'resources'),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '10',
            'table' => $this->getTable(),
            'field' => 'date_mod',
            'name' => __('Last update'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '80',
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __('Entity'),
            'datatype' => 'dropdown',
        ];

        return $tab;
    }

    /**
     * Display the budget form
     *
     * @param $ID integer ID of the item
     * @param $options array
     *     - target filename : where to go when done.
     *     - withtemplate boolean : template or basic item
     *
     * @return boolean item found
     * */
    public function showForm($ID, $options = [""])
    {
        $this->initForm($ID, $options);

        $profession_rank = Resource::getProfessionRankFields($this->fields, true);

        $params = $options;
        if (Session::getCurrentInterface() != 'central') {
            $params['candel'] = false;
        }

        TemplateRenderer::getInstance()->display('@resources/budget_form.html.twig', [
            'item'                => $this,
            'params'              => $params,
            'budgettype_class'    => BudgetType::class,
            'budgetvolume_class'  => BudgetVolume::class,
            'profession_dropdown' => $profession_rank['profession_dropdown'],
            'rank_html'           => $profession_rank['rank_html'],
        ]);

        return true;
    }

    /**
     * @param $menu
     *
     * @return mixed
     */
    public static function getMenuOptions($menu)
    {
        $plugin_page = PLUGIN_RESOURCES_WEBDIR . '/front/budget.php';
        $itemtype = self::getType();

        //Menu entry in admin
        $menu['options'][$itemtype]['title'] = self::getTypeName();
        $menu['options'][$itemtype]['page'] = $plugin_page;
        $menu['options'][$itemtype]['links']['search'] = $plugin_page;
        $menu['options'][$itemtype]['links']['lists'] = "";
        $menu['options'][$itemtype]['lists_itemtype'] = self::getType();

        if (Session::haveright(self::$rightname, UPDATE)) {
            $menu['options'][$itemtype]['links']['add'] = PLUGIN_RESOURCES_WEBDIR . '/front/budget.form.php';
        }

        return $menu;
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
                        `id`           int {$default_key_sign} NOT NULL auto_increment,
                        `entities_id`                       int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `is_recursive`                      tinyint      NOT NULL                   DEFAULT '0',
                        `name`                              varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `plugin_resources_professions_id`   int {$default_key_sign} NOT NULL                   DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_professions (id)',
                        `plugin_resources_ranks_id`         int {$default_key_sign} NOT NULL                   DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_ranks (id)',
                        `plugin_resources_budgettypes_id`   int {$default_key_sign} NOT NULL                   DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_budgettypes (id)',
                        `plugin_resources_budgetvolumes_id` int {$default_key_sign} NOT NULL                   DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_budgetvolumes (id)',
                        `begin_date`                        timestamp    NULL                       DEFAULT NULL,
                        `end_date`                          timestamp    NULL                       DEFAULT NULL,
                        `volume`                            int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `date_mod`                          timestamp    NULL                       DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `name` (`name`),
                        KEY `plugin_resources_professions_id` (`plugin_resources_professions_id`),
                        KEY `plugin_resources_ranks_id` (`plugin_resources_ranks_id`),
                        KEY `plugin_resources_budgettypes_id` (`plugin_resources_budgettypes_id`),
                        KEY `plugin_resources_budgetvolumes_id` (`plugin_resources_budgetvolumes_id`),
                        KEY `date_mod` (`date_mod`),
                        KEY `entities_id` (`entities_id`),
                        KEY `is_recursive` (`is_recursive`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 6,
                    'rank' => 1,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 7,
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
                    'num' => 3,
                    'rank' => 4,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 5,
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

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 9,
                    'rank' => 7,
                    'users_id' => 0,
                    'interface' => 'central'],
            );
        }
    }
}
