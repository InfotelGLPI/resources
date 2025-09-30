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

use CommonDBTM;
use CommonGLPI;
use DbUtils;
use Dropdown;
use Html;
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
        global $CFG_GLPI;

        if ($item->getType() == Resource::class) {
            $self = new self();
            $self->showEmployeeForm($item->getField('id'), 0, $withtemplate);
        }
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
     * @param $newitemtype  itemtype of the new item (= $itemtype if empty) (default '')
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
        if ($employee_spotted) {
            echo "<div class='center'>";
            if ($withtemplate < 2) {
                echo "<form method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php\">";
            }
            if (!empty($plugin_resources_resources_id)) {
                $resource->getFromDB($plugin_resources_resources_id);
                $entity = $resource->fields["entities_id"];
            } else {
                $entity = $_SESSION["glpiactive_entity"];
            }

            echo "<table class='tab_cadre_fixe'>";
            echo "<tr><th colspan='4'>" . self::getTypeName(1) . "</th></tr>";
            if (empty($plugin_resources_resources_id)) {
                echo "<tr class='tab_bg_1'><td colspan='4' class='center'>" . __(
                        'The resource is also created if not existent',
                        'resources'
                    );
                echo "</td></tr>";
            }
            echo "<tr class='tab_bg_1'><td colspan='2' class='center'>";

            echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);

            echo Employer::getTypeName(1) . "</td>";
            echo "<td colspan='2'>";

            $params = [
                'name' => 'plugin_resources_employers_id',
                'value' => $this->fields['plugin_resources_employers_id'],
                'entity' => $entity,
                'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownLocation.php",
                'span' => 'span_location'
            ];
            Resource::showGenericDropdown(Employer::class, $params);
            echo "</td></tr>";

            $locationId = 0;
            if ($this->fields["plugin_resources_employers_id"] > 0) {
                $employer = new Employer();
                $employer->getFromDB($this->fields["plugin_resources_employers_id"]);
                $locationId = $employer->fields["locations_id"];
            }

            echo "<tr class='tab_bg_1'><td colspan='2' class='center'>";
            echo __('Address');
            echo "</td><td colspan='2'>";
            echo "<span id='span_location' name='span_location'>";
            if ($locationId > 0) {
                echo Dropdown::getDropdownName('glpi_locations', $locationId);
            } else {
                echo __('None');
            }
            echo "</span>";
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'><td colspan='2' class='center'>";
            echo Client::getTypeName(1) . "</td>";
            echo "<td>";
            Dropdown::show(
                Client::class,
                [
                    'value' => $this->fields["plugin_resources_clients_id"],
                    'entity' => $entity,
                    'on_change' => "plugin_resources_security_compliance(\"" . $CFG_GLPI['root_doc'] . "\", this.value);"
                ]
            );

            if (Client::isSecurityCompliance($this->fields["plugin_resources_clients_id"])) {
                $img = "<i style='color:green' class='ti ti-circle-check' alt=\"" . __('OK') . "\"></i>";
                $color = "color: green;";
            } else {
                $img = "<i style='color:red' class='ti ti-circle-x' alt=\"" . __('KO') . "\"></i>";
                $color = "color: red;";
            }
            echo "</td><td><div id='security_compliance'>";
            echo "<span style='$color'>";
            echo __('Security compliance', 'resources') . "&nbsp;";
            echo $img;
            echo "</span>";
            echo "</div></td></tr>";

            echo "<tr>";
            echo "<td class='tab_bg_2 top' colspan='4'>";
            if ($withtemplate < 2) {
                if (empty($ID)) {
                    if ($this->canCreate() && $canedit) {
                        echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                        if (!empty($plugin_resources_resources_id)) {
                            echo "<div class='center'>";
                            echo Html::submit(
                                _sx('button', 'Add'),
                                ['name' => 'addemployee', 'class' => 'btn btn-primary']
                            );
                            echo "</div>";
                        } else {
                            echo "<div class='center'>";
                            $resource->dropdownTemplate("templates_id", $_SESSION["glpiactive_entity"]);
                            echo Html::hidden('users_id', ['value' => $users_id]);
                            echo "&nbsp;";
                            echo Html::submit(
                                _sx('button', 'Add'),
                                ['name' => 'addressourceandemployee', 'class' => 'btn btn-primary']
                            );
                            echo "</div>";
                        }
                    }
                } else {
                    if ($this->canCreate() && $canedit) {
                        echo Html::hidden('id', ['value' => $ID]);
                        echo Html::hidden(
                            'plugin_resources_resources_id',
                            ['value' => $this->fields["plugin_resources_resources_id"]]
                        );
                        echo "<div class='center'>";
                        echo Html::submit(
                            _sx('button', 'Update'),
                            ['name' => 'updateemployee', 'class' => 'btn btn-primary']
                        );
                        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                        echo Html::submit(
                            _sx('button', 'Delete permanently'),
                            ['name' => 'deleteemployee', 'class' => 'btn btn-primary']
                        );
                        echo "</div>";
                    }
                }
            }
            echo "</td>";
            echo "</tr>";
            echo "</table>";
            if ($withtemplate < 2) {
                Html::closeForm();
            }
            echo "</div>";
        }
    }


    /**
     * @param $plugin_resources_resources_id
     * @param $exist
     *
     * @return bool
     */
    function showFormHelpdesk($plugin_resources_resources_id, $exist)
    {
        global $CFG_GLPI;

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
        if ($employee_spotted) {
            echo "<div class='center'><br>";
            if ($exist == 0 || empty($ID)) {
                echo "<form method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR . "/front/employee.form.php\">";
            } else {
                echo "<form method='post' action=\"" . PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php\">";
            }

            $entity = $resource->fields["entities_id"];

            echo "<table class='tab_cadre_fixe'>";
            echo "<tr><th colspan='4'>" . self::getTypeName(1) . "</th></tr>";

            echo "<tr class='tab_bg_1'><td colspan='2' class='center'>";
            echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
            echo Employer::getTypeName(1) . "</td>";
            echo "<td colspan='2'>";
            Dropdown::show(Employer::class, [
                'name' => "plugin_resources_employers_id",
                'value' => $this->fields["plugin_resources_employers_id"],
                'entity' => $entity
            ]);
            echo "</td></tr>";

            echo "<tr class='tab_bg_1'><td colspan='2' class='center'>";
            echo Client::getTypeName(1) . "</td>";
            echo "<td colspan='2'>";
            Dropdown::show(Client::class, [
                'name' => "plugin_resources_clients_id",
                'value' => $this->fields["plugin_resources_clients_id"],
                'entity' => $entity
            ]);
            echo "</td></tr>";

            if ($this->canCreate()) {
                if ($exist == 0) {
                    echo "<tr><td class='tab_bg_2 top' colspan='4'>";
                    echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                    echo "<div class='center'>";
                    echo Html::submit(
                        _sx('button', 'Next step', 'resources'),
                        ['name' => 'add_helpdesk_employee', 'class' => 'btn btn-primary']
                    );
                    echo "</td></tr>";
                } elseif (empty($ID)) {
                    echo "<tr><td class='tab_bg_2 top' colspan='4'>";
                    echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                    echo Html::submit(
                        _sx('button', 'Add'),
                        ['name' => 'add_helpdesk_employee', 'class' => 'btn btn-primary']
                    );
                    echo "</td></tr>";
                } else {
                    if ($resource->fields["is_leaving"] != 1) {
                        echo "<tr><td class='tab_bg_2 top' colspan='4'>";
                        echo Html::hidden('id', ['value' => $ID]);
                        echo Html::hidden('plugin_resources_resources_id', ['value' => $plugin_resources_resources_id]);
                        echo "<div class='center'>";
                        echo Html::submit(
                            _sx('button', 'Update'),
                            ['name' => 'updateemployee', 'class' => 'btn btn-primary']
                        );
                        echo "</div>";
                        echo "</td></tr>";
                    }
                }
            }

            echo "</table>";
            Html::closeForm();
            echo "</div>";
        }
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
        $query = "SELECT *
               FROM `glpi_plugin_resources_employees`
               WHERE `plugin_resources_resources_id` = '$ID'";
        $result = $DB->doQuery($query);
        $number = $DB->numrows($result);

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
            for ($i = 0; $i < $number; $i++) {
                $employer = $DB->result($result, $i, "plugin_resources_employers_id");
                $client = $DB->result($result, $i, "plugin_resources_clients_id");

                $pdf->displayLine(
                    Dropdown::getDropdownName("glpi_plugin_resources_employers", $employer),
                    Dropdown::getDropdownName("glpi_plugin_resources_clients", $client)
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
}
