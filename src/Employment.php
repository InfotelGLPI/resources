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

use Alert;
use CommonDBTM;
use CommonGLPI;
use DBConnection;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Migration;
use Plugin;
use Search;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Employment
 */
class Employment extends CommonDBTM
{
    public static $rightname = 'plugin_resources_employment';

    public static $itemtype = Resource::class;
    public static $items_id = 'plugin_resources_resources_id';

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
        return _n('Employment', 'Employments', $nb, 'resources');
    }

    public static function getIcon()
    {
        return "ti ti-briefcase-2";
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
     * Display tab for each emplyment
     **/
    public function defineTabs($options = [])
    {
        $ong = [];

        $this->addDefaultFormTab($ong);
        $this->addStandardTab('Document', $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     * Display employment's tab for each resource except template
     **/
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == Resource::class
            && $this->canView()
            //          && $withtemplate == 0
        ) {
            if ($_SESSION['glpishow_count_on_tabs']) {
                $dbu = new DbUtils();
                return self::createTabEntry(
                    self::getTypeName(2),
                    $dbu->countElementsInTable(
                        $this->getTable(),
                        ["plugin_resources_resources_id" => $item->getID()],
                    ),
                );
            }
            return self::createTabEntry(self::getTypeName(2));
        }
        return '';
    }

    /**
     * display tab's content for each resource
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Resource::class) {
            if (Session::haveRight('plugin_resources_employment', UPDATE)) {
                self::addNewEmployments($item);
            }
            if (Session::haveRight('plugin_resources_employment', READ)) {
                self::showMinimalList($item);
            }
        }
        return true;
    }

    /**
     * Actions done when an employment is deleted from the database
     *
     * @return nothing
     **/
    public function cleanDBonPurge() {}

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
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'name',
            'name' => __('Human resource', 'resources'),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '4',
            'table' => 'glpi_plugin_resources_ranks',
            'field' => 'name',
            'name' => __('Rank', 'resources'),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '5',
            'table' => 'glpi_plugin_resources_professions',
            'field' => 'name',
            'name' => __('Profession', 'resources'),
            'datatype' => 'dropdown',
            'massiveaction' => false,
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
            'table' => 'glpi_plugin_resources_employmentstates',
            'field' => 'name',
            'name' => __('Employment state', 'resources'),
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '9',
            'table' => 'glpi_plugin_resources_employers',
            'field' => 'completename',
            'name' => __('Employer', 'resources'),
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '10',
            'table' => $this->getTable(),
            'field' => 'ratio_employment_budget',
            'name' => __('Ratio Employment / Budget', 'resources'),
            'datatype' => 'decimal',
        ];
        $tab[] = [
            'id' => '13',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'id',
            'name' => __('Human resource', 'resources') . __('ID'),
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '14',
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
     * Display the employment form
     *
     * @param $ID integer ID of the item
     * @param $options array
     *     - target filename : where to go when done.
     *     - withtemplate boolean : template or basic item
     *
     * @return boolean item found
     **/
    public function showForm($ID, $options = [""])
    {
        //validation des droits
        if (!$this->canView()) {
            return false;
        }

        $plugin_resources_resources_id = 0;
        if (isset($options['plugin_resources_resources_id'])) {
            $plugin_resources_resources_id = $options['plugin_resources_resources_id'];
        }

        if ($ID > 0) {
            $this->check($ID, READ);
        } else {
            // Create item
            $input = ['plugin_resources_resources_id' => $plugin_resources_resources_id];
            $this->check(-1, UPDATE, $input);
        }

        if ($ID > 0) {
            $resource = $this->fields["plugin_resources_resources_id"];
        } else {
            $resource = $plugin_resources_resources_id;
        }

        $profession_rank = Resource::getProfessionRankFields($this->fields, true);

        $params = $options;
        if (Session::getCurrentInterface() != 'central') {
            $params['candel'] = false;
        }

        TemplateRenderer::getInstance()->display('@resources/employment_form.html.twig', [
            'item'                  => $this,
            'params'                => $params,
            'employer_class'        => Employer::class,
            'employmentstate_class' => EmploymentState::class,
            'resource_class'        => Resource::class,
            'resource_value'        => $resource,
            'profession_dropdown'   => $profession_rank['profession_dropdown'],
            'rank_html'             => $profession_rank['rank_html'],
            'ratio_value'           => Html::formatNumber($this->fields["ratio_employment_budget"], true),
        ]);

        return true;
    }

    /**
     * adding of an employment in resource side
     *
     * @static
     *
     * @param CommonGLPI $item
     */
    public static function addNewEmployments(CommonGLPI $item)
    {
        $ID = $item->getField('id');

        ob_start();
        Dropdown::show(
            Employment::class,
            [
                'condition' => ["plugin_resources_resources_id" => '0'],
                'entity'    => $item->getField("entities_id"),
            ],
        );
        $employment_dropdown = (string) ob_get_clean();

        TemplateRenderer::getInstance()->display('@resources/employment_add_form.html.twig', [
            'can_declare'         => Session::haveRight(self::$rightname, UPDATE) && $item->can($ID, UPDATE),
            'declare_url'         => PLUGIN_RESOURCES_WEBDIR
                . "/front/employment.form.php?plugin_resources_resources_id=" . $ID,
            'form_action'         => PLUGIN_RESOURCES_WEBDIR . "/front/employment.form.php",
            'items_id'            => $ID,
            'itemtype'            => $item->getType(),
            'employment_label'    => self::getTypeName(1),
            'employment_dropdown' => $employment_dropdown,
        ]);
    }

    /**
     * Display the employments list of a resource
     *
     * @static
     *
     * @param CommonGLPI $item
     */
    public static function showMinimalList(Resource $item)
    {
        $employemnt = new Employment();

        // Set search params
        $params = [
            'start' => 0,
            'order' => 'DESC',
            'is_deleted' => 0,
            'as_map' => 0,
        ];

        $toview = null;
        foreach ($employemnt->rawSearchOptions() as $option) {
            if (isset($option['table'])) {
                if ($option['table'] == "glpi_plugin_resources_resources" && $option['field'] == "id") {
                    $params['criteria'][] = [
                        'field' => $option['id'],
                        'searchtype' => 'contains',
                        'value' => $item->fields['id'],
                    ];
                    $toview = $option['id'];
                }
                if ($option['table'] == $employemnt->getTable() && $option['field'] == "name") {
                    $params['sort'] = $option['id'];
                }
            }
        }

        $data = Search::prepareDatasForSearch(self::getType(), $params);
        // Force to view resource id
        if ($toview != null && !in_array($toview, $data['toview'])) {
            array_push($data['toview'], $toview);
        }
        Search::constructSQL($data);
        Search::constructData($data);
        Search::displayData($data);
    }

    ////// CRON FUNCTIONS ///////
    //Cron action
    /**
     * @param $name
     *
     * @return array
     */
    public static function cronInfo($name)
    {
        switch ($name) {
            case 'ResourcesLeaving':
                return [
                    'description' => __(
                        'Updating leaving resources (declaring leaving, state of employment)',
                        'resources',
                    ),
                ];   // Optional
                break;
        }
        return [];
    }

    /**
     * @return string
     */
    public function queryLeavingResources()
    {
        $date = date("Y-m-d H:i:s");
        $query = "SELECT *
            FROM `glpi_plugin_resources_resources`
            WHERE `date_end` IS NOT NULL
            AND `date_end` < '" . $date . "'
            AND `is_leaving` = 0
            AND `is_template` = 0
            AND `is_deleted` = 0";

        return $query;
    }

    /**
     * Cron action on tasks : LeavingResources
     *
     * @param $task for log, if NULL display
     *
     **/
    public static function cronResourcesLeaving($task = null)
    {
        global $DB;

        $cron_status = 0;
        $message = [];

        $REmployment = new Employment();
        $query_expired = $REmployment->doQueryLeavingResources();

        $querys = [Alert::END => $query_expired];

        $task_infos = [];
        $task_messages = [];

        foreach ($querys as $type => $query) {
            $task_infos[$type] = [];
            foreach ($DB->request($query) as $data) {
                //when a resource is leaving, current employment get default state
                $default = EmploymentState::getDefault();
                // only current employment
                $restrict = "`plugin_resources_resources_id` = '" . $data["id"] . "'
                     AND ((`begin_date` < '" . $data['date_end'] . "'
                           OR `begin_date` IS NULL)
                           AND (`end_date` > '" . $data['date_end'] . "'
                                 OR `end_date` IS NULL)) ";
                $iterator = $DB->request("glpi_plugin_resources_employments", $restrict);
                foreach ($iterator as $employment) {
                    $values = [
                        'plugin_resources_employmentstates_id' => $default,
                        'end_date' => $data['date_end'],
                        'id' => $employment['id'],
                    ];
                    $REmployment->update($values);
                }

                $resource = new Resource();
                $resource->getFromDB($data["id"]);
                $resource->update([
                    'is_leaving' => 1,
                    'id' => $data["id"],
                    'date_declaration_departure' => date('Y-m-d H:i:s'),
                    'date_end' => $data['date_end'],
                ]);
                $entity = $data['entities_id'];
                if (!isset($message[$entity])) {
                    $message = [$entity => ''];
                }
                $message[$entity] .= $data["name"] . " " . $data["firstname"] . " : " .
                    Html::convDate($data["date_end"]) . "<br>\n";
                $task_infos[$type][$entity][] = $data;

                if (!isset($task_messages[$type][$entity])) {
                    $task_messages[$type][$entity] = __(
                        'These resources left the company, linked current employment have been updated',
                        'resources',
                    ) . "<br />";
                }
                $task_messages[$type][$entity] .= $message[$entity];
            }
        }

        foreach ($querys as $type => $query) {
            foreach ($task_infos[$type] as $entity => $resources) {
                Plugin::loadLang('resources');

                $message = $task_messages[$type][$entity];
                $cron_status = 1;
                if ($task) {
                    $task->log(
                        Dropdown::getDropdownName(
                            "glpi_entities",
                            $entity,
                        ) . ":  $message\n",
                    );
                    $task->addVolume(count($resources));
                } else {
                    Session::addMessageAfterRedirect(
                        Dropdown::getDropdownName(
                            "glpi_entities",
                            $entity,
                        ) . ":  $message",
                    );
                }
            }
        }

        return $cron_status;
    }

    /**
     * @param $menu
     *
     * @return mixed
     */
    public static function getMenuOptions($menu)
    {
        $plugin_page = PLUGIN_RESOURCES_WEBDIR . '/front/employment.php';
        $itemtype = self::getType();
        //Menu entry in admin
        $menu['options'][$itemtype]['title'] = self::getTypeName();
        $menu['options'][$itemtype]['page'] = $plugin_page;
        $menu['options'][$itemtype]['links']['search'] = $plugin_page;
        $menu['options'][$itemtype]['links']['lists'] = "";
        $menu['options'][$itemtype]['lists_itemtype'] = self::getType();

        if (Session::haveright(self::$rightname, UPDATE)) {
            $menu['options'][$itemtype]['links']['add'] = PLUGIN_RESOURCES_WEBDIR . '/front/employment.form.php';
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
                        `name`                                 varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `entities_id`                          int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `is_recursive`                         tinyint        NOT NULL                 DEFAULT '0',
                        `plugin_resources_resources_id`        int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_resources (id)',
                        `plugin_resources_professions_id`      int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_professions (id)',
                        `plugin_resources_ranks_id`            int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_resources_ranks (id)',
                        `plugin_resources_employmentstates_id` int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_employmentstates (id)',
                        `plugin_resources_employers_id`        int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_employers (id)',
                        `ratio_employment_budget`              decimal(10, 2) NOT NULL                 DEFAULT '0.00',
                        `begin_date`                           timestamp      NULL                     DEFAULT NULL,
                        `end_date`                             timestamp      NULL                     DEFAULT NULL,
                        `date_mod`                             timestamp      NULL                     DEFAULT NULL,
                        `comment`                              TEXT COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`),
                        KEY `name` (`name`),
                        KEY `plugin_resources_resources_id` (`plugin_resources_resources_id`),
                        KEY `plugin_resources_professions_id` (`plugin_resources_professions_id`),
                        KEY `plugin_resources_ranks_id` (`plugin_resources_ranks_id`),
                        KEY `plugin_resources_employmentstates_id` (`plugin_resources_employmentstates_id`),
                        KEY `plugin_resources_employers_id` (`plugin_resources_employers_id`),
                        KEY `entities_id` (`entities_id`),
                        KEY `date_mod` (`date_mod`),
                        KEY `is_recursive` (`is_recursive`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 9,
                    'rank' => 1,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 5,
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
                    'num' => 6,
                    'rank' => 4,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 7,
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
