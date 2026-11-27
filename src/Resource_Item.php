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

use CommonDBRelation;
use CommonDBTM;
use CommonGLPI;
use DBConnection;
use DbUtils;
use Dropdown;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\BadRequestHttpException;
use Html;
use Migration;
use Plugin;
use PluginPdfSimplePDF;
use Profile_User;
use Session;
use Toolbox;

/**
 * Class Resource_Item
 */
class Resource_Item extends CommonDBRelation
{
    // Rows of a resource: creating one is an update of the resource
    use ResourceChildRightTrait;

    public static string $rightname = 'plugin_resources';

    public static ?string $itemtype_1 = Resource::class;
    public static ?string $items_id_1 = 'plugin_resources_resources_id';
    public static bool $take_entity_1 = false;

    public static ?string $itemtype_2 = 'itemtype';
    public static ?string $items_id_2 = 'items_id';
    public static bool $take_entity_2 = true;

    /**
     * Set by server-side code that checked the linked object itself, the next add() of this
     * object then skips the READ test of the session on it: the checklist seeding
     * (Checklistconfig::addResourceChecklist()), which checks the object against the entity of
     * the resource through isLinkableTo() and may run from a cron, without a session entity.
     * Consumed by prepareInputForAdd(), never read from the input.
     */
    public bool $item_checked_by_caller = false;

    /**
     * A link is created by many callers (forms, massive actions, template clone, checklist
     * seeding, change workflow): the type and the linked object are checked here, once for
     * all of them. The type is one the plugin links, the object exists and the session may
     * read it (right and entity), unless the caller checked it (item_checked_by_caller).
     *
     * @param array $input
     *
     * @return array|false
     */
    public function prepareInputForAdd($input)
    {
        $checked_by_caller            = $this->item_checked_by_caller;
        $this->item_checked_by_caller = false;

        if (!is_array($input)) {
            return false;
        }

        $itemtype = $input['itemtype'] ?? '';
        try {
            $items_id = Resource::getPostedId($input['items_id'] ?? null);
        } catch (BadRequestHttpException) {
            $items_id = 0;
        }
        $item = in_array($itemtype, Resource::getTypes(true), true) ? getItemForItemtype($itemtype) : null;
        if ($items_id <= 0
            || !$item
            || !($checked_by_caller ? $item->getFromDB($items_id) : $item->can($items_id, READ))) {
            Session::addMessageAfterRedirect(
                __("You don't have permission to perform this action."),
                false,
                ERROR,
            );
            return false;
        }
        $input['items_id'] = $items_id;

        return parent::prepareInputForAdd($input);
    }

    /**
     * Only the comment of a link changes: the resource and the object it relates were checked
     * when it was created, and are not the request's to change afterwards.
     *
     * @param array $input
     *
     * @return array|false
     */
    public function prepareInputForUpdate($input)
    {
        if (!is_array($input)) {
            return false;
        }
        unset($input['plugin_resources_resources_id'], $input['itemtype'], $input['items_id']);

        return parent::prepareInputForUpdate($input);
    }

    /**
     * Whether an object may be linked to a resource by server-side code (checklist seeding),
     * independently of the session: the type is one the plugin links, the object exists and
     * it belongs to the scope of the entity of the resource, as the dropdowns of that entity
     * offer it (same entity, or a parent one for a recursive object). A user must have a
     * profile in that entity.
     *
     * @param string   $itemtype
     * @param int      $items_id
     * @param Resource $resource the resource, loaded
     *
     * @return bool
     */
    public static function isLinkableTo(string $itemtype, int $items_id, Resource $resource): bool
    {
        if ($items_id <= 0 || !in_array($itemtype, Resource::getTypes(true), true)) {
            return false;
        }
        $item = getItemForItemtype($itemtype);
        if (!$item || !$item->getFromDB($items_id)) {
            return false;
        }

        $entities_id = (int) $resource->fields['entities_id'];
        if ($item instanceof \User) {
            return in_array($entities_id, Profile_User::getUserEntities($items_id, true));
        }
        if (!$item->isEntityAssign()) {
            return true;
        }

        $dbu = new DbUtils();

        return $dbu->countElementsInTable(
            $item->getTable(),
            ['id' => $items_id] + $dbu->getEntitiesRestrictCriteria(
                $item->getTable(),
                '',
                $entities_id,
                $item->maybeRecursive(),
            ),
        ) > 0;
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
     * @param CommonDBTM $item
     */
    public static function cleanForItem(CommonDBTM $item)
    {
        $temp = new self();
        $temp->deleteByCriteria(
            [
                'itemtype' => $item->getType(),
                'items_id' => $item->getField('id'),
            ],
        );
    }

    public static function getIcon()
    {
        return "ti ti-device-laptop";
    }

    /**
     * @param CommonGLPI $item
     * @param int $withtemplate
     *
     * @return array|string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == Resource::class
            && count(Resource::getTypes(false))
        ) {
            if ($_SESSION['glpishow_count_on_tabs']) {
                return self::createTabEntry(
                    _n('Associated item', 'Associated items', 2),
                    self::countForResource($item),
                );
            }
            return self::createTabEntry(_n('Associated item', 'Associated items', 2));
        } elseif (in_array($item->getType(), Resource::getTypes(true))
            && $this->canView() && !$withtemplate
        ) {
            if ($_SESSION['glpishow_count_on_tabs']) {
                return self::createTabEntry(Resource::getTypeName(2), self::countForItem($item));
            }
            return self::createTabEntry(Resource::getTypeName(2));
        }
        return '';
    }


    /**
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Resource::class) {
            self::showForResource($item, $withtemplate);
        } elseif (in_array($item->getType(), Resource::getTypes(true))) {
            self::showForItem($item);
        }
        return true;
    }

    /**
     * @param PluginPdfSimplePDF $pdf
     * @param CommonGLPI $item
     * @param                     $tab
     *
     * @return bool
     */
    public static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab)
    {
        if ($item->getType() == Resource::class) {
            self::pdfForResource($pdf, $item);
        } elseif (in_array($item->getType(), Resource::getTypes(true))) {
            self::PdfFromItems($pdf, $item);
        } else {
            return false;
        }
        return true;
    }

    /**
     * @param resource $item
     *
     * @return int
     */
    public static function countForResource(Resource $item)
    {
        $types = $item->getTypes();
        if (count($types) == 0) {
            return 0;
        }
        $dbu = new DbUtils();
        return $dbu->countElementsInTable(
            'glpi_plugin_resources_resources_items',
            [
                "itemtype" => $types,
                "plugin_resources_resources_id" => $item->getID(),
            ],
        );
    }


    /**
     * @param CommonDBTM $item
     *
     * @return int
     */
    public static function countForItem(CommonDBTM $item)
    {
        $dbu = new DbUtils();
        return $dbu->countElementsInTable(
            'glpi_plugin_resources_resources_items',
            [
                "itemtype" => $item->getType(),
                "items_id" => $item->getID(),
            ],
        );
    }

    /**
     * Callers pass the ids they checked: as integers, the criteria cannot designate another
     * row, or several.
     *
     * @param int    $plugin_resources_resources_id
     * @param int    $items_id
     * @param string $itemtype
     *
     * @return bool
     */
    public function getFromDBbyResourcesAndItem(int $plugin_resources_resources_id, int $items_id, string $itemtype)
    {
        // Use the query builder (getFromDBByCrit) so the itemtype/items_id parameters are
        // bound instead of concatenated into raw SQL. Returns true only on a single match,
        // preserving the previous "numrows != 1 -> false" semantics.
        return $this->getFromDBByCrit([
            'plugin_resources_resources_id' => $plugin_resources_resources_id,
            'itemtype'                      => $itemtype,
            'items_id'                      => $items_id,
        ]);
    }

    /**
     * @param $options
     *
     * @return bool
     */
    public function addItem($options)
    {
        if (!isset($options["plugin_resources_resources_id"])
            || $options["plugin_resources_resources_id"] <= 0
        ) {
            return false;
        } else {
            // prepareInputForAdd() refuses an object the session may not read: nothing is
            // linked then, and the location of a user refused is not touched either.
            if (!$this->add([
                'plugin_resources_resources_id' => $options["plugin_resources_resources_id"],
                'items_id' => $options["items_id"],
                'itemtype' => $options["itemtype"],
            ])) {
                return false;
            }

            if ($options["itemtype"] == 'User') {
                $values["id"] = $options["items_id"];
                $item = new Resource();
                $item->getFromDB($options["plugin_resources_resources_id"]);

                if (isset($item->fields["locations_id"])) {
                    $values["locations_id"] = $item->fields["locations_id"];
                } else {
                    $values["locations_id"] = 0;
                }
                $this->updateLocation($values, $options["itemtype"]);
            }
        }

        return true;
    }

    /**
     * @param int    $ID      id of the row the caller checked
     * @param string $comment
     */
    public function updateItem(int $ID, string $comment)
    {
        if ($ID > 0) {
            $values["id"] = $ID;
            $values["comment"] = $comment;
            $this->update($values);
        }
    }

    /**
     * @param int $ID id of the row the caller checked
     */
    public function deleteItem(int $ID)
    {
        $this->delete(['id' => $ID]);
    }

    /**
     * @param int    $plugin_resources_resources_id id of the resource the caller checked
     * @param int    $items_id
     * @param string $itemtype
     *
     * @return bool
     */
    public function deleteItemByResourcesAndItem(int $plugin_resources_resources_id, int $items_id, string $itemtype)
    {
        if ($this->getFromDBbyResourcesAndItem($plugin_resources_resources_id, $items_id, $itemtype)) {
            return $this->delete(['id' => $this->fields["id"]]);
        }

        return false;
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

        $resource = new Resource();
        if (!$resource->getFromDB((int) $newid)) {
            return;
        }

        $query
            = [
                'SELECT' => [
                    '*',
                ],
                'FROM' => 'glpi_plugin_resources_resources_items',
                'WHERE' => [
                    'plugin_resources_resources_id' => $oldid,
                ],
            ];

        $skipped = 0;
        foreach ($DB->request($query) as $data) {
            // The template was checked by Resource::prepareInputForAdd(): its links are copied
            // when the object fits the new resource (type linked by the plugin, object of the
            // scope of its entity), the way the checklist seeding checks them, not on the
            // rights of the creator on each type. A profile declaring arrivals usually holds
            // none on the hardware, nor on ComputerType and PhoneType (the "dropdown" right),
            // the types a template uses to express a need.
            if (!self::isLinkableTo((string) $data["itemtype"], (int) $data["items_id"], $resource)) {
                $skipped++;
                continue;
            }
            $item = new self();
            $item->item_checked_by_caller = true;
            $item->add([
                'plugin_resources_resources_id' => $newid,
                'itemtype' => $data["itemtype"],
                'items_id' => $data["items_id"],
                'comment' => $data["comment"],
            ]);
        }

        if ($skipped > 0) {
            self::addTemplateItemsSkippedMessage();
        }
    }

    /**
     * Report, once, that objects of a template were not copied to a resource created from it,
     * being out of the scope of its entity (cloneItem(), Task::cloneItem()).
     *
     * @return void
     */
    public static function addTemplateItemsSkippedMessage(): void
    {
        // check_once: the links and the task items of the same template report it once
        Session::addMessageAfterRedirect(
            __('Some items linked to the template are out of the entity of the resource: they were not copied', 'resources'),
            true,
            WARNING,
        );
    }

    /**
     * @param $values
     * @param $itemtype
     */
    public function updateLocation($values, $itemtype)
    {
        global $DB;

        $id = 0;
        if ($itemtype == Resource::class) {
            $restrict = [
                "itemtype" => 'User',
                "plugin_resources_resources_id" => $values["id"],
            ];
            $dbu = new DbUtils();
            $resources = $dbu->getAllDataFromTable($this->getTable(), $restrict);

            if (!empty($resources)) {
                foreach ($resources as $resource) {
                    $id = $resource["items_id"];
                }
            }
        } elseif ($itemtype == "User") {
            $id = $values["id"];
        }
        if (isset($id) && $id > 0 && isset($values["locations_id"]) && $values["locations_id"] > 0) {
            $item = new \User();
            // The write lands on a core user: linking it to a resource, or changing the location
            // of that resource, takes no right on the user. Only a session allowed to update
            // that very user may move it.
            if (!$item->can((int) $id, UPDATE)) {
                return;
            }
            $update["id"] = $id;
            $update["locations_id"] = $values["locations_id"];
            if ($itemtype == Resource::class) {
                $update["_UpdateFromResource_"] = 1;
            }
            if ($item->update($update)) {
                Session::addMessageAfterRedirect(
                    __("Modification of the associated user's location", "resources"),
                    true,
                );
            }
        }
    }

    /**
     * @param $ID
     *
     * @return int
     */
    public function searchAssociatedBadge($ID)
    {
        if (Plugin::isPluginActive("badges")) {
            //search is the user have a linked badge
            $restrict = [
                "itemtype" => 'User',
                "plugin_resources_resources_id" => $ID,
            ];
            $dbu = new DbUtils();
            $resources = $dbu->getAllDataFromTable($this->getTable(), $restrict);

            if (!empty($resources)) {
                foreach ($resources as $resource) {
                    $restrictbadge = ["users_id" => $resource["items_id"]];
                    $badges = $dbu->getAllDataFromTable("glpi_plugin_badges_badges", $restrictbadge);
                    //if the user have a linked badge, send email for his badge
                    if (!empty($badges)) {
                        foreach ($badges as $badge) {
                            return $badge["id"];
                        }
                    } else {
                        return 0;
                    }
                }
            }
        }
    }

    /**
     * Dropdown of the items linked to a resource, so one of them can be attached to a task.
     *
     * The option value pairs the item id with its itemtype, which is what Task_Item reads back.
     *
     * @param int   $ID      Resource id
     * @param array $used    Item ids already attached, excluded from the list
     * @param bool  $display Echo the dropdown, or return it as a string
     *
     * @return string|void
     */
    public function dropdownItems($ID, $used = [], $display = true)
    {
        global $DB;

        $restrict = ["plugin_resources_resources_id" => $ID];
        $dbu = new DbUtils();
        $resources = $dbu->getAllDataFromTable($this->getTable(), $restrict);

        $self_table = $this->getTable();
        $options    = [];

        foreach ($resources as $resource) {
            $table = $dbu->getTableForItemType($resource["itemtype"]);
            if (empty($table)) {
                // Stale row pointing at an itemtype that no longer exists: no table to join on.
                continue;
            }

            $where = [
                "$self_table.itemtype" => $resource["itemtype"],
                "$self_table.items_id" => (int) $resource["items_id"],
            ];
            if (count($used)) {
                $where[] = ['NOT' => ["$table.id" => array_merge([0], array_map('intval', $used))]];
            }
            $iterator = $DB->request([
                'SELECT'     => ["$table.*"],
                'FROM'       => $self_table,
                'INNER JOIN' => [
                    $table => [
                        'ON' => [
                            $table      => 'id',
                            $self_table => 'items_id',
                        ],
                    ],
                ],
                'WHERE'      => $where,
                'ORDER'      => "$table.name",
            ]);

            $data = $iterator->current();
            if ($data === null) {
                continue;
            }

            $name = $data["name"];
            if ($resource["itemtype"] == 'User') {
                $name = $dbu->getUserName($data["id"]);
            }
            if (empty($data["name"]) || $_SESSION["glpiis_ids_visible"] == 1) {
                $name = sprintf(__('%1$s (%2$s)'), $name, $data["id"]);
            }

            $options[] = [
                'value' => $data["id"] . "," . $resource["itemtype"],
                'label' => $name,
            ];
        }

        $params = [
            'field_name'  => 'item_item',
            'empty_value' => Dropdown::EMPTY_VALUE,
            'options'     => $options,
        ];

        if (!$display) {
            return TemplateRenderer::getInstance()->render('@resources/resource_item_dropdown.html.twig', $params);
        }

        TemplateRenderer::getInstance()->display('@resources/resource_item_dropdown.html.twig', $params);
    }

    /**
     * @since version 0.84
     **/
    public function getForbiddenStandardMassiveAction()
    {
        $forbidden = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }


    /**
     * Print the HTML array for Items linked to a resource
     *
     * @param resource $resource
     * @param int $withtemplate
     *
     * @return bool
     **/
    public static function showForResource(Resource $resource, int $withtemplate = 0): bool
    {
        $instID = $resource->getID();

        if (!$resource->can($instID, READ)) {
            return false;
        }
        $canedit = $resource->canEdit($instID);
        $rand = mt_rand();

        $types_iterator = self::getDistinctTypes($instID);

        $totalnb = 0;
        $entity_names_cache = [];
        $entries = [];
        $used = [];

        foreach ($types_iterator as $row) {
            $itemtype = $row['itemtype'];
            if (!($item = getItemForItemtype($itemtype)) || !$item::canView()) {
                continue;
            }

            $itemtype_name = $item::getTypeName(1);
            $iterator = self::getTypeItems($instID, $itemtype);
            $nb = count($iterator);

            foreach ($iterator as $data) {
                $name = $data[$itemtype::getNameField()];
                if (
                    $_SESSION["glpiis_ids_visible"]
                    || empty($data[$itemtype::getNameField()])
                ) {
                    $name = sprintf(__('%1$s (%2$s)'), $name, $data["id"]);
                }
                $link = $item::getFormURLWithID($data['id']);

                if ($itemtype == 'User') {
                    $name = formatUserName(
                        $data["id"],
                        $data["name"],
                        $data["realname"],
                        $data["firstname"],
                    );
                }
                $namelink = "<a href=\"" . htmlescape($link) . "\">" . htmlescape($name) . "</a>";

                if ($itemtype == 'User') {
                    $data['entity'] = 0;
                }
                if (!isset($entity_names_cache[$data['entity']])) {
                    if ($itemtype != 'User') {
                        $entity_names_cache[$data['entity']] = Dropdown::getDropdownName("glpi_entities", $data['entity']);
                    } else {
                        $entity_names_cache[$data['entity']] = "";
                    }
                }

                $entries[] = [
                    'itemtype' => self::class,
                    'id' => $data['linkid'],
                    'row_class' => (isset($data['is_deleted']) && $data['is_deleted']) ? 'table-deleted' : '',
                    'type' => $itemtype_name,
                    'name' => $namelink,
                    'entity' => $entity_names_cache[$data['entity']],
                    'serial' => $data["serial"] ?? '-',
                    'otherserial' => $data["otherserial"] ?? '-',
                ];
                $used[$itemtype][$data['id']] = $data['id'];
            }
            $totalnb += $nb;
        }

        $columns = [
            'type' => _n('Type', 'Types', 1),
        ];
        if (Session::isMultiEntitiesMode()) {
            $columns['entity'] = Entity::getTypeName(1);
        }
        $columns += [
            'name' => __('Name'),
            'serial' => __('Serial number'),
            'otherserial' => __('Inventory number'),
        ];
        $formatters = [
            'name' => 'raw_html',
        ];
        $footers = [];
        if ($totalnb > 0) {
            $footers = [
                [sprintf(__('%1$s = %2$s'), __('Total'), $totalnb)],
            ];
        }

        TemplateRenderer::getInstance()->display('@resources/item_resource.html.twig', [
            'item' => $resource,
            'can_edit' => $canedit && $withtemplate != 2,
            'withtemplate' => $withtemplate,
            'used' => $used,
            // Filtered list: an itemtype the session cannot view is refused by the additem
            // branch of front/resource.form.php, so do not offer it here.
            'types' => Resource::getTypes(),
            'datatable_params' => [
                'is_tab' => true,
                'nofilter' => true,
                'nosort' => true,
                'columns' => $columns,
                'formatters' => $formatters,
                'entries' => $entries,
                'footers' => $footers,
                'total_number' => count($entries),
                'filtered_number' => count($entries),
                'showmassiveactions' => $canedit,
                'massiveactionparams' => [
                    'container' => 'massiveactioncontainer' . $rand,
                    'itemtype' => self::class,
                ],
            ],
        ]);

        return true;
    }


    private static function showForItem(CommonDBTM $item): bool
    {
        global $DB;

        $used = $entries = [];


        $criteria = [
            'SELECT' => [
                'glpi_plugin_resources_resources_items.id AS assocID',
                'glpi_entities.id AS entity',
                'glpi_plugin_resources_resources.name AS assocName',
                'glpi_plugin_resources_resources.*',
            ],
            'FROM' => 'glpi_plugin_resources_resources_items',
            'LEFT JOIN' => [
                'glpi_plugin_resources_resources' => [
                    'ON' => [
                        'glpi_plugin_resources_resources_items' => 'plugin_resources_resources_id',
                        'glpi_plugin_resources_resources' => 'id',
                    ],
                ],
                'glpi_entities' => [
                    'ON' => [
                        'glpi_plugin_resources_resources' => 'entities_id',
                        'glpi_entities' => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'glpi_plugin_resources_resources_items.items_id' => $item->getID(),
                'glpi_plugin_resources_resources_items.itemtype' => $item->getType(),
            ],
            'ORDERBY' => 'assocName',
        ];
        $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
            'glpi_plugin_resources_resources',
            '',
            '',
            true,
        );

        $iterator_list = $DB->request($criteria);
        $rand = mt_rand();

        foreach ($iterator_list as $value) {
            $used[] = $value['id'];
            $resource = new Resource();

            $result = $resource->getFromDB($value['id']);

            if ($result === false || !$resource->can($resource->getID(), READ)) {
                continue;
            }

            $entries[] = [
                'itemtype' => self::class,
                'id' => $value['assocID'],
                'name' => $resource->getLink(),
                'firstname' => $resource->fields['firstname'],
                'entities_id' => Dropdown::getDropdownName("glpi_entities", $resource->fields['entities_id']),
                'locations_id' => Dropdown::getDropdownName("glpi_locations", $resource->fields["locations_id"]),
                'plugin_resources_contracttypes_id' => Dropdown::getDropdownName(
                    "glpi_plugin_resources_contracttypes",
                    $resource->fields["plugin_resources_contracttypes_id"],
                ),
                'plugin_resources_departments_id' => Dropdown::getDropdownName(
                    "glpi_plugin_resources_departments",
                    $resource->fields["plugin_resources_departments_id"],
                ),
                'date_begin' => Html::convDate($resource->fields['date_begin']),
                'date_end' => Html::convDate($resource->fields['date_end']),
            ];
        }

        $cols = [
            'columns' => [
                "name" => __('Surname'),
                "firstname" =>  __('First name'),
                "entities_id" => __s('Entity'),
                "locations_id" => __s('Location'),
                "plugin_resources_contracttypes_id" => ContractType::getTypeName(1),
                "plugin_resources_departments_id" => Department::getTypeName(1),
                "date_begin" => __('Arrival date', 'resources'),
                "date_end" => __('Departure date', 'resources'),
            ],
            // raw_html turns Twig escaping off (templates/components/datatable.html.twig).
            // Only 'name' carries deliberate markup (getLink()); every other column is a
            // plain database value — firstname is user input and the dropdown labels are
            // stored verbatim — so they go through the default escaping formatter.
            'formatters' => [
                'name' => 'raw_html',
            ],
        ];


        $footers = [];

        TemplateRenderer::getInstance()->display('@resources/item_resource.html.twig', [
            'item' => $item,
            'can_edit' => $item->canEdit($item->getID()),
            'used' => $used,
            'datatable_params' => [
                'is_tab' => true,
                'nofilter' => true,
                'nosort' => true,
                'columns' => $cols['columns'],
                'formatters' => $cols['formatters'],
                'entries' => $entries,
                'footers' => $footers,
                'total_number' => count($entries),
                'filtered_number' => count($entries),
                'showmassiveactions' => $item->canEdit($item->getID()),
                'massiveactionparams' => [
                    'container' => 'massiveactioncontainer' . $rand,
                    'itemtype' => self::class,
                ],
            ],
        ]);

        //        if ($item->getType() == "User") {
        //            $Employee = new Employee();
        //            $Employee->showEmployeeForm($resourceID, $ID, 0);
        //        }

        return true;
    }


    /**
     * Show for PDF an resources - asociated devices
     *
     * @param $pdf object for the output
     * @param $ID of the resources
     */
    public static function pdfForResource(PluginPdfSimplePDF $pdf, Resource $appli)
    {
        global $DB;

        $ID = $appli->fields['id'];

        if (!$appli->can($ID, READ)) {
            return false;
        }

        if (!Session::haveRight(Resource::$rightname, READ)) {
            return false;
        }

        $dbu = new DbUtils();

        // The pdf plugin writes every argument as HTML (TCPDF writeHTMLCell): only the static
        // markup below is kept, every label and stored value is escaped.
        $pdf->setColumnsSize(100);
        $pdf->displayTitle('<b>' . htmlescape(_n('Associated item', 'Associated items', 2)) . '</b>');

        $iterator = $DB->request([
            'SELECT'   => 'itemtype',
            'DISTINCT' => true,
            'FROM'     => 'glpi_plugin_resources_resources_items',
            'WHERE'    => ['plugin_resources_resources_id' => (int) $ID],
            'ORDER'    => 'itemtype',
        ]);
        $number = count($iterator);

        if (Session::isMultiEntitiesMode()) {
            $pdf->setColumnsSize(12, 27, 25, 18, 18);
            $pdf->displayTitle(
                '<b><i>' . htmlescape(__('Type')),
                htmlescape(__('Name')),
                htmlescape(__('Entity')),
                htmlescape(__('Serial Number')),
                htmlescape(__('Inventory number')) . '</i></b>',
            );
        } else {
            $pdf->setColumnsSize(25, 31, 22, 22);
            $pdf->displayTitle(
                '<b><i>' . htmlescape(__('Type')),
                htmlescape(__('Name')),
                htmlescape(__('Serial Number')),
                htmlescape(__('Inventory number')) . '</i></b>',
            );
        }

        if (!$number) {
            $pdf->displayLine(htmlescape(__('No results found')));
        } else {
            foreach ($iterator as $row) {
                $type = $row["itemtype"];
                if (!($item = $dbu->getItemForItemtype($type))) {
                    continue;
                }
                if ($item->canView()) {
                    $column = "name";
                    $table = $dbu->getTableForItemType($type);
                    $items = new $type();

                    $ri = 'glpi_plugin_resources_resources_items';
                    $where = [
                        "$ri.itemtype"                      => $type,
                        "$ri.plugin_resources_resources_id" => (int) $ID,
                    ];
                    if ($type != 'User') {
                        $entities_crit = $dbu->getEntitiesRestrictCriteria($table, '', '', $items->maybeRecursive());
                        if (count($entities_crit)) {
                            $where[] = $entities_crit;
                        }
                    }

                    if ($items->maybeTemplate()) {
                        $where["$table.is_template"] = 0;
                    }

                    $iterator_linked = $DB->request([
                        'SELECT'     => [
                            "$table.*",
                            'glpi_entities.id AS entity',
                        ],
                        'FROM'       => $ri,
                        'INNER JOIN' => [
                            $table => [
                                'ON' => [
                                    $table => 'id',
                                    $ri    => 'items_id',
                                ],
                            ],
                        ],
                        'LEFT JOIN'  => [
                            'glpi_entities' => [
                                'ON' => [
                                    'glpi_entities' => 'id',
                                    $table          => 'entities_id',
                                ],
                            ],
                        ],
                        'WHERE'      => $where,
                        'ORDER'      => ['glpi_entities.completename', "$table.$column"],
                    ]);

                    if (count($iterator_linked)) {
                        foreach ($iterator_linked as $data) {
                            // The query above restricts the entity of most types, not the
                            // one of the users: each item printed has to be readable, as on
                            // the tab.
                            if (!$items->can($data["id"], READ)) {
                                continue;
                            }
                            $items_id_display = "";

                            if ($_SESSION["glpiis_ids_visible"] || empty($data["name"])) {
                                $items_id_display = " (" . $data["id"] . ")";
                            }
                            if ($type == 'User') {
                                $name = getUserName($data["id"]) . $items_id_display;
                            } else {
                                $name = $data["name"] . $items_id_display;
                            }

                            if ($type != 'User') {
                                $entity = Dropdown::getDropdownName("glpi_entities", $data['entity']);
                            } else {
                                $entity = "-";
                            }

                            $serial      = isset($data["serial"]) ? (string) $data["serial"] : "-";
                            $otherserial = isset($data["otherserial"]) ? (string) $data["otherserial"] : "-";
                            if (Session::isMultiEntitiesMode()) {
                                $pdf->setColumnsSize(12, 27, 25, 18, 18);
                                $pdf->displayLine(
                                    htmlescape($items->getTypeName()),
                                    htmlescape($name),
                                    htmlescape($entity),
                                    htmlescape($serial),
                                    htmlescape($otherserial),
                                );
                            } else {
                                // A data row, printed as such (it was printed as a title)
                                $pdf->setColumnsSize(25, 31, 22, 22);
                                $pdf->displayLine(
                                    htmlescape($items->getTypeName()),
                                    htmlescape($name),
                                    htmlescape($serial),
                                    htmlescape($otherserial),
                                );
                            }
                        } // Each device
                    }
                } // type right
            } // each type
        } // numrows type
    }

    /**
     * show for PDF the resources associated with a device
     *
     * @param $ID of the device
     * @param $itemtype : type of the device
     *
     */
    public static function PdfFromItems($pdf, $item)
    {
        global $DB;

        // The resources listed are HR records: the PDF of an item prints them only for a
        // profile allowed to read resources, as the tab of that item requires.
        if (!Session::haveRight(Resource::$rightname, READ)) {
            return;
        }

        // The pdf plugin writes every argument as HTML (TCPDF writeHTMLCell): only the static
        // markup below is kept, every label and stored value is escaped.
        $pdf->setColumnsSize(100);
        $pdf->displayTitle('<b>' . htmlescape(__('Associated Human Resource', 'resources')) . '</b>');

        $ID = $item->getField('id');
        $itemtype = get_Class($item);

        $Resource = new Resource();
        $dbu = new DbUtils();

        $where = [
            'glpi_plugin_resources_resources_items.items_id'  => (int) $ID,
            'glpi_plugin_resources_resources_items.itemtype'  => $itemtype,
        ];
        $entities_crit = $dbu->getEntitiesRestrictCriteria(
            'glpi_plugin_resources_resources',
            '',
            '',
            $Resource->maybeRecursive(),
        );
        if (count($entities_crit)) {
            $where[] = $entities_crit;
        }

        $iterator = $DB->request([
            'SELECT'     => 'glpi_plugin_resources_resources.*',
            'FROM'       => 'glpi_plugin_resources_resources_items',
            'INNER JOIN' => [
                'glpi_plugin_resources_resources' => [
                    'ON' => [
                        'glpi_plugin_resources_resources_items' => 'plugin_resources_resources_id',
                        'glpi_plugin_resources_resources'       => 'id',
                    ],
                ],
            ],
            'LEFT JOIN'  => [
                'glpi_entities' => [
                    'ON' => [
                        'glpi_entities'                   => 'id',
                        'glpi_plugin_resources_resources' => 'entities_id',
                    ],
                ],
            ],
            'WHERE'      => $where,
        ]);
        $number = count($iterator);

        if (!$number) {
            $pdf->displayLine(htmlescape(__('No results found')));
        } else {
            if (Session::isMultiEntitiesMode()) {
                $pdf->setColumnsSize(14, 14, 14, 14, 14, 14, 16);
                $pdf->displayTitle(
                    '<b><i>' . htmlescape(__('Name')),
                    htmlescape(__('Entity')),
                    htmlescape(__('Location')),
                    htmlescape(ContractType::getTypeName(1)),
                    htmlescape(Department::getTypeName(1)),
                    htmlescape(__('Arrival date', 'resources')),
                    htmlescape(__('Departure date', 'resources')) . '</i></b>',
                );
            } else {
                $pdf->setColumnsSize(17, 17, 17, 17, 17, 17);
                $pdf->displayTitle(
                    '<b><i>' . htmlescape(__('Name')),
                    htmlescape(__('Location')),
                    htmlescape(ContractType::getTypeName(1)),
                    htmlescape(Department::getTypeName(1)),
                    htmlescape(__('Arrival date', 'resources')),
                    htmlescape(__('Departure date', 'resources')) . '</i></b>',
                );
            }
            foreach ($iterator as $data) {
                $values = [
                    (string) $data["name"],
                    Dropdown::getDropdownName("glpi_locations", $data["locations_id"]),
                    Dropdown::getDropdownName(
                        "glpi_plugin_resources_contracttypes",
                        $data["plugin_resources_contracttypes_id"],
                    ),
                    Dropdown::getDropdownName(
                        "glpi_plugin_resources_departments",
                        $data["plugin_resources_departments_id"],
                    ),
                    (string) Html::convDate($data["date_begin"]),
                    (string) Html::convDate($data["date_end"]),
                ];

                if (Session::isMultiEntitiesMode()) {
                    // The entity column comes second
                    array_splice($values, 1, 0, [Dropdown::getDropdownName("glpi_entities", $data['entities_id'])]);
                    $pdf->setColumnsSize(14, 14, 14, 14, 14, 14, 16);
                } else {
                    $pdf->setColumnsSize(17, 17, 17, 17, 17, 17);
                }
                $pdf->displayLine(...array_map(static fn(string $value): string => htmlescape($value), $values));
            }
        }
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        unset($tab[1]);
        $tab[] = [
            'id' => '2',
            'table' => Resource::getTable(),
            'field' => 'name',
            'name' => Resource::getTypeName(1),
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '5',
            'table' => $this->getTable(),
            'field' => 'items_id',
            'name' => __('Items id'),
            'datatype' => 'text',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '6',
            'table' => $this->getTable(),
            'field' => 'itemtype',
            'name' => __('Itemtype'),
            'datatype' => 'text',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '7',
            'table' => $this->getTable(),
            'field' => 'plugin_resources_resources_id',
            'name' => __('plugin_resources_resources_id'),
            'datatype' => 'text',
            'massiveaction' => false,
        ];
        return $tab;
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();
        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                        `id`           int {$default_key_sign} NOT NULL auto_increment,
                        `plugin_resources_resources_id` int {$default_key_sign}                            NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_resources (id)',
                        `items_id`                      int {$default_key_sign}                            NOT NULL DEFAULT '0' COMMENT 'RELATION to various table, according to itemtype (id)',
                        `itemtype`                      varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'see .class.php file',
                        `comment`                       TEXT COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unicity` (`plugin_resources_resources_id`, `itemtype`, `items_id`),
                        KEY `FK_device` (`items_id`, `itemtype`),
                        KEY `item` (`itemtype`, `items_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
