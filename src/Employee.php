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
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Html;
use Migration;
use PluginPdfSimplePDF;
use Session;
use Toolbox;

/**
 * Class Employee
 */
class Employee extends CommonDBTM
{
    // The table has no entities_id: every generic entry point (API, massive actions) reached
    // the rows of all the entities with the Employee right alone. The row answers to its
    // resource (READ to see it, UPDATE to write it); the global right stays the Employee one.
    use ResourceChildItemRightTrait;

    public static $rightname = 'plugin_resources_employee';

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
        return _n('Employee', 'Employees', $nb, 'resources');
    }

    public static function getIcon()
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
    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
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
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
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
     * @return array|false the modified $input array, false to refuse the row
     **/
    public function prepareInputForAdd($input)
    {
        // Not attached to resource -> not added
        try {
            $resources_id = Resource::getPostedId($input['plugin_resources_resources_id'] ?? null);
        } catch (BadRequestHttpException) {
            return false;
        }
        if ($resources_id <= 0) {
            return false;
        }
        $input['plugin_resources_resources_id'] = $resources_id;
        if ($this->getFromDBByCrit(['plugin_resources_resources_id' => $resources_id])) {
            return false;
        }

        return self::checkEmployerAndClient($input, $resources_id);
    }

    /**
     * Prepare input datas for updating the item
     *
     * @param array $input datas used to update the item
     *
     * @return array|false the modified $input array, false to refuse the update
     **/
    public function prepareInputForUpdate($input)
    {
        // An employee record stays under the resource it was created for. Callers authorise
        // that stored resource; a parent sent along with the update has been checked by nobody
        // and would move the record, with its employer and client, onto another resource.
        unset($input['plugin_resources_resources_id']);

        return self::checkEmployerAndClient($input, (int) ($this->fields['plugin_resources_resources_id'] ?? 0));
    }

    /**
     * Check the employer and the client named by an input against the resource of the row.
     *
     * Both carry an entity. The forms only offer those of the entity of the resource, or of a
     * parent entity when they are recursive (the scope Dropdown::show() gives them), but the
     * values posted were stored as they came: the forms of the resource then displayed the
     * name of an employer or a client of an entity out of reach of the session.
     *
     * @param array<string, mixed> $input        input of add() or update()
     * @param int                  $resources_id resource the row belongs to
     *
     * @return array<string, mixed>|false the input, its values as plain ids, or false when one
     *                                    of them does not qualify
     */
    private static function checkEmployerAndClient(array $input, int $resources_id): array|false
    {
        $fields = [
            'plugin_resources_employers_id' => Employer::class,
            'plugin_resources_clients_id'   => Client::class,
        ];
        foreach ($fields as $field => $itemtype) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $id = self::getAllowedLinkedId($itemtype, $input[$field], $resources_id);
            if ($id === null) {
                Session::addMessageAfterRedirect(__('Item not found'), false, ERROR);
                return false;
            }
            $input[$field] = $id;
        }

        return $input;
    }

    /**
     * Employer or client that may be stored for a resource.
     *
     * @param class-string<Employer|Client> $itemtype
     * @param mixed                         $value        id, as received
     * @param int                           $resources_id resource the row belongs to
     *
     * @return int|null the id, 0 for none, null when it does not exist or is out of the scope
     *                  of the entity of the resource (its own entity, or a parent one for a
     *                  recursive value)
     */
    public static function getAllowedLinkedId(string $itemtype, mixed $value, int $resources_id): ?int
    {
        try {
            $id = Resource::getPostedId($value);
        } catch (BadRequestHttpException) {
            return null;
        }
        if ($id === 0) {
            return 0;
        }

        $resource = new Resource();
        if ($resources_id <= 0 || !$resource->getFromDB($resources_id)) {
            return null;
        }

        $dbu   = new DbUtils();
        $table = $itemtype::getTable();
        $found = $dbu->countElementsInTable(
            $table,
            ['id' => $id] + $dbu->getEntitiesRestrictCriteria(
                $table,
                '',
                (int) $resource->fields['entities_id'],
                true,
            ),
        );

        return $found > 0 ? $id : null;
    }

    /**
     * Employee write carried by a resource form (resource form, wizard second step).
     *
     * Those forms post the employer along with the resource, and the employer is stored on
     * the Employee row of the resource, which answers to the Employee right, not to the
     * resource right their caller checked. They also show the field to profiles that may
     * only read it (plugin_resources_employee_core_form) and post it back unchanged: an
     * unchanged employer writes nothing, so that those profiles still save the resource.
     *
     * Called before the resource is written, so that a refusal leaves nothing half done.
     *
     * A resource created from a template gets the Employee row of the template
     * (Resource::post_addItem()), and the forms show the employer of that row: that employer
     * posted back is no change either. Another one is the choice of the employer of a new
     * resource, which takes the same right as without template (CREATE).
     *
     * @param int                  $resources_id resource of the employer, 0 for one being created
     * @param array<string, mixed> $input        posted values
     * @param int                  $templates_id template the new resource is cloned from, as
     *                                           Resource::getTemplateSourceId() resolves it
     *
     * @return array<string, mixed>|null input of the Employee write to do once the resource is
     *                                   written (with the row id when it exists), null for none
     *
     * @throws AccessDeniedHttpException when the write needs an Employee right the session lacks
     */
    public static function getEmployerWrite(int $resources_id, array $input, int $templates_id = 0): ?array
    {
        if (!array_key_exists('plugin_resources_employers_id', $input)) {
            return null;
        }
        $employers_id = (int) $input['plugin_resources_employers_id'];

        $employee = new self();
        if ($resources_id > 0
            && $employee->getFromDBByCrit(['plugin_resources_resources_id' => $resources_id])) {
            if ((int) $employee->fields['plugin_resources_employers_id'] === $employers_id) {
                return null;
            }
            Session::checkRight(self::$rightname, UPDATE);

            return [
                'id'                            => $employee->getID(),
                'plugin_resources_employers_id' => $employers_id,
                'plugin_resources_clients_id'   => 0,
            ];
        }

        if ($resources_id <= 0
            && $templates_id > 0
            && $employee->getFromDBByCrit(['plugin_resources_resources_id' => $templates_id])) {
            if ((int) $employee->fields['plugin_resources_employers_id'] === $employers_id) {
                return null;
            }
            Session::checkRight(self::$rightname, CREATE);

            // saveEmployerWrite() writes it over the row cloned from the template
            return [
                'plugin_resources_employers_id' => $employers_id,
                'plugin_resources_clients_id'   => 0,
            ];
        }

        // No employer chosen and no right to create the row: nothing worth refusing the
        // resource for, the row would only record the absence of an employer.
        if ($employers_id <= 0 && !Session::haveRight(self::$rightname, CREATE)) {
            return null;
        }
        Session::checkRight(self::$rightname, CREATE);

        return [
            'plugin_resources_employers_id' => $employers_id,
            'plugin_resources_clients_id'   => 0,
        ];
    }

    /**
     * Run the write getEmployerWrite() prepared, once the resource is written.
     *
     * @param int                       $resources_id resource the caller wrote and checked
     * @param array<string, mixed>|null $write        see getEmployerWrite()
     *
     * @return void
     */
    public static function saveEmployerWrite(int $resources_id, ?array $write): void
    {
        if ($write === null || $resources_id <= 0) {
            return;
        }

        $employee = new self();
        if (isset($write['id'])) {
            $employee->update($write);
        } elseif ($employee->getFromDBByCrit(['plugin_resources_resources_id' => $resources_id])) {
            // The row Resource::post_addItem() cloned from the template of a new resource:
            // a resource has a single Employee row, prepareInputForAdd() refusing a second one,
            // so the employer chosen is written over the copy.
            $employee->update(['id' => $employee->getID()] + $write);
        } else {
            $employee->add(['plugin_resources_resources_id' => $resources_id] + $write);
        }
    }

    /**
     * Employer stored for a resource (or a template), the value the resource forms show.
     *
     * @param int $resources_id
     *
     * @return int 0 when the resource has no Employee row
     */
    public static function getEmployerOf(int $resources_id): int
    {
        $employee = new self();
        if ($resources_id > 0
            && $employee->getFromDBByCrit(['plugin_resources_resources_id' => $resources_id])) {
            return (int) $employee->fields['plugin_resources_employers_id'];
        }

        return 0;
    }

    /**
     * Whether the session may change the employer a resource form shows, the way
     * getEmployerWrite() decides it: UPDATE when the resource has an Employee row, CREATE for
     * a resource without one or being created (from a template or not).
     *
     * The forms show the field read-only otherwise: the value posted back unchanged writes
     * nothing, where a change would end on an access denied page losing the whole form.
     *
     * @param int $resources_id resource of the form, 0 for one being created
     *
     * @return bool
     */
    public static function canChangeEmployer(int $resources_id): bool
    {
        if ($resources_id > 0
            && (new self())->getFromDBByCrit(['plugin_resources_resources_id' => $resources_id])) {
            return Session::haveRight(self::$rightname, UPDATE);
        }

        return Session::haveRight(self::$rightname, CREATE);
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
    public static function cloneItem($oldid, $newid)
    {
        global $DB;

        $query =
            [
                'SELECT' => [
                    '*',
                ],
                'FROM' => 'glpi_plugin_resources_employees',
                'WHERE' => [
                    'plugin_resources_resources_id' => $oldid,
                ],
            ];
        foreach ($DB->request($query) as $data) {
            // The new resource may sit in an entity the employer or the client of its template
            // is not visible from: the copy keeps the row, without that value, rather than
            // have prepareInputForAdd() refuse the whole row.
            $employee = new self();
            $employee->add([
                'plugin_resources_resources_id' => $newid,
                'plugin_resources_employers_id' => self::getAllowedLinkedId(
                    Employer::class,
                    (int) $data["plugin_resources_employers_id"],
                    (int) $newid,
                ) ?? 0,
                'plugin_resources_clients_id' => self::getAllowedLinkedId(
                    Client::class,
                    (int) $data["plugin_resources_clients_id"],
                    (int) $newid,
                ) ?? 0,
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
    public function showEmployeeForm($plugin_resources_resources_id, $users_id, $withtemplate = '')
    {

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
            'span'   => 'span_location',
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
                'toupdate'  => [
                    'value_fieldname' => 'plugin_resources_clients_id',
                    'to_update'       => 'security_compliance',
                    'url'             => PLUGIN_RESOURCES_WEBDIR . '/ajax/employee.php',
                ],
            ],
        ));

        // Action buttons, rendered by employee_form.html.twig. Null when none is allowed. Each
        // one is offered on the right its handler in front/resource.form.php requires: CREATE
        // to add the row, UPDATE to change it, PURGE to delete it.
        $buttons = null;
        if ($withtemplate < 2 && $canedit) {
            if (empty($ID)) {
                if (Session::haveRight(self::$rightname, CREATE)) {
                    if (!empty($plugin_resources_resources_id)) {
                        $buttons = [
                            'mode'         => 'add',
                            'resources_id' => $plugin_resources_resources_id,
                        ];
                    } else {
                        $buttons = [
                            'mode'              => 'add_with_resource',
                            'resources_id'      => $plugin_resources_resources_id,
                            'users_id'          => $users_id,
                            'template_dropdown' => $capture(
                                fn() => Resource::dropdownTemplate("templates_id", $_SESSION["glpiactive_entity"]),
                            ),
                        ];
                    }
                }
            } else {
                $can_update = Session::haveRight(self::$rightname, UPDATE);
                $can_purge  = Session::haveRight(self::$rightname, PURGE);
                if ($can_update || $can_purge) {
                    $buttons = [
                        'mode'         => 'update',
                        'id'           => $ID,
                        'resources_id' => $this->fields["plugin_resources_resources_id"],
                        'can_update'   => $can_update,
                        'can_purge'    => $can_purge,
                    ];
                }
            }
        }

        TemplateRenderer::getInstance()->display('@resources/employee_form.html.twig', [
            'show_form'           => ($withtemplate < 2),
            'form_action'         => PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php",
            'resources_id'        => $plugin_resources_resources_id,
            'title'               => self::getTypeName(1),
            'show_created_note'   => empty($plugin_resources_resources_id),
            'created_note'        => __('The resource is also created if not existent', 'resources'),
            'label_employer'      => Employer::getTypeName(1),
            'employer_dropdown'   => $employer_dropdown,
            'label_address'       => __('Address'),
            'address_html'        => $address_html,
            'label_client'        => Client::getTypeName(1),
            'client_dropdown'     => $client_dropdown,
            'compliant'           => Client::isSecurityCompliance($this->fields["plugin_resources_clients_id"]),
            'buttons'             => $buttons,
        ]);

        return true;
    }

    /**
     * @param $plugin_resources_resources_id
     * @param $exist
     *
     * @return bool
     */
    public function showFormHelpdesk($plugin_resources_resources_id, $exist)
    {

        if (!$this->canView()) {
            return false;
        }

        $employee_spotted = false;

        // canView() above only answers for the global right, and getFromDB() applies no
        // entity restriction. Carry the per-object guard on the method itself so every
        // entry point (tab, export, another front) gets the boundary replayed too.
        $resource = new Resource();
        if (!$resource->can((int) $plugin_resources_resources_id, READ)) {
            return false;
        }

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
            'entity' => $entity,
        ]));

        $client_dropdown = $capture(fn() => Dropdown::show(Client::class, [
            'name'   => "plugin_resources_clients_id",
            'value'  => $this->fields["plugin_resources_clients_id"],
            'entity' => $entity,
        ]));

        // Action row, rendered by employee_helpdesk_form.html.twig. Null when none is allowed.
        // Adding the row (add_helpdesk_employee) requires CREATE, changing it (updateemployee)
        // UPDATE: each button is offered on the right its handler checks.
        $buttons = null;
        if ($exist == 0 || empty($ID)) {
            if (Session::haveRight(self::$rightname, CREATE)) {
                $buttons = ['mode' => $exist == 0 ? 'next' : 'add'];
            }
        } elseif ($resource->fields["is_leaving"] != 1 && Session::haveRight(self::$rightname, UPDATE)) {
            $buttons = ['mode' => 'update', 'id' => $ID];
        }

        TemplateRenderer::getInstance()->display('@resources/employee_helpdesk_form.html.twig', [
            'form_action'         => $form_action,
            'resources_id'        => $plugin_resources_resources_id,
            'title'               => self::getTypeName(1),
            'label_employer'      => Employer::getTypeName(1),
            'employer_dropdown'   => $employer_dropdown,
            'label_client'        => Client::getTypeName(1),
            'client_dropdown'     => $client_dropdown,
            'buttons'             => $buttons,
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
    public static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab)
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
    public static function pdfForResource(PluginPdfSimplePDF $pdf, Resource $appli)
    {
        global $DB;

        $ID = $appli->fields['id'];

        if (!$appli->can($ID, READ)) {
            return false;
        }

        // The on-screen tab requires the employee right: the PDF prints the same rows.
        if (!Session::haveRight(Resource::$rightname, READ) || !self::canView()) {
            return false;
        }
        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_resources_employees',
            'WHERE' => ['plugin_resources_resources_id' => (int) $ID],
        ]);
        $number = count($iterator);

        // The pdf plugin writes every argument as HTML (TCPDF writeHTMLCell): only the static
        // markup below is kept, every label and stored value is escaped.
        $pdf->setColumnsSize(100);

        $pdf->displayTitle('<b>' . htmlescape(self::getTypeName(1)) . '</b>');

        $pdf->setColumnsSize(33, 33, 34);
        $pdf->displayTitle(
            '<b><i>' .
            htmlescape(Employer::getTypeName(1)),
            htmlescape(Client::getTypeName(1)) . '</i></b>',
        );

        if (!$number) {
            $pdf->displayLine(htmlescape(__('No results found')));
        } else {
            foreach ($iterator as $data) {
                $pdf->displayLine(
                    htmlescape(Dropdown::getDropdownName("glpi_plugin_resources_employers", $data["plugin_resources_employers_id"])),
                    htmlescape(Dropdown::getDropdownName("glpi_plugin_resources_clients", $data["plugin_resources_clients_id"])),
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
    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id' => '2',
            'table' => 'glpi_plugin_resources_employers',
            'field' => 'name',
            'name' => Employer::getTypeName(1),
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '3',
            'table' => 'glpi_plugin_resources_clients',
            'field' => 'name',
            'name' => Client::getTypeName(1),
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '31',
            'table' => $this->getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'datatype' => 'number',
            'massiveaction' => false,
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
