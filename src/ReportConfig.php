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

use CommonDBTM;
use CommonGLPI;
use DBConnection;
use DbUtils;
use Dropdown;
use Html;
use Migration;
use Session;
use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ReportConfig
 */
class ReportConfig extends CommonDBTM
{

    static $rightname = 'plugin_resources';

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @param integer $nb Number of items
     *
     * @return string
     **/
    static function getTypeName($nb = 0)
    {
        return _n('Notification', 'Notifications', $nb);
    }

    static function getIcon()
    {
        return "ti ti-mail";
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
    static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return
     **/
    static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
    }

    /**
     * Get Tab Name used for itemtype
     *
     * NB : Only called for existing object
     *      Must check right on what will be displayed + template
     *
     * @param CommonGLPI $item Item on which the tab need to be displayed
     * @param boolean $withtemplate is a template object ? (default 0)
     *
     * @return string tab name
     **@since 0.83
     *
     */
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == Resource::class && $this->canView()) {
            return self::createTabEntry(self::getTypeName(2));
        }
        return '';
    }

    /**
     * show Tab content
     *
     * @param CommonGLPI $item Item on which the tab need to be displayed
     * @param integer $tabnum tab number (default 1)
     * @param boolean $withtemplate is a template object ? (default 0)
     *
     * @return boolean
     **@since 0.83
     *
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $CFG_GLPI;

        if ($item->getType() == Resource::class) {
            $ID = $item->getField('id');
            self::showReports($ID, $withtemplate);

            if ($item->can($ID, UPDATE) && !self::checkIfReportsExist($ID)) {
                $self = new self();
                $self->showForm("", [
                    'plugin_resources_resources_id' => $ID,
                    'target' => PLUGIN_RESOURCES_WEBDIR . "/front/reportconfig.form.php"
                ]);
            }

            if ($item->can($ID, UPDATE) && self::checkIfReportsExist($ID) && !$withtemplate) {
                Resource::showReportForm([
                    'id' => $ID,
                    'target' => PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php"
                ]);
            }
        }
        return true;
    }

    /**
     * Prepare input datas for adding the item
     *
     * @param array $input datas used to add the item
     *
     * @return array the modified $input array
     **/
    function prepareInputForAdd($input)
    {
        // Not attached to reference -> not added
        if (!isset($input['plugin_resources_resources_id']) || $input['plugin_resources_resources_id'] <= 0) {
            return false;
        }
        return $input;
    }

    /**
     * @param $ID
     *
     * @return bool
     */
    static function checkIfReportsExist($ID)
    {
        $restrict = ["plugin_resources_resources_id" => $ID];
        $dbu = new DbUtils();
        $reports = $dbu->getAllDataFromTable("glpi_plugin_resources_reportconfigs", $restrict);

        if (!empty($reports)) {
            foreach ($reports as $report) {
                return $report["id"];
            }
        } else {
            return false;
        }
    }

    /**
     * @param $plugin_resources_resources_id
     *
     * @return bool
     */
    function getFromDBByResource($plugin_resources_resources_id)
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'  => $this->getTable(),
            'WHERE' => ['plugin_resources_resources_id' => (int) $plugin_resources_resources_id],
        ]);
        if (count($iterator) != 1) {
            return false;
        }
        $this->fields = $iterator->current();
        if (is_array($this->fields) && count($this->fields)) {
            return true;
        }
        return false;
    }

    /**
     * Duplicate item resources from an item template to its clone
     *
     * @param $oldid        ID of the item to clone
     * @param $newid        ID of the item cloned
     *
     * @since version 0.84
     *
     */
    static function cloneItem($oldid, $newid)
    {
        global $DB;

        $query =
            [
                'SELECT' => [
                    '*',
                ],
                'FROM' => 'glpi_plugin_resources_reportconfigs',
                'WHERE' => [
                    'plugin_resources_resources_id' => $oldid
                ],
            ];

        foreach ($DB->request($query) as $data) {
            $report = new self();
            $report->add([
                'plugin_resources_resources_id' => $newid,
                'information' => addslashes($data["information"]),
                'comment' => addslashes($data["comment"]),
                'send_transfer_notif' => $data["send_transfer_notif"],
                'send_report_notif' => $data["send_report_notif"],
                'send_other_notif' => $data["send_other_notif"]
            ]);
        }
    }

    /**
     * @param       $ID
     * @param array $options
     *
     * @return bool
     */
    function showForm($ID, $options = [])
    {
        if (!$this->canview()) {
            return false;
        }

        $plugin_resources_resources_id = -1;
        if (isset($options['plugin_resources_resources_id'])) {
            $plugin_resources_resources_id = $options['plugin_resources_resources_id'];
        }

        if ($ID > 0) {
            $this->check($ID, READ);
        } else {
            $resource = new Resource();
            $resource->getFromDB($plugin_resources_resources_id);
            // Create item
            $input = ['plugin_resources_resources_id' => $plugin_resources_resources_id];
            $this->check(-1, UPDATE, $input);
        }

        $options["colspan"] = 1;
        //$this->showTabs($options);
        $this->showFormHeader($options);

        // Capture the GLPI yes/no dropdowns as HTML fragments for the template.
        ob_start();
        Dropdown::showYesNo('send_report', $this->fields["send_report_notif"]);
        $send_report_dropdown = ob_get_clean();

        ob_start();
        Dropdown::showYesNo('send_transfer_notif', $this->fields["send_transfer_notif"]);
        $send_transfer_dropdown = ob_get_clean();

        ob_start();
        Dropdown::showYesNo('send_report', $this->fields["send_other_notif"]);
        $send_other_dropdown = ob_get_clean();

        TemplateRenderer::getInstance()->display('@resources/reportconfig_form.html.twig', [
            'resource_hidden'        => Html::hidden(
                'plugin_resources_resources_id',
                ['value' => $plugin_resources_resources_id]
            ),
            'label_comments'         => __('Comments'),
            'comment_field'          => Html::textarea([
                'name'    => 'comment',
                'value'   => $this->fields["comment"],
                'cols'    => '100',
                'rows'    => '6',
                'display' => false,
            ]),
            'label_information'      => _n('Information', 'Informations', 2),
            'information_field'      => Html::textarea([
                'name'    => 'information',
                'value'   => $this->fields["information"],
                'cols'    => '100',
                'rows'    => '6',
                'display' => false,
            ]),
            'label_send_report'      => __('Send resource creation report notification', 'resources'),
            'send_report_dropdown'   => $send_report_dropdown,
            'label_send_transfer'    => __('Send resource transfer notification', 'resources'),
            'send_transfer_dropdown' => $send_transfer_dropdown,
            'label_send_other'       => __('Send other notification', 'resources'),
            'send_other_dropdown'    => $send_other_dropdown,
        ]);

        $options['candel'] = false;
        $this->showFormButtons($options);

        return true;
    }

    /**
     * @param        $ID
     * @param string $withtemplate
     */
    static function showReports($ID, $withtemplate = '')
    {
        global $DB;

        $rand = mt_rand();
        $resource = new Resource();
        $resource->getFromDB($ID);
        $canedit = $resource->can($ID, UPDATE);

        Session::initNavigateListItems(
            ReportConfig::class,
            Resource::getTypeName(1) . " = " . $resource->fields["name"]
        );

        $reportconfigs = 'glpi_plugin_resources_reportconfigs';
        $resources     = 'glpi_plugin_resources_resources';
        $iterator = $DB->request([
            'SELECT'    => [
                "$reportconfigs.id",
                "$reportconfigs.plugin_resources_resources_id",
                "$reportconfigs.information",
                "$reportconfigs.send_report_notif",
                "$reportconfigs.send_other_notif",
                "$reportconfigs.send_transfer_notif",
                "$reportconfigs.comment",
            ],
            'FROM'      => $reportconfigs,
            'LEFT JOIN' => [
                $resources => [
                    'ON' => [
                        $resources     => 'id',
                        $reportconfigs => 'plugin_resources_resources_id',
                    ],
                ],
            ],
            'WHERE'     => ["$reportconfigs.plugin_resources_resources_id" => (int) $ID],
            'LIMIT'     => 1,
        ]);
        $number = count($iterator);

        if ($number != "0") {
            $show_form    = ($withtemplate < 2);
            $show_buttons = ($withtemplate < 2 && $canedit);

            $reports = [];
            foreach ($iterator as $data) {
                // Capture the GLPI yes/no dropdowns as HTML fragments for the template.
                ob_start();
                Dropdown::showYesNo('send_report_notif', $data["send_report_notif"]);
                $send_report_dropdown = ob_get_clean();

                ob_start();
                Dropdown::showYesNo('send_transfer_notif', $data["send_transfer_notif"]);
                $send_transfer_dropdown = ob_get_clean();

                ob_start();
                Dropdown::showYesNo('send_other_notif', $data["send_other_notif"]);
                $send_other_dropdown = ob_get_clean();

                $reports[] = [
                    'comment_field'          => Html::textarea([
                        'name'    => 'comment',
                        'value'   => $data["comment"],
                        'cols'    => '100',
                        'rows'    => '6',
                        'display' => false,
                    ]),
                    'information_field'      => Html::textarea([
                        'name'    => 'information',
                        'value'   => $data["information"],
                        'cols'    => '100',
                        'rows'    => '6',
                        'display' => false,
                    ]),
                    'send_report_dropdown'   => $send_report_dropdown,
                    'send_transfer_dropdown' => $send_transfer_dropdown,
                    'send_other_dropdown'    => $send_other_dropdown,
                    'id_hidden'              => Html::hidden('id', ['value' => $data["id"]]),
                    'resource_hidden'        => Html::hidden(
                        'plugin_resources_resources_id',
                        ['value' => $ID]
                    ),
                ];
            }

            TemplateRenderer::getInstance()->display('@resources/reportconfig_reports.html.twig', [
                'rand'                => $rand,
                'show_form'           => $show_form,
                'show_buttons'        => $show_buttons,
                'title'               => __('Notification configuration', 'resources'),
                'label_comments'      => __('Comments'),
                'label_information'   => _n('Information', 'Informations', 2),
                'label_send_report'   => __('Send resource creation report notification', 'resources'),
                'label_send_transfer' => __('Send resource transfer notification', 'resources'),
                'label_send_other'    => __('Send other notification', 'resources'),
                'reports'             => $reports,
            ]);
        }
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
                        `send_report_notif`             tinyint      NOT NULL DEFAULT '1',
                        `send_other_notif`              tinyint      NOT NULL DEFAULT '0',
                        `send_transfer_notif`           tinyint      NOT NULL DEFAULT '0',
                        `comment`                       TEXT COLLATE utf8mb4_unicode_ci,
                        `information`                   TEXT COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`),
                        KEY `plugin_resources_resources_id` (`plugin_resources_resources_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
