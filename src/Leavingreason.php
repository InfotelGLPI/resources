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

use CommonDropdown;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class LeavingReason
 */
class LeavingReason extends CommonDropdown
{
    public static $rightname = 'plugin_resources';
    public $can_be_translated = true;

    /**
     * @param $nb
     **@since 0.85
     *
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Leaving reason', 'Leaving reasons', $nb, 'resources');
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return
     **/
    public static function canCreate(): bool
    {
        return Session::haveRightsOr('dropdown', [CREATE, UPDATE, DELETE]);
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
     * During a resource's transfer
     *
     * @static
     * @param $ID
     * @param $entity
     * @return
     */
    public static function transfer($ID, $entity)
    {
        global $DB;

        if ($ID > 0) {
            $table = self::getTable();
            $iterator = $DB->request([
                'FROM' => $table,
                'WHERE' => ['id' => $ID],
            ]);

            foreach ($iterator as $data) {
                $input['name'] = $data['name'];
                $input['entities_id'] = $entity;
                $temp = new self();
                $newID = $temp->getID();
                if ($newID < 0) {
                    $newID = $temp->import($input);
                }

                return $newID;
            }
        }
        return 0;
    }
}
