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
use DBConnection;
use DbUtils;
use Dropdown;
use Migration;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

// Class for a Dropdown

/**
 * Class ContractType
 */
class ContractType extends CommonDropdown
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
        return _n('Type of contract', 'Types of contract', $nb, 'resources');
    }


    /**
     * Return Additional Fields for this type
     *
     * @return array
     **/
    public function getAdditionalFields()
    {
        $tab = [
            [
                'name' => 'code',
                'label' => __('Code', 'resources'),
                'type' => 'text',
                'list' => true,
            ],
            [
                'name' => "",
                'label' => __('Wizard resource creation', 'resources'),
                'type' => '',
                'list' => false,
            ],
            [
                'name' => 'use_employee_wizard',
                'label' => __('Enter employer information about the resource', 'resources'),
                'type' => 'bool',
                'list' => true,
            ],
            [
                'name' => 'use_need_wizard',
                'label' => __('Enter the computing needs of the resource', 'resources'),
                'type' => 'bool',
                'list' => true,
            ],
            [
                'name' => 'use_picture_wizard',
                'label' => __('Add a picture', 'resources'),
                'type' => 'bool',
                'list' => true,
            ],
            [
                'name' => 'use_habilitation_wizard',
                'label' => __('Enter habilitation information ', 'resources'),
                'type' => 'bool',
                'list' => true,
            ],
            [
                'name' => 'use_entrance_information',
                'label' => __('Use recruiting information', 'resources'),
                'type' => 'bool',
                'list' => true,
            ],
            [
                'name' => 'use_second_list_employer',
                'label' => __('Use second list of employer', 'resources'),
                'type' => 'bool',
                'list' => true,
            ],
            [
                'name' => 'use_second_matricule',
                'label' => __('Use second matricule', 'resources'),
                'type' => 'bool',
                'list' => true,
            ],
            [
                'name' => 'use_resignation_form',
                'label' => __('Use resignation form', 'resources'),
                'type' => 'bool',
                'list' => true,
            ],
            [
                'name' => 'use_documents_wizard',
                'label' => __('Use documents form', 'resources'),
                'type' => 'bool',
                'list' => true,
            ],
        ];

        return $tab;
    }

    /**
     * @return array
     */
    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id' => '14',
            'table' => $this->getTable(),
            'field' => 'code',
            'name' => __('Code', 'resources'),
        ];

        $tab[] = [
            'id' => '15',
            'table' => $this->getTable(),
            'field' => 'use_employee_wizard',
            'name' => __('Enter employer information about the resource', 'resources'),
            'datatype' => 'bool',
        ];
        $tab[] = [
            'id' => '20',
            'table' => $this->getTable(),
            'field' => 'use_need_wizard',
            'name' => __('Enter the computing needs of the resource', 'resources'),
            'datatype' => 'bool',
        ];
        $tab[] = [
            'id' => '17',
            'table' => $this->getTable(),
            'field' => 'use_picture_wizard',
            'name' => __('Add a picture', 'resources'),
            'datatype' => 'bool',
        ];
        $tab[] = [
            'id' => '18',
            'table' => $this->getTable(),
            'field' => 'use_habilitation_wizard',
            'name' => __('Enter habilitation information', 'resources'),
            'datatype' => 'bool',
        ];
        $tab[] = [
            'id' => '19',
            'table' => $this->getTable(),
            'field' => 'use_resignation_form',
            'name' => __('Use resignation form', 'resources'),
            'datatype' => 'bool',
        ];
        $tab[] = [
            'id' => '21',
            'table' => $this->getTable(),
            'field' => 'use_documents_wizard',
            'name' => __('Use documents form', 'resources'),
            'datatype' => 'bool',
        ];

        return $tab;
    }

    /**
     * @param $ID
     * @param $field
     *
     * @return bool
     */
    public static function checkWizardSetup($ID, $field)
    {
        if ($ID > 0) {
            $resource = new Resource();
            $self = new self();

            if ($resource->getFromDB($ID)) {
                if ($self->getFromDB($resource->fields["plugin_resources_contracttypes_id"])) {
                    if ($self->fields[$field] > 0) {
                        return true;
                    }
                }
            }
        }
        return false;
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

    /**
     * @param     $name
     * @param int $value
     *
     * @return int|string
     */
    public function dropdownContractType($name, $value = 0)
    {
        $dbu = new DbUtils();
        $restrict = $dbu->getEntitiesRestrictCriteria($this->getTable(), '', '', $this->maybeRecursive())
            + ["ORDER" => "`name`"];
        $types = $dbu->getAllDataFromTable($this->getTable(), $restrict);

        $option[0] = __('Without contract', 'resources');

        if (!empty($types)) {
            foreach ($types as $type) {
                $option[$type["id"]] = $type["name"];
            }
        }

        return Dropdown::showFromArray($name, $option, ['value' => $value]);
    }

    /**
     * @param $value
     *
     * @return string
     */
    public function getContractTypeName($value)
    {
        switch ($value) {
            case 0:
                return __('Without contract', 'resources');
            default:
                if ($this->getFromDB($value)) {
                    $name = "";
                    if (isset($this->fields["name"])) {
                        $name = $this->fields["name"];
                    }
                    return $name;
                }
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
                        `entities_id`              int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `is_recursive`             tinyint      NOT NULL                   DEFAULT '0',
                        `name`                     varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `code`                     varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `use_employee_wizard`      tinyint      NOT NULL                   DEFAULT '1',
                        `use_need_wizard`          tinyint      NOT NULL                   DEFAULT '1',
                        `use_picture_wizard`       tinyint      NOT NULL                   DEFAULT '1',
                        `use_habilitation_wizard`  tinyint      NOT NULL                   DEFAULT '0',
                        `comment`                  TEXT COLLATE utf8mb4_unicode_ci,
                        `use_second_matricule`     tinyint      NOT NULL                   DEFAULT '0',
                        `use_second_list_employer` tinyint      NOT NULL                   DEFAULT '0',
                        `use_resignation_form`     tinyint      NOT NULL                   DEFAULT '0',
                        `use_entrance_information` tinyint      NOT NULL                   DEFAULT '0',
                        `use_documents_wizard`     tinyint      NOT NULL                   DEFAULT '0',
                        PRIMARY KEY (`id`),
                        KEY `name` (`name`),
                        KEY `entities_id` (`entities_id`),
                        KEY `is_recursive` (`is_recursive`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            $DB->insert(
                $table,
                ['name' => __('Long term contract', 'resources'),
                    'entities_id' => 0,
                    'is_recursive' => 1]
            );

            $DB->insert(
                $table,
                ['name' => __('Fixed term contract', 'resources'),
                    'entities_id' => 0,
                    'is_recursive' => 1]
            );

            $DB->insert(
                $table,
                ['name' => __('Trainee', 'resources'),
                    'entities_id' => 0,
                    'is_recursive' => 1]
            );
        }
    }
}
