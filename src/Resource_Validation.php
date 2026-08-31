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
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Resource_Validation
 */
class Resource_Validation extends CommonDBTM
{
    public static $rightname = 'plugin_resources_validation';

    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        if ($nb == 1) {
            return __('Validation', 'resources');
        }
        return __('AD Synchronization', 'resources');
    }

    public static function getIcon()
    {
        return "ti ti-checkbox";
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
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return booleen
     **/
    public static function canCreate(): bool
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
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if ($item->getType() == Resource::class
            && $this->canView()
        ) {
            if (!$item->fields['valid_resource_information']) {
                return self::createTabEntry(self::getTypeName(1));
            }
            return self::createTabEntry(self::getTypeName(2));
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item->getType() == Resource::class) {

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
    public function showValidationForm($plugin_resources_resources_id)
    {
        if (!$this->canView()) {
            return false;
        }

        $canedit = $this->canCreate();
        $resources = new Resource();

        $resources->getFromDB($plugin_resources_resources_id);

        if (!$resources->fields['valid_resource_information'] && (empty($resources->fields['users_id']) || $_SESSION['glpiID'] != $resources->fields['users_id'])) {
            TemplateRenderer::getInstance()->display('@resources/alert_message.html.twig', [
                'level'   => 'info',
                'message' => __(
                    'The direct manager of the resource must validate the information before it can be synchronized.',
                    'resources',
                ),
            ]);
            return false;
        }

        if (!$resources->fields['valid_resource_information']) {
            echo Ajax::createModalWindow(
                'popupAnswer',
                PLUGIN_RESOURCES_WEBDIR . '/front/modalvalidationinfo.php',
                [
                    'title' => __('Are you sure?', 'resources'),
                    'reloadonclose' => false,
                    'width' => 1180,
                    'height' => 500,
                ],
            );

            TemplateRenderer::getInstance()->display('@resources/resource_validation_form.html.twig', [
                'validated' => false,
            ]);

            // Called by the confirmation modal loaded above.
            $url = PLUGIN_RESOURCES_WEBDIR;
            $resources_id = (int) $plugin_resources_resources_id;
            echo Html::scriptBlock(<<<JAVASCRIPT
                function validinformation() {
                    $.ajax({
                        type: 'POST',
                        url: '{$url}/ajax/validinformation.php',
                        data: {
                            'plugin_resources_resources_id': {$resources_id},
                            'validSaisie': 1
                        },
                        success: function() {
                            window.location.reload();
                        }
                    });
                }
                JAVASCRIPT);

            return;
        }

        if (!$canedit) {
            TemplateRenderer::getInstance()->display('@resources/alert_message.html.twig', [
                'level'   => 'danger',
                'message' => __('You are not able to synchronize this account.', 'resources'),
            ]);
            return false;
        }

        TemplateRenderer::getInstance()->display('@resources/resource_validation_form.html.twig', [
            'validated'    => true,
            'form_action'  => PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php",
            'resources_id' => $plugin_resources_resources_id,
            'sync_label'   => __('Synchronize with Active Directory', 'resources'),
        ]);
    }
}
