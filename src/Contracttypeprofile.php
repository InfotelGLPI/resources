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
use DBConnection;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Migration;

/**
 * Class Contracttypeprofile
 */
class Contracttypeprofile extends CommonDBTM
{
    // Each row restricts the contract types offered to a profile (templates of the creation
    // forms, resources of the leaving form). front/contracttypeprofile.form.php writes them
    // under the core config right; with the plugin right they inherited, the generic entry
    // points (API, forged massive actions) let a restricted user lift the restriction of their
    // own profile, as with Actionprofile.
    use ConfigRightTrait;
    // Written from the profile form only
    use NoStandardMassiveActionTrait;

    public static $rightname = 'plugin_resources';
    public $dohistory = true;

    /**
     * Add a category to profile
     * @param   $profiles_id
     * @param   $canedit
     * @global  $CFG_GLPI
     *
     */
    public static function addContracttype($profiles_id, $canedit)
    {
        if (!$canedit) {
            return;
        }

        $contracttypeprofile = new self();
        $plugin_resources_contracttypes_id = [];
        if ($contracttypeprofile->getFromDBByCrit(['profiles_id' => $profiles_id])) {
            $plugin_resources_contracttypes_id = json_decode(
                $contracttypeprofile->fields['plugin_resources_contracttypes_id'],
            );
        }

        $dbu = new DbUtils();
        $result = $dbu->getAllDataFromTable(ContractType::getTable());

        $temp = [];
        $temp[0] = __("Without contract", 'resources');
        foreach ($result as $item) {
            $temp[$item['id']] = $item['name'];
        }

        $params = [
            "name" => 'plugin_resources_contracttypes_id',
            'entity' => $_SESSION['glpiactive_entity'],
            "display" => false,
            "multiple" => true,
            "width" => '200px',
            'values' => $plugin_resources_contracttypes_id,
            'display_emptychoice' => true,
        ];

        $dropdown = Dropdown::showFromArray("plugin_resources_contracttypes_id", $temp, $params);

        TemplateRenderer::getInstance()->display('@resources/profile_authorization_form.html.twig', [
            'profiles_id' => $profiles_id,
            'form_action' => PLUGIN_RESOURCES_WEBDIR . "/front/contracttypeprofile.form.php",
            'title'       => __('Contract type authorization', 'resources'),
            'label'       => __('Available contract type', 'resources'),
            'dropdown'    => $dropdown,
            'submit_name' => 'addContracttype',
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
                        `plugin_resources_contracttypes_id` varchar(255) NOT NULL DEFAULT '0',
                        `profiles_id`                       int {$default_key_sign} NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
