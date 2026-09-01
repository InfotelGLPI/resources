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
use CommonGLPI;
use DBConnection;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Item_Ticket;
use Location;
use Migration;
use Phone;
use Session;
use Ticket;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class LinkAd
 */
class LinkAd extends CommonDBTM
{
    public static $rightname = 'plugin_resources_checklist';

    public const RESOURCES_CHECKLIST_IN = 1;
    public const RESOURCES_CHECKLIST_OUT = 2;
    public const RESOURCES_CHECKLIST_TRANSFER = 3;

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @param integer $nb Number of items
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return __('Update LDAP directory', 'resources');
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
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return
     **/
    public static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
    }


    /**
     * Get Tab Name used for itemtype
     *
     * NB : Only called for existing object
     *      Must check right on what will be displayed + template
     *
     * @param CommonGLPI $item Item on which the tab need to be displayed
     * @param boolean $withtemplate is a template object ? (default 0)
     *
     * @return string tab name
     **@since 0.83
     *
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate) {
            if ($item->getType() == 'Ticket'
                && $item->getID()
                && $this->canView()) {
                $items = new Item_Ticket();
                if ($items->getFromDBByCrit([
                    "tickets_id" => $item->getID(),
                    "itemtype" => Resource::getType(),
                ])) {
                    $adConfig = new Adconfig();
                    $adConfig->getFromDB(1);
                    $adConfig->fields = $adConfig->prepareFields($adConfig->fields);
                    if ((is_array($adConfig->fields["creation_categories_id"])
                            && in_array(
                                $item->getField('itilcategories_id'),
                                $adConfig->getField("creation_categories_id"),
                            ))
                        || (is_array($adConfig->fields["modification_categories_id"])
                            && in_array(
                                $item->getField('itilcategories_id'),
                                $adConfig->getField("modification_categories_id"),
                            ))
                        || (is_array($adConfig->fields["deletion_categories_id"])
                            && in_array(
                                $item->getField('itilcategories_id'),
                                $adConfig->getField("deletion_categories_id"),
                            ))) {
                        if ($_SESSION['glpishow_count_on_tabs']) {
                            return self::createTabEntry(self::getTypeName(2), self::countForItem($item));
                        }
                        return self::createTabEntry(self::getTypeName(2));
                    }
                }
            }
        }
        return '';
    }

    /**
     * show Tab content
     *
     * @param CommonGLPI $item Item on which the tab need to be displayed
     * @param integer $tabnum tab number (default 1)
     * @param boolean $withtemplate is a template object ? (default 0)
     *
     * @return boolean
     **@since 0.83
     *
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $ID = $item->getField('id');

        if ($item->getType() == Ticket::getType()) {
            $items = new Item_Ticket();
            if ($items->getFromDBByCrit(["tickets_id" => $ID, "itemtype" => Resource::getType()])) {
                self::showFromResources($items->getField("items_id"), $item);
            }


            return true;
        }
        return false;
    }

    /**
     * @param $item
     *
     * @return int
     */
    public static function countForItem($item)
    {
        if ($item->getField('is_leaving') == 1) {
            $checklist_type = self::RESOURCES_CHECKLIST_OUT;
        } else {
            $checklist_type = self::RESOURCES_CHECKLIST_IN;
        }
        $dbu = new DbUtils();
        $restrict = [
            "plugin_resources_resources_id" => $item->getField('id'),
            "checklist_type" => $checklist_type,
            "NOT" => ["is_checked" => 1],
        ];
        $nb = $dbu->countElementsInTable(['glpi_plugin_resources_checklists'], $restrict);

        return $nb;
    }


    /**
     * @param       $ID
     * @param array $options
     *
     * @return bool
     */
    public function showForm($ID, $options = [])
    {
        if (!$this->canView()) {
            return false;
        }

        $plugin_resources_contracttypes_id = -1;
        if (isset($options['plugin_resources_contracttypes_id'])) {
            $plugin_resources_contracttypes_id = $options['plugin_resources_contracttypes_id'];
        }

        $checklist_type = -1;
        if (isset($options['checklist_type'])) {
            $checklist_type = $options['checklist_type'];
        }

        $plugin_resources_resources_id = -1;

        if (isset($options['plugin_resources_resources_id'])) {
            $plugin_resources_resources_id = $options['plugin_resources_resources_id'];
            $item = new Resource();
            if ($item->getFromDB($plugin_resources_resources_id)) {
                $options["entities_id"] = $item->fields["entities_id"];
            }
        }

        if ($ID > 0) {
            $this->check($ID, READ);
        } else {
            // Create item
            $this->check(-1, UPDATE, $input);
        }

        TemplateRenderer::getInstance()->display('@resources/linkad_form.html.twig', [
            'item'              => $this,
            'params'            => $options,
            'resources_id'      => $plugin_resources_resources_id,
            'contracttypes_id'  => $ID > 0
                ? $this->fields["plugin_resources_contracttypes_id"]
                : $plugin_resources_contracttypes_id,
            'checklist_type'    => $ID > 0 ? $this->fields["checklist_type"] : $checklist_type,
        ]);

        return true;
    }

    /**
     * show from resources
     *
     * @param        $plugin_resources_resources_id
     * @param        $checklist_type
     * @param string $withtemplate
     *
     * @return bool
     */
    public static function showFromResources($plugin_resources_resources_id, $ticket)
    {
        global $CFG_GLPI;

        if (!self::canView()) {
            return false;
        }

        $config = new Config();
        $configAD = new Adconfig();
        $config->getFromDB(1);
        $configAD->getFromDB(1);
        $configAD->fields = $configAD->prepareFields($configAD->fields);
        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);
        $canedit = $resource->can($plugin_resources_resources_id, UPDATE);
        $entities_id = $resource->fields["entities_id"];
        $plugin_resources_contracttypes_id = $resource->fields["plugin_resources_contracttypes_id"];
        $enddate = $resource->getField("date_end");
        $linkAD = new self();
        $linkAD->getEmpty();
        $islink = $linkAD->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);
        if (!$islink) {
            $ret = self::processLogin($resource);
            $linkAD->fields["login"] = $ret[0];
            $logAvailable = $ret[1];

            $mail = self::processMail($resource, $linkAD->fields["login"]);
            $linkAD->fields["mail"] = $mail;
            $role = Dropdown::getDropdownName(Role::getTable(), $resource->fields['plugin_resources_roles_id']);
            $linkAD->fields["role"] = $role;
            $service = Dropdown::getDropdownName(
                Service::getTable(),
                $resource->fields['plugin_resources_services_id'],
            );
            $linkAD->fields["service"] = $service;
            $location = Dropdown::getDropdownName(Location::getTable(), $resource->fields['locations_id']);
            $linkAD->fields["location"] = $location;
        }
        $ID = $linkAD->getID();

        $employee = new Employee();
        $employee->getFromDBByCrit(["plugin_resources_resources_id" => $resource->getID()]);

        $login_option = ['value' => $linkAD->fields["login"]];
        if ($islink) {
            // The AD account already exists, so its login is fixed. Read-only rather than
            // disabled: the field keeps being posted, and front/linkad.form.php overwrites
            // it from the stored value on updateAD/disableAD anyway.
            $login_option['readonly'] = 'readonly';
        }

        $ad_buttons = [];
        if (!$islink && !$linkAD->fields["action_done"] && in_array(
            $ticket->fields["itilcategories_id"],
            $configAD->fields["creation_categories_id"],
        ) && $logAvailable) {
            $ad_buttons[] = ['name' => 'createAD', 'label' => _x('button', 'Create user in AD', 'resources')];
        }
        if ($islink && !$linkAD->fields["action_done"] && in_array(
            $ticket->fields["itilcategories_id"],
            $configAD->fields["modification_categories_id"],
        )) {
            $ad_buttons[] = ['name' => 'updateAD', 'label' => _x('button', 'Modify user in AD', 'resources')];
        }
        if ($islink && !$linkAD->fields["action_done"] && in_array(
            $ticket->fields["itilcategories_id"],
            $configAD->fields["deletion_categories_id"],
        )) {
            $ad_buttons[] = ['name' => 'disableAD', 'label' => _x('button', 'Disable user in AD', 'resources')];
        }

        TemplateRenderer::getInstance()->display('@resources/linkad_resource_form.html.twig', [
            'form_action'        => Toolbox::getItemTypeFormURL(self::getType()),
            'resources_id'       => $plugin_resources_resources_id,
            'ticket_id'          => $ticket->getID(),
            'contracttypes_id'   => $plugin_resources_contracttypes_id,
            'entities_id'        => $entities_id,
            'enddate'            => $enddate,
            'id'                 => $ID,
            'can_edit'           => self::canCreate() && $canedit,
            'login_input'        => Html::input('login', $login_option),
            'department'         => Dropdown::getDropdownName(
                'glpi_plugin_resources_departments',
                $resource->getField("plugin_resources_departments_id"),
            ),
            'resource_name'      => $resource->getField("name"),
            'resource_firstname' => $resource->getField("firstname"),
            'phone_input'        => Html::input('phone', ['value' => $linkAD->fields["phone"]]),
            'mail_input'         => Html::input('mail', ['type' => 'email', 'value' => $linkAD->fields["mail"]]),
            'company'            => Dropdown::getDropdownName(
                'glpi_plugin_resources_employers',
                $employee->getField("plugin_resources_employers_id"),
            ),
            'contract'           => Dropdown::getDropdownName(
                'glpi_plugin_resources_contracttypes',
                $resource->getField("plugin_resources_contracttypes_id"),
            ),
            'cellphone_input'    => Html::input('cellphone', ['value' => $linkAD->fields["cellphone"]]),
            'role_input'         => Html::input('role', ['value' => $linkAD->fields["role"]]),
            'service_label'      => Service::getTypeName(1),
            'service_input'      => Html::input('service', ['value' => $linkAD->fields["service"]]),
            'location_label'     => Location::getTypeName(1),
            'location_input'     => Html::input('location', ['value' => $linkAD->fields["location"]]),
            'ad_buttons'         => $ad_buttons,
        ]);
    }


    public static function processLogin(Resource $resource)
    {
        $config = new Adconfig();
        $config->getFromDB(1);
        $login = self::getLoginFromRule(
            $resource->fields["firstname"],
            $resource->fields["name"],
            $config->fields["first_form"],
        );
        $ldap = new LDAP();
        $exist = $ldap->existingUser($login);
        if ($exist) {
            $login = self::getLoginFromRule(
                $resource->fields["firstname"],
                $resource->fields["name"],
                $config->fields["second_form"],
            );
            $exist = $ldap->existingUser($login);
            if ($exist) {
                return [__("existing login", "resources"), false];
            } else {
                return [$login, true];
            }
        } else {
            return [$login, true];
        }
    }

    public static function processMail(Resource $resource, $login)
    {
        $config = new Adconfig();
        $config->getFromDB(1);
        $mail = "";
        if ($config->fields["mail_prefix"] == 2) {
            $mail = $login;
        } elseif ($config->fields["mail_prefix"] == 1) {
            $nametab = explode(" ", strtolower($resource->fields["name"]));
            $name = "";

            foreach ($nametab as $namepart) {
                $name .= $namepart;
            }

            $firstnametab = explode(" ", strtolower($resource->fields["firstname"]));
            $firstname = "";

            foreach ($firstnametab as $namepart) {
                $firstname .= $namepart;
            }

            $prefix = $firstname . "." . $name;
            $mail = $prefix;
        }
        $mail .= "@" . $config->fields["mail_suffix"];
        return $mail;
    }

    public static function getLoginFromRule($firstname, $name, $conf)
    {
        switch ($conf) {
            case 1:
                //            $name = strtolower($name);
                $nametab = explode(" ", strtolower($name));
                $name = "";

                foreach ($nametab as $namepart) {
                    $name .= $namepart;
                }
                $firstnametab = explode(" ", strtolower($firstname));
                $firstname = "";

                foreach ($firstnametab as $namepart) {
                    $firstname .= substr($namepart, 0, 1);
                }

                $login = $firstname . $name;
                break;
            case 2:
                //            $name = strtolower($name);
                $nametab = explode(" ", strtolower($name));
                $name = "";

                foreach ($nametab as $namepart) {
                    $name .= $namepart;
                }
                $firstnametab = explode(" ", strtolower($firstname));
                $firstname = "";

                foreach ($firstnametab as $namepart) {
                    $firstname .= $namepart;
                }
                $login = $firstname . $name;
                break;
            case 3:
                $name = substr($name, 0, 2);
                $firstname = substr($firstname, 0, 2);
                $login = $firstname . $name;
                break;
            default:
                $login = "";
        }
        return $login;
    }

    public static function getMapping($val)
    {
        $mapping["logAD"] = "login";
        $mapping["nameAD"] = "name";
        $mapping["phoneAD"] = "phone";

        $mapping["firstnameAD"] = "firstname";
        $mapping["mailAD"] = "mail";

        $mapping["cellPhoneAD"] = "cellphone";
        $mapping["roleAD"] = "role";
        $mapping["serviceAD"] = "service";
        $mapping["locationAD"] = "location";
        $mapping["companyAD"] = "company";
        $mapping["departmentAD"] = "department";
        $mapping["contractTypeAD"] = "contract";
        $mapping["contractEndAD"] = "enddate";
        $mapping["fonctionAD"]     = "function";

        if (isset($mapping[$val])) {
            return $mapping[$val];
        }
        return null;
    }

    public static function getNameMapping($val)
    {
        $mapping["login"] = __('Login');
        $mapping["firstname"] = __('Firstname', 'resources');
        $mapping["phone"] = Phone::getTypeName(1);

        $mapping["name"] = __('Name');
        $mapping["mail"] = __('Mail');

        $mapping["cellphone"] = __('Mobile phone');
        $mapping["role"] = __('Role', 'resources');
        $mapping["service"] = Service::getTypeName(1);
        $mapping["contract"] = __("Contract type");
        $mapping["company"] = __('Company', 'resources');
        $mapping["department"] = __('Department', 'resources');

        $mapping["enddate"] = __('Departure date', 'resources');

        if (isset($mapping[$val])) {
            return $mapping[$val];
        }
        return null;
    }

    /**
     * Displaying message solution
     *
     * @param $params
     *
     * @return bool
     */
    public static function messageSolution($params)
    {
        if (isset($params['item'])) {
            $item = $params['item'];
            if ($item->getType() == 'ITILSolution') {
                self::showMessage($params);
            }
        }
    }

    /**
     * Displaying questions in GLPI's ticket satisfaction
     *
     * @param $params
     *
     * @return bool
     */
    public static function deleteButtton($params)
    {
        if (isset($params['item'])) {
            $item = $params['item'];
            if ($item->getType() == 'ITILSolution') {
                if (self::cancelButtonSolution($params)) {
                    $params['options']['canedit'] = false;
                    return $params;
                }
            }
        }
    }

    /**
     * show warning message
     *
     * @param $params
     *
     * @return bool
     */
    public static function showMessage($params)
    {
        if (isset($params['options'])) {
            $options = $params['options'];
            $ticket = new Ticket();
            if ($ticket->getFromDB($options['item']->fields["id"])) {
                $adconfig = new Adconfig();
                $adconfig->getFromDB(1);
                $adconfig->fields = $adconfig->prepareFields($adconfig->fields);
                $linkad = new LinkAd();
                $items = new Item_Ticket();
                $conf = new Config();
                $conf->getFromDB(1);
                if (is_array($adconfig->fields["creation_categories_id"]) && in_array(
                    $ticket->fields["itilcategories_id"],
                    $adconfig->fields["creation_categories_id"],
                )) {
                    if ($items->getFromDBByCrit(
                        ["tickets_id" => $ticket->getID(), "itemtype" => Resource::getType()],
                    )) {
                        if ($conf->fields["mandatory_adcreation"] == 1) {
                            if (!$linkad->getFromDBByCrit(
                                ['plugin_resources_resources_id' => $items->getField('items_id')],
                            ) || ($linkad->getFromDBByCrit(
                                ['plugin_resources_resources_id' => $items->getField('items_id')],
                            ) && $linkad->getField('action_done') == 0)) {
                                $ldapaction = true;
                            }
                        }
                        if ($conf->fields["mandatory_checklist"] == 1) {
                            $checklist = new Checklist();
                            $checklists = $checklist->find(
                                [
                                    "plugin_resources_resources_id" => $items->getField('items_id'),
                                    "is_checked" => 0,
                                    "checklist_type" => Checklist::RESOURCES_CHECKLIST_IN,
                                ],
                            );
                            if (!empty($checklists)) {
                                $checklistaction = true;
                            }
                        }
                    }
                } elseif (is_array($adconfig->fields["deletion_categories_id"]) && in_array(
                    $ticket->fields["itilcategories_id"],
                    $adconfig->fields["deletion_categories_id"],
                )) {
                    if ($items->getFromDBByCrit(
                        ["tickets_id" => $ticket->getID(), "itemtype" => Resource::getType()],
                    )) {
                        if ($conf->fields["mandatory_adcreation"] == 1) {
                            if (!$linkad->getFromDBByCrit(
                                ['plugin_resources_resources_id' => $items->getField('items_id')],
                            ) || ($linkad->getFromDBByCrit(
                                ['plugin_resources_resources_id' => $items->getField('items_id')],
                            ) && $linkad->getField('action_done') == 0)) {
                                $ldapaction = true;
                            }
                        }
                        if ($conf->fields["mandatory_checklist"] == 1) {
                            $checklist = new Checklist();
                            $checklists = $checklist->find(
                                [
                                    "plugin_resources_resources_id" => $items->getField('items_id'),
                                    "is_checked" => 0,
                                    "checklist_type" => Checklist::RESOURCES_CHECKLIST_OUT,
                                ],
                            );
                            if (!empty($checklists)) {
                                $checklistaction = true;
                            }
                        }
                    }
                }
                $text = "";
                if (isset($ldapaction) && isset($checklistaction)) {
                    $text = __(
                        'You have to perform the action on the LDAP directory before and you have to do all checklist in action before',
                        'resources',
                    );
                } elseif (isset($ldapaction)) {
                    $text = __('You have to perform the action on the LDAP directory before', 'resources');
                } elseif (isset($checklistaction)) {
                    $text = __('You have to do all checklist in action before', 'resources');
                }
                if (!empty($text)) {
                    // Emitted through PRE_ITEM_FORM on ITILSolution, i.e. outside any table:
                    // the legacy <tr class='warning'> wrapper could never style anything there.
                    TemplateRenderer::getInstance()->display('@resources/alert_warning.html.twig', [
                        'message' => $text,
                    ]);
                }
            }
        }
    }

    public static function cancelButtonSolution($params)
    {
        if (isset($params['options'])) {
            $options = $params['options'];
            $ticket = new Ticket();
            if ($ticket->getFromDB($options['item']->fields["id"])) {
                $adconfig = new Adconfig();
                $adconfig->getFromDB(1);
                $adconfig->fields = $adconfig->prepareFields($adconfig->fields);
                $linkad = new LinkAd();
                $items = new Item_Ticket();
                $conf = new Config();
                $conf->getFromDB(1);
                if (is_array($adconfig->fields["creation_categories_id"]) && in_array(
                    $ticket->fields["itilcategories_id"],
                    $adconfig->fields["creation_categories_id"],
                )) {
                    if ($items->getFromDBByCrit(
                        ["tickets_id" => $ticket->getID(), "itemtype" => Resource::getType()],
                    )) {
                        if ($conf->fields["mandatory_adcreation"] == 1) {
                            if (!$linkad->getFromDBByCrit(
                                ['plugin_resources_resources_id' => $items->getField('items_id')],
                            ) || ($linkad->getFromDBByCrit(
                                ['plugin_resources_resources_id' => $items->getField('items_id')],
                            ) && $linkad->getField('action_done') == 0)) {
                                return true;
                            }
                        }
                        if ($conf->fields["mandatory_checklist"] == 1) {
                            $checklist = new Checklist();
                            $checklists = $checklist->find(
                                [
                                    "plugin_resources_resources_id" => $items->getField('items_id'),
                                    "is_checked" => 0,
                                    "checklist_type" => Checklist::RESOURCES_CHECKLIST_IN,
                                ],
                            );
                            if (!empty($checklists)) {
                                return true;
                            }
                        }
                    }
                    //            } else if (in_array($ticket->fields["itilcategories_id"] , $adconfig->fields["modification_categories_id"])) {
                    //               if ($items->getFromDBByCrit(["tickets_id" => $ticket->getID(), "itemtype" => Resource::getType()])) {
                    //                  if (!$linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) || ($linkad->getFromDBByCrit(['plugin_resources_resources_id' => $items->getField('items_id')]) && $linkad->getField('action_done') == 0)) {
                    //                     return true;
                    //
                    //                  }
                    //
                    //               }
                } elseif (is_array($adconfig->fields["deletion_categories_id"]) && in_array(
                    $ticket->fields["itilcategories_id"],
                    $adconfig->fields["deletion_categories_id"],
                )) {
                    if ($items->getFromDBByCrit(
                        ["tickets_id" => $ticket->getID(), "itemtype" => Resource::getType()],
                    )) {
                        if ($conf->fields["mandatory_adcreation"] == 1) {
                            if (!$linkad->getFromDBByCrit(
                                ['plugin_resources_resources_id' => $items->getField('items_id')],
                            ) || ($linkad->getFromDBByCrit(
                                ['plugin_resources_resources_id' => $items->getField('items_id')],
                            ) && $linkad->getField('action_done') == 0)) {
                                return true;
                            }
                        }
                        if ($conf->fields["mandatory_checklist"] == 1) {
                            $checklist = new Checklist();
                            $checklists = $checklist->find(
                                [
                                    "plugin_resources_resources_id" => $items->getField('items_id'),
                                    "is_checked" => 0,
                                    "checklist_type" => Checklist::RESOURCES_CHECKLIST_OUT,
                                ],
                            );
                            if (!empty($checklists)) {
                                return true;
                            }
                        }
                    }
                }
                return false;
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
                        `plugin_resources_resources_id` int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `auth_id`                       int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `login`                         varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `mail`                          varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `phone`                         varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `role`                          varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `service`                       varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `location`                      varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `cellphone`                     varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `action_done`                   tinyint      NOT NULL                   DEFAULT '0',
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unicity` (`login`),
                        UNIQUE KEY `unicity2` (`plugin_resources_resources_id`),
                        KEY `login` (`login`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
