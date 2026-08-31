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

use Ajax;
use Appliance;
use CommonDBTM;
use CommonGLPI;
use CommonITILActor;
use DBConnection;
use DbUtils;
use Dropdown;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Group_Ticket;
use Html;
use ITILCategory;
use Location;
use Log;
use Migration;
use Session;
use Ticket;
use TicketTemplate;
use TicketTemplatePredefinedField;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Resource_Change
 */
class Resource_Change extends CommonDBTM
{
    public static $rightname = 'plugin_resources';

    //List of possible actions
    public const CHANGE_RESOURCEMANAGER = 1;
    public const CHANGE_ACCESSPROFIL = 2;
    public const CHANGE_CONTRACTTYPE = 3;
    public const CHANGE_AGENCY = 4;
    public const CHANGE_TRANSFER = 5;
    public const BADGE_RESTITUTION = 6;
    public const CHANGE_RESOURCESALE = 7;
    public const CHANGE_RESOURCEINFORMATIONS = 8;
    public const CHANGE_RESOURCECOMPANY = 9;
    public const CHANGE_RESOURCEDEPARTMENT = 10;
    public const CHANGE_RESOURCEMATERIAL = 11;
    public const CHANGE_RESOURCEITEMAPPLICATION = 12;
    public const CHANGE_RESOURCESERVICE = 13;
    public const CHANGE_RESOURCEROLE = 14;
    public const CHANGE_RESOURCEFUNCTION = 15;
    public const CHANGE_RESOURCETEAM = 16;
    public const CHANGE_NAME = 17;

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return __("Managing change actions", 'resources');
    }

    public static function getIcon()
    {
        return "ti ti-replace-user";
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return self::createTabEntry(self::getTypeName());
    }
    /**
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     * @see CommonGLPI::displayTabContentForItem()
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Config::class) {
            $self = new self();
            $self->showFormActions();
        }
        return true;
    }

    /**
     * Returns all actions
     */
    public static function getAllActions($menu = false)
    {
        $actions = [];
        $actions[0] = self::getNameActions(0);
        $actions[self::CHANGE_RESOURCEMANAGER] = self::getNameActions(self::CHANGE_RESOURCEMANAGER);
        $actions[self::CHANGE_RESOURCESALE] = self::getNameActions(self::CHANGE_RESOURCESALE);
        $actions[self::CHANGE_ACCESSPROFIL] = self::getNameActions(self::CHANGE_ACCESSPROFIL);
        $actions[self::CHANGE_CONTRACTTYPE] = self::getNameActions(self::CHANGE_CONTRACTTYPE);
        $actions[self::CHANGE_AGENCY] = self::getNameActions(self::CHANGE_AGENCY);
        $actions[self::CHANGE_RESOURCEINFORMATIONS] = self::getNameActions(self::CHANGE_RESOURCEINFORMATIONS);
        $actions[self::CHANGE_NAME]                   = self::getNameActions(self::CHANGE_NAME);
        $actions[self::CHANGE_RESOURCECOMPANY] = self::getNameActions(self::CHANGE_RESOURCECOMPANY);
        $actions[self::CHANGE_RESOURCEDEPARTMENT] = self::getNameActions(self::CHANGE_RESOURCEDEPARTMENT);
        $actions[self::CHANGE_RESOURCEMATERIAL] = self::getNameActions(self::CHANGE_RESOURCEMATERIAL);
        $actions[self::CHANGE_RESOURCEITEMAPPLICATION] = self::getNameActions(self::CHANGE_RESOURCEITEMAPPLICATION);
        $actions[self::CHANGE_RESOURCESERVICE] = self::getNameActions(self::CHANGE_RESOURCESERVICE);
        $actions[self::CHANGE_RESOURCEROLE] = self::getNameActions(self::CHANGE_RESOURCEROLE);
        $actions[self::CHANGE_RESOURCEFUNCTION] = self::getNameActions(self::CHANGE_RESOURCEFUNCTION);
        $actions[self::CHANGE_RESOURCETEAM] = self::getNameActions(self::CHANGE_RESOURCETEAM);
        $transfer = new TransferEntity();
        $dataEntity = $transfer->find();
        if (is_array($dataEntity) && count($dataEntity) > 0) {
            $actions[self::CHANGE_TRANSFER] = self::getNameActions(self::CHANGE_TRANSFER);
        }
        if ($menu == false) {
            foreach ($actions as $key => $val) {
                if ($key == 0) {
                    continue;
                }
                $self = new self();
                $conf = [];
                $conf = $self->find(['actions_id' => $key]);
                if (count($conf) == 0) {
                    unset($actions[$key]);
                }
            }
        }

        return $actions;
    }

    /**
     * Returns the label of the action
     *
     * @param $actions_id
     *
     * @return string
     */
    public static function getNameActions($actions_id)
    {
        switch ($actions_id) {
            case self::CHANGE_RESOURCEMANAGER:
                return __("Change manager", 'resources');
            case self::CHANGE_RESOURCESALE:
                return __("Change the sales manager", 'resources');
            case self::CHANGE_ACCESSPROFIL:
                return __("Change the access profil", 'resources');
            case self::CHANGE_CONTRACTTYPE:
                return __("Change contract type", 'resources');
            case self::CHANGE_AGENCY:
                return __("Change of agency", 'resources');
            case self::CHANGE_TRANSFER:
                return __("Change direction (mutation)", 'resources');
            case self::BADGE_RESTITUTION:
                return __('Badge restitution', 'resources');
            case self::CHANGE_RESOURCEINFORMATIONS:
                return __(' Change information', 'resources');
            case self::CHANGE_NAME :
                return __(' Change name', 'resources');
            case self::CHANGE_RESOURCECOMPANY:
                return __('Change company', 'resources');
            case self::CHANGE_RESOURCEDEPARTMENT:
                return __('Change department ', 'resources');
            case self::CHANGE_RESOURCEMATERIAL:
                return __('Change material', 'resources');
            case self::CHANGE_RESOURCEITEMAPPLICATION:
                return __('Add application', 'resources');
            case self::CHANGE_RESOURCESERVICE:
                return __('Change service', 'resources');
            case self::CHANGE_RESOURCEROLE:
                return __('Change role', 'resources');
            case self::CHANGE_RESOURCEFUNCTION:
                return __('Change function', 'resources');
            case self::CHANGE_RESOURCETEAM:
                return __('Change team', 'resources');
            default:
                return Dropdown::EMPTY_VALUE;
        }
    }

    /**
     * Form for each change
     *
     * @param $action_id
     * @param $plugin_resources_resources_id
     */
    public static function setFieldByAction($action_id, $plugin_resources_resources_id)
    {
        global $DB;

        if ($plugin_resources_resources_id == 0) {
            TemplateRenderer::getInstance()->display('@resources/alert_message.html.twig', [
                'level'   => 'danger',
                'message' => __('Please select a resource', 'resources'),
            ]);
            return;
        }

        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);

        $dbu = new DbUtils();

        // GLPI dropdowns echo their markup and return their rand: capture both, so the
        // markup can be injected as |raw and the rand can anchor the generated JS.
        $captureRand = static function (callable $renderer, &$rand): string {
            ob_start();
            $rand = $renderer();
            return (string) ob_get_clean();
        };
        $capture = static function (callable $renderer): string {
            ob_start();
            $renderer();
            return (string) ob_get_clean();
        };

        $rand       = 0;
        $rows       = [];
        $js         = '';
        $row_class  = 'row';
        $cell_class = 'col-md-4 mb-2';

        //Display for each action
        switch ($action_id) {
            case self::CHANGE_RESOURCEMANAGER:
                $rows[] = [
                    'label'  => __("Manager for the current resource", "resources"),
                    'widget' => '&nbsp;' . htmlescape($dbu->getUserName($resource->getField('users_id'))),
                ];
                $rows[] = [
                    'label'  => __('New resource manager', 'resources'),
                    'widget' => $captureRand(fn() => User::dropdown([
                        'name' => "users_id",
                        'entity' => $resource->fields["entities_id"],
                        'right' => 'all',
                        'used' => [$resource->getField('users_id')],
                        'on_change' => 'plugin_resources_load_button_changeresources_manager()',
                    ]), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_manager',
                    ['action' => self::CHANGE_RESOURCEMANAGER, 'users_id' => '__VALUE__'],
                    'dropdown_users_id' . $rand,
                );
                break;

            case self::CHANGE_RESOURCESALE:
                $rows[] = [
                    'label'  => __("Sales manager for the current resource", "resources"),
                    'widget' => '&nbsp;' . htmlescape($dbu->getUserName($resource->getField('users_id_sales'))),
                ];
                $rows[] = [
                    'label'  => __('New resource sales manager', 'resources'),
                    'widget' => $captureRand(fn() => User::dropdown([
                        'name' => "users_id_sales",
                        'entity' => $resource->fields["entities_id"],
                        'right' => 'all',
                        'used' => [$resource->getField('users_id_sales')],
                        'on_change' => 'plugin_resources_load_button_changeresources_sale()',
                    ]), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_sale',
                    ['action' => self::CHANGE_RESOURCESALE, 'users_id_sales' => '__VALUE__'],
                    'dropdown_users_id_sales' . $rand,
                );
                break;

            case self::CHANGE_ACCESSPROFIL:
                $criteria = [
                    'SELECT' => [
                        'glpi_plugin_resources_habilitations.id',
                    ],
                    'FROM' => 'glpi_plugin_resources_resourcehabilitations',
                    'LEFT JOIN' => [
                        'glpi_plugin_resources_habilitations' => [
                            'ON' => [
                                'glpi_plugin_resources_resourcehabilitations' => 'plugin_resources_habilitations_id',
                                'glpi_plugin_resources_habilitations' => 'id',
                            ],
                        ],
                        'glpi_plugin_resources_habilitationlevels' => [
                            'ON' => [
                                'glpi_plugin_resources_habilitations' => 'plugin_resources_habilitationlevels_id',
                                'glpi_plugin_resources_habilitationlevels' => 'id',
                            ],
                        ],
                    ],
                    'WHERE' => [
                        'plugin_resources_resources_id' => $plugin_resources_resources_id,
                        'glpi_plugin_resources_habilitationlevels.is_mandatory_creating_resource' => 1,
                    ],
                ];

                $used = [];
                $current = '';
                foreach ($DB->request($criteria) as $data) {
                    $current .= '&nbsp;' . htmlescape(Dropdown::getDropdownName(
                        'glpi_plugin_resources_habilitations',
                        $data['id'],
                    )) . '<br>';
                    $used[] = $data['id'];
                }
                $rows[] = [
                    'label'  => __("Current access profile of the resource", "resources"),
                    'widget' => $current,
                ];

                //level
                $habilitationlevel = new HabilitationLevel();
                $levels = $habilitationlevel->find(['is_mandatory_creating_resource' => 1]);
                $condition = [];
                foreach ($levels as $level) {
                    $condition["plugin_resources_habilitationlevels_id"] = $level['id'];
                }

                $rows[] = [
                    'label'  => __('New access profile of the resource', 'resources'),
                    'widget' => $captureRand(fn() => Habilitation::dropdown([
                        'name' => "plugin_resources_habilitations_id",
                        'entity' => $resource->fields["entities_id"],
                        'right' => 'all',
                        'condition' => $condition,
                        'used' => $used,
                        'on_change' => 'plugin_resources_load_button_changeresources_profil()',
                    ]), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_profil',
                    [
                        'action' => self::CHANGE_ACCESSPROFIL,
                        'plugin_resources_habilitations_id' => '__VALUE__',
                    ],
                    'dropdown_plugin_resources_habilitations_id' . $rand,
                );
                break;

            case self::CHANGE_CONTRACTTYPE:
                $rows[] = [
                    'label'  => __("Current contract type of the resource", "resources"),
                    'widget' => '&nbsp;' . htmlescape(Dropdown::getDropdownName(
                        'glpi_plugin_resources_contracttypes',
                        $resource->getField('plugin_resources_contracttypes_id'),
                    )),
                ];
                $rows[] = [
                    'label'  => __('New type of contract', 'resources'),
                    'widget' => $captureRand(fn() => ContractType::dropdown([
                        'name' => "plugin_resources_contracttypes_id",
                        'entity' => $resource->fields["entities_id"],
                        'right' => 'all',
                        'used' => [$resource->getField('plugin_resources_contracttypes_id')],
                        'on_change' => 'plugin_resources_load_button_changeresources_contract()',
                    ]), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_contract',
                    [
                        'action' => self::CHANGE_CONTRACTTYPE,
                        'plugin_resources_contracttypes_id' => '__VALUE__',
                    ],
                    'dropdown_plugin_resources_contracttypes_id' . $rand,
                );
                $rows[] = [
                    'label'  => __('Date of contract type change', 'resources'),
                    'widget' => $capture(fn() => Html::showDateField("date_of_change")),
                ];
                break;

            case self::CHANGE_AGENCY:
                $rows[] = [
                    'label'  => __("Current agency of the resource", "resources"),
                    'widget' => '&nbsp;' . htmlescape(
                        Dropdown::getDropdownName('glpi_locations', $resource->getField('locations_id')),
                    ),
                ];
                $rows[] = [
                    'label'  => __('New resource agency', 'resources'),
                    'widget' => $captureRand(fn() => Location::dropdown([
                        'name' => "locations_id",
                        'entity' => $resource->fields["entities_id"],
                        'right' => 'all',
                        'used' => [$resource->getField('locations_id')],
                        'on_change' => 'plugin_resources_load_button_changeresources_agency();',
                    ]), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_agency',
                    ['action' => self::CHANGE_AGENCY, 'locations_id' => '__VALUE__'],
                    'dropdown_locations_id' . $rand,
                );

                $rows[] = [
                    'label'  => __("Current team of the resource", "resources"),
                    'widget' => '&nbsp;' . htmlescape(Dropdown::getDropdownName(
                        'glpi_plugin_resources_teams',
                        $resource->getField('plugin_resources_teams_id'),
                    )),
                ];
                $rows[] = [
                    'label'  => __('New resource team', 'resources'),
                    'widget' => $captureRand(fn() => Team::dropdown([
                        'name' => "plugin_resources_teams_id",
                        'entity' => $resource->fields["entities_id"],
                        'right' => 'all',
                        'used' => [$resource->getField('plugin_resources_teams_id')],
                    ]), $rand),
                ];
                $rows[] = [
                    'label'  => __('Date of location change', 'resources'),
                    'widget' => $capture(fn() => Html::showDateField("date_of_change")),
                ];
                break;

            case self::CHANGE_TRANSFER:
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_transfer',
                    ['action' => self::CHANGE_TRANSFER],
                    "",
                );
                $js .= "plugin_resources_load_button_changeresources_transfer();";
                break;

            case self::CHANGE_RESOURCEINFORMATIONS:
                $rand = mt_rand();
                $rows[] = [
                    'label'  => __('Name', 'resources'),
                    'widget' => Html::input('name', [
                        'rand'  => $rand,
                        'value' => $resource->fields["name"],
                    ]),
                ];
                // NOTE: the legacy code built the firstname input but never printed it,
                // and the JS below sends the stored firstname rather than a typed one.
                $rows[] = [
                    'label'  => __('Firstname', 'resources'),
                    'widget' => '',
                ];
                $rows[] = [
                    'label'  => __('Departure date', 'resources'),
                    'widget' => $capture(fn() => Html::showDateField(
                        "date_end",
                        ['value' => $resource->fields["date_end"]],
                    )),
                ];

                $root_doc = PLUGIN_RESOURCES_WEBDIR;
                $action = self::CHANGE_RESOURCEINFORMATIONS;
                $firstname = json_encode(
                    (string) $resource->fields["firstname"],
                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP,
                );
                $js .= <<<JAVASCRIPT
                    $('input[name="date_end"]').change(function() {
                        plugin_resources_load_button_changeresources_information();
                    });
                    $('input[name="name"]').on("input", function() {
                        this.value = this.value.toUpperCase();
                        plugin_resources_load_button_changeresources_information();
                    });
                    function plugin_resources_load_button_changeresources_information(){
                        $('#plugin_resources_buttonchangeresources').load('{$root_doc}/ajax/resourcechange.php', {
                            load_button_changeresources: true,
                            action: {$action},
                            name: $('input[name="name"]').val(),
                            firstname: {$firstname},
                            date_end: $('input[name="date_end"]').val()
                        });
                    }
                    JAVASCRIPT;
                break;

            case self::CHANGE_NAME:
                $row_class  = 'form-row';
                $cell_class = 'bt-feature col-md-4';
                $rand       = mt_rand();
                $rows[] = [
                    'label'  => __('Name', 'resources'),
                    'widget' => Html::input('name', [
                        'rand'     => $rand,
                        'value'    => $resource->fields["name"],
                        'onChange' => "javascript:this.value=this.value.toUpperCase(); "
                            . "plugin_resources_load_button_changeresources_information(); ",
                    ]),
                ];

                $root_doc = PLUGIN_RESOURCES_WEBDIR;
                $action = self::CHANGE_NAME;
                $js .= <<<JAVASCRIPT
                    $('input[name="name"]').change(function() {
                        plugin_resources_load_button_changeresources_information();
                    });
                    function plugin_resources_load_button_changeresources_information(){
                        $('#plugin_resources_buttonchangeresources').load('{$root_doc}/ajax/resourcechange.php', {
                            load_button_changeresources: true,
                            action: {$action},
                            name: $('input[name="name"]').val()
                        });
                    }
                    JAVASCRIPT;
                break;

            case self::CHANGE_RESOURCECOMPANY:
                $employee = new Employee();
                $employee->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);
                $rows[] = [
                    'label'  => __("Current company of the resource", "resources"),
                    'widget' => '&nbsp;' . htmlescape(Dropdown::getDropdownName(
                        'glpi_plugin_resources_employers',
                        $employee->getField("plugin_resources_employers_id"),
                    )),
                ];
                $rows[] = [
                    'label'  => __('New resource company', 'resources'),
                    'widget' => $captureRand(fn() => Employer::dropdown([
                        'name' => "employer_id",
                        'right' => 'all',
                        'used' => [$employee->getField('plugin_resources_employers_id')],
                        'on_change' => 'plugin_resources_load_button_changeresources_company();',
                    ]), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_company',
                    [
                        'action' => self::CHANGE_RESOURCECOMPANY,
                        'plugin_resources_employers_id' => '__VALUE__',
                    ],
                    'dropdown_employer_id' . $rand,
                );
                break;

            case self::CHANGE_RESOURCEDEPARTMENT:
                $rows[] = [
                    'label'  => __("Current department of the resource", "resources"),
                    'widget' => '&nbsp;' . htmlescape(Dropdown::getDropdownName(
                        'glpi_plugin_resources_departments',
                        $resource->getField("plugin_resources_departments_id"),
                    )),
                ];
                $rows[] = [
                    'label'  => __('New resource department', 'resources'),
                    'widget' => $captureRand(fn() => Department::dropdown([
                        'name' => "department_id",
                        'entity' => $resource->fields["entities_id"],
                        'right' => 'all',
                        'used' => [$resource->getField('plugin_resources_departments_id')],
                        'on_change' => 'plugin_resources_load_button_changeresources_department();',
                    ]), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_department',
                    [
                        'action' => self::CHANGE_RESOURCEDEPARTMENT,
                        'plugin_resources_departments_id' => '__VALUE__',
                    ],
                    'dropdown_department_id' . $rand,
                );
                break;

            case self::CHANGE_RESOURCESERVICE:
                $rows[] = [
                    'label'  => __("Current service of the resource", "resources"),
                    'widget' => '&nbsp;' . htmlescape(Dropdown::getDropdownName(
                        'glpi_plugin_resources_services',
                        $resource->getField("plugin_resources_services_id"),
                    )),
                ];
                $rows[] = [
                    'label'  => __('New resource service', 'resources'),
                    'widget' => $captureRand(fn() => Service::dropdownFromDepart(
                        $resource->fields["plugin_resources_departments_id"],
                        [
                            'name' => "service_id",
                            'value' => $resource->fields["plugin_resources_services_id"],
                            'entity' => $resource->fields["entities_id"],
                            'right' => 'all',
                            'used' => [$resource->getField('plugin_resources_services_id')],
                            'on_change' => 'plugin_resources_load_button_changeresources_service();',
                        ],
                    ), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_service',
                    [
                        'action' => self::CHANGE_RESOURCESERVICE,
                        'plugin_resources_services_id' => '__VALUE__',
                    ],
                    'dropdown_service_id' . $rand,
                );
                break;

            case self::CHANGE_RESOURCEROLE:
                $rows[] = [
                    'label'  => __("Current role of the resource", "resources"),
                    'widget' => '&nbsp;' . htmlescape(Dropdown::getDropdownName(
                        'glpi_plugin_resources_roles',
                        $resource->getField("plugin_resources_roles_id"),
                    )),
                ];
                $rows[] = [
                    'label'  => __('New resource role', 'resources'),
                    'widget' => $captureRand(fn() => Role::dropdownFromService(
                        $resource->fields["plugin_resources_services_id"],
                        [
                            'name' => "role_id",
                            'value' => $resource->fields["plugin_resources_roles_id"],
                            'entity' => $resource->fields["entities_id"],
                            'right' => 'all',
                            'used' => [$resource->getField('plugin_resources_roles_id')],
                            'on_change' => 'plugin_resources_load_button_changeresources_role();',
                        ],
                    ), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_role',
                    ['action' => self::CHANGE_RESOURCEROLE, 'plugin_resources_roles_id' => '__VALUE__'],
                    'dropdown_role_id' . $rand,
                );
                break;

            case self::CHANGE_RESOURCEFUNCTION:
                $rows[] = [
                    'label'  => __("Current function of the resource", "resources"),
                    'widget' => '&nbsp;' . htmlescape(Dropdown::getDropdownName(
                        'glpi_plugin_resources_resourcefunctions',
                        $resource->getField("plugin_functions_functions_id"),
                    )),
                ];
                $rows[] = [
                    'label'  => __('New resource function', 'resources'),
                    'widget' => $captureRand(fn() => ResourceFunction::dropdown([
                        'name' => "function_id",
                        'entity' => $resource->fields["entities_id"],
                        'right' => 'all',
                        'used' => [$resource->getField('plugin_resources_functions_id')],
                        'on_change' => 'plugin_resources_load_button_changeresources_function();',
                    ]), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_function',
                    [
                        'action' => self::CHANGE_RESOURCEFUNCTION,
                        'plugin_resources_functions_id' => '__VALUE__',
                    ],
                    'dropdown_function_id' . $rand,
                );
                break;

            case self::CHANGE_RESOURCETEAM:
                $rows[] = [
                    'label'  => __("Current team of the resource", "resources"),
                    'widget' => '&nbsp;' . htmlescape(Dropdown::getDropdownName(
                        'glpi_plugin_resources_teams',
                        $resource->getField("plugin_functions_teams_id"),
                    )),
                ];
                $rows[] = [
                    'label'  => __('New resource function', 'resources'),
                    'widget' => $captureRand(fn() => Team::dropdown([
                        'name' => "team_id",
                        'entity' => $resource->fields["entities_id"],
                        'right' => 'all',
                        'used' => [$resource->getField('plugin_resources_teams_id')],
                        'on_change' => 'plugin_resources_load_button_changeresources_team();',
                    ]), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_team',
                    ['action' => self::CHANGE_RESOURCETEAM, 'plugin_resources_teams_id' => '__VALUE__'],
                    'dropdown_team_id' . $rand,
                );
                break;

            case self::CHANGE_RESOURCEMATERIAL:
                $material = $capture(fn() => Html::textarea(['name' => "content"]));
                $material .= Ajax::updateItemOnInputTextEvent(
                    'content',
                    'plugin_resources_buttonchangeresources',
                    PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
                    ['load_button_changeresources' => true, 'action' => self::CHANGE_RESOURCEMATERIAL],
                );
                $rows[] = [
                    'label'  => __("Change material", "resources"),
                    'widget' => $material,
                ];
                break;

            case self::CHANGE_RESOURCEITEMAPPLICATION:
                $resource_item = new Resource_Item();
                $resource_items = $resource_item->find(
                    ['plugin_resources_resources_id' => $resource->fields['id'], 'itemtype' => Appliance::getType()],
                );
                $appliances = [];
                foreach ($resource_items as $it) {
                    $appliances[] = $it["items_id"];
                }
                $rows[] = [
                    'label'  => __('New Application to add to the resource', 'resources'),
                    'widget' => $captureRand(fn() => Appliance::dropdown([
                        'name' => "appliances_id",
                        'entity' => $resource->fields["entities_id"],
                        'right' => 'all',
                        'used' => $appliances,
                        'on_change' => 'plugin_resources_load_button_changeresources_application();',
                    ]), $rand),
                ];
                $js .= self::loadButtonJs(
                    'plugin_resources_load_button_changeresources_application',
                    [
                        'action' => self::CHANGE_RESOURCEITEMAPPLICATION,
                        'appliances_id' => '__VALUE__',
                    ],
                    'dropdown_appliances_id' . $rand,
                );
                break;
        }

        if (!empty($rows)) {
            TemplateRenderer::getInstance()->display('@resources/resource_change_fields.html.twig', [
                'rows'       => $rows,
                'row_class'  => $row_class,
                'cell_class' => $cell_class,
            ]);
        }

        if ($js !== '') {
            echo Html::scriptBlock($js);
        }
    }

    /**
     * Build the JS function reloading the action button area when a field changes.
     *
     * @param string $function_name name of the generated JS function
     * @param array  $params        AJAX parameters, merged after load_button_changeresources
     * @param string $observed      id of the input whose change feeds __VALUE__
     *
     * @return string the function declaration, to be emitted in a script block
     */
    private static function loadButtonJs(string $function_name, array $params, string $observed): string
    {
        $js = "function {$function_name}(){";
        $js .= Ajax::updateItemJsCode(
            'plugin_resources_buttonchangeresources',
            PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
            ['load_button_changeresources' => true] + $params,
            $observed,
            false,
        );
        $js .= "}";

        return $js;
    }

    /**
     * @param $action_id
     * @param $options
     */
    public function loadButtonChangeResources($action_id, $options)
    {
        $display = false;

        //Display for each action
        switch ($action_id) {
            case self::CHANGE_RESOURCEMANAGER:
                if (isset($options['users_id'])
                    && !empty($options['users_id'])
                    && $options['users_id'] != 0) {
                    $display = true;
                }
                break;
            case self::CHANGE_RESOURCESALE:
                if (isset($options['users_id_sales'])
                    && !empty($options['users_id_sales'])
                    && $options['users_id_sales'] != 0) {
                    $display = true;
                }
                break;

            case self::CHANGE_ACCESSPROFIL:
                if (isset($options['plugin_resources_habilitations_id'])
                    && !empty($options['plugin_resources_habilitations_id'])
                    && $options['plugin_resources_habilitations_id'] != 0) {
                    $display = true;
                }
                break;
            case self::CHANGE_CONTRACTTYPE:
                if (isset($options['plugin_resources_contracttypes_id'])
                    && !empty($options['plugin_resources_contracttypes_id'])
                    && $options['plugin_resources_contracttypes_id'] != 0) {
                    $display = true;
                }
                break;
            case self::CHANGE_AGENCY:
                if (isset($options['locations_id'])
                    && !empty($options['locations_id'])
                    && $options['locations_id'] != 0) {
                    $display = true;
                }
                break;

            case self::CHANGE_RESOURCEMATERIAL:
            case self::CHANGE_RESOURCEITEMAPPLICATION:
            case self::CHANGE_TRANSFER:
                $display = true;
                break;
            case self::CHANGE_RESOURCEINFORMATIONS:
                if (isset($options['name'])
                    && !empty($options['name'])
                    && isset($options['firstname'])
                    && !empty($options['firstname'])) {
                    $display = true;
                }

                break;
            case self::CHANGE_NAME:
                if (isset($options['name'])
                    && !empty($options['name'])) {
                    $display = true;
                }

                break;
            case self::CHANGE_RESOURCECOMPANY:
                if (isset($options['plugin_resources_employers_id'])
                    && !empty($options['plugin_resources_employers_id'])) {
                    $display = true;
                }

                break;
            case self::CHANGE_RESOURCEDEPARTMENT:
                if (isset($options['plugin_resources_departments_id'])
                    && !empty($options['plugin_resources_departments_id'])) {
                    $display = true;
                }

                break;
            case self::CHANGE_RESOURCESERVICE:
                if (isset($options['plugin_resources_services_id'])
                    && !empty($options['plugin_resources_services_id'])) {
                    $display = true;
                }
                break;
            case self::CHANGE_RESOURCEROLE:
                if (isset($options['plugin_resources_roles_id'])
                    && !empty($options['plugin_resources_roles_id'])) {
                    $display = true;
                }
                break;
            case self::CHANGE_RESOURCEFUNCTION:
                if (isset($options['plugin_resources_functions_id'])
                    && !empty($options['plugin_resources_functions_id'])) {
                    $display = true;
                }

                break;
            case self::CHANGE_RESOURCETEAM:
                if (isset($options['plugin_resources_teams_id'])
                    && !empty($options['plugin_resources_teams_id'])) {
                    $display = true;
                }

                break;
        }

        if ($display) {
            echo "<div class='next'>";
            echo Html::submit(
                __s('Starting change', 'resources'),
                ['name' => 'changeresources', 'class' => 'btn btn-success'],
            );
            echo "</div>";
        }
    }

    /**
     * Launch of change for ticket creation
     *
     * @param       $plugin_resources_resources_id
     * @param       $action_id
     * @param array $options
     */
    public static function startingChange($plugin_resources_resources_id, $action_id, $options = [])
    {
        global $DB;

        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);

        $dbu = new DbUtils();

        //Preparation of ticket data
        $data = [];
        $data['itilcategories_id'] = 0;
        $data['tickettemplates_id'] = 0;
        $data['entities_id'] = $resource->fields['entities_id'];
        $data['plugin_resources_resources_id'] = $plugin_resources_resources_id;

        //Search for the entity-related category for that action
        $resource_change = new Resource_Change();
        if ($resource_change->getFromDBByCrit([
            'actions_id' => $action_id,
            'entities_id' => $resource->fields['entities_id'],
        ])) {
            $data['itilcategories_id'] = $resource_change->fields['itilcategories_id'];

            //Search of the ticket template
            $itil_category = new ITILCategory();
            if ($itil_category->getFromDB($data['itilcategories_id'])) {
                $data['tickettemplates_id'] = $itil_category->fields['tickettemplates_id_demand'];
            }
        }

        // name and content of ticket
        switch ($action_id) {
            case self::CHANGE_RESOURCEMANAGER:
                $data['name'] = __("Change manager for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change manager for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";
                $data['content'] .= __("Manager for the current resource", 'resources') . "&nbsp;:&nbsp;" .
                    $dbu->getUserName($resource->getField('users_id')) . "\n";
                $data['content'] .= __("New resource manager", 'resources') . "&nbsp;:&nbsp;" .
                    $dbu->getUserName($options['users_id']) . "\n";

                $input['users_id'] = $options['users_id'];
                break;

            case self::CHANGE_RESOURCESALE:
                $data['name'] = __("Change of sales manager for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change of sales manager for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";
                $data['content'] .= __("Sales manager for the current resource", 'resources') . "&nbsp;:&nbsp;" .
                    $dbu->getUserName($resource->getField('users_id_sales')) . "\n";
                $data['content'] .= __("New sales manager for the resource", 'resources') . "&nbsp;:&nbsp;" .
                    $dbu->getUserName($options['users_id_sales']) . "\n";

                $input['users_id_sales'] = $options['users_id_sales'];
                break;
            case self::CHANGE_ACCESSPROFIL:
                $data['name'] = __("Change the access profile for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change the access profile for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";

                $data['content'] .= __("Current access profile of the resource", 'resources') . "&nbsp;:&nbsp;";

                $criteria = [
                    'SELECT' => [
                        'glpi_plugin_resources_habilitations.id',
                    ],
                    'FROM' => 'glpi_plugin_resources_resourcehabilitations',
                    'LEFT JOIN' => [
                        'glpi_plugin_resources_habilitations' => [
                            'ON' => [
                                'glpi_plugin_resources_resourcehabilitations' => 'plugin_resources_habilitations_id',
                                'glpi_plugin_resources_habilitations' => 'id',
                            ],
                        ],
                        'glpi_plugin_resources_habilitationlevels' => [
                            'ON' => [
                                'glpi_plugin_resources_habilitations' => 'plugin_resources_habilitationlevels_id',
                                'glpi_plugin_resources_habilitationlevels' => 'id',
                            ],
                        ],
                    ],
                    'WHERE' => [
                        'plugin_resources_resources_id' => $plugin_resources_resources_id,
                        'glpi_plugin_resources_habilitationlevels.is_mandatory_creating_resource' => 1,
                    ],
                ];

                foreach ($DB->request($criteria) as $habilitation) {
                    $data['content'] .= Dropdown::getDropdownName(
                        'glpi_plugin_resources_habilitations',
                        $habilitation['id'],
                    ) . "\n";
                }

                $data['content'] .= __("New access profile of the resource", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName(
                        'glpi_plugin_resources_habilitations',
                        $options['plugin_resources_habilitations_id'],
                    ) . "\n";

                $input['plugin_resources_habilitations_id'] = $options['plugin_resources_habilitations_id'];
                break;
            case self::CHANGE_CONTRACTTYPE:
                $data['name'] = __("Change the type of contract for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change the type of contract for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";
                $data['content'] .= __("Current contract type of the resource", 'resources') . " " . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName(
                        'glpi_plugin_resources_contracttypes',
                        $resource->getField('plugin_resources_contracttypes_id'),
                    ) . "\n";
                $data['content'] .= __("New type of contract", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName(
                        'glpi_plugin_resources_contracttypes',
                        $options['plugin_resources_contracttypes_id'],
                    ) . "\n";

                $input['plugin_resources_contracttypes_id'] = $options['plugin_resources_contracttypes_id'];
                $input['contract_type_change'] = 1;
                $input['date_of_last_contract_type'] = !empty($options['date_of_change']) ? $options['date_of_change'] : date(
                    'Y-m-d',
                );
                $input['last_contract_type'] = $resource->getField('plugin_resources_contracttypes_id');
                break;
            case self::CHANGE_AGENCY:
                $data['name'] = __("Change of agency for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change of agency for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";
                $data['content'] .= __("Current agency of the resource", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName('glpi_locations', $resource->getField('locations_id')) . "\n";
                $data['content'] .= __("New resource agency", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName('glpi_locations', $options['locations_id']) . "\n";
                if (!empty($options['plugin_resources_teams_id'])) {
                    $data['content'] .= __("Current team of the resource", 'resources') . "&nbsp;:&nbsp;" .
                        Dropdown::getDropdownName(
                            'glpi_plugin_resources_teams',
                            $resource->getField('plugin_resources_teams_id'),
                        ) . "\n";
                    $data['content'] .= __("New resource team", 'resources') . "&nbsp;:&nbsp;" .
                        Dropdown::getDropdownName(
                            'glpi_plugin_resources_teams',
                            $options['plugin_resources_teams_id'],
                        ) . "\n";
                }

                $input['locations_id'] = $options['locations_id'];
                if (!empty($options['plugin_resources_teams_id'])) {
                    $input['plugin_resources_teams_id'] = $options['plugin_resources_teams_id'];
                }

                $input['date_of_last_location'] = !empty($options['date_of_change']) ? $options['date_of_change'] : date(
                    'Y-m-d',
                );
                $input['last_location'] = $resource->getField('locations_id');
                break;
            case self::CHANGE_RESOURCEINFORMATIONS:
                $data['name'] = __("Change information for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change information for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";
                $data['content'] .= __("Current name of the resource", 'resources') . "&nbsp;:&nbsp;" .
                    $resource->getField('name') . "\n";
                $data['content'] .= __("New resource name", 'resources') . "&nbsp;:&nbsp;" .
                    $options['name'] . "\n";
                if (isset($options['firstname'])) {
                    $data['content'] .= __("Current firstname of the resource", 'resources') . "&nbsp;:&nbsp;" .
                        $resource->getField('firstname') . "\n";
                    $data['content'] .= __("New resource firstname", 'resources') . "&nbsp;:&nbsp;" .
                        $options['firstname'] . "\n";
                }
                $data['content'] .= __("Current departure date of the resource", 'resources') . "&nbsp;:&nbsp;" .
                    $resource->getField('date_end') . "\n";
                $data['content'] .= __("New resource departure date", 'resources') . "&nbsp;:&nbsp;" .
                    $options['date_end'] . "\n";

                $input['name'] = $options['name'];
                $input['firstname'] = isset($options['firstname']) ? $options['firstname'] : $resource->getField('firstname');
                $input['date_end'] = $options['date_end'];
                break;
            case self::CHANGE_NAME:
                $data['name']    = __("Change information for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change information for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";
                $data['content'] .= __("Current name of the resource", 'resources') . "&nbsp;:&nbsp;" .
                    $resource->getField('name') . "\n";
                $data['content'] .= __("New resource name", 'resources') . "&nbsp;:&nbsp;" .
                    $options['name'] . "\n";

                $input['name']      = $options['name'];
                break;
            case self::CHANGE_RESOURCECOMPANY:
                $data['name'] = __("Change of company for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change of company for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";
                $employee = new Employee();
                $employee->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id]);
                $data['content'] .= __("Current company of the resource", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName(
                        'glpi_plugin_resources_employers',
                        $employee->getField('plugin_resources_employers_id'),
                    ) . "\n";
                $data['content'] .= __("New resource company", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName('glpi_plugin_resources_employers', $options['employer_id']) . "\n";

                $input['plugin_resources_employers_id'] = $options['employer_id'];
                $input['id'] = $employee->getID();

                $employee->update($input);

                break;
            case self::CHANGE_RESOURCEDEPARTMENT:
                $data['name'] = __("Change of department for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change of department for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";
                $employee = new Employee();
                $employee->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id]);
                $data['content'] .= __("Current department of the resource", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName(
                        'glpi_plugin_resources_departments',
                        $resource->getField('plugin_resources_departments_id'),
                    ) . "\n";
                $data['content'] .= __("New resource department", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName('glpi_plugin_resources_departments', $options['department_id']) . "\n";

                $input['plugin_resources_departments_id'] = $options['department_id'];

                break;
            case self::CHANGE_RESOURCESERVICE:
                $data['name'] = __("Change of service for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change of service for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";
                $employee = new Employee();
                $employee->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id]);
                $data['content'] .= __("Current service of the resource", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName(
                        'glpi_plugin_resources_services',
                        $resource->getField('plugin_resources_services_id'),
                    ) . "\n";
                $data['content'] .= __("New resource service", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName('glpi_plugin_resources_services', $options['service_id']) . "\n";

                $input['plugin_resources_services_id'] = $options['service_id'];

                break;
            case self::CHANGE_RESOURCEROLE:
                $data['name'] = __("Change of role for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change of role for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";
                $employee = new Employee();
                $employee->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id]);
                $data['content'] .= __("Current role of the resource", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName(
                        'glpi_plugin_resources_roles',
                        $resource->getField('plugin_resources_roles_id'),
                    ) . "\n";
                $data['content'] .= __("New resource role", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName('glpi_plugin_resources_roles', $options['role_id']) . "\n";

                $input['plugin_resources_roles_id'] = $options['role_id'];

                break;
            case self::CHANGE_RESOURCEFUNCTION:
                $data['name'] = __("Change of function for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change of function for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";
                $employee = new Employee();
                $employee->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id]);
                $data['content'] .= __("Current function of the resource", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName(
                        'glpi_plugin_resources_resourcefunctions',
                        $resource->getField('plugin_resources_functions_id'),
                    ) . "\n";
                $data['content'] .= __("New resource function", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName('glpi_plugin_resources_resourcefunctions', $options['function_id']) . "\n";

                $input['plugin_resources_functions_id'] = $options['function_id'];

                break;
            case self::CHANGE_RESOURCETEAM:
                $data['name'] = __("Change of team for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = __("Change of team for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id) . "\n";

                $data['content'] .= __("Current team of the resource", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName(
                        'glpi_plugin_resources_teams',
                        $resource->getField('plugin_resources_teams_id'),
                    ) . "\n";
                $data['content'] .= __("New resource team", 'resources') . "&nbsp;:&nbsp;" .
                    Dropdown::getDropdownName('glpi_plugin_resources_teams', $options['role_id']) . "\n";

                $input['plugin_resources_teams_id'] = $options['team_id'];

                break;
            case self::CHANGE_RESOURCEMATERIAL:
                $data['name'] = __("Change material for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = $options['content'];

                break;
            case self::CHANGE_RESOURCEITEMAPPLICATION:
                $data['name'] = __("Add application for", 'resources') . " " .
                    Resource::getResourceName($plugin_resources_resources_id);
                $data['content'] = sprintf(
                    __("The added appliance is %s ", 'resources'),
                    Dropdown::getDropdownName('glpi_appliances', $options['appliances_id']),
                );
                $resource_item = new Resource_Item();
                $inputInfo = [];
                $inputInfo['itemtype'] = Appliance::getType();
                $inputInfo['items_id'] = $options['appliances_id'];
                $inputInfo['plugin_resources_resources_id'] = $plugin_resources_resources_id;
                $resource_item->add($inputInfo);
                break;
        }

        $input['id'] = $plugin_resources_resources_id;
        $input['send_notification'] = 0;
        //update resource
        $resource->update($input);

        self::createTicket($data);

        $linkad = new LinkAd();
        if ($linkad->getFromDBByCrit(["plugin_resources_resources_id" => $plugin_resources_resources_id])) {
            $input2 = [];
            $input2['action_done'] = 0;
            $input2['id'] = $linkad->getID();
            $linkad->update($input2);
        }
    }

    /**
     * Setup form
     */
    public function showConfigForm()
    {
        TemplateRenderer::getInstance()->display('@resources/resource_change_config_form.html.twig', [
            'form_action' => self::getFormURL(),
            'title'       => __("Managing change actions", 'resources'),
            'setup_url'   => "./resource_change.form.php",
            'setup_label' => __('Setup'),
        ]);
    }

    /**
     * Setup form for each action
     *
     * @return bool
     */
    public function showFormActions()
    {

        if (!$this->canView()) {
            return false;
        }
        if (!$this->canCreate()) {
            return false;
        }

        $actions = self::getAllActions(true);
        $actions[self::BADGE_RESTITUTION] = self::getNameActions(self::BADGE_RESTITUTION);
        //delete mutation
        unset($actions[self::CHANGE_TRANSFER]);

        $canedit = true;

        // Build the action cell (label + dropdown + AJAX reload script). The
        // script depends on the rand returned by the dropdown, so both are
        // captured together as a single trusted HTML fragment.
        ob_start();
        echo __('Action') . '&nbsp;';
        $rand = Dropdown::showFromArray('actions_id', $actions, ['on_change' => 'plugin_resources_load_entity();']);
        // Dropdown list according to the entity
        echo "<script type='text/javascript'>";
        echo "function plugin_resources_load_entity(){";
        $params = ['action'     => 'loadEntity',
            'actions_id' => '__VALUE__'];
        Ajax::updateItemJsCode(
            'plugin_resources_entity_itil_categories',
            PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
            $params,
            'dropdown_actions_id' . $rand,
        );
        echo ";";
        $params = ['action'     => 'clean',
            'actions_id' => '__VALUE__'];
        Ajax::updateItemJsCode(
            'plugin_resources_button_add',
            PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
            $params,
            'dropdown_actions_id' . $rand,
        );
        echo "}";
        echo "</script>";
        $action_cell = (string) ob_get_clean();

        TemplateRenderer::getInstance()->display('@resources/resource_change_actions_form.html.twig', [
            'form_action' => self::getFormURL(),
            'alert_text'  => __('Define entity & ticket category for each change action', 'resources'),
            'title'       => __("Managing change actions", 'resources'),
            'action_cell' => $action_cell,
        ]);

        self::listItems($canedit);
    }

    /**
     * List of entities and categories already added
     *
     * @param $canedit
     */
    private function listItems($canedit)
    {
        // Entity already added for this action
        $datas = $this->find([], "actions_id");

        $rand = mt_rand();

        if (count($datas) > 0) {
            echo "<div class='left'>";
            if ($canedit) {
                Html::openMassiveActionsForm('massResource_Change' . $rand);
                $massiveactionparams = ['item' => __CLASS__, 'container' => 'massResource_Change' . $rand];
                Html::showMassiveActions($massiveactionparams);
            }
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr>";
            echo "<th colspan='4'>" . __('List') . "</th>";
            echo "</tr>";
            echo "<tr>";
            echo "<th width='10'>";
            if ($canedit) {
                echo Html::getCheckAllAsCheckbox('massResource_Change' . $rand);
            }
            echo "</th>";
            echo "<th>" . __('Action') . "</th>";
            echo "<th>" . __('Entity') . "</th>";
            echo "<th>" . __('Category') . "</th>";
            echo "</tr>";
            foreach ($datas as $action) {
                echo "<tr class='tab_bg_1'>";
                echo "<td width='10'>";
                if ($canedit) {
                    Html::showMassiveActionCheckBox(__CLASS__, $action['id']);
                }
                echo "</td>";
                //DATA LINE
                echo "<td>" . self::getNameActions($action['actions_id']) . "</td>";
                echo "<td>" . Dropdown::getDropdownName('glpi_entities', $action['entities_id']) . "</td>";
                echo "<td>" . Dropdown::getDropdownName('glpi_itilcategories', $action['itilcategories_id']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            if ($canedit) {
                $massiveactionparams['ontop'] = false;
                Html::showMassiveActions($massiveactionparams);
                Html::closeForm();
            }
            echo "</div>";
        }

    }

    /**
     * @param $actions_id
     */
    public function loadEntity($actions_id)
    {
        global $CFG_GLPI;

        // Entity already added for this action
        $datas = $this->find(['actions_id' => $actions_id]);

        $used_entities = [];
        if ($datas) {
            foreach ($datas as $field) {
                $used_entities[] = $field['entities_id'];
            }
        }

        echo __('Entity') . '&nbsp;';
        $mrand = Dropdown::show("Entity", [
            'name' => 'entities_id',
            'used' => $used_entities,
            'on_change' => 'plugin_resources_load_category();',
        ]);

        //Dropdown list according to the entity
        echo "<script type='text/javascript'>";
        echo "function plugin_resources_load_category(){";
        $params = ['action' => 'loadCategory', 'entities_id' => '__VALUE__'];
        Ajax::updateItemJsCode(
            'plugin_resource_itil_categories',
            PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
            $params,
            'dropdown_entities_id' . $mrand,
        );
        echo "};";
        echo "</script>";

        echo "<span id='plugin_resource_itil_categories'>";
        self::displayCategory($_SESSION['glpiactive_entity']);
        echo "</span>";
    }

    /**
     * Display dropdown list of the category
     *
     * @param $entities_id
     */
    public static function displayCategory($entities_id)
    {
        global $CFG_GLPI;

        echo __('Category') . "&nbsp;";
        $rand = Dropdown::show('ITILCategory', [
            'name' => 'itilcategories_id',
            'entity' => $entities_id,
            'condition' => ['is_request' => 1],
            'on_change' => 'plugin_resources_load_buttonadd();',
        ]);

        echo "<script type='text/javascript'>";
        echo "function plugin_resources_load_buttonadd(){";
        $params = ['action' => 'loadButtonAdd', 'itilcategories_id' => '__VALUE__'];
        Ajax::updateItemJsCode(
            'plugin_resources_button_add',
            PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
            $params,
            'dropdown_itilcategories_id' . $rand,
        );
        echo "};";
        echo "</script>";
    }

    /**
     * @param $itilcategories_id
     */
    public static function displayButtonAdd($itilcategories_id)
    {
        if ($itilcategories_id != 0) {
            echo Html::submit(_sx('button', 'Add'), ['name' => 'add_entity_category', 'class' => 'btn btn-primary']);
        }
    }

    /**
     * Creation of ticket for change
     *
     * @param $data
     *
     * @return bool
     */
    public static function createTicket($data)
    {
        $result = false;
        $tt = new TicketTemplate();

        // Create ticket based on ticket template and entity informations of ticketrecurrent
        if ($tt->getFromDB($data['tickettemplates_id'])) {
            // Get default values for ticket
            $input = Ticket::getDefaultValues($data['entities_id']);
            // Apply tickettemplates predefined values
            $ttp = new TicketTemplatePredefinedField();
            $predefined = $ttp->getPredefinedFields($data['tickettemplates_id'], true);

            if (count($predefined)) {
                foreach ($predefined as $predeffield => $predefvalue) {
                    $input[$predeffield] = $predefvalue;
                }
            }
        }

        // Set date to creation date
        $createtime = date('Y-m-d H:i:s');
        $input['date'] = $createtime;
        $input['type'] = Ticket::DEMAND_TYPE;
        $input['itilcategories_id'] = $data['itilcategories_id'];
        // Compute time_to_resolve if predefined based on create date
        if (isset($predefined['time_to_resolve'])) {
            $input['time_to_resolve'] = Html::computeGenericDateTimeSearch(
                $predefined['time_to_resolve'],
                false,
                strtotime($createtime),
            );
        }
        // Set entity
        $input['entities_id'] = $data['entities_id'];
        $res = new Resource();
        if ($res->getFromDB($data['plugin_resources_resources_id'])) {
            $default_use_notif = Entity::getUsedConfig('is_notif_enable_default', $input['entities_id'], '', 1);
            $input['users_id_recipient'] = Session::getLoginUserID();
            $input['_users_id_requester'] = [Session::getLoginUserID()];
            $input['_users_id_requester_notif']['use_notification'] = [$default_use_notif];

            $alternativeEmail = '';
            if (filter_var(Session::getLoginUserID(), FILTER_VALIDATE_EMAIL) !== false) {
                $alternativeEmail = Session::getLoginUserID();
            }
            $input['_users_id_requester_notif']['alternative_email'] = [$alternativeEmail];

            $input["items_id"] = [Resource::class => [$data['plugin_resources_resources_id']]];
        }
        $input["name"] = $data['name'];
        $input["content"] = $data['content'];
        $input["content"] .= addslashes("\n\n");
        $input['id'] = 0;
        $ticket = new Ticket();

        if ($tid = $ticket->add($input)) {
            $msg = __('Create a end treatment ticket', 'resources') . " OK - ($tid)"; // Success
            $result = true;
        } else {
            $msg = __('Failed operation'); // Failure
        }
        if ($tid) {
            $config = new Config();
            $config->getFromDB(1);
            if ($config->fields["default_assignment_group"]) {
                $groupticket = new Group_Ticket();
                $groupticket->fields['tickets_id'] = $tid;
                $groupticket->fields['groups_id'] = $config->fields["default_assignment_group"];
                $groupticket->fields['type'] = CommonITILActor::ASSIGN;
                unset($groupticket->fields["id"]);
                $groupticket->add($groupticket->fields);
            }
            $changes[0] = 0;
            $changes[1] = '';
            $changes[2] = addslashes($msg);
            Log::history(
                $data['plugin_resources_resources_id'],
                Resource::class,
                $changes,
                '',
                Log::HISTORY_LOG_SIMPLE_MESSAGE,
            );
        }
        return $result;
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
                        `entities_id`       int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `actions_id`        int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `itilcategories_id` varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `comment`           TEXT COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`),
                        KEY `entities_id` (`entities_id`),
                        KEY `itilcategories_id` (`itilcategories_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
