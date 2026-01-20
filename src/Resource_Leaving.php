<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2026 by the resources Development Team.

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
use Html;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginResourcesChoice
 */
class Resource_Leaving extends CommonDBTM
{

    static $rightname = 'plugin_resources';

    /**
     * @param int $nb
     *
     * @return string
     */
    static function getTypeName($nb = 0)
    {

        return __('Leaving','resources');
    }

    public static function getIcon()
    {
        return "ti ti-door-exit";
    }

    /**
     * Have I the global right to "view" the Object
     *
     * Default is true and check entity if the objet is entity assign
     *
     * May be overloaded if needed
     *
     * @return booleen
     **/
    static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return booleen
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
     * @since 0.83
     *
     * @param CommonGLPI $item         Item on which the tab need to be displayed
     * @param boolean    $withtemplate is a template object ? (default 0)
     *
     *  @return string tab name
     **/
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

        $wizard_need = ContractType::checkWizardSetup($item->getField('id'), "use_need_wizard");

        if ($item->getType() == Resource::class
            && $this->canView()
            && $wizard_need
        ) {
            return self::createTabEntry(self::getTypeName(2));
        }
        return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

        if ($item->getType() == Resource::class) {

            $self = new self();
            $self->showLeavingForm($item->getField('id'));
        }
        return true;
    }

    /**
     * @param        $plugin_resources_resources_id
     * @param        $exist
     * @param string $withtemplate
     */
    function showLeavingForm($plugin_resources_resources_id) {
        if (!$this->canView()) {
            return false;
        }

        $canedit = $this->canCreate();
        $resources = new Resource();

        $resources->getFromDB( $plugin_resources_resources_id);

        if (empty($resources->fields['date_declaration_leaving'])) {
            echo "<div class='alert alert-info'>" . __(
                    'The resource is not leaving',
                    'resources'
                ) . "</div>";
            return false;
        }


        if (empty($resources->fields['remove_manager']) || $_SESSION['glpiID'] != $resources->fields['remove_manager']) {
            echo "<div class='alert alert-danger'>" . __(
                    'You are not the manager of this resource departure',
                    'resources'
                ) . "</div>";
            return false;
        }

        if ($canedit) {
            echo "<form name='form' method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR. "/front/resource.form.php\">";

            echo "<div align='center'><table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_1'><th colspan='2'>" . __('Give a leaving order', 'resources') . "</th></tr>";
            echo "<tr class='tab_bg_1'><td class='tab_bg_2'>";
            echo __('Date of departure', 'resources');
            echo "</td><td class='tab_bg_2'>";
            echo Html::input('date_declaration_leaving', ['value' => $resources->fields['date_declaration_leaving'], 'readonly' => true]);
            echo "</td></tr>";
            echo "<tr class='tab_bg_1'><td class='tab_bg_2'>";
            echo __('Order', 'resources');
            echo "</td><td class='tab_bg_2 center'>";
            echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
            Html::textarea(['name' => 'remove_order', 'value' => $resources->fields['remove_order']]);
            echo "</td></tr>";
            echo "<tr class='tab_bg_1'><td colspan='2' class='tab_bg_2 center'>";
            if (empty($resources->fields['remove_order'])) {
                echo Html::submit(_sx('button', 'Validate'), ['name' => 'validOrderLeaving', 'class' => 'btn btn-primary']);
            }
            echo "</td></tr>";
            echo "</table></div>";
            Html::closeForm();
        }
    }
}