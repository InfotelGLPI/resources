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
 * Class Department
 */
class Department extends CommonDropdown
{
    public $can_be_translated = true;
    static $rightname = 'plugin_resources';

    /**
     * @param $nb
     **@since 0.85
     *
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Department', 'Departments', $nb, 'resources');
    }

    /**
     * @return
     */
    static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * @return
     */
    static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
    }

    public function getAdditionalFields()
    {
        return [
            //         ['name'  => 'plugin_release_typerollbacks_id',
            //            'label' => __('Type test','Type tests', 'release'),
            //            'type'  => 'dropdownRollbacks',
            //         ],
            [
                'name' => 'plugin_resources_employers_id',
                'label' => _n('Employer', 'Employers', 1, 'resources'),
                'type' => 'dropdownEmployers',
            ],


        ];
    }

    /**
     * @see CommonDropdown::displaySpecificTypeField()
     **/
    public function displaySpecificTypeField($ID, $field = [], array $options = [])
    {
        switch ($field['type']) {
            case 'dropdownEmployers':
                $this->getFromDB($ID);
                Employer::dropdown(
                    [
                        "name" => "plugin_resources_employers_id",
                        "value" => $this->fields["plugin_resources_employers_id"],
                    ]
                );
                break;
        }
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();


        $tab[] = [
            'id' => '103',
            'name' => _n('Employer', 'Employers', 1, 'resources'),
            'field' => 'name',
            'table' => getTableForItemType(Employer::class),
            'datatype' => 'dropdown',
        ];

        return $tab;
    }

    /**
     * @param $ID
     * @param $entity
     *
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
