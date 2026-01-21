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
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginResourcesChoice
 */
class PluginResourcesResource_Validation extends CommonDBTM
{

    static $rightname = 'plugin_resources';

    /**
     * @param int $nb
     *
     * @return string
     */
    static function getTypeName($nb = 0)
    {

        return __('Validation and AD Synchronization','resources');
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
    static function canView()
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return booleen
     **/
    static function canCreate()
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

        $wizard_need = PluginResourcesContractType::checkWizardSetup($item->getField('id'), "use_need_wizard");

        if ($item->getType() == 'PluginResourcesResource'
            && $this->canView()
            && $wizard_need
        ) {
            return self::getTypeName(2);
        }
        return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

        if ($item->getType() == 'PluginResourcesResource') {

            $self = new self();
            $self->showValidationForm($item->getField('id'));
        }
        return true;
    }

    /**
     * @param        $plugin_resources_resources_id
     * @param        $exist
     * @param string $withtemplate
     */
    function showValidationForm($plugin_resources_resources_id) {
        if (!$this->canView()) {
            return false;
        }

        $canedit = $this->canCreate();
        $resources = new PluginResourcesResource();

        $resources->getFromDB( $plugin_resources_resources_id);

        if (!$resources->fields['valid_resource_information'] && (empty($resources->fields['users_id']) || $_SESSION['glpiID'] != $resources->fields['users_id'])) {
            echo "<div class='alert alert-info'>" . __(
                    'The direct manager of the resource must validate the information before it can be synchronized.',
                    'resources'
                ) . "</div>";
            return false;
        }

        if ($canedit) {
            if (!$resources->fields['valid_resource_information']) {

                echo Ajax::createModalWindow(
                    'popupAnswer',
                    PLUGIN_RESOURCES_WEBDIR . '/front/modalvalidationinfo.php',
                    [
                        'title' => __('Are you sure?', 'resources'),
                        'reloadonclose' => false,
                        'width' => 1180,
                        'height' => 500,
                    ]
                );
//                echo "<form name='form' method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR. "/front/resource.form.php\">";

                echo "<div align='center'><table class='tab_cadre_fixe'>";
                echo "<tr class='tab_bg_1'><th colspan='2'>" . __('Validation', 'resources') . "</th></tr>";

                echo "<tr class='tab_bg_1'><td colspan='2' class='tab_bg_2 center'>";
                echo "<a class='btn btn-primary overflow-hidden text-nowrap' href='#' onclick='popupAnswer.show();' title='" . __("Validate", "resources") . "'>" . __("Validate", "resources") . "</a>";
                echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                //echo Html::submit(_sx('button', 'Validate'), ['name' => 'validSaisie', 'class' => 'btn btn-primary', 'data-bs-toggle' => "modal", 'data-bs-target' => "#popupAnswer"]);
                echo "</td></tr>";
//                echo "</table></div>";
                echo Html::scriptBlock("
                function validinformation() {
                   $.ajax({
                       type: 'POST',
                       url: '" . PLUGIN_RESOURCES_WEBDIR . "/ajax/validinformation.php',
                       data:{
                           'plugin_resources_resources_id' : " . $plugin_resources_resources_id . ",
                           'validSaisie' : 1,
                       },
                       success: function(){
                        window.location.reload();
                       },
                   });


           }
                ");
                Html::closeForm();
            } else {
                echo "<form name='form' method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR. "/front/resource.form.php\">";

                echo "<div align='center'><table class='tab_cadre_fixe'>";
                echo "<tr class='tab_bg_1'><th colspan='2'>" . __('Active Directory synchronization', 'resources') . "</th></tr>";

                echo "<tr class='tab_bg_1'><td colspan='2' class='tab_bg_2 center'>";
                echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                echo Html::submit(_sx('button', __('Synchronize with Active Directory', 'resources')), ['name' => 'synchActiveDirectory', 'class' => 'btn btn-primary']);
                echo "</td></tr>";
                echo "</table></div>";
                Html::closeForm();
            }
        }
    }
}