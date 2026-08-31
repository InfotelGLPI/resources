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
use CommonDBTM;
use DBConnection;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Location;
use Migration;
use NotificationEvent;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ResourceResting
 */
class ResourceResting extends CommonDBTM
{
    public static $rightname = 'plugin_resources_resting';
    public $dohistory = true;

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return _n('Non contract period', 'Non contract periods', $nb, 'resources');
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
     * Prepare input datas for adding the item
     *
     * @param $input datas used to add the item
     *
     * @return datas modified $input array
     **/
    public function prepareInputForAdd($input)
    {
        if (!isset($input["date_begin"]) || $input["date_begin"] == 'NULL') {
            Session::addMessageAfterRedirect(
                __('The begin date of the non contract period must be filled', 'resources'),
                false,
                ERROR,
            );
            return [];
        }

        return $input;
    }

    /**
     * Actions done after the ADD of the item in the database
     *
     * @return
     **/
    public function post_addItem()
    {
        global $CFG_GLPI;

        Session::addMessageAfterRedirect(__('Non contract period declaration of a resource performed', 'resources'));

        $Resource = new Resource();
        if ($CFG_GLPI["notifications_mailing"]) {
            $options = ['resting_id' => $this->fields["id"]];
            if ($Resource->getFromDB($this->fields["plugin_resources_resources_id"])) {
                NotificationEvent::raiseEvent("newresting", $Resource, $options);
            }
        }
    }

    /**
     * Prepare input datas for updating the item
     *
     * @param $input datas used to update the item
     *
     * @return datas modified $input array
     **/
    public function prepareInputForUpdate($input)
    {
        if (!isset($input["date_begin"]) || $input["date_begin"] == 'NULL') {
            Session::addMessageAfterRedirect(
                __('The begin date of the non contract period must be filled', 'resources'),
                false,
                ERROR,
            );
            return [];
        }
        if (isset($input['date_end']) && empty($input['date_end'])) {
            $input['date_end'] = 'NULL';
        }

        //unset($input['picture']);
        $this->getFromDB($input["id"]);

        $input["_old_date_begin"] = $this->fields["date_begin"];
        $input["_old_date_end"] = $this->fields["date_end"];
        $input["_old_locations_id"] = $this->fields["locations_id"];
        $input["_old_at_home"] = $this->fields["at_home"];
        $input["_old_comment"] = $this->fields["comment"];

        return $input;
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
        global $CFG_GLPI;

        if ($CFG_GLPI["notifications_mailing"] && count($this->updates)) {
            $options = [
                'resting_id' => $this->fields["id"],
                'oldvalues' => $this->oldvalues,
            ];
            $Resource = new Resource();
            if ($Resource->getFromDB($this->fields["plugin_resources_resources_id"])) {
                NotificationEvent::raiseEvent("updateresting", $Resource, $options);
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

        if ($CFG_GLPI["notifications_mailing"]) {
            $Resource = new Resource();
            $options = ['resting_id' => $this->fields["id"]];
            if ($Resource->getFromDB($this->fields["plugin_resources_resources_id"])) {
                NotificationEvent::raiseEvent("deleteresting", $Resource, $options);
            }
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
    public function rawSearchOptions()
    {
        $tab[] = [
            'id' => 'common',
            'name' => self::GetTypeName(),
        ];

        $tab[] = [
            'id' => '1',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'name',
            'name' => __('Surname'),
            'datatype' => 'itemlink',
            'itemlink_type' => $this->getType(),
        ];
        if (!Session::haveRight("plugin_resources_all", READ)) {
            $tab[] = [
                'id' => '1',
                'searchtype' => 'contains',
            ];
        }

        $tab[] = [
            'id' => '2',
            'table' => 'glpi_plugin_resources_resources',
            'field' => 'firstname',
            'name' => __('First name'),
        ];

        $tab[] = [
            'id' => '5',
            'table' => $this->getTable(),
            'field' => 'date_begin',
            'name' => __('Begin date'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id' => '4',
            'table' => $this->getTable(),
            'field' => 'date_end',
            'name' => __('End date'),
            'datatype' => 'date',
        ];
        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

        $tab[] = [
            'id' => '6',
            'table' => $this->getTable(),
            'field' => 'at_home',
            'name' => __('At home', 'resources'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id' => '7',
            'table' => $this->getTable(),
            'field' => 'comment',
            'name' => __('Comments'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id' => '30',
            'table' => $this->getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'datatype' => 'number',
            'massiveaction' => false,
        ];

        return $tab;
    }

    /**
     *Menu
     */
    public function showMenu()
    {
        ob_start();
        Wizard::WizardHeader(
            _n('Non contract period management', 'Non contract periods management', 2, 'resources'),
        );
        $wizard_header = (string) ob_get_clean();

        $tiles = [];
        if (Session::haveright('plugin_resources_resting', UPDATE)) {
            $tiles = [
                [
                    'url'   => './resourceresting.form.php',
                    'img'   => PLUGIN_RESOURCES_WEBDIR . "/pics/newresting.png",
                    'label' => __('Declare a non contract period', 'resources'),
                ],
                [
                    'url'   => './resourceresting.form.php?end',
                    'img'   => PLUGIN_RESOURCES_WEBDIR . "/pics/closeresting.png",
                    'label' => __('Declaring the end of non contract periods', 'resources'),
                ],
                [
                    'url'   => './resourceresting.php',
                    'img'   => PLUGIN_RESOURCES_WEBDIR . "/pics/restinglist.png",
                    'label' => __('List of non contract periods', 'resources'),
                ],
            ];
        }

        TemplateRenderer::getInstance()->display('@resources/wizard_tiles_menu.html.twig', [
            'wizard_header' => $wizard_header,
            'tiles'         => $tiles,
        ]);
    }

    /**
     * Show form from helpdesk to add resting of a resource
     *
     * @param $ID
     * @param array $options
     */
    public function showForm($ID, $options = [])
    {
        $this->initForm($ID, $options);

        $title = __('Declare a non contract period', 'resources');
        if ($ID > 0) {
            $title = __('Detail of non contract period', 'resources');
        }

        ob_start();
        Wizard::WizardHeader($title, PLUGIN_RESOURCES_WEBDIR . "/pics/newresting.png");
        $wizard_header = (string) ob_get_clean();

        TemplateRenderer::getInstance()->display('@resources/resourceresting_form.html.twig', [
            'item'              => $this,
            'wizard_header'     => $wizard_header,
            'form_action'       => PLUGIN_RESOURCES_WEBDIR . "/front/resourceresting.form.php",
            'is_new'            => $ID <= 0,
            'resource_class'    => Resource::class,
            'resource_label'    => Resource::getTypeName(1),
            'resource_entities' => $_SESSION['glpiactiveentities'],
            'location_class'    => Location::class,
        ]);
    }

    /**
     * Show form from helpdesk to add resting of a resource
     *
     * @param $ID
     * @param array $options
     */
    public function showFormEnd($ID, $options = [])
    {
        $this->initForm($ID, $options);

        ob_start();
        Wizard::WizardHeader(
            __('Declaring the end of non contract periods', 'resources'),
            PLUGIN_RESOURCES_WEBDIR . "/pics/newresting.png",
        );
        $wizard_header = (string) ob_get_clean();

        // The rand is imposed here so the generated JS can observe the dropdown id.
        $rand = mt_rand();

        TemplateRenderer::getInstance()->display('@resources/resourceresting_end_form.html.twig', [
            'wizard_header'     => $wizard_header,
            'form_action'       => PLUGIN_RESOURCES_WEBDIR . "/front/resourceresting.form.php",
            'rand'              => $rand,
            'resource_class'    => Resource::class,
            'resource_label'    => Resource::getTypeName(1),
            'resource_entities' => $_SESSION['glpiactiveentities'],
        ]);

        $js = "function plugin_resources_load_user_resting(){";
        $js .= Ajax::updateItemJsCode(
            'plugin_resources_resting',
            PLUGIN_RESOURCES_WEBDIR . '/ajax/resourceresting.php',
            [
                'action' => 'loadResting',
                'plugin_resources_resources_id' => '__VALUE__',
            ],
            'dropdown_plugin_resources_resources_id' . $rand,
            false,
        );
        $js .= "}";
        echo Html::scriptBlock($js);
    }

    /**
     * Display of the choice of the intercontrat
     *
     * @param $plugin_resources_resources_id
     */
    public function loadResting($plugin_resources_resources_id)
    {
        $resting = new ResourceResting();
        $restrict = [
            'plugin_resources_resources_id' => $plugin_resources_resources_id,
            [
                'OR' => [
                    ['date_end' => null],
                    ['date_end' => '0000-00-00'],
                ],
            ],
        ];

        $restings = $resting->find($restrict);

        //array of resting
        $elements = [];
        $elements[0] = Dropdown::EMPTY_VALUE;
        foreach ($restings as $data) {
            $elements[$data['id']] = Resource::getResourceName($plugin_resources_resources_id) . " - " . Html::convDate(
                $data['date_begin'],
            );
        }

        // The rand is imposed here so the generated JS can observe the dropdown id.
        $rand = mt_rand();

        TemplateRenderer::getInstance()->display('@resources/resourceresting_choice.html.twig', [
            'elements' => $elements,
            'rand'     => $rand,
        ]);

        //script for display of end date
        $observed = 'dropdown_plugin_resources_resting_id' . $rand;
        $js = "function plugin_resources_load_end_date_resting(){";
        $js .= Ajax::updateItemJsCode(
            'plugin_resources_endate_resting',
            PLUGIN_RESOURCES_WEBDIR . '/ajax/resourceresting.php',
            ['action' => 'loadEndDateResting', 'plugin_resources_resting_id' => '__VALUE__'],
            $observed,
            false,
        );
        $js .= Ajax::updateItemJsCode(
            'plugin_resources_button_resting',
            PLUGIN_RESOURCES_WEBDIR . '/ajax/resourceresting.php',
            ['action' => 'loadButtonResting', 'plugin_resources_resting_id' => '__VALUE__'],
            $observed,
            false,
        );
        $js .= "}";
        echo Html::scriptBlock($js);
    }

    /**
     * Display of end date
     *
     * @param $plugin_resources_resting_id
     */
    public function loadEndDateResting($plugin_resources_resting_id)
    {
        TemplateRenderer::getInstance()->display('@resources/resourceresting_end_date.html.twig', [
            'resting_id' => $plugin_resources_resting_id,
        ]);
    }

    /**
     * Display of end date
     *
     * @param $plugin_resources_resting_id
     */
    public function loadButtonResting($plugin_resources_resting_id)
    {
        TemplateRenderer::getInstance()->display('@resources/resourceresting_end_button.html.twig');
    }

    /**
     * @param $menu
     *
     * @return mixed
     */
    public static function getMenuOptions($menu)
    {
        $plugin_page = PLUGIN_RESOURCES_WEBDIR . '/front/resourceresting.php';
        $itemtype = self::getType();

        //Menu entry in admin
        $menu['options'][$itemtype]['title'] = self::getTypeName();
        $menu['options'][$itemtype]['page'] = $plugin_page;
        $menu['options'][$itemtype]['links']['search'] = $plugin_page;
        $menu['options'][$itemtype]['links']['lists'] = "";
        $menu['options'][$itemtype]['lists_itemtype'] = self::getType();

        if (Session::haveright(self::$rightname, UPDATE)) {
            $menu['options'][$itemtype]['links']['add'] = PLUGIN_RESOURCES_WEBDIR . '/front/resourceresting.form.php';
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
                        `plugin_resources_resources_id` int {$default_key_sign} NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_resources (id)',
                        `date_begin`                    timestamp    NULL     DEFAULT NULL,
                        `date_end`                      timestamp    NULL     DEFAULT NULL,
                        `locations_id`                  int {$default_key_sign} NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_locations (id)',
                        `at_home`                       tinyint      NOT NULL DEFAULT '0',
                        `comment`                       TEXT COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`),
                        KEY `plugin_resources_resources_id` (`plugin_resources_resources_id`),
                        KEY `locations_id` (`locations_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);


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

        }
    }
}
