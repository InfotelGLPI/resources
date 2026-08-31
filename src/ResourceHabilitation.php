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
use DBConnection;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Log;
use Migration;
use PluginPdfSimplePDF;
use Session;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ResourceHabilitation
 */
class ResourceHabilitation extends CommonDBTM
{
    public static $rightname = 'plugin_resources';
    public $dohistory = true;

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Habilitation', 'Habilitations', $nb, 'resources');
    }

    public static function getIcon()
    {
        return "ti ti-lock";
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
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == Resource::class
            && $this->canView()) {
            if ($_SESSION['glpishow_count_on_tabs']) {
                return self::createTabEntry(self::getTypeName(2), self::countForResource($item));
            }
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
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Resource::class) {
            $self = new self();
            $self->showItem($item);
        }
        //        if ($item->getType() == Resource::class) {
        //            $wizard = new Wizard();
        //            $wizard->wizardSixStep($item->getField('id'), ['default_button' => true, 'target' => 'item']);
        //        }
        return true;
    }

    /**
     * @param Resource $item
     *
     * @return int
     */
    public static function countForResource(Resource $item)
    {
        $restrict = ["plugin_resources_resources_id" => $item->getField('id')];
        $dbu = new DbUtils();
        $nb = $dbu->countElementsInTable(['glpi_plugin_resources_resourcehabilitations'], $restrict);

        return $nb;
    }

    /**
     * @param $item
     *
     * @return bool
     */
    public function showItem($item)
    {
        if (!$this->canView()) {
            return false;
        }

        $canedit = $this->canCreate();

        $data = $this->find(['plugin_resources_resources_id' => $item->getField('id')]);

        if ($canedit) {
            $used = [];
            foreach ($data as $habilitation) {
                $used[] = $habilitation['plugin_resources_habilitations_id'];
            }
            // Capture the GLPI dropdown, which echoes directly, for the template.
            ob_start();
            Dropdown::show(Habilitation::class, [
                'used'   => $used,
                'entity' => $item->getField("entities_id"),
            ]);
            $habilitation_dropdown = (string) ob_get_clean();

            TemplateRenderer::getInstance()->display('@resources/resourcehabilitation_add_form.html.twig', [
                'form_action'           => Toolbox::getItemTypeFormURL(ResourceHabilitation::class),
                'habilitation_label'    => self::getTypeName(1),
                'habilitation_dropdown' => $habilitation_dropdown,
                'resources_id'          => $item->getField('id'),
            ]);
        }
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
        if (empty($fields)) {
            return;
        }

        $entries = [];
        foreach ($fields as $field) {
            $entries[] = [
                'itemtype' => self::class,
                'id'       => $field['id'],
                'name'     => Dropdown::getDropdownName(
                    'glpi_plugin_resources_habilitations',
                    $field['plugin_resources_habilitations_id'],
                ),
            ];
        }

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'super_header'        => self::getTypeName(),
            'columns'             => ['name' => __('Name')],
            'entries'             => $entries,
            'total_number'        => count($entries),
            'filtered_number'     => count($entries),
            'showmassiveactions'  => $canedit,
            'massiveactionparams' => [
                'num_displayed' => count($entries),
                'container'     => 'masshabil' . mt_rand(),
            ],
        ]);
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
    public static function cloneItem($oldid, $newid)
    {
        global $DB;

        $query =
            [
                'SELECT' => [
                    '*',
                ],
                'FROM' => 'glpi_plugin_resources_resourcehabilitations',
                'WHERE' => [
                    'plugin_resources_resources_id' => $oldid,
                ],
            ];

        foreach ($DB->request($query) as $data) {
            $habilitation = new self();
            $habilitation->add([
                'plugin_resources_resources_id' => $newid,
                'plugin_resources_habilitations_id' => $data["plugin_resources_habilitations_id"],
            ]);
        }
    }

    public function post_addItem()
    {
        $changes[0] = 0;
        $changes[1] = '';
        $changes[2] = addslashes(
            sprintf(
                __('Adding the habilitation: %s', 'resources'),
                Dropdown::getDropdownName(
                    'glpi_plugin_resources_habilitations',
                    $this->input['plugin_resources_habilitations_id'],
                ),
            ),
        );
        Log::history(
            $this->input['plugin_resources_resources_id'],
            Resource::class,
            $changes,
            '',
            Log::HISTORY_LOG_SIMPLE_MESSAGE,
        );
    }

    /**
     * @return void
     */
    public function post_deleteFromDB()
    {
        $changes[0] = 0;
        $changes[1] = '';
        $changes[2] = addslashes(
            sprintf(
                __('Suppression of the habilitation: %s', 'resources'),
                Dropdown::getDropdownName(
                    'glpi_plugin_resources_habilitations',
                    $this->fields['plugin_resources_habilitations_id'],
                ),
            ),
        );
        Log::history(
            $this->fields['plugin_resources_resources_id'],
            Resource::class,
            $changes,
            '',
            Log::HISTORY_LOG_SIMPLE_MESSAGE,
        );
    }



    /**
     * Adding habilitations to the resource via the wizard
     *
     * @param $params
     */
    public function addResourceHabilitation($params)
    {
        $habilitation_level = new HabilitationLevel();

        $ressourcehabilitation = new ResourceHabilitation();
        $ressourcehabilitation->deleteByCriteria(["plugin_resources_resources_id" => $params['plugin_resources_resources_id']]);

        foreach ($params as $key => $val) {
            if (strpos($key, '_') > 0) {
                list($name, $id) = explode('_', $key);
                if (is_array($val)
                    && ($habilitation_level->getFromDB($id))) {
                    foreach ($val as $v) {
                        $this->addResourceHabilitationInDb($v, $params);
                    }
                } elseif ($habilitation_level->getFromDB($id)) {
                    $this->addResourceHabilitationInDb($val, $params);
                }
            }
        }
    }

    /**
     * @param $id
     * @param $params
     */
    public function addResourceHabilitationInDb($id, $params)
    {
        $resourceHabilitation = new self();
        $habilitation = new Habilitation();

        if ($habilitation->getFromDB($id)) {
            $input["plugin_resources_habilitations_id"] = $id;
            $input["plugin_resources_resources_id"] = $params["plugin_resources_resources_id"];
            $resourceHabilitation->add($input);
        }
    }

    /**
     * Verification if level of mandatory habilitations
     * return true if required fields are completed correctly
     * false if not
     *
     * @param array $params
     *
     * @return bool
     */
    public function checkRequiredFields($params = [])
    {

        $resource = new Resource();
        $resource->getFromDB($params['plugin_resources_resources_id']);
        $dbu = new DbUtils();

        $habilitation_level = new HabilitationLevel();
        $condition = ['is_mandatory_creating_resource' => 1] + $dbu->getEntitiesRestrictCriteria(
            $habilitation_level->getTable(),
            'entities_id',
            $resource->getEntityID(),
            $habilitation_level->maybeRecursive(),
        );
        $levels = $habilitation_level->find($condition, "name");

        foreach ($levels as $level) {
            if (!isset($params['habilitation_' . $level['id']])
                || (isset($params['habilitation_' . $level['id']])
                    && (empty($params['habilitation_' . $level['id']])))) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param \PluginPdfSimplePDF $pdf
     * @param \CommonGLPI $item
     * @param                     $tab
     *
     * @return bool
     */
    public static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab)
    {
        if ($item->getType() == Resource::class) {
            self::pdfForResource($pdf, $item);
        } else {
            return false;
        }
        return true;
    }

    /**
     * Show for PDF an resources : employee informations
     *
     * @param $pdf object for the output
     * @param $appli Resource Class
     */
    public static function pdfForResource(PluginPdfSimplePDF $pdf, Resource $appli)
    {
        global $DB;

        $ID = $appli->fields['id'];

        if (!$appli->can($ID, READ)) {
            return false;
        }

        if (!Session::haveRight("plugin_resources", READ)) {
            return false;
        }

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_resources_resourcehabilitations',
            'WHERE' => ['plugin_resources_resources_id' => (int) $ID],
        ]);
        $number = count($iterator);
        $pdf->setColumnsSize(100);

        $pdf->displayTitle('<b>' . self::getTypeName(2) . '</b>');

        if (!$number) {
            $pdf->displayLine(__('No results found'));
        } else {
            foreach ($iterator as $data) {
                $pdf->displayLine(Dropdown::getDropdownName("glpi_plugin_resources_habilitations", $data["plugin_resources_habilitations_id"]));
            }
        }

        $pdf->displaySpace();
    }

    public static function getHabilitationTxt($id)
    {
        $html = "";
        $habilitationsResource = new self();
        $habilitation = new Habilitation();
        $habilitationsResources = $habilitationsResource->find(['plugin_resources_resources_id' => $id]);
        if (count($habilitationsResources) > 0) {
            $html .= "<p><b>Habilitations actuelles : </b><br />";
            foreach ($habilitationsResources as $habilitationResource) {
                $habilitation->getFromDB($habilitationResource['plugin_resources_habilitations_id']);
                $html .= $habilitation->getField('completename') . "<br />";
            }
        }

        $html .= "</p>";

        return $html;
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
                        `plugin_resources_resources_id`     int unsigned NOT NULL DEFAULT '0',
                        `plugin_resources_habilitations_id` int unsigned NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`),
                        KEY `plugin_resources_resources_id` (`plugin_resources_resources_id`),
                        KEY `glpi_plugin_resources_habilitations_id` (`plugin_resources_habilitations_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
