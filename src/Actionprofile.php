<?php
/*
 -------------------------------------------------------------------------
 Resources plugin for GLPI
 Copyright (C) 2015 by the Resources Development Team.
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Resources.

 Resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Resources;

use CommonDBTM;
use DBConnection;
use Dropdown;
use Html;
use Migration;

/**
 * Class Actionprofile
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class Actionprofile extends CommonDBTM
{

    static $rightname = 'plugin_resources';
    var $dohistory = true;

    /**
     * Add a category to profile
     * @param   $profiles_id
     * @param   $canedit
     * @global  $CFG_GLPI
     *
     */
    static function addAction($profiles_id, $canedit)
    {
        global $CFG_GLPI;

        if ($canedit) {
            echo "<form method='post' action='" . PLUGIN_RESOURCES_WEBDIR . "/front/actionprofile.form.php" . "'>";
            echo Html::hidden('profiles_id', ['value' => $profiles_id]);
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_1'><th colspan='4'>";
            echo __('Action authorization', 'resources');
            echo "</th></tr>";

            echo "<tr class='tab_bg_1'><td>";

            echo "<td>";
            echo __('Available actions', 'resources');
            echo "</td><td>";
            $actionprofile = new self();
            if ($actionprofile->getFromDBByCrit(['profiles_id' => $profiles_id])) {
                $actions_id = json_decode($actionprofile->fields['actions_id']);
            }


            $temp = Resource_Change::getAllActions(false);
            unset($temp[0]);


            $params = [
                "name" => 'actions_id',
                'entity' => $_SESSION['glpiactive_entity'],
                "display" => false,
                "multiple" => true,
                "width" => '200px',
                'values' => isset($actions_id) ? $actions_id : [],
                'display_emptychoice' => true
            ];

            $dropdown = Dropdown::showFromArray("actions_id", $temp, $params);

            echo $dropdown;

            echo "</td></tr>";

            echo "<tr class='tab_bg_2'><td colspan='4' style='text-align:center'>";
            echo Html::submit(_sx('button', 'Save'), ['name' => 'addAction', 'class' => 'btn btn-primary']);
            echo "</td></tr>";

            echo "</table></div>";
            Html::closeForm();
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
                        `actions_id`  varchar(255) NOT NULL DEFAULT '0',
                        `profiles_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
