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
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Html;
use MassiveAction;
use Migration;
use Session;
use Toolbox;

/**
 * Class Choice
 */
class Choice extends CommonDBTM
{
    // Rows of a resource: creating one is an update of the resource
    use ResourceChildRightTrait;
    // No entity of its own: the item rights are those of the resource. With the global right
    // alone, the generic entry points of the core (API, massive actions) created, rewrote or
    // purged the needs of the resources of every entity.
    use ResourceChildItemRightTrait;

    public static string $rightname = 'plugin_resources';

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
     * Add a need to a resource.
     *
     * @param int $resources_id    id of the resource the caller checked: the row is attached
     *                             to that id, never to a value read from the request
     * @param int $choiceitems_id  need
     *
     * @return false|int
     */
    public function addHelpdeskItem(int $resources_id, int $choiceitems_id)
    {
        if ($resources_id <= 0 || $choiceitems_id <= 0) {
            return false;
        }

        return $this->add([
            'plugin_resources_resources_id' => $resources_id,
            'plugin_resources_choiceitems_id' => $choiceitems_id,
            'comment' => '',
        ]);
    }

    /**
     * Append the "others needs" comment to a resource.
     *
     * @param Resource $resource the resource the caller loaded and checked: the comment is
     *                           read from and written to that very row
     * @param string   $comment
     *
     * @return bool
     */
    public function addComment(Resource $resource, string $comment): bool
    {
        if ($resource->isNewItem()) {
            return false;
        }

        if (!empty($resource->fields['comment'])) {
            $comment = $resource->fields['comment'] .
                "\r\n\r" . __('Others needs', 'resources') . "\r\n\r" . $comment;
        }

        return $resource->update([
            'id' => $resource->getID(),
            'comment' => $comment,
        ]);
    }

    /**
     * @param int    $id      id of the need the caller checked
     * @param string $comment
     *
     * @return bool
     */
    public function addNeedComment(int $id, string $comment): bool
    {
        return $this->update([
            'id' => $id,
            'comment' => $comment,
        ]);
    }

    /**
     * Prepare input datas for adding the item
     *
     * @param array $input datas used to add the item
     *
     * @return array|false the modified $input array, false to refuse the need
     **/
    public function prepareInputForAdd($input)
    {
        // A need only exists under a resource and names an existing need item. canCreateItem()
        // answered for the resource named here: both ids are kept as the plain values checked.
        try {
            $resources_id   = Resource::getPostedId($input['plugin_resources_resources_id'] ?? null);
            $choiceitems_id = Resource::getPostedId($input['plugin_resources_choiceitems_id'] ?? null);
        } catch (BadRequestHttpException) {
            return false;
        }
        $choice_item = new ChoiceItem();
        if ($resources_id <= 0
            || $choiceitems_id <= 0
            || !(new Resource())->getFromDB($resources_id)
            || !$choice_item->getFromDB($choiceitems_id)) {
            return false;
        }
        $input['plugin_resources_resources_id']   = $resources_id;
        $input['plugin_resources_choiceitems_id'] = $choiceitems_id;

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

    public function prepareInputForUpdate($input)
    {
        // canUpdateItem() answered for the resource the need is stored under, not for the one
        // an update would move it to
        if (!$this->canMoveToParentResource($input)) {
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
        TemplateRenderer::getInstance()->display('@resources/choice_add_comment_form.html.twig', [
            'items_id' => $item['id'],
            'rand'     => $rand,
            'ajax_url' => PLUGIN_RESOURCES_WEBDIR . '/ajax/addneedcomment.php',
        ]);
    }

    /**
     * @param $item
     * @param $rand
     */
    public static function showModifyCommentForm($item, $rand)
    {

        $items_id = $item['id'];

        TemplateRenderer::getInstance()->display('@resources/choice_comment_form.html.twig', [
            'items_id' => $items_id,
            'rand'     => $rand,
            'comment'  => (string) $item["comment"],
            'ajax_url' => PLUGIN_RESOURCES_WEBDIR . '/ajax/inputtext.php',
            // Posted back to ajax/inputtext.php, which fills the textarea with it.
            'raw_comment' => rawurlencode((string) $item["comment"]),
        ]);
    }

    /**
     * @param        $plugin_resources_resources_id
     * @param        $exist
     * @param string $withtemplate
     */
    public function showItemHelpdesk($plugin_resources_resources_id, $exist, $withtemplate = '')
    {
        $plugin_resources_resources_id = (int) $plugin_resources_resources_id;

        $resource = new Resource();
        // Both callers only test the global right, and canView() loads no record and never
        // calls checkEntity(): on the controller path the id comes straight from $_GET, so the
        // needs of any resource of any entity were readable from that id alone. Authorize the
        // record here, at the sink, so the tab path (self::displayTabContentForItem()) is
        // covered by the same guard, the way Resource::showResourcesToTransfer() does it. The
        // config bypass is the one every controller of the plugin grants to a super
        // administrator holding no plugin right.
        if (!$resource->getFromDB($plugin_resources_resources_id)
            || !Session::haveAccessToEntity(
                $resource->fields['entities_id'],
                $resource->fields['is_recursive'],
            )
            || !($resource->canView() || Session::haveRight(\Config::$rightname, UPDATE))) {
            throw new AccessDeniedHttpException();
        }

        $restrict = ["plugin_resources_resources_id" => $plugin_resources_resources_id];
        $dbu = new DbUtils();
        $choices = $dbu->getAllDataFromTable($this->getTable(), $restrict);
        $config = new Config();
        $configchoice = json_decode($config->fields['view_needs_parts']);
        $configchoice = is_array($configchoice) ? $configchoice : [];

        // The guard above loaded the record, so the entity is always the resource's own.
        $input['entities_id'] = $resource->fields["entities_id"];
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

                $rows[] = [
                    'id'   => $choice["id"],
                    'name' => Dropdown::getDropdownName(
                        "glpi_plugin_resources_choiceitems",
                        $choice["plugin_resources_choiceitems_id"],
                    ),
                    'comments' => (string) $items_comments,
                    'comment'  => (string) $choice["comment"],
                    'rand'     => mt_rand(),
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
                'delete_action'       => PLUGIN_RESOURCES_WEBDIR . '/front/resource_item.list.php',
                // Same endpoints as showModifyCommentForm() / showAddCommentForm().
                'comment_ajax_url'     => PLUGIN_RESOURCES_WEBDIR . '/ajax/inputtext.php',
                'add_comment_ajax_url' => PLUGIN_RESOURCES_WEBDIR . '/ajax/addneedcomment.php',
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
                && !$resource->isFrozenAfterValidation();

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
