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
use DbUtils;
use Dropdown;
use GlpiPlugin\Badges\Badge;
use Html;
use Session;
use Toolbox;
use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ResourceCard
 */
class ResourceCard extends CommonDBTM
{
    public static $rightname = 'plugin_resources';

    public static $types = ['Computer', 'Peripheral', 'Phone', 'Printer', 'PluginSimcardSimcard', Badge::class];

    /**
     * @param $ID
     */
    public static function resourceCard($ID)
    {
        global $CFG_GLPI;

        $resource = new Resource();
        $resource->getFromDB($ID);

        $resource_item = new Resource_Item();
        $data = $resource_item->find([
            'itemtype' => 'User',
            'plugin_resources_resources_id' => $ID,
        ], [], [1]);

        $data = reset($data);

        $users_id = $data['items_id'] ?? 0;

        $user     = new \User();
        $has_user = $users_id > 0 && $user->getFromDB($users_id);

        TemplateRenderer::getInstance()->display('@resources/resourcecard.html.twig', [
            'has_user'  => $has_user,
            'vcard_url' => $has_user
                ? $CFG_GLPI["root_doc"] . "/front/user.form.php?getvcard=1&id=" . $user->getID()
                : '',
            'card_url'  => PLUGIN_RESOURCES_WEBDIR . "/front/resource.card.form.php",
            'identity'  => self::getIdentityData($resource, $has_user ? $user : false),
            'items'     => $has_user ? self::getItemsData($user) : [],
        ]);
    }

    /**
     * Build the "about" pane of the card.
     *
     * Values are returned raw: the template escapes them, so nothing must be pre-encoded here.
     *
     * @param Resource    $resource
     * @param \User|false $user     Linked user, false when the resource has none
     *
     * @return array
     */
    private static function getIdentityData(Resource $resource, $user = false): array
    {
        $dbu = new DbUtils();

        $identity = [
            'has_user'            => $user !== false,
            'emails'              => [],
            'arrival_date'        => Html::convDate($resource->fields["date_begin"]),
            'habilitations'       => [],
            'habilitations_title' => '',
        ];

        if ($user === false) {
            $identity['picture_url'] = User::getThumbnailURLForPicture('');
            $identity['title']       = sprintf(
                __('%1$s %2$s'),
                (string) $resource->fields['firstname'],
                (string) $resource->fields['name'],
            );
            $identity['subtitle'] = '';
            $identity['infos']    = [
                [
                    'label' => __('Location'),
                    'value' => Dropdown::getDropdownName(
                        $dbu->getTableForItemType('Location'),
                        $resource->fields['locations_id'],
                    ),
                ],
            ];
        } else {
            $identity['picture_url'] = Resource::getThumbnailURLForPicture($resource->fields['picture']);
            $identity['title']       = $dbu->getUsername($user->getID());
            $identity['subtitle']    = Dropdown::getDropdownName('glpi_usertitles', $user->getField('usertitles_id'));
            $identity['infos']       = [
                ['label' => __('Phone'), 'value' => $user->fields['phone']],
                ['label' => __('Phone 2'), 'value' => $user->fields['phone2']],
                ['label' => __('Mobile phone'), 'value' => $user->fields['mobile']],
                [
                    'label' => __('Location'),
                    'value' => Dropdown::getDropdownName(
                        $dbu->getTableForItemType('Location'),
                        $user->fields['locations_id'],
                    ),
                ],
            ];
            // getAllEmails() reads $this->fields['id']: the legacy argument was silently dropped.
            $identity['emails'] = array_values($user->getAllEmails());
        }

        if (ResourceHabilitation::canView() && ($count = ResourceHabilitation::countForResource($resource))) {
            $resourcehabilitation = new ResourceHabilitation();
            $habilitations = $resourcehabilitation->find([
                'plugin_resources_resources_id' => $resource->getField('id'),
            ]);

            $identity['habilitations_title'] = ResourceHabilitation::getTypeName($count);
            foreach ($habilitations as $habilitation) {
                $identity['habilitations'][] = Dropdown::getDropdownName(
                    'glpi_plugin_resources_habilitations',
                    $habilitation['plugin_resources_habilitations_id'],
                );
            }
        }

        return $identity;
    }

    /**
     * Build the "inventory" pane of the card: the assignable items of the linked user,
     * grouped by itemtype.
     *
     * @param \User $user
     *
     * @return array
     */
    private static function getItemsData($user): array
    {
        global $CFG_GLPI, $DB;

        $dbu   = new DbUtils();
        $ID    = $user->getID();
        $items = [];

        foreach ($CFG_GLPI['assignable_types'] as $itemtype) {
            if (!($item = $dbu->getItemForItemtype($itemtype)) || !in_array($itemtype, self::$types)) {
                continue;
            }

            $itemtable = $dbu->getTableForItemType($itemtype);
            $where     = ['users_id' => (int) $ID];

            if ($item->maybeTemplate()) {
                $where['is_template'] = 0;
            }
            if ($item->maybeDeleted()) {
                $where['is_deleted'] = 0;
            }
            $entities_crit = $dbu->getEntitiesRestrictCriteria($itemtable, '', $item->maybeRecursive());
            if (count($entities_crit)) {
                $where[] = $entities_crit;
            }

            $iterator = $DB->request([
                'FROM'  => $itemtable,
                'WHERE' => $where,
            ]);

            if (count($iterator) === 0) {
                continue;
            }

            $rows = [];
            foreach ($iterator as $values) {
                $rows[] = self::getItemRowData($item, $itemtype, $values);
            }

            $items[] = [
                'type_name' => $item->getTypeName(count($rows)),
                'rows'      => $rows,
            ];
        }

        return $items;
    }

    /**
     * Build one inventory row: thumbnail, link and secondary information.
     *
     * @param CommonDBTM $item     Instance used to check the read right on the row
     * @param string     $itemtype
     * @param array      $values   Raw database row
     *
     * @return array
     */
    private static function getItemRowData(CommonDBTM $item, string $itemtype, array $values): array
    {
        $label = (string) $values["name"];
        $url   = '';

        if ($item->can($values["id"], READ) && Session::getCurrentInterface() == 'central') {
            if ($_SESSION["glpiis_ids_visible"] || $label === '') {
                $label = sprintf(__('%1$s (%2$s)'), $label, $values["id"]);
            }
            $url = Toolbox::getItemTypeFormURL($itemtype) . "?id=" . $values["id"];
        }

        // The gallery pictures are shipped under public/, which is the web root of the plugin:
        // the filesystem test must walk through it, only the URL may skip it.
        $picture = PLUGIN_RESOURCES_DIR . "/public/pics/gallery/" . $itemtype . ".jpg";
        $picture = file_exists($picture)
            ? PLUGIN_RESOURCES_WEBDIR . "/pics/gallery/" . $itemtype . ".jpg"
            : PLUGIN_RESOURCES_WEBDIR . "/pics/gallery/nothing.png";

        $details = [];
        if (Session::isMultiEntitiesMode()) {
            $details[] = Dropdown::getDropdownName("glpi_entities", $values["entities_id"]);
        }
        if (!empty($values["locations_id"])) {
            $details[] = Dropdown::getDropdownName("glpi_locations", $values["locations_id"]);
        }
        if (!empty($values["groups_id"])) {
            $details[] = Dropdown::getDropdownName("glpi_groups", $values["groups_id"]);
        }
        if (!empty($values["serial"])) {
            $details[] = $values["serial"];
        }
        if (!empty($values["otherserial"])) {
            $details[] = $values["otherserial"];
        }

        return [
            'picture' => $picture,
            'url'     => $url,
            'label'   => $label,
            'details' => $details,
        ];
    }
}
