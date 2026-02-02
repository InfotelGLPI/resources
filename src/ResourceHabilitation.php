<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2022 by the resources Development Team.

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
use DbUtils;
use Dropdown;
use Html;
use Log;
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

    static $rightname = 'plugin_resources';
    public $dohistory = true;

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @param int $nb
     *
     * @return string
     */
    static function getTypeName($nb = 0)
    {
        return _n('Habilitation', 'Habilitations', $nb, 'resources');
    }

    static function getIcon()
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
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Resource::class) {
            $self = new self();
            $self->showItem($item);
        }
        return true;
    }

    /**
     * @param Resource $item
     *
     * @return int
     */
    static function countForResource(Resource $item)
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
    function showItem($item)
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
            echo "<form name='form' method='post' action='" .
                Toolbox::getItemTypeFormURL(ResourceHabilitation::class) . "'>";

            echo "<div class='center'><table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_1'><th colspan='2'>" . __(
                'Add additional habilitation',
                'resources'
            ) . "</th></tr>";
            echo "<tr class='tab_bg_1'><td class='center'>";
            echo self::getTypeName(1) . "</td>";
            echo "<td class='center'>";
            Dropdown::show(Habilitation::class, [
                'used' => $used,
                'entity' => $item->getField("entities_id")
            ]);
            echo "</td></tr>";

            echo "<tr class='tab_bg_1'><td colspan='2' class='tab_bg_2 center'>";
            echo Html::submit(_sx('button', 'Add'), ['name' => 'add', 'class' => 'btn btn-primary']);
            echo Html::hidden('plugin_resources_resources_id', ['value' => $item->getField('id')]);

            echo "</td></tr>";
            echo "</table></div>";
            Html::closeForm();
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
        if (!empty($fields)) {
            $rand = mt_rand();
            echo "<div class='left'>";
            if ($canedit) {
                Html::openMassiveActionsForm('masshabil' .  $rand);
                $massiveactionparams = ['item' => __CLASS__, 'container' => 'masshabil'  . $rand];
                Html::showMassiveActions($massiveactionparams);
            }
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr>";
            echo "<th colspan='2'>" . self::getTypeName() . "</th>";
            echo "</tr>";
            echo "<tr>";
            if ($canedit) {
                echo "<th width='10'>" . Html::getCheckAllAsCheckbox('masshabil' .  $rand) . "</th>";
            }
            echo "<th>" . __('Name') . "</th>";
            foreach ($fields as $field) {
                echo "<tr class='tab_bg_1'>";
                if ($canedit) {
                    echo "<td width='10'>";
                    Html::showMassiveActionCheckBox(__CLASS__, $field['id']);
                    echo "</td>";
                }
                //DATA LINE
                echo "<td class='left'>" . Dropdown::getDropdownName(
                    'glpi_plugin_resources_habilitations',
                    $field['plugin_resources_habilitations_id']
                ) . "</td>";
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
                'FROM' => 'glpi_plugin_resources_resourcehabilitations',
                'WHERE' => [
                    'plugin_resources_resources_id' => $oldid
                ],
            ];

        foreach ($DB->request($query) as $data) {
            $habilitation = new self();
            $habilitation->add([
                'plugin_resources_resources_id' => $newid,
                'plugin_resources_habilitations_id' => $data["plugin_resources_habilitations_id"]
            ]);
        }
    }

    function post_addItem()
    {
        $changes[0] = 0;
        $changes[1] = '';
        $changes[2] = addslashes(
            sprintf(
                __('Adding the habilitation: %s', 'resources'),
                Dropdown::getDropdownName(
                    'glpi_plugin_resources_habilitations',
                    $this->input['plugin_resources_habilitations_id']
                )
            )
        );
        Log::history(
            $this->input['plugin_resources_resources_id'],
            Resource::class,
            $changes,
            '',
            Log::HISTORY_LOG_SIMPLE_MESSAGE
        );
    }

    /**
     * @return void
     */
    function post_deleteFromDB()
    {
        $changes[0] = 0;
        $changes[1] = '';
        $changes[2] = addslashes(
            sprintf(
                __('Suppression of the habilitation: %s', 'resources'),
                Dropdown::getDropdownName(
                    'glpi_plugin_resources_habilitations',
                    $this->fields['plugin_resources_habilitations_id']
                )
            )
        );
        Log::history(
            $this->fields['plugin_resources_resources_id'],
            Resource::class,
            $changes,
            '',
            Log::HISTORY_LOG_SIMPLE_MESSAGE
        );
    }



    /**
     * Adding habilitations to the resource via the wizard
     *
     * @param $params
     */
    function addResourceHabilitation($params)
    {
        $habilitation_level = new HabilitationLevel();

        foreach ($params as $key => $val) {
            if (strpos($key, '__') > 0) {
                list($name, $id) = explode('__', $key);
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
    function addResourceHabilitationInDb($id, $params)
    {
        $resourceHabilitation = new self();
        $habilitation = new Habilitation();

        if ($habilitation->getFromDB($id)) {
            $params["plugin_resources_habilitations_id"] = $id;
            $resourceHabilitation->add($params);
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
    function checkRequiredFields($params = [])
    {
        $resource = new Resource();
        $resource->getFromDB($params['plugin_resources_resources_id']);
        $dbu = new DbUtils();

        $habilitation_level = new HabilitationLevel();
        $condition = ['is_mandatory_creating_resource' => 1] + $dbu->getEntitiesRestrictCriteria(
            $habilitation_level->getTable(),
            'entities_id',
            $resource->getEntityID(),
            $habilitation_level->maybeRecursive()
        );
        $levels = $habilitation_level->find($condition, "name");

        foreach ($levels as $level) {
            if (!isset($params[str_replace(" ", "_", $level['name']) . '__' . $level['id']])
                || (isset($params[str_replace(" ", "_", $level['name'] . '__' . $level['id'])])
                    && empty($params[str_replace(" ", "_", $level['name'] . '__' . $level['id'])]))) {
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
    static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab)
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
    static function pdfForResource(PluginPdfSimplePDF $pdf, Resource $appli)
    {
        global $DB;

        $ID = $appli->fields['id'];

        if (!$appli->can($ID, READ)) {
            return false;
        }

        if (!Session::haveRight("plugin_resources", READ)) {
            return false;
        }

        $query = "SELECT *
               FROM `glpi_plugin_resources_resourcehabilitations`
               WHERE `plugin_resources_resources_id` = '$ID'";
        $result = $DB->doQuery($query);
        $number = $DB->numrows($result);
        $pdf->setColumnsSize(100);

        $pdf->displayTitle('<b>' . self::getTypeName(2) . '</b>');

        if (!$number) {
            $pdf->displayLine(__('No results found'));
        } else {
            for ($i = 0; $i < $number; $i++) {
                $habilitaion_id = $DB->result($result, $i, "plugin_resources_habilitations_id");
                $pdf->displayLine(Dropdown::getDropdownName("glpi_plugin_resources_habilitations", $habilitaion_id));
            }
        }

        $pdf->displaySpace();
    }

    static function getHabilitationTxt($id)
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
}
