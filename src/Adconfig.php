<?php

/*
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2015-2026 by the resources Development Team.

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

use AuthLDAP;
use CommonDBTM;
use CommonGLPI;
use DBConnection;
use Dropdown;
use GLPIKey;
use Html;
use ITILCategory;
use Location;
use Migration;
use Session;
use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Adconfig
 */
class Adconfig extends CommonDBTM
{

    static $rightname = 'plugin_resources';

    // Bind and default-account secrets are stored encrypted (GLPIKey); keep them out of
    // API/exports and out of the update history so the (encrypted) value is not disclosed.
    public static $undisclosedFields = [
        'password',
        'default_account_password',
    ];

    public $history_blacklist = [
        'password',
        'default_account_password',
    ];

    /**
     * functions mandatory
     * getTypeName(), canCreate(), canView()
     * */
    static function getTypeName($nb = 0)
    {
        return __('Setup LDAP directory', 'resources');
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
    static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * Have I the global right to "create" the Object
     * May be overloaded if needed (ex KnowbaseItem)
     *
     * @return
     **/
    static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
    }

    function __construct()
    {
        global $DB;

        if ($DB->tableExists($this->getTable())) {
            $this->getFromDB(1);
        }
    }

    static function getIcon()
    {
        return "ti ti-vocabulary";
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
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Config::class) {
            $self = new self();
            $self->showConfigForm();
        }
        return true;
    }

    /**
     * @return bool
     */
    function showConfigForm()
    {
        if (!$this->canView()) {
            return false;
        }
        if (!$this->canCreate()) {
            return false;
        }

        $ID = 1;
        $this->getFromDB($ID);

        // Capture a GLPI widget that echoes its own HTML (Dropdown::*) into a string
        // so it can be injected into the Twig template with |raw.
        $capture = static function (callable $renderer): string {
            ob_start();
            $renderer();
            return ob_get_clean();
        };

        // Build the ITILCategory option list once (reused by the three category dropdowns).
        $categories  = [];
        $itilCategory = new ITILCategory();
        foreach ($itilCategory->find([]) as $cat) {
            $categories[$cat['id']] = $cat['completename'];
        }
        $decodeValues = static function ($json): array {
            $values = json_decode((string) $json);
            return is_array($values) ? $values : [];
        };

        $data = [
            'form_action' => $this->getFormURL(),
            'id'          => $ID,
            'title'       => self::getTypeName(),

            // Labels (translated server-side, auto-escaped by Twig).
            'label_rootdn'                => __('RootDN (for non anonymous binds)'),
            'label_password'              => __('Password'),
            'label_clear'                 => __('Clear'),
            'label_server'                => __('Server'),
            'label_creation_category'     => __('Creation category', 'resources'),
            'label_modification_category' => __('Modification category', 'resources'),
            'label_deletion_category'     => __('Deletion category', 'resources'),
            'label_login_creation'        => __('Login Creation', 'resources'),
            'label_first_form'            => __('First Form', 'resources'),
            'label_second_form'           => __('Second Form', 'resources'),
            'label_password_creation'     => __('Password Creation', 'resources'),
            'label_use_password_module'   => __('Use Password creation module', 'resources'),
            'label_format_password'       => __('Format password', 'resources'),
            'label_prefix'                => __('Prefix', 'resources'),
            'label_mail_creation'         => __('Mail Creation', 'resources'),
            'label_suffix'                => __('Suffix', 'resources'),
            'label_field_mapping'         => __('Field mapping', 'resources'),

            // Bind credentials (the password itself is rendered empty by the template).
            'login_field' => Html::input('login', ['value' => $this->fields['login'], 'size' => 40]),

            // Directory + ITIL categories.
            'auth_dropdown' => $capture(fn() => Dropdown::show(
                AuthLDAP::getType(),
                ['name' => 'auth_id', 'value' => $this->getField('auth_id')]
            )),
            'creation_dropdown' => $capture(fn() => Dropdown::showFromArray(
                'creation_categories_id',
                $categories,
                ['values' => $decodeValues($this->fields['creation_categories_id']), 'multiple' => 'multiples']
            )),
            'modification_dropdown' => $capture(fn() => Dropdown::showFromArray(
                'modification_categories_id',
                $categories,
                ['values' => $decodeValues($this->fields['modification_categories_id']), 'multiple' => 'multiples']
            )),
            'deletion_dropdown' => $capture(fn() => Dropdown::showFromArray(
                'deletion_categories_id',
                $categories,
                ['values' => $decodeValues($this->fields['deletion_categories_id']), 'multiple' => 'multiples']
            )),

            // Login creation.
            'first_form_dropdown' => $capture(fn() => Dropdown::showFromArray(
                'first_form',
                $this->loginForm(),
                ['value' => $this->fields['first_form']]
            )),
            'second_form_dropdown' => $capture(fn() => Dropdown::showFromArray(
                'second_form',
                $this->loginForm(),
                ['value' => $this->fields['second_form']]
            )),

            // Mail creation.
            'mail_prefix_dropdown' => $capture(fn() => Dropdown::showFromArray(
                'mail_prefix',
                $this->prefixForm(),
                ['value' => $this->fields['mail_prefix']]
            )),
            'mail_suffix_field' => Html::input('mail_suffix', ['value' => $this->fields['mail_suffix']]),
        ];

        // Optional password-creation block (only offered for an SSL/TLS-secured AD).
        $data['ssl_tls'] = (new LDAP())->isSSLorTLSAD();
        if ($data['ssl_tls']) {
            $data['use_password_module_dropdown'] = $capture(
                fn() => Dropdown::showYesNo('use_password_module', $this->fields['use_password_module'])
            );
            $data['show_format'] = (bool) $this->fields['use_password_module'];

            $format = (int) $this->fields['format_default_account_password'];
            $data['format_dropdown'] = $capture(fn() => Dropdown::showFromArray(
                'format_default_account_password',
                [
                    0 => Dropdown::EMPTY_VALUE,
                    1 => 'prefixe dynamique et suffixe statique',
                    2 => __('Static default password ', 'resources'),
                ],
                ['value' => $format]
            ));
            $data['show_password_detail'] = ($format != 0);
            $data['is_prefix_format']     = ($format == 1);
            $data['prefix_dropdown'] = $capture(fn() => Dropdown::showFromArray(
                'prefix_default_account_password',
                [
                    0 => Dropdown::EMPTY_VALUE,
                    1 => __('First name initial (uppercase) + last name initial (lowercase) + arrival date (DDMMYYYY)', 'resources'),
                    2 => __('First name initial (uppercase) + last name initial (lowercase)', 'resources'),
                ],
                ['value' => $this->fields['prefix_default_account_password']]
            ));
            $data['password_detail_label'] = ($format == 2)
                ? __('Default password', 'resources')
                : __('Suffix', 'resources');
            // Never re-expose the stored secret in the HTML: render an empty field (like the
            // bind password). Only require input on first configuration; an empty submit keeps
            // the existing value (see prepareInputForUpdate).
            $data['default_account_password_field'] = Html::input('default_account_password', [
                'value'    => '',
                'required' => empty($this->fields['default_account_password']),
            ]);
        }

        // Field mapping: an ordered label => AD attribute list, laid out two cells per row.
        $mapping_fields = [
            'logAD'               => __('Login'),
            'departmentAD'        => _n('Department', 'Departments', 1, 'resources'),
            'nameAD'              => __('Name'),
            'firstnameAD'         => __('First name'),
            'phoneAD'             => __('Phone'),
            'mailAD'              => __('Email'),
            'companyAD'           => Employer::getTypeName(1),
            'contractEndAD'       => __('Departure date', 'resources'),
            'cellPhoneAD'         => __('Mobile phone'),
            'roleAD'              => __('Role', 'resources'),
            'contractTypeAD'      => _n('Contract type', 'Contract types', 1),
            'serviceAD'           => Service::getTypeName(1),
            'locationAD'          => Location::getTypeName(),
            'fonctionAD'          => __('Function', 'resources'),
            'ouDesactivateUserAD' => __('Destination OU on user deactivation', 'resources'),
            'ouUser'              => __('Destination OU during user creation', 'resources'),
        ];
        $mapping_rows = [];
        $cells        = [];
        foreach ($mapping_fields as $field => $label) {
            $cells[] = [
                'label' => $label,
                'field' => Html::input($field, ['value' => $this->fields[$field], 'entity' => -1]),
            ];
            if (count($cells) === 2) {
                $mapping_rows[] = $cells;
                $cells          = [];
            }
        }
        if (!empty($cells)) {
            $mapping_rows[] = $cells;
        }
        $data['mapping_rows'] = $mapping_rows;

        TemplateRenderer::getInstance()->display('@resources/adconfig_config.html.twig', $data);

        return true;
    }

    function loginForm()
    {
        $options[0] = Dropdown::EMPTY_VALUE;
        $options[1] = __("first letter of given name + name", 'resources');
        $options[2] = __("given name + name", 'resources');
        $options[3] = __("2 letters of given name + 2 letters of name", 'resources');

        return $options;
    }

    function prefixForm()
    {
        $options[0] = Dropdown::EMPTY_VALUE;
        $options[1] = __("given name.name", 'resources');
        $options[2] = __("Login");

        return $options;
    }

    /**
     * @param $input
     *
     * @return $input
     */
    function prepareInputForAdd($input)
    {
        return $this->encodeSubtypes($input);
    }


    /**
     * Encode sub types
     *
     * @param $input
     *
     * @return
     */
    function encodeSubtypes($input)
    {
        if (!empty($input['creation_categories_id'])) {
            $input['creation_categories_id'] = json_encode(array_values($input['creation_categories_id']));
        } else {
            $input['creation_categories_id'] = json_encode([]);
        }
        if (!empty($input['modification_categories_id'])) {
            $input['modification_categories_id'] = json_encode(array_values($input['modification_categories_id']));
        } else {
            $input['modification_categories_id'] = json_encode([]);
        }
        if (!empty($input['deletion_categories_id'])) {
            $input['deletion_categories_id'] = json_encode(array_values($input['deletion_categories_id']));
        } else {
            $input['deletion_categories_id'] = json_encode([]);
        }

        return $input;
    }


    function prepareInputForUpdate($input)
    {
        if (isset($input["password"])) {
            if (empty($input["password"])) {
                unset($input["password"]);
            } else {
                $input["password"] = (new GLPIKey())->encrypt($input["password"]);
            }
        }

        if (isset($input["_blank_passwd"]) && $input["_blank_passwd"]) {
            $input['password'] = '';
        }

        if (isset($input["default_account_password"])) {
            if (empty($input["default_account_password"])) {
                // Empty submit: keep the currently stored secret instead of wiping it.
                unset($input["default_account_password"]);
            } else {
                $input["default_account_password"] = (new GLPIKey())->encrypt($input["default_account_password"]);
            }
        }

        $input = $this->encodeSubtypes($input);


        return $input;
    }

    /**
     * @return mixed
     */
    function useSecurity()
    {
        return $this->fields['security_display'];
    }

    /**
     * @return mixed
     */
    function useSecurityCompliance()
    {
        return $this->fields['security_compliance'];
    }


    function getArrayAttributes()
    {
        $array = [
            "logAD",
            "nameAD",
            "phoneAD",
            "companyAD",
            "departmentAD",
            "firstnameAD",
            "mailAD",
            "contractEndAD",
            "contractTypeAD",
            "cellPhoneAD",
            "roleAD",
            "serviceAD",
            "locationAD",
            "fonctionAD"
        ];
        return $array;
    }

    function prepareFields($fields)
    {
        $fields['creation_categories_id'] = json_decode($fields['creation_categories_id']);


        $fields['modification_categories_id'] = json_decode($fields['modification_categories_id']);


        $fields['deletion_categories_id'] = json_decode($fields['deletion_categories_id']);

        return $fields;
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
                        `auth_id`                    int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `login`                      varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `password`                   varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `creation_categories_id`     TEXT         NOT NULL,
                        `modification_categories_id` TEXT         NOT NULL,
                        `deletion_categories_id`     TEXT         NOT NULL,
                        `logAD`                      varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `nameAD`                     varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `phoneAD`                    varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `companyAD`                  varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `departmentAD`               varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `firstnameAD`                varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `mailAD`                     varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `contractEndAD`              varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `contractTypeAD`             varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `ouDesactivateUserAD`        varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `ouUser`                     varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `cellPhoneAD`                varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `roleAD`                     varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `serviceAD`                  varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `locationAD`                 varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        `first_form`                 int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `second_form`                int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `mail_prefix`                int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `mail_suffix`                varchar(255) NOT NULL                   DEFAULT '',
                        `fonctionAD`                 varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
                        PRIMARY KEY (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            $DB->insert(
                $table,
                ['id' => 1,
                    'auth_id' => 0,
                    'login' => '',
                    'password' => '',
                    'creation_categories_id' => '',
                    'modification_categories_id' => '',
                    'deletion_categories_id' => '',
                    'logAD' => '',
                    'nameAD' => '',
                    'phoneAD' => '',
                    'companyAD' => '',
                    'departmentAD' => '',
                    'firstnameAD' => '',
                    'mailAD' => '',
                    'contractEndAD' => '',
                    'contractTypeAD' => '',
                    'ouDesactivateUserAD' => '',
                    'ouUser' => '',
                    'cellPhoneAD' => '',
                    'roleAD' => '',
                    'serviceAD' => '',
                    'locationAD' => '',
                    'first_form' => 0,
                    'second_form' => 0,
                    'mail_prefix' => 0,
                    'mail_suffix' => '',
                    'fonctionAD' => '']
            );
        }
    }
}
