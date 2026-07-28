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

use CommonDBTM;
use CommonGLPI;
use DBConnection;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Migration;
use PluginPdfSimplePDF;
use Session;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Employee
 */
class Employee extends CommonDBTM
{

    static $rightname = 'plugin_resources_employee';

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @param integer $nb Number of items
     *
     * @return string
     **/
    static function getTypeName($nb = 0)
    {
        return _n('Employee', 'Employees', $nb, 'resources');
    }

    static function getIcon()
    {
        return "ti ti-buildings";
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
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        $wizard_employee = ContractType::checkWizardSetup($item->getField('id'), "use_employee_wizard");

        if ($item->getType() == Resource::class
            && $this->canView()
            && $wizard_employee
        ) {
            return self::createTabEntry(self::getTypeName(1));
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
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item->getType() == Resource::class) {
            $self = new self();
            $self->showEmployeeForm($item->getField('id'), 0, $withtemplate);
        }
//        if ($item->getType() == Resource::class) {
//            $wizard = new Wizard();
//            $wizard->wizardThirdStep($item->getField('id'), ['default_button' => true, 'target' => 'item']);
//        }
        return true;
    }


    /**
     * Prepare input datas for adding the item
     *
     * @param array $input datas used to add the item
     *
     * @return array the modified $input array
     **/
    function prepareInputForAdd($input)
    {
        // Not attached to resource -> not added
        if (!isset($input['plugin_resources_resources_id']) || $input['plugin_resources_resources_id'] <= 0) {
            return false;
        }
        if ($this->getFromDBByCrit(['plugin_resources_resources_id' => $input['plugin_resources_resources_id']])) {
            return false;
        }
        return $input;
    }

    /**
     * Duplicate item resources from an item template to its clone
     *
     * @param $itemtype     itemtype of the item
     * @param $oldid        ID of the item to clone
     * @param $newid        ID of the item cloned
     * @param $newitemtype  itemtype of the new item (= $itemtype if empty) (DEFAULT '')
     **@since version 0.84
     *
     */
    static function cloneItem($oldid, $newid)
    {
        global $DB;

        $query =
            [
                'SELECT' => [
                    '*',
                ],
                'FROM' => 'glpi_plugin_resources_employees',
                'WHERE' => [
                    'plugin_resources_resources_id' => $oldid
                ],
            ];
        foreach ($DB->request($query) as $data) {
            $employee = new self();
            $employee->add([
                'plugin_resources_resources_id' => $newid,
                'plugin_resources_employers_id' => $data["plugin_resources_employers_id"],
                'plugin_resources_clients_id' => $data["plugin_resources_clients_id"]
            ]);
        }
    }

    /**
     * @param        $plugin_resources_resources_id
     * @param        $users_id
     * @param string $withtemplate
     *
     * @return bool
     */
    function showEmployeeForm($plugin_resources_resources_id, $users_id, $withtemplate = '')
    {
        global $CFG_GLPI;

        if (!$this->canView()) {
            return false;
        }

        $employee_spotted = false;
        $resource = new Resource();

        $restrict = ["plugin_resources_resources_id" => $plugin_resources_resources_id];
        $dbu = new DbUtils();
        $employees = $dbu->getAllDataFromTable($this->getTable(), $restrict);

        $canedit = $resource->can($plugin_resources_resources_id, UPDATE);

        $ID = 0;
        if (!empty($employees)) {
            foreach ($employees as $employer) {
                $ID = $employer["id"];
            }
        }
        if (empty($ID)) {
            if ($this->getEmpty()) {
                $employee_spotted = true;
            }
        } else {
            if ($this->getfromDB($ID)) {
                $employee_spotted = true;
            }
        }
        if (!$employee_spotted) {
            return false;
        }

        if (!empty($plugin_resources_resources_id)) {
            $resource->getFromDB($plugin_resources_resources_id);
            $entity = $resource->fields["entities_id"];
        } else {
            $entity = $_SESSION["glpiactive_entity"];
        }

        // Capture GLPI widgets that echo directly, so they can be injected as |raw.
        $capture = static function (callable $renderer): string {
            ob_start();
            $renderer();
            return (string) ob_get_clean();
        };

        $params = [
            'name'   => 'plugin_resources_employers_id',
            'value'  => $this->fields['plugin_resources_employers_id'],
            'entity' => $entity,
            'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownLocation.php",
            'span'   => 'span_location'
        ];
        $employer_dropdown = $capture(fn() => Resource::showGenericDropdown(Employer::class, $params));

        $locationId = 0;
        if ($this->fields["plugin_resources_employers_id"] > 0) {
            $employer = new Employer();
            $employer->getFromDB($this->fields["plugin_resources_employers_id"]);
            $locationId = $employer->fields["locations_id"];
        }
        if ($locationId > 0) {
            $address_html = Dropdown::getDropdownName('glpi_locations', $locationId);
        } else {
            $address_html = __('None');
        }

        $client_dropdown = $capture(fn() => Dropdown::show(
            Client::class,
            [
                'value'     => $this->fields["plugin_resources_clients_id"],
                'entity'    => $entity,
                'on_change' => "plugin_resources_security_compliance(\"" . $CFG_GLPI['root_doc'] . "\", this.value);"
            ]
        ));

        if (Client::isSecurityCompliance($this->fields["plugin_resources_clients_id"])) {
            $img   = "<i style='color:green' class='ti ti-circle-check' alt=\"" . __('OK') . "\"></i>";
            $color = "color: green;";
        } else {
            $img   = "<i style='color:red' class='ti ti-circle-x' alt=\"" . __('KO') . "\"></i>";
            $color = "color: red;";
        }

        // Action buttons cell (mix of returned Html::* helpers and the echoing template dropdown).
        $buttons_cell = '';
        if ($withtemplate < 2) {
            if (empty($ID)) {
                if ($this->canCreate() && $canedit) {
                    $buttons_cell .= Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                    if (!empty($plugin_resources_resources_id)) {
                        $buttons_cell .= "<div class='center'>";
                        $buttons_cell .= Html::submit(
                            _sx('button', 'Add'),
                            ['name' => 'addemployee', 'class' => 'btn btn-primary']
                        );
                        $buttons_cell .= "</div>";
                    } else {
                        $buttons_cell .= "<div class='center'>";
                        $buttons_cell .= $capture(fn() => Resource::dropdownTemplate("templates_id", $_SESSION["glpiactive_entity"]));
                        $buttons_cell .= Html::hidden('users_id', ['value' => $users_id]);
                        $buttons_cell .= "&nbsp;";
                        $buttons_cell .= Html::submit(
                            _sx('button', 'Add'),
                            ['name' => 'addressourceandemployee', 'class' => 'btn btn-primary']
                        );
                        $buttons_cell .= "</div>";
                    }
                }
            } else {
                if ($this->canCreate() && $canedit) {
                    $buttons_cell .= Html::hidden('id', ['value' => $ID]);
                    $buttons_cell .= Html::hidden(
                        'plugin_resources_resources_id',
                        ['value' => $this->fields["plugin_resources_resources_id"]]
                    );
                    $buttons_cell .= "<div class='center'>";
                    $buttons_cell .= Html::submit(
                        _sx('button', 'Update'),
                        ['name' => 'updateemployee', 'class' => 'btn btn-primary']
                    );
                    $buttons_cell .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                    $buttons_cell .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                    $buttons_cell .= Html::submit(
                        _sx('button', 'Delete permanently'),
                        ['name' => 'deleteemployee', 'class' => 'btn btn-primary']
                    );
                    $buttons_cell .= "</div>";
                }
            }
        }

        TemplateRenderer::getInstance()->display('@resources/employee_form.html.twig', [
            'show_form'           => ($withtemplate < 2),
            'form_action'         => PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php",
            'hidden_resources_id' => Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]),
            'title'               => self::getTypeName(1),
            'show_created_note'   => empty($plugin_resources_resources_id),
            'created_note'        => __('The resource is also created if not existent', 'resources'),
            'label_employer'      => Employer::getTypeName(1),
            'employer_dropdown'   => $employer_dropdown,
            'label_address'       => __('Address'),
            'address_html'        => $address_html,
            'label_client'        => Client::getTypeName(1),
            'client_dropdown'     => $client_dropdown,
            'compliance_color'    => $color,
            'compliance_label'    => __('Security compliance', 'resources'),
            'compliance_img'      => $img,
            'buttons_cell'        => $buttons_cell,
        ]);

        return true;
    }


    /**
     * @param $plugin_resources_resources_id
     * @param $exist
     *
     * @return bool
     */
    function showFormHelpdesk($plugin_resources_resources_id, $exist)
    {

        if (!$this->canView()) {
            return false;
        }

        $employee_spotted = false;

        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);

        $restrict = ["plugin_resources_resources_id" => $plugin_resources_resources_id];
        $dbu = new DbUtils();
        $employees = $dbu->getAllDataFromTable($this->getTable(), $restrict);

        $ID = 0;
        if (!empty($employees)) {
            foreach ($employees as $employer) {
                $ID = $employer["id"];
            }
        }
        if (empty($ID)) {
            if ($this->getEmpty()) {
                $employee_spotted = true;
            }
        } else {
            if ($this->getfromDB($ID)) {
                $employee_spotted = true;
            }
        }
        if (!$employee_spotted) {
            return false;
        }

        $entity = $resource->fields["entities_id"];

        if ($exist == 0 || empty($ID)) {
            $form_action = PLUGIN_RESOURCES_WEBDIR . "/front/employee.form.php";
        } else {
            $form_action = PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php";
        }

        // Capture GLPI dropdowns that echo directly, so they can be injected as |raw.
        $capture = static function (callable $renderer): string {
            ob_start();
            $renderer();
            return (string) ob_get_clean();
        };

        $employer_dropdown = $capture(fn() => Dropdown::show(Employer::class, [
            'name'   => "plugin_resources_employers_id",
            'value'  => $this->fields["plugin_resources_employers_id"],
            'entity' => $entity
        ]));

        $client_dropdown = $capture(fn() => Dropdown::show(Client::class, [
            'name'   => "plugin_resources_clients_id",
            'value'  => $this->fields["plugin_resources_clients_id"],
            'entity' => $entity
        ]));

        // Conditional action row: each branch emits its own <tr>/<td> wrapper.
        $buttons_block = '';
        if ($this->canCreate()) {
            if ($exist == 0) {
                $buttons_block .= "<tr><td class='tab_bg_2 top' colspan='4'>";
                $buttons_block .= Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                $buttons_block .= "<div class='center'>";
                $buttons_block .= Html::submit(
                    _sx('button', 'Next step', 'resources'),
                    ['name' => 'add_helpdesk_employee', 'class' => 'btn btn-primary']
                );
                $buttons_block .= "</td></tr>";
            } elseif (empty($ID)) {
                $buttons_block .= "<tr><td class='tab_bg_2 top' colspan='4'>";
                $buttons_block .= Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                $buttons_block .= Html::submit(
                    _sx('button', 'Add'),
                    ['name' => 'add_helpdesk_employee', 'class' => 'btn btn-primary']
                );
                $buttons_block .= "</td></tr>";
            } else {
                if ($resource->fields["is_leaving"] != 1) {
                    $buttons_block .= "<tr><td class='tab_bg_2 top' colspan='4'>";
                    $buttons_block .= Html::hidden('id', ['value' => $ID]);
                    $buttons_block .= Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                    $buttons_block .= "<div class='center'>";
                    $buttons_block .= Html::submit(
                        _sx('button', 'Update'),
                        ['name' => 'updateemployee', 'class' => 'btn btn-primary']
                    );
                    $buttons_block .= "</div>";
                    $buttons_block .= "</td></tr>";
                }
            }
        }

        TemplateRenderer::getInstance()->display('@resources/employee_helpdesk_form.html.twig', [
            'form_action'         => $form_action,
            'hidden_resources_id' => Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]),
            'title'               => self::getTypeName(1),
            'label_employer'      => Employer::getTypeName(1),
            'employer_dropdown'   => $employer_dropdown,
            'label_client'        => Client::getTypeName(1),
            'client_dropdown'     => $client_dropdown,
            'buttons_block'       => $buttons_block,
        ]);

        return true;
    }

    /**
     * @param \PluginPdfSimplePDF $pdf
     * @param \CommonGLPI $item
     * @param                     $tab
     *
     * @return bool
     */
    static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab)
    {
        if ($item->getType() == Resource::class) {
            self::pdfForResource($pdf, $item);
        } else {
            return false;
        }
        return true;
    }

    /**
     * Show for PDF an resources : employee informations
     *
     * @param $pdf object for the output
     * @param $appli Resource Class
     */
    static function pdfForResource(PluginPdfSimplePDF $pdf, Resource $appli)
    {
        global $DB;

        $ID = $appli->fields['id'];

        if (!$appli->can($ID, READ)) {
            return false;
        }

        if (!Session::haveRight("plugin_resources", READ)) {
            return false;
        }
        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_resources_employees',
            'WHERE' => ['plugin_resources_resources_id' => (int) $ID],
        ]);
        $number = count($iterator);

        $pdf->setColumnsSize(100);

        $pdf->displayTitle('<b>' . self::getTypeName(1) . '</b>');

        $pdf->setColumnsSize(33, 33, 34);
        $pdf->displayTitle(
            '<b><i>' .
            Employer::getTypeName(1),
            Client::getTypeName(1) . '</i></b>'
        );

        if (!$number) {
            $pdf->displayLine(__('No results found'));
        } else {
            foreach ($iterator as $data) {
                $pdf->displayLine(
                    Dropdown::getDropdownName("glpi_plugin_resources_employers", $data["plugin_resources_employers_id"]),
                    Dropdown::getDropdownName("glpi_plugin_resources_clients", $data["plugin_resources_clients_id"])
                );
            }
        }

        $pdf->displaySpace();
    }

    /**
     * Provides search options configuration. Do not rely directly
     * on this, @return array a *not indexed* array of search options
     *
     * @since 9.3
     *
     * This should be overloaded in Class
     *
     * @see CommonDBTM::searchOptions instead.
     *
     * @see https://glpi-developer-documentation.rtfd.io/en/master/devapi/search.html
     **/
    function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id' => '2',
            'table' => 'glpi_plugin_resources_employers',
            'field' => 'name',
            'name' => Employer::getTypeName(1),
            'datatype' => 'dropdown'
        ];
        $tab[] = [
            'id' => '3',
            'table' => 'glpi_plugin_resources_clients',
            'field' => 'name',
            'name' => Client::getTypeName(1),
            'datatype' => 'dropdown'
        ];
        $tab[] = [
            'id' => '31',
            'table' => $this->getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'datatype' => 'number',
            'massiveaction' => false
        ];

        return $tab;
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
                        `plugin_resources_resources_id` int {$default_key_sign} NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_resources (id)',
                        `plugin_resources_employers_id` int {$default_key_sign} NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_employers (id)',
                        `plugin_resources_clients_id`   int {$default_key_sign} NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_clients (id)',
                        PRIMARY KEY (`id`),
                        KEY `plugin_resources_resources_id` (`plugin_resources_resources_id`),
                        KEY `plugin_resources_employers_id` (`plugin_resources_employers_id`),
                        KEY `plugin_resources_clients_id` (`plugin_resources_clients_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
