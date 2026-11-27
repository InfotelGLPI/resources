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

use CommonDBChild;
use CommonGLPI;
use DBConnection;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\BadRequestHttpException;
use Migration;

/**
 * Class ResourceImport
 */
class ResourceImport extends CommonDBChild
{
    public static string $rightname = 'plugin_resources_import';
    public bool $dohistory = true;

    public static string $itemtype = Resource::class;
    public static string $items_id = 'plugin_resources_resources_id';

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return _n('Import', 'Imports', $nb, 'resources');
    }

    public static function getIcon()
    {
        return "ti ti-transfer-in";
    }

    /**
     * Get Tab Name used for itemtype
     *
     * NB : Only called for existing object
     *      Must check right on what will be displayed + template
     *
     * @param CommonGLPI $item CommonDBTM object for which the tab need to be displayed
     * @param bool|int $withtemplate boolean  is a template object ? (default 0)
     *
     * @return string tab name
     * @since version 0.83
     *
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        // can exists for template
        if ($item->getType() == Resource::class) {
            if ($_SESSION['glpishow_count_on_tabs']) {
                $dbu = new DbUtils();
                $table = $dbu->getTableForItemType(__CLASS__);
                return self::createTabEntry(
                    self::getTypeName(),
                    $dbu->countElementsInTable(
                        $table,
                        [self::$items_id => $item->getID()],
                    ),
                );
            }
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    public function prepareInputForUpdate($input)
    {
        // An imported value stays the value of its column for its resource: the name and the
        // parent are set when the row is created, only the value changes afterwards.
        foreach (array_keys($input) as $key) {
            if (!in_array($key, ['id', 'value'], true) && !str_starts_with((string) $key, '_')) {
                unset($input[$key]);
            }
        }

        return parent::prepareInputForUpdate($input);
    }

    /**
     * Create a resource, and the imported values attached to it, from a staged import row.
     *
     * This used to override add(), giving it another meaning: any generic entry point (the
     * legacy API among them) creating a ResourceImport created a Resource instead, with
     * force = 1 and without the right to create one being checked.
     *
     * @param int                 $import_id id of the staged row (ImportResource)
     * @param array<mixed, mixed> $datas     values posted, keyed by ImportResourceData id
     *
     * @return int id of the resource created
     */
    public function createResourceFromImport(int $import_id, array $datas): int
    {
        $resourceInputs = [];
        $resourceInputs['entities_id'] = $_SESSION['glpiactive_entity'];

        // The resource is created in the active entity, with force = 1 below (no required
        // fields): the right to create one there is checked before anything else.
        $check_input = ['entities_id' => $resourceInputs['entities_id']];
        (new Resource())->check(-1, CREATE, $check_input);

        $pluginResourcesImportResource = new ImportResource();
        $pluginResourcesImportResourceData = new ImportResourceData();
        $pluginResourcesImportColumn = new ImportColumn();

        if (!$pluginResourcesImportResource->getFromDB($import_id)) {
            throw new BadRequestHttpException('ImportResource not found');
        }

        $resourceImportInputs = [];

        foreach ($datas as $importResourceDataID => $inputValue) {
            if (!$pluginResourcesImportResourceData->getFromDB($importResourceDataID)) {
                throw new BadRequestHttpException('ImportResourceData not found');
            }

            if (!$pluginResourcesImportColumn->getFromDB(
                $pluginResourcesImportResourceData->getField('plugin_resources_importcolumns_id'),
            )) {
                throw new BadRequestHttpException('ImportColumn not found');
            }

            switch ($pluginResourcesImportColumn->getField('resource_column')) {
                case "10": //others
                    $resourceImportInputs[] = [
                        'name' => $pluginResourcesImportResourceData->getField('name'),
                        'value' => $inputValue,
                    ];
                    break;
                default:
                    $resourceTableColumnName = Resource::getResourceColumnNameFromDataNameID(
                        $pluginResourcesImportColumn->getField('resource_column'),
                    );
                    $resourceInputs[$resourceTableColumnName] = $inputValue;
                    break;
            }
        }

        $resource = new Resource();

        // Bypass check required fields
        $keys = array_keys($resourceInputs);

        if (!in_array('locations_id', $keys)) {
            $resourceInputs['locations_id'] = 0;
        }

        if (!in_array('users_id_sales', $keys)) {
            $resourceInputs['users_id_sales'] = 0;
        }

        if (!in_array('plugin_resources_departments_id', $keys)) {
            $resourceInputs['plugin_resources_departments_id'] = 0;
        }

        $resourceInputs['force'] = 1;
        $resourceID = $resource->add($resourceInputs);

        if (!$resourceID) {
            throw new BadRequestHttpException('Failed to create resources');
        }

        foreach ($resourceImportInputs as $resourceImportInput) {
            $resourceImportInput[ResourceImport::$items_id] = $resourceID;
            if (!(new self())->add($resourceImportInput)) {
                throw new BadRequestHttpException('Failed to create resourceimports');
            }
        }

        return (int) $resourceID;
    }

    /**
     * Write the values of a staged import row into an existing resource and its imported
     * values.
     *
     * This used to override update(), giving it another meaning: any generic entry point
     * updating a ResourceImport rewrote the resource named by the input, without any check.
     *
     * @param int                 $resources_id resource to update
     * @param array<mixed, mixed> $datas        values posted, keyed by ImportResourceData id
     *
     * @return bool
     */
    public function importIntoResource(int $resources_id, array $datas): bool
    {
        // Right and entity of the resource rewritten, whatever the caller already checked
        (new Resource())->check($resources_id, UPDATE);
        $resourceID = $resources_id;

        $pluginResourcesImportResourceData = new ImportResourceData();
        $pluginResourcesResourceImport = new ResourceImport();
        $pluginResourcesImportColumn = new ImportColumn();

        $resourceInputs = ['id' => $resourceID];

        foreach ($datas as $importResourceDataID => $inputValue) {
            if (!$pluginResourcesImportResourceData->getFromDB($importResourceDataID)) {
                throw new BadRequestHttpException('ImportResourceData not found');
            }

            if (!$pluginResourcesImportColumn->getFromDB(
                $pluginResourcesImportResourceData->getField('plugin_resources_importcolumns_id'),
            )) {
                throw new BadRequestHttpException('ImportColumn not found');
            }

            $resourceColumn = $pluginResourcesImportColumn->getField('resource_column');

            switch ($resourceColumn) {
                case 10:
                    $criterias = [
                        ResourceImport::$items_id => $resourceID,
                        'name' => $pluginResourcesImportResourceData->getField('name'),
                    ];

                    // Resource Import already exist
                    if ($pluginResourcesResourceImport->getFromDBByCrit($criterias)) {
                        $resourceImportInput = [
                            ResourceImport::getIndexName() => $pluginResourcesResourceImport->getID(),
                            'value' => $inputValue,
                        ];

                        if (!(new self())->update($resourceImportInput)) {
                            throw new BadRequestHttpException('Error when updating Resource Import');
                        }
                        // Resource import doesn't exist yet
                    } else {
                        $resourceImportInput = [
                            "plugin_resources_resources_id" => $resourceID,
                            'name' => $pluginResourcesImportResourceData->getField('name'),
                            'value' => $inputValue,
                        ];

                        if (!(new self())->add($resourceImportInput)) {
                            throw new BadRequestHttpException('Error when creating Resource Import');
                        }
                    }
                    break;
                default:
                    // Get the column name from resource_column
                    $fieldName = Resource::getResourceColumnNameFromDataNameID(
                        $pluginResourcesImportColumn->getField('resource_column'),
                    );

                    $resourceInputs[$fieldName] = $inputValue;
            }
        }

        $resource = new Resource();

        // Update resource column. The import setup may map a column on the writer of the
        // resource, a column an ordinary update no longer writes (see
        // Resource::prepareInputForUpdate()): the import is the server code allowed to. The
        // import keeps the resources in line with the HR source, validated ones included.
        $resource->allow_structural_fields = ['users_id_recipient'];
        if (!$resource->updateFromWorkflow($resourceInputs)) {
            throw new BadRequestHttpException('Error when updating Resource Import');
        }

        return true;
    }

    /**
     * show Tab content
     *
     * @param          $item                  CommonGLPI object for which the tab need to be displayed
     * @param          $tabnum       integer  tab number (default 1)
     * @param bool|int $withtemplate boolean  is a template object ? (default 0)
     *
     * @return true
     * @since version 0.83
     *
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Resource::class) {
            self::showImportResources($item, $withtemplate);
        }
        return true;
    }

    public static function showImportResources($item, $withtemplate)
    {
        $pluginResourcesResourceImport = new ResourceImport();
        $resourceImports = $pluginResourcesResourceImport->find([
            'plugin_resources_resources_id' => $item->getID(),
        ]);

        TemplateRenderer::getInstance()->display('@resources/resourceimport_list.html.twig', [
            'title'       => __("Imported values", 'resources'),
            'label_name'  => __("Name"),
            'label_value' => __("Value"),
            'rows'        => $resourceImports,
        ]);
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
                        `name`                          varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                        `value`                         varchar(255) COLLATE utf8mb4_unicode_ci NULL,
                        `plugin_resources_resources_id` int {$default_key_sign}                            NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
