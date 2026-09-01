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
use CommonDBChild;
use CommonGLPI;
use DBConnection;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Migration;
use Session;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ImportColumn
 */
class ImportColumn extends CommonDBChild
{
    public static $rightname = 'plugin_resources_import';
    public $dohistory = true;

    public static $itemtype = Import::class;
    public static $items_id = 'plugin_resources_imports_id';

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return _n('Column', 'Columns', $nb, 'resources');
    }

    public static function getColumnsTypes()
    {
        return [
            __("Integer", "resources"),
            __("Decimal", "resources"),
            __("String", "resources"),
            __("Date", "resources"),
        ];
    }

    /**
     * Alternative to find to order array by resource_column
     */
    public function getColumnsByImport($importID, $distinctResourceColumns = false)
    {
        global $DB;

        $criteria = [
            'FROM'  => self::getTable(),
            'WHERE' => [self::$items_id => (int) $importID],
            'ORDER' => 'resource_column',
        ];
        if ($distinctResourceColumns) {
            $criteria['GROUPBY'] = 'resource_column';
        }

        $iterator = $DB->request($criteria);

        $temp = [];

        $it = 0;
        foreach ($iterator as $data) {
            $temp[$it] = $data;
            $it++;
        }
        return $temp;
    }

    /**
     * Get Tab Name used for itemtype
     *
     * NB : Only called for existing object
     *      Must check right on what will be displayed + template
     *
     * @param CommonGLPI $item CommonDBTM object for which the tab need to be displayed
     * @param bool|int $withtemplate boolean  is a template object ? (default 0)
     *
     * @return string tab name
     * @since version 0.83
     *
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        // can exists for template
        if ($item->getType() == self::$itemtype) {
            if ($_SESSION['glpishow_count_on_tabs']) {
                $dbu = new DbUtils();
                $table = $dbu->getTableForItemType(__CLASS__);
                return self::createTabEntry(
                    self::getTypeName(),
                    $dbu->countElementsInTable(
                        $table,
                        [Import::$keyInOtherTables => $item->getID()],
                    ),
                );
            }
            return self::getTypeName();
        }
        return '';
    }

    /**
     * show Tab content
     *
     * @param          $item                  CommonGLPI object for which the tab need to be displayed
     * @param          $tabnum       integer  tab number (default 1)
     * @param bool|int $withtemplate boolean  is a template object ? (default 0)
     *
     * @return true
     * @since version 0.83
     *
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == self::$itemtype) {
            self::showForImport($item, $withtemplate);
        }
        return true;
    }

    public static function getIdentifierNames()
    {
        return [
            __("No Identifier", "resources"),
            __("Level 1 Identifier", "resources"),
            __("Level 2 Identifier", "resources"),
        ];
    }

    public function getIsIdentifierDropdown($value, $disabled = false)
    {
        $names = self::getIdentifierNames();

        $param = [
            'value' => $value,
            'disabled' => $disabled,
        ];

        return Dropdown::showFromArray("is_identifier", $names, $param);
    }

    public static function showForImport(Import $import, $withtemplate = '')
    {
        $importInstance = new self();
        $sID = $import->fields['id'];
        $rand = mt_rand();

        $jsFunctionName = "viewAddColumn$sID$rand";
        $viewDomElementName = "viewcolumn$sID$rand";

        $canadd = Session::haveRight(self::$rightname, CREATE);
        $canedit = Session::haveRight(self::$rightname, UPDATE);
        $canpurge = Session::haveRight(self::$rightname, PURGE);

        TemplateRenderer::getInstance()->display('@resources/importcolumn_add_link.html.twig', [
            'dom_id'      => $viewDomElementName,
            'can_add'     => $canadd,
            'js_function' => $jsFunctionName,
        ]);

        if ($canadd) {
            $importInstance->addEvent($sID, $jsFunctionName, $viewDomElementName);
        }

        // Display existing columns
        $columns = $importInstance->find([self::$items_id => $sID], 'id');
        if (count($columns) == 0) {
            TemplateRenderer::getInstance()->display('@resources/alert_message.html.twig', [
                'level'   => 'info',
                'message' => __('No columns for this import', 'resources'),
            ]);
            return;
        }

        $types       = self::getColumnsTypes();
        $data_names  = Resource::getDataNames();
        $identifiers = self::getIdentifierNames();

        $entries = [];
        $edit_js = '';
        foreach ($columns as $column) {
            if (!$importInstance->getFromDB($column['id'])) {
                continue;
            }

            // The name cell doubles as the edit affordance, so it carries markup.
            $name = nl2br(htmlescape((string) $importInstance->fields['name']));
            if ($canedit) {
                $editFunctionName = "viewEditColumn"
                    . $importInstance->fields[self::$items_id] . $importInstance->fields['id'] . $rand;
                $edit_js .= $importInstance->editEvent($editFunctionName, $viewDomElementName);
                $name = '<a href="javascript:' . $editFunctionName . '();">' . $name . '</a>';
            }

            $entries[] = [
                'itemtype'        => self::class,
                'id'              => $importInstance->fields['id'],
                'name'            => $name,
                'type'            => $types[$importInstance->fields['type']] ?? '',
                'resource_column' => $data_names[$importInstance->fields['resource_column']] ?? '',
                'is_identifier'   => $identifiers[$importInstance->fields['is_identifier']] ?? '',
            ];
        }

        if ($edit_js !== '') {
            echo Html::scriptBlock($edit_js);
        }

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'super_header'        => self::getTypeName(2),
            'columns'             => [
                'name'            => __('Name'),
                'type'            => __('Type'),
                'resource_column' => __('Resource Attribute', 'resources'),
                'is_identifier'   => __('Identifiers', 'resources'),
            ],
            'formatters'          => ['name' => 'raw_html'],
            'entries'             => $entries,
            'total_number'        => count($entries),
            'filtered_number'     => count($entries),
            'showmassiveactions'  => $canpurge,
            'massiveactionparams' => [
                'num_displayed' => count($entries),
                // Backslashes of the namespaced class would break the jQuery selector.
                'container'     => 'mass' . str_replace('\\', '', self::class) . $rand,
            ],
        ]);
    }

    public function showForm($ID, $options = [])
    {
        $importColumn = new self();
        if (!$importColumn->canView()) {
            return false;
        }

        // The parent Import is required to build the relation hidden field.
        if (!isset($options['parent']) || !($options['parent'] instanceof Import)) {
            return false;
        }
        $import = $options['parent'];

        if ($ID <= 0) {
            $importColumn->getEmpty();
            $title = __('Add a column', 'resources');
            $name = "";
            $submit_name = 'add';
            $submit_label = _x('button', 'Add');
        } else {
            $importColumn->getFromDB($ID);
            $title = __('Edit a column', 'resources');
            $name = $importColumn->getField('name');
            $submit_name = 'update';
            $submit_label = _x('button', 'Save');
        }

        // Capture GLPI dropdowns that echo directly, so they can be injected as |raw.
        $capture = static function (callable $renderer): string {
            ob_start();
            $renderer();
            return (string) ob_get_clean();
        };

        TemplateRenderer::getInstance()->display('@resources/importcolumn_form.html.twig', [
            'form_action'         => Toolbox::getItemTypeFormURL(self::getType()),
            'column_id'           => $importColumn->getID(),
            'items_id_field'      => self::$items_id,
            'items_id'            => $import->getID(),
            'title'               => $title,
            'name_input'          => Html::input('name', ['value' => $name]),
            'type_dropdown'       => $capture(fn() => Dropdown::showFromArray(
                'type',
                self::getColumnsTypes(),
                ['value' => $importColumn->fields['type']],
            )),
            'attribute_dropdown'  => $capture(fn() => Dropdown::showFromArray(
                'resource_column',
                Resource::getDataNames(),
                ['value' => $importColumn->fields['resource_column']],
            )),
            'identifier_dropdown' => $capture(
                fn() => $this->getIsIdentifierDropdown($importColumn->getField('is_identifier'), false),
            ),
            'submit_name'         => $submit_name,
            'submit_label'        => $submit_label,
        ]);

        return true;
    }


    private function addEvent($ID, $jsFunctionName, $viewDomElementName)
    {
        global $CFG_GLPI;

        $js = "function $jsFunctionName() {\n";
        $js .= Ajax::updateItemJsCode(
            $viewDomElementName,
            $CFG_GLPI["root_doc"] . "/ajax/viewsubitem.php",
            [
                'type' => self::class,
                'parenttype' => self::$itemtype,
                self::$items_id => $ID,
                'id' => -1,
            ],
            '',
            false,
        );
        $js .= "};";

        echo Html::scriptBlock($js);
    }

    /**
     * Build the JS opening the edit form of the current column.
     *
     * @return string the function declaration, to be emitted in a script block
     */
    private function editEvent($jsFunctionName, $viewDomElementName): string
    {
        global $CFG_GLPI;

        $js = "function $jsFunctionName(){\n";
        $js .= Ajax::updateItemJsCode(
            $viewDomElementName,
            $CFG_GLPI["root_doc"] . "/ajax/viewsubitem.php",
            [
                'type' => self::class,
                'parenttype' => self::$itemtype,
                self::$items_id => $this->fields[self::$items_id],
                'id' => $this->fields["id"],
            ],
            '',
            false,
        );
        $js .= "};";

        return $js;
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
                        `name`                        varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                        `type`                        varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                        `resource_column`             int {$default_key_sign}                            NOT NULL,
                        `is_identifier`               tinyint                                 NOT NULL DEFAULT '0',
                        `plugin_resources_imports_id` int {$default_key_sign}                            NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
