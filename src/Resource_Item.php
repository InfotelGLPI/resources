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
use Html;
use Migration;
use Plugin;
use PluginPdfSimplePDF;
use Session;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Resource_Item
 */
class Resource_Item extends CommonDBRelation
{
    public static $rightname = 'plugin_resources';

    public static $itemtype_1 = Resource::class;
    public static $items_id_1 = 'plugin_resources_resources_id';
    public static $take_entity_1 = false;

    public static $itemtype_2 = 'itemtype';
    public static $items_id_2 = 'items_id';
    public static $take_entity_2 = true;
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
     * @param $plugin_resources_resources_id
     * @param $items_id
     * @param $itemtype
     *
     * @return bool
     */
    public function getFromDBbyResourcesAndItem($plugin_resources_resources_id, $items_id, $itemtype)
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
            $this->add([
                'plugin_resources_resources_id' => $options["plugin_resources_resources_id"],
                'items_id' => $options["items_id"],
                'itemtype' => $options["itemtype"],
            ]);

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
    }

    /**
     * @param $ID
     * @param $comment
     */
    public function updateItem($ID, $comment)
    {
        if ($ID > 0) {
            $values["id"] = $ID;
            $values["comment"] = $comment;
            $this->update($values);
        }
    }

    /**
     * @param $ID
     */
    public function deleteItem($ID)
    {
        $this->delete(['id' => $ID]);
    }

    /**
     * @param $plugin_resources_resources_id
     * @param $items_id
     * @param $itemtype
     *
     * @return bool
     */
    public function deleteItemByResourcesAndItem($plugin_resources_resources_id, $items_id, $itemtype)
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

        foreach ($DB->request($query) as $data) {
            $item = new self();
            $item->add([
                'plugin_resources_resources_id' => $newid,
                'itemtype' => $data["itemtype"],
                'items_id' => $data["items_id"],
                'comment' => $data["comment"],
            ]);
        }
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

        $render = TemplateRenderer::getInstance()->render('@resources/resource_item_dropdown.html.twig', [
            'field_name'  => 'item_item',
            'empty_value' => Dropdown::EMPTY_VALUE,
            'options'     => $options,
        ]);

        if (!$display) {
            return $render;
        }

        echo $render;
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
            'types' => Resource::getTypes(true),
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
            'formatters' => [
                'name' => 'raw_html',
                'firstname' => 'raw_html',
                'entities_id' => 'raw_html',
                'locations_id' => 'raw_html',
                'plugin_resources_contracttypes_id' => 'raw_html',
                'plugin_resources_departments_id' => 'raw_html',
                "date_begin" => 'raw_html',
                "date_end" => 'raw_html',
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

        if (!Session::haveRight("plugin_resources", READ)) {
            return false;
        }

        $dbu = new DbUtils();

        $pdf->setColumnsSize(100);
        $pdf->displayTitle('<b>' . _n('Associated item', 'Associated items', 2) . '</b>');

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
                '<b><i>' . __('Type'),
                __('Name'),
                __('Entity'),
                __('Serial Number'),
                __('Inventory number') . '</i></b>',
            );
        } else {
            $pdf->setColumnsSize(25, 31, 22, 22);
            $pdf->displayTitle(
                '<b><i>' . __('Type'),
                __('Name'),
                __('Serial Number'),
                __('Inventory number') . '</i></b>',
            );
        }

        if (!$number) {
            $pdf->displayLine(__('No results found'));
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
                            if (!$items->getFromDB($data["id"])) {
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

                            if (Session::isMultiEntitiesMode()) {
                                $pdf->setColumnsSize(12, 27, 25, 18, 18);
                                $pdf->displayLine(
                                    $items->getTypeName(),
                                    $name,
                                    $entity,
                                    (isset($data["serial"]) ? "" . $data["serial"] . "" : "-"),
                                    (isset($data["otherserial"]) ? "" . $data["otherserial"] . "" : "-"),
                                );
                            } else {
                                $pdf->setColumnsSize(25, 31, 22, 22);
                                $pdf->displayTitle(
                                    $items->getTypeName(),
                                    $name,
                                    (isset($data["serial"]) ? "" . $data["serial"] . "" : "-"),
                                    (isset($data["otherserial"]) ? "" . $data["otherserial"] . "" : "-"),
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
        global $DB, $CFG_GLPI;

        $pdf->setColumnsSize(100);
        $pdf->displayTitle('<b>' . __('Associated Human Resource', 'resources') . '</b>');

        $ID = $item->getField('id');
        $itemtype = get_Class($item);
        $canread = $item->can($ID, READ);
        $canedit = $item->can($ID, UPDATE);

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
            $pdf->displayLine(__('No results found'));
        } else {
            if (Session::isMultiEntitiesMode()) {
                $pdf->setColumnsSize(14, 14, 14, 14, 14, 14, 16);
                $pdf->displayTitle(
                    '<b><i>' . __('Name'),
                    __('Entity'),
                    __('Location'),
                    ContractType::getTypeName(1),
                    Department::getTypeName(1),
                    __('Arrival date', 'resources'),
                    __('Departure date', 'resources') . '</i></b>',
                );
            } else {
                $pdf->setColumnsSize(17, 17, 17, 17, 17, 17);
                $pdf->displayTitle(
                    '<b><i>' . __('Name'),
                    __('Location'),
                    ContractType::getTypeName(1),
                    Department::getTypeName(1),
                    __('Arrival date', 'resources'),
                    __('Departure date', 'resources') . '</i></b>',
                );
            }
            foreach ($iterator as $data) {
                $resourcesID = $data["id"];

                if (Session::isMultiEntitiesMode()) {
                    $pdf->setColumnsSize(14, 14, 14, 14, 14, 14, 16);
                    $pdf->displayLine(
                        $data["name"],
                        Dropdown::getDropdownName("glpi_entities", $data['entities_id']),
                        Dropdown::getDropdownName("glpi_locations", $data["locations_id"]),
                        Dropdown::getDropdownName(
                            "glpi_plugin_resources_contracttypes",
                            $data["plugin_resources_contracttypes_id"],
                        ),
                        Dropdown::getDropdownName(
                            "glpi_plugin_resources_departments",
                            $data["plugin_resources_departments_id"],
                        ),
                        Html::convDate($data["date_begin"]),
                        Html::convDate($data["date_end"]),
                    );
                } else {
                    $pdf->setColumnsSize(17, 17, 17, 17, 17, 17);
                    $pdf->displayLine(
                        $data["name"],
                        Dropdown::getDropdownName("glpi_locations", $data["locations_id"]),
                        Dropdown::getDropdownName(
                            "glpi_plugin_resources_contracttypes",
                            $data["plugin_resources_contracttypes_id"],
                        ),
                        Dropdown::getDropdownName(
                            "glpi_plugin_resources_departments",
                            $data["plugin_resources_departments_id"],
                        ),
                        Html::convDate($data["date_begin"]),
                        Html::convDate($data["date_end"]),
                    );
                }
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
