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
use NotificationEvent;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ResourceHoliday
 */
class ResourceHoliday extends CommonDBTM
{
    public static $rightname = 'plugin_resources_holiday';

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
        return _n('Holiday', 'Holidays', $nb, 'resources');
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
     * @param array $input datas used to add the item
     *
     * @return array the modified $input array
     **/
    public function prepareInputForAdd($input)
    {
        if (!isset($input["date_begin"]) || $input["date_begin"] == 'NULL') {
            Session::addMessageAfterRedirect(
                __('The begin date of the forced holiday period must be filled', 'resources'),
                false,
                ERROR,
            );
            return [];
        }
        if (!isset($input["date_end"]) || $input["date_end"] == 'NULL') {
            Session::addMessageAfterRedirect(
                __('The end date of the forced holiday period must be filled', 'resources'),
                false,
                ERROR,
            );
            return [];
        }

        return $input;
    }

    public function post_addItem()
    {
        global $CFG_GLPI;

        Session::addMessageAfterRedirect(__('Forced holiday declaration of a resource performed', 'resources'));

        $Resource = new Resource();
        if ($CFG_GLPI["notifications_mailing"]) {
            $options = ['holiday_id' => $this->fields["id"]];
            if ($Resource->getFromDB($this->fields["plugin_resources_resources_id"])) {
                NotificationEvent::raiseEvent("newholiday", $Resource, $options);
            }
        }
    }

    /**
     * Prepare input datas for updating the item
     *
     * @param array $input data used to update the item
     *
     * @return array the modified $input array
     **/
    public function prepareInputForUpdate($input)
    {
        if (!isset($input["date_begin"]) || $input["date_begin"] == 'NULL') {
            Session::addMessageAfterRedirect(
                __('The begin date of the forced holiday period must be filled', 'resources'),
                false,
                ERROR,
            );
            return [];
        }
        if (!isset($input["date_end"]) || $input["date_end"] == 'NULL') {
            Session::addMessageAfterRedirect(
                __('The end date of the forced holiday period must be filled', 'resources'),
                false,
                ERROR,
            );
            return [];
        }

        //unset($input['picture']);
        $this->getFromDB($input["id"]);

        $input["_old_date_begin"] = $this->fields["date_begin"];
        $input["_old_date_end"] = $this->fields["date_end"];
        $input["_old_comment"] = $this->fields["comment"];

        return $input;
    }

    /**
     * Actions done after the UPDATE of the item in the database
     *
     * @param boolean $history store changes history ? (default 1)
     *
     * @return void
     **/
    public function post_updateItem($history = 1)
    {
        global $CFG_GLPI;

        if ($CFG_GLPI["notifications_mailing"] && count($this->updates)) {
            $options = [
                'holiday_id' => $this->fields["id"],
                'oldvalues' => $this->oldvalues,
            ];
            $Resource = new Resource();
            if ($Resource->getFromDB($this->fields["plugin_resources_resources_id"])) {
                NotificationEvent::raiseEvent("updateholiday", $Resource, $options);
            }
        }
    }

    /**
     * Actions done before the DELETE of the item in the database /
     * Maybe used to add another check for deletion
     *
     * @return boolean true if item need to be deleted else false
     **/
    public function pre_deleteItem()
    {
        global $CFG_GLPI;

        if ($CFG_GLPI["notifications_mailing"]) {
            $Resource = new Resource();
            $options = ['holiday_id' => $this->fields["id"]];
            if ($Resource->getFromDB($this->fields["plugin_resources_resources_id"])) {
                NotificationEvent::raiseEvent("deleteholiday", $Resource, $options);
            }
        }
        return true;
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
            'id' => '3',
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

        $tab[] = [
            'id' => '5',
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
        Wizard::WizardHeader(__('Forced holiday management', 'resources'));
        $wizard_header = (string) ob_get_clean();

        $tiles = [];
        if (Session::haveright('plugin_resources_holiday', UPDATE)) {
            $tiles = [
                [
                    'url'   => './resourceholiday.form.php',
                    'img'   => PLUGIN_RESOURCES_WEBDIR . "/pics/holidayresource.png",
                    'label' => __('Declare a forced holiday', 'resources'),
                ],
                [
                    'url'   => './resourceholiday.php',
                    'img'   => PLUGIN_RESOURCES_WEBDIR . "/pics/holidaylist.png",
                    'label' => __('List of forced holidays', 'resources'),
                ],
            ];
        }

        TemplateRenderer::getInstance()->display('@resources/wizard_tiles_menu.html.twig', [
            'wizard_header' => $wizard_header,
            'tiles'         => $tiles,
        ]);
    }

    //Show form from helpdesk to add holiday of a resource

    /**
     * @param       $ID
     * @param array $options
     */
    public function showForm($ID, $options = [])
    {

        $this->initForm($ID, $options);

        $title = __('Declare a forced holiday', 'resources');
        if ($ID > 0) {
            $title = __('Detail of the forced holiday', 'resources');
        }

        ob_start();
        Wizard::WizardHeader($title, PLUGIN_RESOURCES_WEBDIR . "/pics/holidayresource.png");
        $wizard_header = (string) ob_get_clean();

        TemplateRenderer::getInstance()->display('@resources/resourceholiday_form.html.twig', [
            'item'              => $this,
            'wizard_header'     => $wizard_header,
            'form_action'       => PLUGIN_RESOURCES_WEBDIR . "/front/resourceholiday.form.php",
            'is_new'            => $ID <= 0,
            'resource_class'    => Resource::class,
            'resource_label'    => Resource::getTypeName(1),
            'resource_entities' => $_SESSION['glpiactiveentities'],
        ]);
    }

    /**
     * @param $menu
     *
     * @return mixed
     */
    public static function getMenuOptions($menu)
    {
        $plugin_page = PLUGIN_RESOURCES_WEBDIR . '/front/resourceholiday.php';
        $itemtype = self::getType();

        //Menu entry in admin
        $menu['options'][$itemtype]['title'] = self::getTypeName();
        $menu['options'][$itemtype]['page'] = $plugin_page;
        $menu['options'][$itemtype]['links']['search'] = $plugin_page;
        $menu['options'][$itemtype]['links']['lists'] = "";
        $menu['options'][$itemtype]['lists_itemtype'] = self::getType();

        if (Session::haveright(self::$rightname, UPDATE)) {
            $menu['options'][$itemtype]['links']['add'] = PLUGIN_RESOURCES_WEBDIR . '/front/resourceholiday.form.php';
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
                        `plugin_resources_resources_id` int unsigned NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_resources (id)',
                        `date_begin`                    timestamp    NULL     DEFAULT NULL,
                        `date_end`                      timestamp    NULL     DEFAULT NULL,
                        `comment`                       TEXT COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`),
                        KEY `plugin_resources_resources_id` (`plugin_resources_resources_id`)
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
        }
    }
}
