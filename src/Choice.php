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

use Ajax;
use CommonDBTM;
use CommonGLPI;
use DbUtils;
use Dropdown;
use Html;
use Session;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Choice
 */
class Choice extends CommonDBTM
{

    static $rightname = 'plugin_resources';

    /**
     * @param int $nb
     *
     * @return string
     */
    static function getTypeName($nb = 0)
    {
        return _n('Need', 'Needs', $nb, 'resources');
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
        $wizard_need = ContractType::checkWizardSetup($item->getField('id'), "use_need_wizard");

        if ($item->getType() == Resource::class
            && $this->canView()
            && $wizard_need
        ) {
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
            $self->showItemHelpdesk($item->getField('id'), 0, $withtemplate);
        }
        return true;
    }

    static function getIcon()
    {
        return "ti ti-package-import";
    }

    /**
     * @param Resource $item
     *
     * @return int
     */
    static function countForResource(Resource $item)
    {
        $dbu = new DbUtils();
        $restrict = ["plugin_resources_resources_id" => $item->getField('id')];
        $nb = $dbu->countElementsInTable(['glpi_plugin_resources_choices'], $restrict);

        return $nb;
    }

    /**
     * @param $values
     */
    function addHelpdeskItem($values)
    {
        $this->add([
            'plugin_resources_resources_id' => $values["plugin_resources_resources_id"],
            'plugin_resources_choiceitems_id' => $values["plugin_resources_choiceitems_id"],
            'comment' => ''
        ]);
    }

    /**
     * @param $values
     */
    function addComment($values)
    {
        $resource = new Resource();
        $resource->getFromDB($values['plugin_resources_resources_id']);

        $comment = $values['comment'];

        if (!empty($resource->fields['comment'])) {
            $comment = $resource->fields['comment'] .
                "\r\n\r" . __('Others needs', 'resources') . "\r\n\r" . $values['comment'];
        }

        $resource->update([
            'id' => $values['plugin_resources_resources_id'],
            'comment' => addslashes($comment)
        ]);

        $_SESSION['plugin_ressources_' . $values['plugin_resources_resources_id'] . '_comment'] = $comment;
    }

    /**
     * @param $values
     */
    function updateComment($values)
    {
        $resource = new Resource();
        $resource->getFromDB($values['plugin_resources_resources_id']);

        $comment = $values['comment'];

        $resource->update([
            'id' => $values['plugin_resources_resources_id'],
            'comment' => addslashes($comment)
        ]);

        $_SESSION['plugin_ressources_' . $values['plugin_resources_resources_id'] . '_comment'] = $comment;
    }

    /**
     * @param $values
     */
    function addNeedComment($values)
    {
        $this->update([
            'id' => $values['id'],
            'comment' => $values['commentneed']
        ]);
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
        $choice_item = new ChoiceItem();
        $choice_item->getfromDB($input['plugin_resources_choiceitems_id']);
        $childs = $choice_item->haveChildren();
        if ($childs) {
            Session::addMessageAfterRedirect(
                __("Cannot add a choice that contains children", "resources"),
                true,
                ERROR
            );
            return false;
        }

        return $input;
    }

    /**
     * Duplicate item resources from an item template to its clone
     *
     * @param $itemtype     itemtype of the item
     * @param $oldid        ID of the item to clone
     * @param $newid        ID of the item cloned
     * @param $newitemtype  itemtype of the new item (= $itemtype if empty) (default '')
     **@since version 0.84
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
                'FROM' => 'glpi_plugin_resources_choices',
                'WHERE' => [
                    'plugin_resources_resources_id' => $oldid
                ],
            ];

        foreach ($DB->request($query) as $data) {
            $choice = new self();
            $choice->add([
                'plugin_resources_resources_id' => $newid,
                'plugin_resources_choiceitems_id' => $data["plugin_resources_choiceitems_id"],
                'comment' => $data["comment"]
            ]);
        }
    }


    /**
     * @param $item
     * @param $rand
     */
    static function showAddCommentForm($item, $rand)
    {
        global $CFG_GLPI;

        $items_id = $item['id'];
        echo "<div class='center' id='addneedcomment" . "$items_id$rand'></div>\n";
        echo "<script type='text/javascript' >\n";
        echo "function viewAddNeedComment" . "$items_id(){\n";
        $params = [
            'id' => $items_id,
            'rand' => $rand
        ];
        Ajax::UpdateItemJsCode(
            "addneedcomment" . "$items_id$rand",
            PLUGIN_RESOURCES_WEBDIR . "/ajax/addneedcomment.php",
            $params,
            false
        );
        echo "};";
        echo "</script>\n";
        echo "<p class='center'><a href='javascript:viewAddNeedComment" . "$items_id();'>";
        echo __('Add a comment', 'resources');
        echo "</a></p>\n";

        echo "<script type='text/javascript' >\n";
        echo "function hideAddForm$items_id() {\n";
        echo "$('#addcommentneed$items_id$rand').hide();";
        echo "$('#viewaccept$items_id').hide();";
        echo "}\n";
        echo "</script>\n";
    }

    /**
     * @param $item
     * @param $rand
     */
    static function showModifyCommentFrom($item, $rand)
    {
        global $CFG_GLPI;

        $items_id = $item['id'];
        echo "<script type='text/javascript' >\n";
        echo "function showComment$items_id () {\n";
        echo "$('#commentneed$items_id$rand').hide();";
        echo "$('#viewaccept$items_id$rand').show();";

        $params = [
            'name' => 'commentneed' . $items_id,
            'data' => rawurlencode($item["comment"])
        ];
        Ajax::UpdateItemJsCode(
            "viewcommentneed$items_id$rand",
            PLUGIN_RESOURCES_WEBDIR . "/ajax/inputtext.php",
            $params,
            false
        );
        echo "}";
        echo "</script>\n";
        echo "<div id='commentneed$items_id$rand' class='center' onClick='showComment$items_id()'>\n";
        echo $item["comment"];
        echo "</div>\n";
        echo "<div id='viewcommentneed$items_id$rand'>\n";
        echo "</div>\n";
        echo "<div id='viewaccept$items_id$rand' style='display:none;' class='center'>";
        echo "<p><input type='submit' name='updateneedcomment[" . $items_id . "]' value=\"" .
            _sx('button', 'Update') . "\" class='submit btn btn-primary'>";
        echo "&nbsp;<input type='button' onclick=\"hideForm$items_id();\" value=\"" .
            _sx('button', 'Cancel') . "\" class='submit btn btn-primary'></p>";
        echo "</div>";
        echo "<script type='text/javascript' >\n";
        echo "function hideForm$items_id() {\n";
        echo "$('#viewcommentneed$items_id$rand textarea').remove();";
        echo "$('#commentneed$items_id$rand').show();";
        echo "$('#viewaccept$items_id$rand').hide();";
        echo "}\n";
        echo "</script>\n";
    }

    /**
     * @param        $plugin_resources_resources_id
     * @param        $exist
     * @param string $withtemplate
     */
    function showItemHelpdesk($plugin_resources_resources_id, $exist, $withtemplate = '')
    {
        global $CFG_GLPI;

        $restrict = ["plugin_resources_resources_id" => $plugin_resources_resources_id];
        $dbu = new DbUtils();
        $choices = $dbu->getAllDataFromTable($this->getTable(), $restrict);

        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);

        $canedit = $resource->can($plugin_resources_resources_id, UPDATE)
            && $withtemplate < 2
            && $resource->fields["is_leaving"] != 1;
        if ($exist == 0) {
            echo "<form method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR . "/front/resource_item.list.php\">";
        } elseif ($exist == 1) {
            echo "<form method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php\">";
        }

        echo "<div class='center'><table class='tab_cadre_fixe'>";
        echo "<tr>";
        echo "<th colspan='4'>" . __('Element(s) to be affected', 'resources') . "</th>";
        echo "</tr>";
        echo "<tr>";
        echo "<th>" . __('Type') . "</th>";
        echo "<th>" . __('Description') . "</th>";
        echo "<th>" . __('Comments') . "</th>";
        if ($canedit) {
            echo "<th>&nbsp;</th>";
        }
        echo "</tr>";

        $used = [];
        if (!empty($choices)) {
            foreach ($choices as $choice) {
                $used[] = $choice["plugin_resources_choiceitems_id"];
                echo "<tr class='tab_bg_1'>";

                $items = Dropdown::getDropdownName(
                    "glpi_plugin_resources_choiceitems",
                    $choice["plugin_resources_choiceitems_id"],
                    1
                );
                echo "<td class='left'>";
                echo $items['name'];
                echo "</td>";
                echo "<td class='left'>";
                echo nl2br($items["comment"]);
                echo "</td>";
                echo "<td class='center'>";

                $rand = mt_rand();
                if (!empty($choice["comment"])) {
                    self::showModifyCommentFrom($choice, $rand);
                } else {
                    self::showAddCommentForm($choice, $rand);
                }
                echo "</td>";
                if ($canedit) {
                    echo "<td class='center' class='tab_bg_2'>";
                    Html::showSimpleForm(
                        PLUGIN_RESOURCES_WEBDIR . '/front/resource_item.list.php',
                        'deletehelpdeskitem',
                        _x('button', 'Delete permanently'),
                        ['id' => $choice["id"]]
                    );
                    echo "</td>";
                }
                echo "</tr>";
            }
        }
        if ($canedit) {
            echo "<tr class='tab_bg_1'>";
            echo "<th colspan='4'>" . __('Add a need', 'resources') . " :</th>";
            echo "</tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td colspan='4' class='center'>";
            echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);

            $condition = [];
            if (Session::getCurrentInterface() != 'central') {
                $condition = ['is_helpdesk_visible' => 1];
            }
            Dropdown::show(
                ChoiceItem::class,
                [
                    'name' => 'plugin_resources_choiceitems_id',
                    'entity' => $resource->getEntityID(),
                    'condition' => $condition,
                    'used' => $used,
                    'addicon' => true
                ]
            );
            echo "</td></tr>";
            echo "<tr class='tab_bg_1'>";
            echo "<td class='center' colspan='4'>";
            echo Html::submit(_sx('button', 'Add'), ['name' => 'addhelpdeskitem', 'class' => 'btn btn-primary']);
            echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
            if (Session::getCurrentInterface() != 'central') {
                if ($exist != 1) {
                    echo Html::submit(
                        __('Terminate the declaration', 'resources'),
                        ['name' => 'finish', 'class' => 'btn btn-primary']
                    );
                } else {
                    echo Html::submit(
                        __('Resend the declaration', 'resources'),
                        ['name' => 'resend', 'class' => 'btn btn-primary']
                    );
                }
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table></div>";
        Html::closeForm();
        echo "<br>";

        echo "<form method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR. "/front/resource_item.list.php\">";

        echo "<div align='center'><table class='tab_cadre_fixe'>";
        echo "<tr>";
        echo "<th colspan='4'>" . __('Specials requirements', 'resources') . "</th>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>";
        echo __('Computer and phone equipment needs', 'resources');
        echo "</td>";
        echo "<td>";
        Html::textarea(['name' => 'computer_phone_equipment', 'value' => $resource->fields['computer_phone_equipment']]);
        echo "</td>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>";
        echo __('Softwares requirements', 'resources');;
        echo "</td>";
        echo "<td>";
        Html::textarea(['name' => 'softwares_requirements', 'value' => $resource->fields['softwares_requirements']]);
        echo "</td>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>";
        echo __('Furnitures needs', 'resources');;
        echo "</td>";
        echo "<td>";
        Html::textarea(['name' => 'furnitures_needs', 'value' => $resource->fields['furnitures_needs']]);
        echo "</td>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>";
        echo __('Other needs', 'resources');;
        echo "</td>";
        echo "<td>";
        Html::textarea(['name' => 'other_needs', 'value' => $resource->fields['other_needs']]);
        echo "</td>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td class='center' colspan='2'>";
        echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
        echo Html::submit(_sx('button', 'Save'), ['name' => 'updateSpecialRequirement', 'class' => 'btn btn-primary']);
        echo "</td>";
        echo "</tr>";
        echo "</table></div>";

        Html::closeForm();
    }
}

