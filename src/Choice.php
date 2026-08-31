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
use DBConnection;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use MassiveAction;
use Migration;
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
    public static $rightname = 'plugin_resources';

    public const TYPE_CHOICE = [1 => 'Element(s) to be affected', 2 => 'Specials requirements'];

    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
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
        $config = new Config();
        $wizard_need = true;
        if (!$config->fields['needs_tab_access']) {
            $wizard_need = ContractType::checkWizardSetup($item->getField('id'), "use_need_wizard");
        }

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
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Resource::class) {
            $self = new self();
            $self->showItemHelpdesk($item->getField('id'), 0, $withtemplate);
        }
        return true;
    }

    public static function getIcon()
    {
        return "ti ti-package-import";
    }

    /**
     * @param Resource $item
     *
     * @return int
     */
    public static function countForResource(Resource $item)
    {
        $dbu = new DbUtils();
        $restrict = ["plugin_resources_resources_id" => $item->getField('id')];
        $nb = $dbu->countElementsInTable(['glpi_plugin_resources_choices'], $restrict);

        return $nb;
    }

    /**
     * @param $values
     */
    public function addHelpdeskItem($values)
    {
        $this->add([
            'plugin_resources_resources_id' => $values["plugin_resources_resources_id"],
            'plugin_resources_choiceitems_id' => $values["plugin_resources_choiceitems_id"],
            'comment' => '',
        ]);
    }

    /**
     * @param $values
     */
    public function addComment($values)
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
            'comment' => addslashes($comment),
        ]);

    }

    /**
     * @param $values
     */
    public function addNeedComment($values)
    {
        $this->update([
            'id' => $values['id'],
            'comment' => $values['commentneed'],
        ]);
    }

    /**
     * Prepare input datas for adding the item
     *
     * @param array $input datas used to add the item
     *
     * @return array the modified $input array
     **/
    public function prepareInputForAdd($input)
    {
        $choice_item = new ChoiceItem();
        $choice_item->getfromDB($input['plugin_resources_choiceitems_id']);
        $childs = $choice_item->haveChildren();
        if ($childs) {
            Session::addMessageAfterRedirect(
                __("Cannot add a choice that contains children", "resources"),
                true,
                ERROR,
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
     * @param $newitemtype  itemtype of the new item (= $itemtype if empty) (DEFAULT '')
     **@since version 0.84
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
                'FROM' => 'glpi_plugin_resources_choices',
                'WHERE' => [
                    'plugin_resources_resources_id' => $oldid,
                ],
            ];

        foreach ($DB->request($query) as $data) {
            $choice = new self();
            $choice->add([
                'plugin_resources_resources_id' => $newid,
                'plugin_resources_choiceitems_id' => $data["plugin_resources_choiceitems_id"],
                'comment' => $data["comment"],
            ]);
        }
    }

    /**
     * @param $item
     * @param $rand
     */
    public static function showAddCommentForm($item, $rand)
    {
        $items_id = $item['id'];

        $show_js = "function viewAddNeedComment$items_id(){\n";
        $show_js .= Ajax::updateItemJsCode(
            "addneedcomment$items_id$rand",
            PLUGIN_RESOURCES_WEBDIR . "/ajax/addneedcomment.php",
            [
                'id' => $items_id,
                'rand' => $rand,
            ],
            false,
            false,
        );
        $show_js .= "};";
        echo Html::scriptBlock($show_js);

        TemplateRenderer::getInstance()->display('@resources/choice_add_comment_form.html.twig', [
            'items_id' => $items_id,
            'rand'     => $rand,
        ]);

        $hide_js = "function hideAddForm$items_id() {\n";
        $hide_js .= "$('#addcommentneed$items_id$rand').hide();";
        $hide_js .= "$('#viewaccept$items_id').hide();";
        $hide_js .= "}\n";
        echo Html::scriptBlock($hide_js);
    }

    /**
     * @param $item
     * @param $rand
     */
    public static function showModifyCommentForm($item, $rand)
    {

        $items_id = $item['id'];

        $params = [
            'name' => 'commentneed' . $items_id,
            'data' => rawurlencode($item["comment"]),
        ];

        $show_js = "function showComment$items_id () {\n";
        $show_js .= "$('#commentneed$items_id$rand').hide();";
        $show_js .= "$('#viewaccept$items_id$rand').show();";
        $show_js .= Ajax::updateItemJsCode(
            "viewcommentneed$items_id$rand",
            PLUGIN_RESOURCES_WEBDIR . "/ajax/inputtext.php",
            $params,
            false,
            false,
        );
        $show_js .= "}";
        echo Html::scriptBlock($show_js);

        TemplateRenderer::getInstance()->display('@resources/choice_comment_form.html.twig', [
            'items_id' => $items_id,
            'rand'     => $rand,
            'comment'  => nl2br(htmlescape($item["comment"])),
        ]);

        $hide_js = "function hideForm$items_id() {\n";
        $hide_js .= "$('#viewcommentneed$items_id$rand textarea').remove();";
        $hide_js .= "$('#commentneed$items_id$rand').show();";
        $hide_js .= "$('#viewaccept$items_id$rand').hide();";
        $hide_js .= "}\n";
        echo Html::scriptBlock($hide_js);
    }

    /**
     * @param        $plugin_resources_resources_id
     * @param        $exist
     * @param string $withtemplate
     */
    public function showItemHelpdesk($plugin_resources_resources_id, $exist, $withtemplate = '')
    {

        $restrict = ["plugin_resources_resources_id" => $plugin_resources_resources_id];
        $dbu = new DbUtils();
        $choices = $dbu->getAllDataFromTable($this->getTable(), $restrict);
        $config = new Config();
        $configchoice = json_decode($config->fields['view_needs_parts']);
        $configchoice = is_array($configchoice) ? $configchoice : [];

        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);

        if (isset($resource->fields["entities_id"])) {
            $input['entities_id'] = $resource->fields["entities_id"];
        } else {
            $input['entities_id'] = $_SESSION['glpiactive_entity'];
        }
        $input['plugin_resources_contracttypes_id'] = $resource->fields["plugin_resources_contracttypes_id"];
        $input['plugin_resources_profiletypes_id'] = $_SESSION["glpiactiveprofile"]['id'];
        $input['plugin_resources_grouptypes_id'] = $_SESSION["glpigroups"];
        $input['plugin_resources_users_id'] = Session::getLoginUserID();
        $input['plugin_resources_users_id_reel'] = $resource->fields['users_id'];

        $readonly                                   = $resource->getReadonlyFields($input);

        $canedit = $resource->can($plugin_resources_resources_id, UPDATE)
            && $withtemplate < 2
            && $resource->fields["is_leaving"] != 1;
        // Capture GLPI helpers that echo directly, so they can be injected as |raw.
        $capture = static function (callable $renderer): string {
            ob_start();
            $renderer();
            return (string) ob_get_clean();
        };

        if (in_array(1, $configchoice)) {
            $used = [];
            $rows = [];
            foreach ($choices as $choice) {
                $used[] = $choice["plugin_resources_choiceitems_id"];

                $items_comments = Dropdown::getDropdownComments(
                    "glpi_plugin_resources_choiceitems",
                    $choice["plugin_resources_choiceitems_id"],
                );

                $rand = mt_rand();
                $comment_cell = $capture(static function () use ($choice, $rand) {
                    if (!empty($choice["comment"])) {
                        self::showModifyCommentForm($choice, $rand);
                    } else {
                        self::showAddCommentForm($choice, $rand);
                    }
                });

                $delete_form = '';
                if ($canedit) {
                    $delete_form = Html::getSimpleForm(
                        PLUGIN_RESOURCES_WEBDIR . '/front/resource_item.list.php',
                        'deletehelpdeskitem',
                        _x('button', 'Delete permanently'),
                        ['id' => $choice["id"]],
                    );
                }

                $rows[] = [
                    'name' => Dropdown::getDropdownName(
                        "glpi_plugin_resources_choiceitems",
                        $choice["plugin_resources_choiceitems_id"],
                    ),
                    'comments'     => nl2br(htmlescape($items_comments)),
                    'comment_cell' => $comment_cell,
                    'delete_form'  => $delete_form,
                ];
            }

            $choiceitem_dropdown = '';
            $declaration_button  = [];
            if ($canedit) {
                $condition = [];
                if (Session::getCurrentInterface() != 'central') {
                    $condition = ['is_helpdesk_visible' => 1];
                }
                $choiceitem_dropdown = $capture(static fn() => Dropdown::show(
                    ChoiceItem::class,
                    [
                        'name' => 'plugin_resources_choiceitems_id',
                        'entity' => $resource->getEntityID(),
                        'condition' => $condition,
                        'used' => $used,
                        'addicon' => true,
                    ],
                ));

                if (Session::getCurrentInterface() != 'central') {
                    $declaration_button = $exist != 1
                        ? ['name' => 'finish', 'label' => __('Terminate the declaration', 'resources')]
                        : ['name' => 'resend', 'label' => __('Resend the declaration', 'resources')];
                }
            }

            $needs_action = $exist == 1
                ? PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php"
                : PLUGIN_RESOURCES_WEBDIR . "/front/resource_item.list.php";

            TemplateRenderer::getInstance()->display('@resources/choice_helpdesk_needs.html.twig', [
                'form_action'         => $needs_action,
                'canedit'             => $canedit,
                'rows'                => $rows,
                'resources_id'        => $plugin_resources_resources_id,
                'choiceitem_dropdown' => $choiceitem_dropdown,
                'declaration_button'  => $declaration_button,
            ]);
        }

        if (in_array(2, $configchoice)) {
            $textareas = [];
            $requirement_fields = [
                'computer_phone_equipment' => __('Computer and phone equipment needs', 'resources'),
                'softwares_requirements'   => __('Softwares requirements', 'resources'),
                'furnitures_needs'         => __('Furnitures needs', 'resources'),
                'other_needs'              => __('Other needs', 'resources'),
            ];
            foreach ($requirement_fields as $field => $label) {
                $textareas[] = [
                    'label'    => $label,
                    'textarea' => Html::textarea([
                        'name'    => $field,
                        'value'   => $resource->fields[$field],
                        'rows'    => 7,
                        'display' => false,
                    ]),
                ];
            }

            $can_save = !in_array("special_need", $readonly)
                && (!$config->fields['use_module_validation']
                    || !$config->fields['freeze_form_after_validation']
                    || !$resource->fields['valid_resource_information']);

            TemplateRenderer::getInstance()->display('@resources/choice_helpdesk_requirements.html.twig', [
                'form_action'  => PLUGIN_RESOURCES_WEBDIR . "/front/resource_item.list.php",
                'textareas'    => $textareas,
                'resources_id' => $plugin_resources_resources_id,
                'can_save'     => $can_save,
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
                        `plugin_resources_resources_id`   int {$default_key_sign} NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_resources (id)',
                        `plugin_resources_choiceitems_id` int {$default_key_sign} NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_choiceitems (id)',
                        `comment`                         TEXT COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`),
                        KEY `plugin_resources_resources_id` (`plugin_resources_resources_id`),
                        KEY `plugin_resources_choiceitems_id` (`plugin_resources_choiceitems_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
