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
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
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
    public static $rightname = 'plugin_resources';

    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {

        return __('Leaving', 'resources');
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
            return self::createTabEntry(self::getTypeName(2));
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

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
    public function showLeavingForm($plugin_resources_resources_id)
    {
        if (!$this->canView()) {
            return false;
        }

        $canedit = $this->canCreate();
        $resources = new Resource();

        $resources->getFromDB($plugin_resources_resources_id);

        if (empty($resources->fields['date_declaration_leaving'])) {
            TemplateRenderer::getInstance()->display('@resources/alert_message.html.twig', [
                'level'   => 'info',
                'message' => __('The resource is not leaving', 'resources'),
            ]);
            return false;
        }

        if (empty($resources->fields['remove_manager']) || $_SESSION['glpiID'] != $resources->fields['remove_manager']) {
            TemplateRenderer::getInstance()->display('@resources/alert_message.html.twig', [
                'level'   => 'danger',
                'message' => __('You are not the manager of this resource departure', 'resources'),
            ]);
            return false;
        }

        if ($canedit) {
            TemplateRenderer::getInstance()->display('@resources/resource_leaving_form.html.twig', [
                'form_action'    => PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php",
                'resources_id'   => $plugin_resources_resources_id,
                'date_end_input' => Html::input(
                    'date_end',
                    ['value' => $resources->fields['date_end'], 'readonly' => true],
                ),
                'order_textarea' => Html::textarea([
                    'name'    => 'remove_order',
                    'value'   => $resources->fields['remove_order'],
                    'display' => false,
                ]),
                'can_validate'   => empty($resources->fields['remove_order']),
                // 'Validate' has no entry in the plugin domain: use the core button context.
                'validate_label' => _x('button', 'Validate'),
            ]);
        }
    }
}
