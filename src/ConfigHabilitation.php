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
use Dropdown;
use GlpiPlugin\Metademands\Metademand;
use GlpiPlugin\Metademands\Metademand_Resource;
use Html;
use Migration;
use Plugin;
use Session;
use Toolbox;
use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ConfigHabilitation
 */
class ConfigHabilitation extends CommonDBTM
{

    static $rightname = 'plugin_resources_habilitation';
    public $dohistory = true;

    const ACTION_ADD = 1;
    const ACTION_DELETE = 2;


    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @param int $nb
     * @return string
     */
    static function getTypeName($nb = 0)
    {
        return _n('Super habilitation management', 'Super habilitations management', 2, 'resources');
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
     * Get the name of the action
     *
     * @param  $action
     * @return
     */
    static function getNameAction($action)
    {
        switch ($action) {
            case self::ACTION_ADD:
                return __('Declare a super habilitation', 'resources');
            case self::ACTION_DELETE:
                return __('Remove a super habilitation', 'resources');
        }
    }


    static function getIcon()
    {
        return "ti ti-lock-down";
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return self::createTabEntry(self::getTypeName());
    }
    /**
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     * @see CommonGLPI::displayTabContentForItem()
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Config::class) {
            $self = new self();
            $self->showFormHabilitation();
        }
        return true;
    }

    /**
     * Display of the link to configure the super habilitation interface
     */
    function showConfigForm()
    {
        TemplateRenderer::getInstance()->display('@resources/confighabilitation_config.html.twig', [
            'form_action' => self::getFormURL(),
            'title'       => self::getTypeName(2),
            'link'        => './confighabilitation.form.php?config',
            'link_label'  => Metademand_Resource::getTypeName(2),
        ]);
    }

    /**
     * Choose link with metademand
     *
     * @return bool
     */
    function showFormHabilitation()
    {
        if (!$this->canView()) {
            return false;
        }
        if (!$this->canCreate()) {
            return false;
        }

        $used_data     = [];
        $data_entities = $this->find(['entities_id' => $_SESSION['glpiactive_entity']]);

        $number_action = count($data_entities);

        if ($data_entities) {
            foreach ($data_entities as $field) {
                $used_data[$field['action']] = $field['action'];
            }
        }
        $canedit = $this->canCreate();

        if ($canedit) {
            $tpl = ['already_linked' => ($number_action == 2)];
            if ($number_action == 2) {
                $tpl['already_linked_message'] = __('The current entity is already linked to a meta-demand', 'resources');
            } else {
                // Capture the two GLPI dropdowns as HTML fragments for the template.
                ob_start();
                Dropdown::showFromArray(
                    'action',
                    [
                        self::ACTION_ADD    => self::getNameAction(self::ACTION_ADD),
                        self::ACTION_DELETE => self::getNameAction(self::ACTION_DELETE),
                    ],
                    ['used' => $used_data]
                );
                $action_dropdown = ob_get_clean();

                ob_start();
                Dropdown::show(Metademand::class, [
                    'name'   => 'plugin_metademands_metademands_id',
                    'entity' => $_SESSION['glpiactive_entity'],
                ]);
                $metademand_dropdown = ob_get_clean();

                $tpl += [
                    'form_action'         => Toolbox::getItemTypeFormURL(ConfigHabilitation::class),
                    'title'               => Metademand_Resource::getTypeName(2),
                    'action_label'        => _n('Action', 'Actions', 1),
                    'action_dropdown'     => $action_dropdown,
                    'metademand_label'    => Metademand::getTypeName(1),
                    'metademand_dropdown' => $metademand_dropdown,
                    'entities_id'         => $_SESSION['glpiactive_entity'],
                ];
            }
            TemplateRenderer::getInstance()->display('@resources/confighabilitation_form.html.twig', $tpl);
        }
        //list metademands
        $data = $this->find();
        $this->listItems($data, $canedit);
    }

    /**
     * List of metademands
     *
     * @param $fields
     * @param $canedit
     */
    private function listItems($fields, $canedit)
    {
        if (!empty($fields)) {
            $rand = mt_rand();
            echo "<div class='left'>";
            if ($canedit) {
                Html::openMassiveActionsForm('massHabilitation' .  $rand);
                $massiveactionparams = ['item' => __CLASS__, 'container' => 'massHabilitation' .  $rand];
                Html::showMassiveActions($massiveactionparams);
            }
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr>";
            echo "<th colspan='4'>" . __('Meta-demands linked', 'metademands') . "</th>";
            echo "</tr>";
            echo "<tr>";
            if ($canedit) {
                echo "<th width='10'>" . Html::getCheckAllAsCheckbox('massHabilitation' .  $rand) . "</th>";
            }
            echo "<th>" . __('Name') . "</th>";
            echo "<th>" . __('Action') . "</th>";
            echo "<th>" . __('Entity') . "</th>";
            foreach ($fields as $field) {
                echo "<tr class='tab_bg_1'>";
                if ($canedit) {
                    echo "<td width='10'>";
                    Html::showMassiveActionCheckBox(__CLASS__, $field['id']);
                    echo "</td>";
                }
                //DATA LINE
                echo "<td>" . Dropdown::getDropdownName(
                    'glpi_plugin_metademands_metademands',
                    $field['plugin_metademands_metademands_id']
                ) . "</td>";
                echo "<td>" . self::getNameAction($field['action']) . "</td>";
                echo "<td>" . Dropdown::getDropdownName('glpi_entities', $field['entities_id']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            if ($canedit) {
                $massiveactionparams['ontop'] = false;
                Html::showMassiveActions($massiveactionparams);
                Html::closeForm();
            }
            echo "</div>";
        }
    }

    /**
     * Display Menu
     */
    function showMenu()
    {

        $title = self::getTypeName(2);
        Wizard::WizardHeader($title);

        echo "<div class='center'><table class='tab_menu' width='30%' cellpadding='5'>";

        $canresting = Session::haveright('plugin_resources_habilitation', UPDATE);

        echo "<tr class=''>";
        if ($canresting) {
            $colspan = 1;
            if (Plugin::isPluginActive("metademands")) {
                //new habilitation
                echo "<td class='tab_td_menu center'>";
                echo "<a href=\"./confighabilitation.form.php?new\">";
                echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/habilitationnew.png'
                  alt='" . __('Declare a super habilitation', 'resources') . "'>";
                echo "<br>" . __('Declare a super habilitation', 'resources') . "</a>";
                echo "</td>";

                //delete habilitation
                echo "<td class='tab_td_menu center' colspan='$colspan'>";
                echo "<a href=\"./confighabilitation.form.php?delete\">";
                echo "<img src='" . PLUGIN_RESOURCES_WEBDIR . "/pics/habilitationdelete.png'
                  alt='" . __('Remove a super habilitation', 'resources') . "'>";
                echo "<br>" . __('Remove a super habilitation', 'resources') . "</a>";
                echo "</td>";
            } else {
                echo "<td class='center' colspan='3'>";
                echo "</td>";
            }
        }
        echo "</tr></table>";
        echo "</div>";
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
                        `entities_id`                       int {$default_key_sign} NOT NULL DEFAULT '0',
                        `action`                            tinyint      NOT NULL DEFAULT '0',
                        `plugin_metademands_metademands_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`),
                        KEY `entities_id` (`entities_id`),
                        KEY `plugin_metademands_metademands_id` (`plugin_metademands_metademands_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
