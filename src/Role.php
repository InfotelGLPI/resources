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

use CommonDropdown;
use DBConnection;
use Dropdown;
use Migration;
use Session;
use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Role
 */
class Role extends CommonDropdown
{
    public static $rightname = 'plugin_resources_role';

    /**
     * @param $nb
     **@since 0.85
     *
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Role', 'Roles', $nb, 'resources');
    }

    /**
     * @return
     */
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * @return
     */
    public static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
    }

    /**
     * Return Additional Fields for this type
     *
     * @return array
     **/
    public function getAdditionalFields()
    {
        return [
            [
                'name' => 'roles_services',
                'label' => Role_Service::getTypeName(2),
                'type' => 'multiple_roles_services',
                'list' => true,
            ],
        ];
    }

    /**
     * @return array
     */
    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();


        return $tab;
    }


    /**
     * Display the roles available for a service.
     *
     * Roles carry no profession of their own: they are linked to services through
     * Role_Service, so the listing is delegated to dropdownFromService().
     *
     * @static
     *
     * @param $options
     */
    public static function showRole($options)
    {
        $servicesId = (int) ($options['plugin_resources_services_id'] ?? 0);
        $entity     = $options['entity'] ?? 0;

        // The rand only builds an element id and comes straight from the request: keep it
        // numeric so it can never break out of the id attribute.
        $rand = (int) ($options['rand'] ?? 0);

        $dropdown = '';
        if ($servicesId > 0) {
            $dropdown = (string) self::dropdownFromService($servicesId, [
                'name'    => 'plugin_resources_roles_id',
                'entity'  => $entity,
                'rand'    => $rand,
                'display' => false,
            ]);
        }

        TemplateRenderer::getInstance()->display('@resources/dependent_dropdown.html.twig', [
            'dropdown'    => $dropdown,
            'field_name'  => 'plugin_resources_roles_id',
            'rand'        => $rand,
            'empty_value' => Dropdown::EMPTY_VALUE,
        ]);
    }


    /**
     * is_active = 1 during a creation
     *
     * @return nothing|void
     */
    public function post_getEmpty()
    {
        $this->fields['is_active'] = 1;
    }

    /**
     * @since 0.85
     * @see CommonDropdown::displaySpecificTypeField()
     **/
    public function displaySpecificTypeField($ID, $field = [], array $options = [])
    {
        switch ($field['type']) {
            case 'multiple_roles_services':
                $service = new Service();
                $values = $service->find(['entities_id' => $_SESSION['glpiactiveentities']]);
                $datas = [];
                foreach ($values as $key => $v) {
                    $datas[$v['id']] = $v['name'];
                }
                $role_service = new Role_Service();
                $role_service_values = $role_service->find(['plugin_resources_roles_id' => $this->fields['id']]);
                $values_selected = [];
                foreach ($role_service_values as $role_service_value) {
                    $values_selected[] = $role_service_value['plugin_resources_services_id'];
                }

                Dropdown::showFromArray(
                    'roles_services',
                    $datas,
                    ['values' => $values_selected, 'multiple' => true, 'display' => true],
                );
                break;
        }
    }

    public function post_addItem()
    {
        $test = true;
        $roles_services = $this->input["roles_services"];
        if (is_array($roles_services)) {
            $role_service = new Role_Service();
            foreach ($roles_services as $key => $id_service) {
                $role_service->add(
                    ['plugin_resources_roles_id' => $this->getID(), 'plugin_resources_services_id' => $id_service],
                );
            }
        }
    }

    public function post_updateItem($history = 1)
    {
        $roles_services = $this->input["roles_services"];
        $role_service = new Role_Service();
        $roleServices = $role_service->find(['plugin_resources_roles_id' => $this->fields['id']]);
        $current_roles_services = [];
        foreach ($roleServices as $key => $val) {
            $current_roles_services[] = $val['plugin_resources_services_id'];
        }

        foreach ($roles_services as $id_service) {
            if (!$role_service->getFromDBByCrit(
                ['plugin_resources_roles_id' => $this->getID(), 'plugin_resources_services_id' => $id_service],
            )) {
                $role_service->add(
                    ['plugin_resources_roles_id' => $this->getID(), 'plugin_resources_services_id' => $id_service],
                );
            }
        }

        foreach ($current_roles_services as $id_service) {
            if (!in_array($id_service, $roles_services)) {
                if ($role_service->getFromDBByCrit(
                    ['plugin_resources_roles_id' => $this->getID(), 'plugin_resources_services_id' => $id_service],
                )) {
                    $role_service->deleteByCriteria(
                        ['plugin_resources_roles_id' => $this->getID(), 'plugin_resources_services_id' => $id_service],
                    );
                }
            }
        }
    }

    public static function dropdownFromService($services_id, $opt)
    {
        $role_service = new Role_Service();
        $role_services = $role_service->find(['plugin_resources_services_id' => $services_id]);
        $roles = [0];
        foreach ($role_services as $s) {
            $roles[] = $s['plugin_resources_roles_id'];
        }
        $options = array_merge(['condition' => ['id' => $roles]], $opt);
        return self::dropdown($options);
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
                        `entities_id`  int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `is_recursive` tinyint      NOT NULL                   DEFAULT '0',
                        `name`         varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `comment`      TEXT COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`),
                        KEY `name` (`name`),
                        KEY `entities_id` (`entities_id`),
                        KEY `is_recursive` (`is_recursive`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
