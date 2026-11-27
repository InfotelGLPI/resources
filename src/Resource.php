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
use Alert;
use Appliance;
use CommonDBTM;
use CommonGLPI;
use CommonITILActor;
use Computer;
use ComputerType;
use ConsumableItem;
use CronTask;
use DateTime;
use DBConnection;
use DbUtils;
use Document;
use Document_Item;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryFunction;
use Glpi\DBAL\QuerySubQuery;
use Glpi\Event;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Positions\Position;
use Group_Ticket;
use Html;
use Item_Problem;
use ITILSolution;
use Item_Ticket;
use Location;
use Log;
use MassiveAction;
use Migration;
use Monitor;
use NetworkEquipment;
use Notepad;
use NotificationEvent;
use Peripheral;
use Phone;
use PhoneType;
use Plugin;
use Printer;
use Profile_User;
use Search;
use Session;
use Software;
use Symfony\Component\HttpFoundation\Response;
use Ticket;
use Toolbox;
use UserCategory;
use UserTitle;

/**
 * Class Resource
 */
class Resource extends CommonDBTM
{
    public static $rightname = 'plugin_resources';

    /**
     * Set server-side by the validation workflow (ajax/validinformation.php) only.
     * It replaces an input key, which any update($_POST) caller could forge.
     */
    public bool $allow_validation_flag = false;

    /**
     * Columns that only server-side code may change on an existing resource.
     *
     * The resource and wizard forms hand the whole request to update(), and so do the massive
     * actions: any of these columns posted along was written, which moved a resource to an
     * entity outside the transfer workflow, or rewrote its template, trash or declaration data.
     */
    private const STRUCTURAL_FIELDS = [
        'entities_id',
        'is_recursive',
        'is_template',
        'is_deleted',
        'users_id_recipient',
        'date_declaration',
    ];

    /**
     * Structural columns (STRUCTURAL_FIELDS) the next update() of this object may write.
     *
     * Set by the server-side code that legitimately changes them (transferResource(), the
     * resource import, the resource form for the writer), never from the input. Consumed by
     * prepareInputForUpdate().
     *
     * @var string[]
     */
    public array $allow_structural_fields = [];

    /**
     * Whether the next update() of this object may write a resource whose information is
     * frozen by the validation (isFrozenAfterValidation()).
     *
     * Set by updateFromWorkflow() only, never from the input. Consumed by
     * prepareInputForUpdate().
     */
    private bool $allow_frozen_update = false;

    public static $types = [
        Computer::class,
        Monitor::class,
        NetworkEquipment::class,
        Peripheral::class,
        Phone::class,
        Printer::class,
        Software::class,
        ConsumableItem::class,
        \User::class,
        Appliance::class,
        ComputerType::class,
        PhoneType::class,
    ];

    public static $itemtype = Resource::class;

    protected $usenotepad = true;

    public $dohistory = true;

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @return string
     **/
    public static function getTypeName($nb = 0)
    {
        return _n('Human resource', 'Human resources', $nb, 'resources');
    }

    public static function getIcon()
    {
        return "ti ti-friends";
    }

    /**
     * Identifier received from a client (form field, array key, massive action key).
     *
     * The value a right is checked on has to be the value the operation then uses. A request
     * value that is not a plain positive integer ("x123", "5x3", "4.0", " 7", an array...)
     * can be read as two different ids by the check and by the operation, so it is refused
     * here rather than cast: callers check and act on the integer returned, never on the raw
     * value.
     *
     * @param mixed $value posted value
     *
     * @return int the id, 0 when the value is absent or empty
     *
     * @throws BadRequestHttpException when the value is not a canonical positive integer
     */
    public static function getPostedId(mixed $value): int
    {
        // Absent, or the empty choice of a dropdown
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return 0;
        }
        if (is_int($value) && $value > 0) {
            return $value;
        }
        // Digits only, no leading zero; filter_var() refuses what overflows an int
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if (is_int($id)) {
                return $id;
            }
        }

        throw new BadRequestHttpException();
    }

    /**
     * check() on an existing item designated by a posted id.
     *
     * The id is refused unless it is a plain positive integer: on 0, check() would only test
     * the right to create an item, whatever right was asked for. $item is left loaded with the
     * row checked, and the id returned is the one the caller may go on with.
     *
     * @param CommonDBTM $item  instance of the itemtype designated
     * @param mixed      $id    id posted
     * @param int        $right right to require on the item
     *
     * @return int id of the item checked
     */
    public static function checkPostedItem(CommonDBTM $item, mixed $id, int $right): int
    {
        $items_id = self::getPostedId($id);
        if ($items_id <= 0) {
            throw new BadRequestHttpException();
        }
        $item->check($items_id, $right);

        return $items_id;
    }

    /**
     * Keep the massive action keys that are canonical ids, report the other ones as failed.
     *
     * The massive action engine passes the posted keys through untouched: each handler checks
     * one of them with can() and then writes with it, so both have to be the same integer.
     *
     * @param array<int|string, mixed> $ids keys posted to the massive action
     *
     * @return array<int, mixed> the valid keys, as integers
     */
    public static function getMassiveActionIds(MassiveAction $ma, CommonDBTM $item, array $ids): array
    {
        $valid = [];
        foreach ($ids as $key => $val) {
            try {
                $id = self::getPostedId($key);
            } catch (BadRequestHttpException) {
                $id = 0;
            }
            if ($id > 0) {
                $valid[$id] = $val;
            } else {
                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
            }
        }

        return $valid;
    }

    /**
     * Guard a mutation performed on a row owned by a Resource.
     *
     * Several child itemtypes of this plugin (LinkAd, ResourceHabilitation,
     * ResourceHoliday, ResourceResting, Employee, ...) carry no entities_id column of
     * their own. On those, CommonDBTM::check()/can() cannot enforce any entity boundary:
     * checkEntity() short-circuits to true as soon as isEntityAssign() is false, so a
     * check() called directly on the child degrades to a plain global right test and lets
     * a user of entity A mutate rows belonging to entity B. The boundary only exists on
     * the owning Resource, so it has to be checked there.
     *
     * An existing Resource is required: on id 0, check() would only test the right to create
     * one. The id returned is the one checked, the only one the caller may go on with.
     *
     * @param mixed $resources_id id of the owning Resource, as posted
     * @param int   $right        right to require on that Resource
     *
     * @return int id of the Resource checked
     */
    public static function checkOwnership(mixed $resources_id, int $right = UPDATE): int
    {
        return self::checkPostedItem(new self(), $resources_id, $right);
    }

    /**
     * Same guard, for a mutation targeting an existing child row.
     *
     * The owning Resource is resolved from the child row read back from the database,
     * never from a parent id posted alongside it: the two are not correlated, so trusting
     * the posted parent would let a caller pass a Resource they own while naming a child
     * id belonging to another one (identifier substitution).
     *
     * $child is left loaded with the row checked. The id returned is the one of that row,
     * the only one the caller may go on with.
     *
     * @param CommonDBTM $child    empty instance of the child itemtype
     * @param mixed      $child_id id of the child row being mutated, as posted
     * @param int        $right    right to require on the owning Resource
     *
     * @return int id of the child row checked
     */
    public static function checkChildOwnership(CommonDBTM $child, mixed $child_id, int $right = UPDATE): int
    {
        $id = self::getPostedId($child_id);
        if ($id <= 0 || !$child->getFromDB($id)) {
            throw new BadRequestHttpException();
        }

        self::checkOwnership($child->fields['plugin_resources_resources_id'] ?? 0, $right);

        return $id;
    }

    /**
     * Whether a row may be attached to the resource named by an input.
     *
     * Attaching a row (employment, period, habilitation...) to a resource changes that
     * resource: UPDATE is required on it, entity included. The check made on the row itself
     * says nothing about the resource it is being moved to. Keeping the current resource, or
     * attaching to none, requires nothing more.
     *
     * @param mixed $resources_id resource named by the input, as received
     * @param int   $current_id   resource the row is attached to now, 0 for a new row
     *
     * @return bool
     */
    public static function canAttachTo(mixed $resources_id, int $current_id = 0): bool
    {
        try {
            $id = self::getPostedId($resources_id);
        } catch (BadRequestHttpException) {
            return false;
        }

        return $id === 0 || $id === $current_id || (new self())->can($id, UPDATE);
    }

    /**
     * Template a resource may be created from.
     *
     * The template data, and in post_addItem() its child rows, end up in the new resource:
     * only an actual template the session may read qualifies, never a plain resource nor one
     * out of the session's entities.
     *
     * @param int $templates_id id of the template, as checked by the caller
     *
     * @return self|null the template, loaded, or null when it does not qualify
     */
    public static function getAllowedTemplate(int $templates_id): ?self
    {
        $template = new self();
        if ($templates_id <= 0
            || !$template->getFromDB($templates_id)
            || (int) $template->fields['is_template'] !== 1
            || !$template->can($templates_id, READ)) {
            return null;
        }

        return $template;
    }

    /**
     * Whether the session may choose the writer (users_id_recipient) of a resource.
     *
     * The resource form only offers the field to those who may declare resources, in the
     * central interface: its update branch lets the update write it on the same test.
     *
     * @return bool
     */
    public static function canSetRecipient(): bool
    {
        return self::canCreate() && Session::getCurrentInterface() == 'central';
    }

    /**
     * Refuse a write on this resource once its information is frozen by the validation.
     *
     * The frozen form only hides its buttons: every server-side path that writes the resource
     * from its form or from the wizard replays the test through this method.
     *
     * @return void
     *
     * @throws AccessDeniedHttpException
     */
    public function checkNotFrozen(): void
    {
        if ($this->isFrozenAfterValidation()) {
            throw new AccessDeniedHttpException();
        }
    }

    /**
     * update() made by a workflow meant to change a resource after its validation.
     *
     * Once the information of a resource is validated and frozen, prepareInputForUpdate()
     * refuses to write it: the forms were the only writers to test the freeze, and the generic
     * entry points of the core (the "Update" and "Amend comment" massive actions, the legacy
     * API) went around it. The changes the plugin makes on purpose afterwards (change actions,
     * leaving, transfer, import, scheduled state changes, synchronization of the location) go
     * through this method.
     *
     * @param array<string, mixed> $input input of update()
     *
     * @return bool
     */
    public function updateFromWorkflow(array $input): bool
    {
        $this->allow_frozen_update = true;
        try {
            return $this->update($input);
        } finally {
            $this->allow_frozen_update = false;
        }
    }

    /**
     * Whether the session may synchronize this resource with the directory.
     *
     * Same condition as the synchronization side of the validation tab (defineTabs()): the
     * validation module is on, the direct manager validated the information, and the active
     * profile is one of those allowed to synchronize.
     *
     * @return bool
     */
    public function canSynchronizeAD(): bool
    {
        $config = new Config();
        if (empty($config->fields['use_module_validation'])
            || empty($this->fields['valid_resource_information'])) {
            return false;
        }

        $profiles = json_decode((string) ($config->fields['can_view_synchronisationAD'] ?? ''));

        return is_array($profiles)
            && in_array($_SESSION['glpiactiveprofile']['id'] ?? 0, $profiles);
    }

    /**
     * Delete the child rows left behind by resources purged before cleanDBonPurge() covered
     * their table: directory links, leaving information and imported values.
     *
     * Such a row was adopted by whatever resource got the id of the purged one afterwards.
     * Idempotent, run by the install and update of the plugin.
     *
     * @return void
     */
    public static function deleteOrphanChildRows(): void
    {
        global $DB;

        foreach ([LinkAd::getTable(), LeavingInformation::getTable(), ResourceImport::getTable()] as $table) {
            if (!$DB->tableExists($table)) {
                continue;
            }
            $DB->delete($table, [
                'NOT' => [
                    'plugin_resources_resources_id' => new QuerySubQuery([
                        'SELECT' => 'id',
                        'FROM'   => self::getTable(),
                    ]),
                ],
            ]);
        }
    }

    /**
     * @return array
     */
    public static function getDataNames()
    {
        return [
            __("Firstname", "resources"),
            __("Lastname", "resources"),
            __("ContractType", "resources"),
            __("Associed User", "resources"),
            __("Location", "resources"),
            __("Resource manager", "resources"),
            __("Department", "resources"),
            __("Arrival date", "resources"),
            __("Departure date", "resources"),
            __("Sales manager", "resources"),
            __("Other", "resources"),
            Team::getTypeName(0),
        ];
    }

    /**
     * @param $dataNameID
     *
     * @return string|null
     */
    public static function getResourceColumnNameFromDataNameID($dataNameID)
    {
        $dataNames = [
            //         "id",
            "firstname",
            "name",
            "plugin_resources_contracttypes_id",
            "users_id_recipient",
            "locations_id",
            "users_id",
            "plugin_resources_departments_id",
            "date_begin",
            "date_end",
            "users_id_sales",
            "other",
            "plugin_resources_teams_id",
        ];

        if (!array_key_exists($dataNameID, $dataNames)) {
            throw new BadRequestHttpException(__("Resource column $dataNameID not found", "resources"));
            return null;
        }
        return $dataNames[$dataNameID];
    }

    /**
     * @return string[]
     */
    public static function getDataTypes()
    {
        $dataTypes = [
            "String",
            "String",
            "ContractType",
            "User",
            "Location",
            "User",
            "Department",
            "Date",
            "Date",
            "User",
            "String",
            "Team",
        ];

        return $dataTypes;
    }

    public static function getDataType($dataNameId)
    {
        $dataTypes = self::getDataTypes();

        if (!array_key_exists($dataNameId, $dataTypes)) {
            throw new BadRequestHttpException(__("Data Type not found", "resources"));
            return null;
        }
        return $dataTypes[$dataNameId];
    }

    public static function getColumnName($dataNameId)
    {
        $columnNames = [
            "firstname",
            "name",
            "plugin_resources_contracttypes_id",
            "users_id",
            "locations_id",
            "users_id_recipient",
            "plugin_resources_departments_id",
            "date_begin",
            "date_end",
            "users_id_sales",
            "others",
            "plugin_resources_teams_id",
        ];

        if (!array_key_exists($dataNameId, $columnNames)) {
            throw new BadRequestHttpException(__("Resource column name not found", "resources"));
            return null;
        }

        return $columnNames[$dataNameId];
    }

    /**
     * For other plugins, add a type to the linkable types
     *
     *
     * @param $type string class name
     **/
    public static function registerType($type)
    {
        if (!in_array($type, self::$types)) {
            self::$types[] = $type;
        }
    }

    /**
     * Type than could be linked to a Resource
     *
     * @param $all boolean, all type, or only allowed ones
     *
     * @return array of types
     **/
    public static function getTypes($all = false)
    {
        if ($all) {
            return self::$types;
        }

        // Only allowed types
        $types = self::$types;

        foreach ($types as $key => $type) {
            if (!class_exists($type)) {
                continue;
            }

            $item = new $type();
            if (!$item->canView()) {
                unset($types[$key]);
            }
        }
        return $types;
    }

    /**
     * Actions done when item is deleted from the database
     *
     * @return nothing
     **/
    public function cleanDBonPurge()
    {
        $temp = new Resource_Item();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new Choice();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new Task();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']], 1);

        $temp = new Employee();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new ReportConfig();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new Checklist();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new ResourceResting();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new ResourceHoliday();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new ResourceHabilitation();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        // These rows used to survive the purge: a resource created later with the same id
        // adopted them, the directory account named by the link included.
        $temp = new LinkAd();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new LeavingInformation();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);

        $temp = new ResourceImport();
        $temp->deleteByCriteria(['plugin_resources_resources_id' => $this->fields['id']]);
    }

    /**
     * Hook called After an item is purge
     *
     * @param CommonDBTM $item
     */
    public static function cleanForItem(CommonDBTM $item)
    {
        $type = get_class($item);
        $temp = new Resource_Item();
        $temp->deleteByCriteria([
            'itemtype' => $type,
            'items_id' => $item->getField('id'),
        ]);

        $task = new Task_Item();
        $task->deleteByCriteria([
            'itemtype' => $type,
            'items_id' => $item->getField('id'),
        ]);
    }

    public function playnotification($resource)
    {
        NotificationEvent::raiseEvent("AlertLeavingRessourceManager", $resource);
    }

    /**
     * Declare the leaving of the loaded resource from the leaving form.
     *
     * Updates the resource, stores its leaving information, seeds the leaving checklists,
     * creates the leaving ticket, notifies the managers and pushes the end date to the
     * directory, as configured in the plugin setup.
     * The caller is responsible for authorising UPDATE on the resource.
     *
     * @param array $post Leaving form values
     *
     * @return void
     */
    public function declareLeaving(array $post): void
    {
        global $CFG_GLPI;

        $date_end = $post["date_end"] ?? '';
        if (empty($date_end)) {
            if (!empty($post["effective_leaving_date"])) {
                $date_end = $post["effective_leaving_date"];
            } elseif (!empty($post["resignation_date"])) {
                $date_end = $post["resignation_date"];
            } else {
                $date_end = date("Y-m-d");
            }
        }

        $resources_id = $this->getID();
        $config = new Config();
        $CronTask = new CronTask();
        $CronTask->getFromDBbyName(Employment::class, "ResourcesLeaving");

        $input = [
            'id' => $resources_id,
            'date_end' => $date_end,
            'remove_manager' => $post["remove_manager"] ?? 0,
            'plugin_resources_leavingreasons_id' => $post["plugin_resources_leavingreasons_id"] ?? 0,
            'withtemplate' => "0",
            'users_id_recipient_leaving' => Session::getLoginUserID(),
            'send_notification' => 1,
        ];
        if (!$config->fields['remove_at_midnight']) {
            $input["date_end"] .= " 23:59:59";
        }
        if (($date_end < date("Y-m-d H:i:s"))
            || ($CronTask->fields["state"] == CronTask::STATE_DISABLE)) {
            $input["is_leaving"] = "1";
            $input["date_declaration_leaving"] = date('Y-m-d H:i:s');
        } else {
            $input["is_leaving"] = "0";
            $input["date_declaration_leaving"] = null;
        }
        // The leaving is declared after the validation of the arrival information
        $this->updateFromWorkflow($input);

        LeavingInformation::saveForResource($resources_id, $post);

        $this->getFromDB($resources_id);
        if (!Checklist::checkIfChecklistExist($resources_id, Checklist::RESOURCES_CHECKLIST_OUT)) {
            (new Checklistconfig())->addChecklistsFromRules($this, Checklist::RESOURCES_CHECKLIST_OUT);
        }
        Session::addMessageAfterRedirect(__('Declaration of resource leaving OK', 'resources'));

        $config->getFromDB(1);
        if ($config->fields["create_ticket_departure"]) {
            $this->getFromDB($resources_id);
            $this->createLeavingTicket($input["date_end"], (int) $input["plugin_resources_leavingreasons_id"]);

            // The directory has to be updated again for this departure.
            LinkAd::setActionDoneForResource($resources_id, false);
        }

        if ($config->fields["create_ticket_departure_instructions"]
            && isset($this->input['send_notification'])
            && $this->input['send_notification'] == 1
            && $CFG_GLPI["notifications_mailing"]
        ) {
            $this->playnotification($this);
        }

        LinkAd::syncLeavingToDirectory($this);
    }

    /**
     * Solve the open tickets linked to the resource with the given solution content.
     *
     * When the escalade plugin is active, the first group of the escalation history is
     * assigned back to each ticket before it is solved.
     * The caller is responsible for authorising UPDATE on the resource.
     *
     * @param string $content Solution content (rich text, already escaped)
     *
     * @return void
     */
    public function solveOpenTickets(string $content): void
    {
        $ticket = new Ticket();
        $ticketResources = (new Item_Ticket())->find([
            'itemtype' => self::class,
            'items_id' => $this->getID(),
        ]);

        foreach ($ticketResources as $ticketResource) {
            $ticket->getFromDB($ticketResource['tickets_id']);
            if ($ticket->getField('status') >= Ticket::SOLVED) {
                continue;
            }
            if (Plugin::isPluginActive("escalade")) {
                $first_history = \PluginEscaladeHistory::getFirstLineForTicket($ticketResource['tickets_id']);
                //add the first history group (if not already exist)
                $group_ticket = new Group_Ticket();
                $condition = [
                    'tickets_id' => $ticketResource['tickets_id'],
                    'groups_id' => $first_history['groups_id'],
                    'type' => CommonITILActor::ASSIGN,
                ];
                if (!$group_ticket->find($condition)) {
                    $group_ticket->add($condition);
                }
            }
            (new ITILSolution())->add([
                'itemtype' => Ticket::class,
                'items_id' => $ticket->getID(),
                'content' => $content,
            ]);
        }
        NotificationEvent::raiseEvent('other', $this);
    }

    /**
     * Create the leaving ticket of the resource, as configured in the plugin setup.
     *
     * The ticket carries the identity of the resource and is linked to it, so it is created in
     * the entity of the resource: the active entity of the session can be a parent one, which
     * would expose those fields to technicians outside the perimeter of the resource.
     *
     * @param string|null $date_end      Leaving date to print, defaults to the one of the resource
     * @param int|null    $leavingreason Leaving reason, defaults to the one of the resource
     * @param bool        $with_order    Append the leaving instructions (remove_order)
     *
     * @return int|false ID of the created ticket
     */
    public function createLeavingTicket(?string $date_end = null, ?int $leavingreason = null, bool $with_order = false)
    {
        $config = new Config();
        $config->getFromDB(1);

        $ticket = new Ticket();
        $tt = $ticket->getITILTemplateToUse(0, Ticket::DEMAND_TYPE, $config->fields["categories_id"]);
        if (count($tt->predefined)) {
            foreach ($tt->predefined as $predeffield => $predefvalue) {
                $ticket->fields[$predeffield] = $predefvalue;
            }
        }

        $identity = $this->fields['name'] . " " . $this->fields['firstname'];
        $date_end ??= $this->fields['date_end'];
        $leavingreason ??= (int) $this->fields['plugin_resources_leavingreasons_id'];

        // The name is a plain text field; the content is rich text, so every free value
        // concatenated into it is escaped.
        $ticket->fields["name"] = __("Departure of", 'resources') . " " . $identity;
        $ticket->fields["itilcategories_id"] = $config->fields["categories_id"];
        $content = htmlescape($identity . " " . __("leave on", "resources") . " " . Html::convDate($date_end));
        if (!empty($leavingreason)) {
            $content .= "<br>" . htmlescape(LeavingReason::getTypeName(0) . " : "
                . Dropdown::getDropdownName(LeavingReason::getTable(), $leavingreason));
        }
        if (!empty($this->fields['plugin_resources_contracttypes_id'])) {
            $content .= "<br>" . htmlescape(ContractType::getTypeName(0) . " : "
                . Dropdown::getDropdownName(ContractType::getTable(), $this->fields['plugin_resources_contracttypes_id']));
        } else {
            $content .= "<br>" . htmlescape(ContractType::getTypeName(0) . " : " . __("Without contract", 'resources'));
        }
        if ($with_order) {
            $content .= "<br>" . htmlescape(__("Order", 'resources') . " : " . $this->fields['remove_order']);
        }
        $ticket->fields["content"] = $content;
        $ticket->fields['users_id_recipient'] = Session::getLoginUserID();
        $ticket->fields['_users_id_requester'] = Session::getLoginUserID();
        $ticket->fields["type"] = Ticket::DEMAND_TYPE;
        $ticket->fields["entities_id"] = $this->fields['entities_id'];
        $ticket->fields['items_id'] = [self::class => [$this->getID()]];
        unset($ticket->fields["id"]);

        return $ticket->add($ticket->fields);
    }

    /**
     * Get Tab Name used for itemtype
     *
     * NB : Only called for existing object
     *      Must check right on what will be displayed + template
     *
     * @param CommonGLPI $item Item on which the tab need to be displayed
     * @param bool $withtemplate is a template object ? (default 0)
     *
     * @return string tab name
     **@since 0.83
     *
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == Client::class
            && $this->canView()) {
            return self::createTabEntry(self::getTypeName(2));
        }

        if ($item->getType() == self::class
            && $this->canView()) {
            return self::createTabEntry(__('Recruiting information', 'resources'));
        }
        return '';
    }

    /**
     * show Tab content
     *
     * @param CommonGLPI $item Item on which the tab need to be displayed
     * @param int $tabnum tab number (default 1)
     * @param bool $withtemplate is a template object ? (default 0)
     *
     * @return bool
     **@since 0.83
     *
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == Client::class) {
            $self = new self();
            $self->showListResourcesForClient($item->getField('id'));
        }
        if ($item->getType() == self::class) {
            $wizard = new Wizard();
            $wizard->wizardEightStep($item->getField('id'), ['default_button' => true, 'target' => 'item']);
        }
        return true;
    }

    public static function getDefaultSearchRequest()
    {

        $config = new Config();
        $search = ['sort'  => $config->fields['order_column'],
            'order' => $config->fields['order_order'],];

        return $search;
    }

    /**
     * Get the Search options for the given Type
     *
     * This should be overloaded in Class
     *
     * @return an array of search options
     * More information on https://forge.indepnet.net/wiki/glpi/SearchEngine
     **/
    public function rawSearchOptions()
    {

        $tab[] = [
            'id' => 'common',
            'name' => self::getTypeName(2),
        ];

        $tab[] = [
            'id' => '1',
            'table' => $this->getTable(),
            'field' => 'name',
            'name' => __('Surname'),
            'datatype' => 'itemlink',
            'itemlink_type' => $this->getType(),
        ];

        $tab[] = [
            'id' => '2',
            'table' => $this->getTable(),
            'field' => 'firstname',
            'name' => __('First name'),
        ];

        $tab[] = [
            'id' => '37',
            'table' => 'glpi_plugin_resources_contracttypes',
            'field' => 'name',
            'name' => ContractType::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '4',
            'table' => 'glpi_users',
            'field' => 'name',
            'name' => __('Resource manager', 'resources'),
            'datatype' => 'dropdown',
            'right' => 'all',
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[4] += ['searchtype' => 'contains'];
        }

        $tab[] = [
            'id' => '5',
            'table' => $this->getTable(),
            'field' => 'date_begin',
            'name' => __('Arrival date', 'resources'),
            'datatype' => 'date',
        ];
        $tab[] = [
            'id' => '6',
            'table' => $this->getTable(),
            'field' => 'date_end',
            'name' => __('Departure date', 'resources'),
            'datatype' => 'date',
        ];
        $tab[] = [
            'id' => '7',
            'table' => $this->getTable(),
            'field' => 'comment',
            'name' => __('Description'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id' => '8',
            'table' => 'glpi_plugin_resources_resources_items',
            'field' => 'items_id',
            'name' => _n('Associated item', 'Associated items', 2),
            'massiveaction' => false,
            'forcegroupby' => false,
            'nosearch' => false,
            'joinparams' => ['jointype' => 'child'],
        ];

        $tab[] = [
            'id' => '9',
            'table' => $this->getTable(),
            'field' => 'date_declaration',
            'name' => __('Request date'),
            'datatype' => 'date',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '10',
            'table' => 'glpi_users',
            'field' => 'name',
            'linkfield' => 'users_id_recipient',
            'name' => __('Requester'),
            'datatype' => 'dropdown',
            'right' => 'all',
            'massiveaction' => false,
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[10] += ['searchtype' => 'contains'];
        }
        $tab[] = [
            'id' => '11',
            'table' => 'glpi_plugin_resources_departments',
            'field' => 'name',
            'name' => Department::getTypeName(1),
            'datatype' => 'dropdown',
        ];
        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());
        $tab[] = [
            'id' => '36',
            'table' => $this->getTable(),
            'field' => 'is_leaving',
            'name' => __('Declared as leaving', 'resources'),
            'datatype' => 'bool',
        ];
        $tab[] = [
            'id' => '14',
            'table' => 'glpi_users',
            'field' => 'name',
            'linkfield' => 'users_id_recipient_leaving',
            'name' => __('Informant of leaving', 'resources'),
            'datatype' => 'dropdown',
            'right' => 'all',
            'massiveaction' => false,
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[2] += ['searchtype' => 'contains'];
        }

        $tab[] = [
            'id' => '15',
            'table' => $this->getTable(),
            'field' => 'is_helpdesk_visible',
            'name' => __('Associable to a ticket'),
            'datatype' => 'bool',
        ];
        $tab[] = [
            'id' => '16',
            'table' => $this->getTable(),
            'field' => 'date_mod',
            'name' => __('Last update'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '17',
            'table' => 'glpi_plugin_resources_resourcestates',
            'field' => 'name',
            'name' => ResourceState::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '18',
            'table' => $this->getTable(),
            'field' => 'picture',
            'name' => __('Photo', 'resources'),
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '19',
            'table' => $this->getTable(),
            'field' => 'is_recursive',
            'name' => __('Child entities'),
            'datatype' => 'bool',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '20',
            'table' => $this->getTable(),
            'field' => 'quota',
            'name' => __('Quota', 'resources'),
            'datatype' => 'decimal',
        ];
        //To have Field in dataInjection
        //      if (Session::getCurrentInterface() != 'central') {

        $tab[] = [
            'id' => '21',
            'table' => 'glpi_plugin_resources_resourcesituations',
            'field' => 'name',
            'name' => ResourceSituation::getTypeName(1),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '22',
            'table' => 'glpi_plugin_resources_contractnatures',
            'field' => 'name',
            'name' => ContractNature::getTypeName(1),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '23',
            'table' => 'glpi_plugin_resources_ranks',
            'field' => 'name',
            'name' => Rank::getTypeName(1),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '24',
            'table' => 'glpi_plugin_resources_resourcespecialities',
            'field' => 'name',
            'name' => ResourceSpeciality::getTypeName(1),
            'massiveaction' => false,
            'datatype' => 'dropdown',
        ];
        //      }

        $tab[] = [
            'id' => '25',
            'table' => 'glpi_plugin_resources_leavingreasons',
            'field' => 'name',
            'name' => LeavingReason::getTypeName(1),
            'datatype' => 'dropdown',
        ];
        $tab[] = [
            'id' => '27',
            'table' => 'glpi_users',
            'field' => 'name',
            'linkfield' => 'users_id_sales',
            'name' => __('Sales manager', 'resources'),
            'datatype' => 'dropdown',
            'right' => 'all',
        ];

        if (Session::getCurrentInterface() != 'central') {
            $tab[27] += ['searchtype' => 'contains'];
        }
        $tab[] = [
            'id' => '28',
            'table' => $this->getTable(),
            'field' => 'date_declaration_leaving',
            'name' => __('Declaration of departure date', 'resources'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];

        $config = new Config();
        if ($config->useSecurity()) {
            $tab[] = [
                'id' => '29',
                'table' => $this->getTable(),
                'field' => 'read_chart',
                'name' => __('Reading the security charter', 'resources'),
                'datatype' => 'bool',
                'massiveaction' => true,
            ];
            $tab[] = [
                'id' => '30',
                'table' => $this->getTable(),
                'field' => 'sensitize_security',
                'name' => __('Sensitized to security', 'resources'),
                'datatype' => 'bool',
                'massiveaction' => true,
            ];
        }

        $tab[] = [
            'id' => '32',
            'table' => 'glpi_plugin_resources_habilitations',
            'field' => 'name',
            'name' => Habilitation::getTypeName(),
            'datatype' => 'itemlink',
            'forcegroupby' => true,
            'massiveaction' => false,
            'joinparams' => [
                'beforejoin'
                => [
                    'table' => 'glpi_plugin_resources_resourcehabilitations',
                    'joinparams' => ['jointype' => 'child'],
                ],
            ],
        ];
        $tab[] = [
            'id' => '33',
            'table' => 'glpi_plugin_resources_employers',
            'field' => 'name',
            'name' => Employer::getTypeName(),
            'datatype' => 'itemlink',
            'forcegroupby' => false,
            'massiveaction' => false,
            'joinparams' => [
                'join'
                => [
                    'table' => 'glpi_plugin_resources_employees',
                    'joinparams' => ['jointype' => 'child'],
                ],
            ],
        ];
        $tab[] = [
            'id' => '34',
            'table' => 'glpi_plugin_resources_clients',
            'field' => 'name',
            'name' => Client::getTypeName(),
            'datatype' => 'itemlink',
            'forcegroupby' => false,
            'massiveaction' => false,
            'joinparams' => [
                'join'
                => [
                    'table' => 'glpi_plugin_resources_employees',
                    'joinparams' => ['jointype' => 'child'],
                ],
            ],
        ];
        if ($config->useSecurityCompliance()) {
            $tab[] = [
                'id' => '35',
                'table' => 'glpi_plugin_resources_employers',
                'field' => 'id',
                'name' => __('Client Sensitized to security', 'resources'),
                'datatype' => 'specific',
                'massiveaction' => false,
                'joinparams' => [
                    'join'
                    => [
                        'table' => 'glpi_plugin_resources_employees',
                        'joinparams' => ['jointype' => 'child'],
                    ],
                ],
            ];
        }
        $tab[] = [
            'id' => '31',
            'table' => $this->getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'massiveaction' => false,
            'datatype' => 'number',
        ];

        $tab[] = [
            'id' => '80',
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __('Entity'),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => '38',
            'table' => $this->getTable(),
            'field' => 'sensitize_security',
            'name' => __('Sensitized to security', 'resources'),
            'datatype' => 'bool',
            'massiveaction' => true,
        ];
        $tab[] = [
            'id' => '39',
            'table' => $this->getTable(),
            'field' => 'matricule',
            'name' => __('Matricule', 'resources'),
            'datatype' => 'text',
            'massiveaction' => true,
        ];

        $tab[] = [
            'id' => '40',
            'table' => Role::getTable(),
            'field' => 'name',
            'name' => Role::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
        ];
        $tab[] = [
            'id' => '41',
            'table' => Service::getTable(),
            'field' => 'name',
            'name' => Service::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
        ];

        $tab[] = [
            'id' => '42',
            'table' => ResourceFunction::getTable(),
            'field' => 'name',
            'name' => ResourceFunction::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
        ];

        $tab[] = [
            'id' => '43',
            'table' => Team::getTable(),
            'field' => 'name',
            'name' => Team::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
            'nosort' => true,
        ];

        $tab[] = [
            'id' => '44',
            'table' => $this->getTable(),
            'field' => 'matricule_second',
            'name' => __('Second matricule', 'resources'),
        ];
        $tab[] = [
            'id' => '45',
            'table' => $this->getTable(),
            'field' => 'date_agreement_candidate',
            'name' => __('Date agreement candidate', 'resources'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '46',
            'table' => DegreeGroup::getTable(),
            'field' => 'name',
            'name' => DegreeGroup::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
        ];
        $tab[] = [
            'id' => '47',
            'table' => RecruitingSource::getTable(),
            'field' => 'name',
            'name' => RecruitingSource::getTypeName(),
            'datatype' => 'dropdown',
            'massiveaction' => true,
        ];
        $tab[] = [
            'id' => '48',
            'table' => $this->getTable(),
            'field' => 'yearsexperience',
            'name' => __('Number of years experience', 'resources'),
            'massiveaction' => false,
            'datatype' => 'number',
        ];
        $tab[] = [
            'id' => '49',
            'table' => $this->getTable(),
            'field' => 'reconversion',
            'name' => __('Reconversion', 'resources'),
            'datatype' => 'bool',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => '50',
            'table' => $this->getTable(),
            'field' => 'date_of_last_contract_type',
            'name' => __('Date of last contract type', 'resources'),
            'datatype' => 'date',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '54',
            'table' => 'glpi_plugin_resources_contracttypes',
            'field' => 'name',
            'linkfield' => 'last_contract_type',
            'name' => __('Last contract type', 'resources'),
            'datatype' => 'dropdown',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '52',
            'table' => $this->getTable(),
            'field' => 'date_of_last_location',
            'name' => __('Date of last location', 'resources'),
            'datatype' => 'date',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id' => '53',
            'table' => Location::getTable(),
            'field' => 'name',
            'linkfield' => 'last_location',
            'name' => __('Last location', 'resources'),
            'datatype' => 'dropdown',
            'massiveaction' => false,
        ];

        //        $tab[] = [
        //            'id'        => '54',
        //            'table'     => 'glpi_plugin_resources_workprofiles',
        //            'field'     => 'name',
        //            'linkfield' => 'plugin_resources_workprofiles_id_entrance',
        //            'name'      => WorkProfile::getTypeName(),
        //            'datatype'  => 'dropdown',
        //            'right'     => 'all'
        //        ];
        $tab[] = [
            'id' => '55',
            'table' => 'glpi_plugin_resources_candidateorigins',
            'field' => 'name',
            'linkfield' => 'plugin_resources_candidateorigins_id',
            'name' => Candidateorigin::getTypeName(),
            'datatype' => 'dropdown',
            'right' => 'all',
        ];
        $tab[] = [
            'id' => '56',
            'table' => $this->getTable(),
            'field' => 'gender',
            'name' => __('Gender', 'resources'),
            'datatype' => 'specific',
            'searchtype' => ['equals', 'notequals'],
        ];
        //      $tab[] = [
        //         'id'    => '45',
        //         'table' => $this->getTable(),
        //         'field' => 'society',
        //         'name'  => __('Society', 'resources'),
        //      ];

        $tab = array_merge($tab, LeavingInformation::rawSearchOptionsToAdd(get_class($this)));
        return $tab;
    }

    /**
     * Define tabs to display
     *
     * NB : Only called for existing object
     *
     * @param $options array
     *     - withtemplate is a template view ?
     *
     * @return array containing the onglets
     **/
    public function defineTabs($options = [])
    {
        $ong = [];

        $config = new Config();

        $this->addDefaultFormTab($ong);
        $this->addStandardTab(Resource::class, $ong, $options);
        if (Session::getCurrentInterface() == 'central') {

            $this->addStandardTab(Resource_Item::class, $ong, $options);

            $resourceItem = new Resource_Item();
            $resourceUsers = $resourceItem->find([
                'plugin_resources_resources_id' => $this->getID(),
                'itemtype' => 'User',
            ]);

            if (is_array($resourceUsers)
                && count($resourceUsers) > 0) {
                $this->addStandardTab(User::class, $ong, $options);
            }
        }

        $this->addStandardTab(Choice::class, $ong, $options);

        // The tab serves the direct manager while the information awaits validation, then the
        // profiles allowed to synchronize it: canSynchronizeAD() is shared with the controller.
        if ($config->fields['use_module_validation']
            && ((!$this->fields['valid_resource_information'] && Session::getLoginUserID() == $this->fields['users_id'])
                || $this->canSynchronizeAD())
        ) {
            $this->addStandardTab(Resource_Validation::class, $ong, $options);
        }
        $this->addStandardTab(ResourceHabilitation::class, $ong, $options);
        $this->addStandardTab(Employment::class, $ong, $options);
        $this->addStandardTab(Employee::class, $ong, $options);
        $this->addStandardTab(LeavingInformation::class, $ong, $options);
        if ($config->fields['use_module_departure_instruction']) {
            $this->addStandardTab(Resource_Leaving::class, $ong, $options);
        }
        $this->addStandardTab(Checklist::class, $ong, $options);
        $this->addStandardTab(Task::class, $ong, $options);

        if (Session::getCurrentInterface() == 'central') {
            $this->addStandardTab(ResourceImport::class, $ong, $options);
            if ($config->fields['view_notification_tab']) {
                $this->addStandardTab(ReportConfig::class, $ong, $options);
            }
            $this->addStandardTab(Document_Item::class, $ong, $options);

            if (!isset($options['withtemplate']) || empty($options['withtemplate'])) {
                $this->addStandardTab(Item_Ticket::class, $ong, $options);
                $this->addStandardTab(Item_Problem::class, $ong, $options);
            }

            $this->addStandardTab(Notepad::class, $ong, $options);
            $this->addStandardTab(Log::class, $ong, $options);
        }
        return $ong;
    }

    /**
     * @param $input
     *
     * @return array
     */
    public function getHiddenFields($input)
    {
        $need = [];
        $rulecollection = new RuleContracttypeHiddenCollection($input['entities_id']);

        $fields = [];
        $fields = $rulecollection->processAllRules($input, $fields, []);

        $field = [];
        foreach ($fields as $key => $val) {
            $hidden = explode("hiddenfields_", $key);
            if (isset($hidden[1])) {
                $field[] = $hidden[1];
            }
        }

        return $field;
    }

    public function getReadonlyFields($input)
    {

        $rulecollection = new RuleContracttypeReadonlyCollection($input['entities_id']);

        $fields = [];
        $fields = $rulecollection->processAllRules($input, $fields, []);

        $field = [];
        foreach ($fields as $key => $val) {
            $hidden = explode("readonlyfields_", $key);
            if (isset($hidden[1])) {
                $field[] = $hidden[1];
            }
        }

        return $field;
    }

    public function afterInsert(Item_Ticket $input)
    {
        if ($input->fields['itemtype'] === Resource::class) {
            $ticket = new Ticket();
            $ticket->getFromDB($input->fields['tickets_id']);
            $config = new Config();
            $config ->getFromDB(1);
            $date = new DateTime();
            $datecreation = new DateTime($ticket->fields['date_creation']);
            $seconds = $date->getTimestamp() - $datecreation->getTimestamp();
            if ($config->fields["default_assignment_group"] && $seconds < 30) {
                $groupticket = new Group_Ticket();
                $groupticket->fields['tickets_id'] = $input->fields['tickets_id'];
                $groupticket->fields['groups_id'] = $config->fields["default_assignment_group"];
                $groupticket->fields['type'] = CommonITILActor::ASSIGN;
                unset($groupticket->fields["id"]);
                $groupticket->add($groupticket->fields);
            }
        }
    }

    /**
     * @param $input
     *
     * @return array
     */
    public function checkRequiredFields($input)
    {
        $need = [];
        if (isset($input['entities_id'])) {
            $rulecollection = new RuleContracttypeCollection($input['entities_id']);

            $fields = [];
            $fields = $rulecollection->processAllRules($input, $fields, []);

            $field = [];

            foreach ($fields as $key => $val) {
                $required = explode("requiredfields_", $key);
                if (isset($required[1])) {
                    $field[] = $required[1];
                }
            }

            if (count($field) > 0) {
                foreach ($field as $key => $val) {
                    if (!isset($input[$val])
                        || empty($input[$val])
                        || is_null($input[$val])
                        || $input[$val] == "NULL"
                    ) {
                        if (isset($input['more_information'])) {
                            if (!Rank::canFillPublicSectorFields()
                                && !in_array(
                                    $val,
                                    [
                                        'date_agreement_candidate',
                                        'plugin_resources_degreegroups_id',
                                        'plugin_resources_recruitingsources_id',
                                        'yearsexperience',
                                        'reconversion',
                                        'interview_date',
                                        'plugin_resources_workprofiles_id',
                                        'plugin_resources_clients_id',
                                        'resignation_date',
                                        'wished_leaving_date',
                                        'effective_leaving_date',
                                        'plugin_resources_destinations_id',
                                        'plugin_resources_leavingreasons_id',
                                        'company_name',
                                        'pay_gap',
                                        'mission_lost',
                                    ],
                                )
                            ) {
                            } else {
                                $need[] = $val;
                            }
                        } else {
                            if ((!Rank::canFillPublicSectorFields()
                                    && in_array(
                                        $val,
                                        [
                                            'plugin_resources_ranks_id',
                                            'plugin_resources_resourcesituations_id',
                                            'date_agreement_candidate',
                                            'plugin_resources_degreegroups_id',
                                            'plugin_resources_recruitingsources_id',
                                            'yearsexperience',
                                            'reconversion',
                                            'interview_date',
                                            'plugin_resources_workprofiles_id',
                                            'plugin_resources_clients_id',
                                            'resignation_date',
                                            'wished_leaving_date',
                                            'effective_leaving_date',
                                            'plugin_resources_destinations_id',
                                            'plugin_resources_leavingreasons_id',
                                            'company_name',
                                            'pay_gap',
                                            'mission_lost',
                                        ],
                                    )) || in_array(
                                        $val,
                                        [
                                            'date_agreement_candidate',
                                            'plugin_resources_degreegroups_id',
                                            'plugin_resources_recruitingsources_id',
                                            'yearsexperience',
                                            'reconversion',
                                            'interview_date',
                                            'plugin_resources_workprofiles_id',
                                            'plugin_resources_clients_id',
                                            'resignation_date',
                                            'wished_leaving_date',
                                            'effective_leaving_date',
                                            'plugin_resources_destinations_id',
                                            'plugin_resources_leavingreasons_id',
                                            'company_name',
                                            'pay_gap',
                                            'mission_lost',
                                        ],
                                    )
                            ) {
                            } else {
                                $need[] = $val;
                            }
                        }
                    }
                }
            }
        }
        return $need;
    }

    /**
     * Prepare input datas for adding the item
     *
     * @param $input datas used to add the item
     *
     * @return the modified $input array
     **/
    public function prepareInputForAdd($input)
    {
        // The primary key is never the caller's choice: a posted id was inserted as is, which
        // let a creator take the id of a purged resource (and adopt the rows it left behind)
        // or push the AUTO_INCREMENT to its limit. The central form creating a resource from a
        // template (withtemplate=2) posts the id of the template there: it may only name the
        // source of the clone, checked below.
        $posted_id = $input['id'] ?? null;
        unset($input['id']);

        // Same rule as an update: the validation flag belongs to the validation workflow.
        $input = $this->filterValidationFlag($input);

        // post_addItem() clones the child rows of the template the resource is created from.
        // Its id is only ever set below, after the template has been checked: a value posted
        // under the former key or under the internal one is dropped.
        unset($input['resources_oldID'], $input['_resources_template_id']);

        if (!isset($input["is_template"])) {
            if (!isset($input['force'])) {
                $required = $this->checkRequiredFields($input);
                $input['plugin_resources_profiletypes_id'] = $_SESSION["glpiactiveprofile"]['id'];
                $input['plugin_resources_grouptypes_id'] = $_SESSION["glpigroups"];

                if (count($required) > 0) {
                    Session::addMessageAfterRedirect(
                        __('Required fields are not filled. Please try again.', 'resources'),
                        false,
                        ERROR,
                    );
                    return [];
                }
            } else {
                unset($input['force']);
            }
        }

        if (isset($input['date_end'])
            && empty($input['date_end'])
        ) {
            $input['date_end'] = 'NULL';
        }

        if (!isset($input['sensitize_security'])) {
            $input['sensitize_security'] = 0;
        }
        if (!isset($input['read_chart'])) {
            $input['read_chart'] = 0;
        }

        if (!isset($input['plugin_resources_resourcestates_id'])
            || empty($input['plugin_resources_resourcestates_id'])
        ) {
            $input['plugin_resources_resourcestates_id'] = '0';
        }
        //Add picture of the resource
        $input['picture'] = "NULL";
        $uploadedfile = self::getUploadedPicturePath($input);
        if ($uploadedfile !== '') {
            if (exif_imagetype($uploadedfile) === IMAGETYPE_JPEG) {
                $max_size = Toolbox::return_bytes_from_ini_vars(ini_get("upload_max_filesize"));
                if (filesize($uploadedfile) <= $max_size) {
                    if (is_writable(GLPI_PLUGIN_DOC_DIR . "/resources/pictures/")) {
                        $input['picture'] = $this->addPhoto($this, $uploadedfile);
                    }
                } else {
                    Session::addMessageAfterRedirect(__('Failed to send the file (probably too large)'), false, ERROR);
                }
            } else {
                Session::addMessageAfterRedirect(__('Invalid filename'), false, ERROR);
            }
        }

        // Source of the clone, see getTemplateSourceId()
        $templates_id = self::getTemplateSourceId(['id' => $posted_id] + $input);
        if ($templates_id > 0) {
            $input['_resources_template_id'] = $templates_id;
        }

        return $input;
    }

    /**
     * Template a resource created from an input is cloned from (post_addItem()).
     *
     * A plain id naming an actual template the session may read (same rule as the wizard).
     * Nothing else is cloned, neither a resource nor a template of an entity out of reach, nor
     * a list of ids. id_template comes first (the wizard); otherwise the id posted by the
     * central form, when it names such a template (any other id is ignored).
     *
     * The forms writing the employer next to the resource resolve the template the same way
     * (Employee::getEmployerWrite()): the row cloned from it is the reference of the employer.
     *
     * @param array<string, mixed> $input input of add(), as posted
     *
     * @return int id of the template, 0 for none
     */
    public static function getTemplateSourceId(array $input): int
    {
        foreach ([$input['id_template'] ?? null, $input['id'] ?? null] as $candidate) {
            if ($candidate === null) {
                continue;
            }
            try {
                $templates_id = self::getPostedId($candidate);
            } catch (BadRequestHttpException) {
                $templates_id = 0;
            }
            $template = self::getAllowedTemplate($templates_id);
            if ($template !== null) {
                return $template->getID();
            }
        }

        return 0;
    }

    /**
     * Drop the manager validation flag from an input, unless the validation workflow set
     * allow_validation_flag.
     *
     * The workflow is recognized through a server-side property, never through the input,
     * which comes straight from $_POST in the resource and wizard forms.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function filterValidationFlag(array $input): array
    {
        if (!$this->allow_validation_flag) {
            unset($input['valid_resource_information']);
        }
        unset($input['_from_validation_workflow']);

        return $input;
    }

    /**
     * Actions done after the ADD of the item in the database
     *
     * @return nothing
     **/
    public function post_addItem()
    {
        global $CFG_GLPI;

        //      if ($this->fields['id'] == 0) {
        //         $this->getFromDBByCrit(
        //            [ 'name'      => $this->fields['name'],
        //               'firstname' => $this->fields['firstname']]);
        //      }
        //       Manage add from template

        // Set by prepareInputForAdd() only, once the template has been checked
        $templates_id = (int) ($this->input['_resources_template_id'] ?? 0);
        if ($templates_id > 0) {
            // ADD choices
            Choice::cloneItem($templates_id, $this->fields['id']);

            // ADD habilitations
            ResourceHabilitation::cloneItem($templates_id, $this->fields['id']);

            // ADD items
            Resource_Item::cloneItem($templates_id, $this->fields['id']);

            // ADD reports
            ReportConfig::cloneItem($templates_id, $this->fields['id']);

            //manage template from helpdesk (no employee to add : resource.form.php)
            if (!isset($this->input["add_from_helpdesk"])) {
                Employee::cloneItem($templates_id, $this->fields['id']);
            }
            // ADD Documents
            $document_items = Document_Item::getItemsAssociatedTo($this->getType(), $this->fields['id']);
            $override_input['items_id'] = $this->getID();
            foreach ($document_items as $document_item) {
                $document_item->clone($override_input);
            }

            // ADD tasks
            Task::cloneItem($templates_id, $this->fields['id']);
        }

        //ADD Checklists from rules
        $Checklistconfig = new Checklistconfig();
        $Checklistconfig->addChecklistsFromRules($this, Checklist::RESOURCES_CHECKLIST_IN);
        $Checklistconfig->addChecklistsFromRules($this, Checklist::RESOURCES_CHECKLIST_OUT);
        $Checklistconfig->addChecklistsFromRules($this, Checklist::RESOURCES_CHECKLIST_TRANSFER);

        //Launch notification

        if (isset($this->input['withtemplate'])
            && $this->input["withtemplate"] != 1
            && isset($this->input['send_notification'])
            && $this->input['send_notification'] == 1
        ) {
            if ($CFG_GLPI["notifications_mailing"]) {
                NotificationEvent::raiseEvent("new", $this);
            }
        }
    }

    public function post_getFromDB()
    {
        $this->fields['states_id'] = 1;
    }

    /**
     * @param        $str
     * @param string $charset
     *
     * @return mixed|string
     */
    public function replace_accents($str, $charset = 'utf-8')
    {
        $str = htmlentities($str, ENT_NOQUOTES, $charset);

        $str = preg_replace('#\&([A-za-z])(?:acute|cedil|circ|grave|ring|tilde|uml)\;#', '\1', $str);
        $str = preg_replace('#\&([A-za-z]{2})(?:lig)\;#', '\1', $str); // pour les ligatures e.g. '&oelig;'
        $str = preg_replace('#\&[^;]+\;#', '', $str); // supprime les autres caractères

        return $str;
    }

    /**
     * Delete the picture file stored for this resource.
     *
     * The file name is always taken from the stored fields, never from the request, so a
     * caller can only remove the picture of the resource it has been authorized on. The file
     * is kept when another resource still references it (pictures created before names were
     * made unique are shared by homonyms).
     *
     * @return bool True when the resource no longer has a picture file to reference
     */
    public function deletePictureFile(): bool
    {
        $picture = (string) ($this->fields['picture'] ?? '');
        if ($picture === '' || strtoupper($picture) === 'NULL') {
            return true;
        }

        $picture_dir = realpath(GLPI_PLUGIN_DOC_DIR . "/resources/pictures");
        if ($picture_dir === false) {
            return false;
        }
        $real = realpath($picture_dir . DIRECTORY_SEPARATOR . basename($picture));
        if ($real === false) {
            // Already gone: nothing left to delete.
            return true;
        }
        if (!str_starts_with($real, $picture_dir . DIRECTORY_SEPARATOR)) {
            return false;
        }

        $shared = countElementsInTable(self::getTable(), [
            'picture' => $picture,
            'NOT'     => ['id' => (int) $this->fields['id']],
        ]);
        if ($shared > 0) {
            return true;
        }

        return unlink($real);
    }

    /**
     * Resolve the picture sent by the asynchronous uploader of Html::file().
     *
     * jQuery File Upload replaces the file input as soon as it is filled, so $_FILES is
     * always empty when the form is finally submitted: the file has already been moved to
     * GLPI_TMP_DIR and only its name is posted in _picture[0]. Containment is enforced with
     * realpath() so a crafted name cannot escape the temporary directory.
     *
     * @param array $input
     *
     * @return string Absolute path of the temporary file, or an empty string when there is none
     */
    public static function getUploadedPicturePath(array $input): string
    {
        // The uploader posts _picture as an array, but a hand crafted request may send a scalar:
        // do not index it blindly, "abc"[0] would silently yield "a" instead of falling back.
        $picture = $input['_picture'] ?? '';
        if (is_array($picture)) {
            $picture = reset($picture);
        }
        $filename = is_string($picture) ? $picture : '';
        if ($filename === '') {
            return '';
        }

        $tmp_dir  = realpath(GLPI_TMP_DIR);
        $fullpath = realpath(GLPI_TMP_DIR . "/" . $filename);

        if ($tmp_dir === false
            || $fullpath === false
            || !str_starts_with($fullpath, $tmp_dir . DIRECTORY_SEPARATOR)) {
            return '';
        }

        return $fullpath;
    }

    /**
     * @param $class
     * @param $uploadedfile Absolute path of the uploaded temporary file
     *
     * @return mixed|string
     */
    public function addPhoto($class, $uploadedfile = null)
    {
        if ($uploadedfile === null) {
            $uploadedfile = self::getUploadedPicturePath(is_array($this->input) ? $this->input : []);
        }

        // Fail-closed defense in depth: never trust the caller's pre-checks. Only process a
        // file that GD confirms is a genuine JPEG, so a spoofed Content-Type or a forged
        // temp path cannot reach imagecreatefromjpeg(). Containment inside GLPI_TMP_DIR is
        // guaranteed by getUploadedPicturePath().
        if ($uploadedfile === '' || !is_file($uploadedfile)
            || exif_imagetype($uploadedfile) !== IMAGETYPE_JPEG) {
            return '';
        }

        $src = imagecreatefromjpeg($uploadedfile);

        [$width, $height] = getimagesize($uploadedfile);

        $newwidth = 75;
        $newheight = ($height / $width) * $newwidth;
        $tmp = imagecreatetruecolor($newwidth, $newheight);

        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        $ext = 'jpg';
        // The photo filename is derived from user-controlled resource fields (name/firstname).
        // replace_accents() only transliterates accents — it leaves path separators (/, \, ..)
        // untouched, so an unsanitized name lets the JPEG be written outside the pictures
        // directory (path traversal on imagejpeg()/rename()). Strip every character outside
        // [a-z0-9_-] from each component before building the path, mirroring the read-side
        // hardening already applied in picture.send.php.
        $resources_name = str_replace(" ", "", strtolower($class->fields["name"]));
        $resources_firstname = str_replace(" ", "", strtolower($class->fields["firstname"]));

        $resources_name = preg_replace('/[^a-z0-9_-]/', '', $this->replace_accents($resources_name));
        $resources_firstname = preg_replace('/[^a-z0-9_-]/', '', $this->replace_accents($resources_firstname));

        // Random suffix: homonyms (possibly in different entities) must not overwrite each
        // other's picture, and the name must not be guessable from the identity alone.
        $name = $resources_name . "_" . $resources_firstname . "_" . bin2hex(random_bytes(8)) . "." . $ext;

        $tmpfile = GLPI_DOC_DIR . "/_uploads/" . $name;
        $filename = GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $name;

        imagejpeg($tmp, $tmpfile, 100);

        rename($tmpfile, $filename);

        // The uploader left the source file in GLPI_TMP_DIR: the resized copy is stored, drop it.
        @unlink($uploadedfile);

        imagedestroy($src);
        imagedestroy($tmp);

        return $name;
    }

    /**
     * Prepare input datas for updating the item
     *
     * @param array<string, mixed> $input datas used to update the item
     *
     * @return array<string, mixed>|false the modified $input array, false to refuse the update
     **/
    public function prepareInputForUpdate($input)
    {
        // The validation flag belongs to the validation workflow (ajax/validinformation.php,
        // manager only, once only): an ordinary update must not set or reset it.
        $input = $this->filterValidationFlag($input);

        // Structural columns: only the server-side code that changes them on purpose lists
        // them in allow_structural_fields (the entity through transferResource(), which checks
        // the destination, the writer through the resource form that offers it). The wizard,
        // for one, posts the current user and date as writer and declaration date of the
        // resource it edits. The list is consumed here, so that it never outlives the write it
        // was set for.
        $allowed = $this->allow_structural_fields;
        $this->allow_structural_fields = [];
        foreach (self::STRUCTURAL_FIELDS as $field) {
            if (!in_array($field, $allowed, true)) {
                unset($input[$field]);
            }
        }

        // Validated information is frozen: the forms test it before saving (checkNotFrozen()),
        // but the generic entry points of the core write through update() alone, the "Update"
        // and "Amend comment" massive actions (run on a forged request without the core
        // checking they were offered) and the legacy API. Every writer comes through here: a
        // write of a column, or of the picture, is refused unless it comes from a workflow
        // meant to change a validated resource (updateFromWorkflow()). The flag is consumed
        // here, like the list above. update() loaded the stored row before calling this method.
        $allow_frozen = $this->allow_frozen_update;
        $this->allow_frozen_update = false;
        if (!$allow_frozen && $this->isFrozenAfterValidation()) {
            $writes = self::getUploadedPicturePath($input) !== '';
            foreach (array_keys($input) as $key) {
                if ($key !== 'id' && array_key_exists($key, $this->fields)) {
                    $writes = true;
                    break;
                }
            }
            if ($writes) {
                Session::addMessageAfterRedirect(
                    __("You don't have permission to perform this action."),
                    false,
                    ERROR,
                );
                return false;
            }
        }

        if (isset($input['date_begin'])
            && empty($input['date_begin'])
        ) {
            $input['date_begin'] = 'NULL';
        }
        if (isset($input['date_end'])
            && empty($input['date_end'])
        ) {
            $input['date_end'] = 'NULL';
        }

        $this->getFromDB($input["id"]);

        $uploadedfile = self::getUploadedPicturePath($input);
        if (!isset($input['_UpdateFromUser_']) && $uploadedfile !== '') {
            if (exif_imagetype($uploadedfile) === IMAGETYPE_JPEG) {
                $max_size = Toolbox::return_bytes_from_ini_vars(ini_get("upload_max_filesize"));
                if (filesize($uploadedfile) <= $max_size) {
                    // Picture names are unique, so the replaced file would otherwise be orphaned.
                    $this->deletePictureFile();
                    $input['picture'] = $this->addPhoto($this, $uploadedfile);
                } else {
                    Session::addMessageAfterRedirect(__('Failed to send the file (probably too large)'), false, ERROR);
                }
            } else {
                Session::addMessageAfterRedirect(__('Invalid filename'), false, ERROR);
            }
        } elseif (isset($input['picture']) && $input['picture'] != "NULL") {
            // The posted "picture" field only feeds the delete_picture action of the front
            // controller, which sets it to NULL. Outside that case the stored file name is
            // never client supplied, so drop the value instead of writing it back.
            unset($input['picture']);
        }

        $input["_old_name"] = $this->fields["name"];
        $input["_old_firstname"] = $this->fields["firstname"];
        $input["_old_plugin_resources_contracttypes_id"] = $this->fields["plugin_resources_contracttypes_id"];
        $input["_old_users_id"] = $this->fields["users_id"];
        $input["_old_users_id_sales"] = $this->fields["users_id_sales"];
        $input["_old_users_id_recipient"] = $this->fields["users_id_recipient"];
        $input["_old_date_declaration"] = $this->fields["date_declaration"];
        $input["_old_date_begin"] = $this->fields["date_begin"];
        $input["_old_date_end"] = $this->fields["date_end"];
        $input["_old_quota"] = $this->fields["quota"];
        $input["_old_plugin_resources_departments_id"] = $this->fields["plugin_resources_departments_id"];
        $input["_old_plugin_resources_resourcestates_id"] = $this->fields["plugin_resources_resourcestates_id"];
        $input["_old_plugin_resources_resourcesituations_id"] = $this->fields["plugin_resources_resourcesituations_id"];
        $input["_old_plugin_resources_contractnatures_id"] = $this->fields["plugin_resources_contractnatures_id"];
        $input["_old_plugin_resources_ranks_id"] = $this->fields["plugin_resources_ranks_id"];
        $input["_old_plugin_resources_resourcespecialities_id"] = $this->fields["plugin_resources_resourcespecialities_id"];
        $input["_old_locations_id"] = $this->fields["locations_id"];
        $input["_old_is_leaving"] = $this->fields["is_leaving"];
        $input["_old_date_declaration_leaving"] = $this->fields["date_declaration_leaving"];
        $input["_old_plugin_resources_leavingreasons_id"] = $this->fields["plugin_resources_leavingreasons_id"];
        $input["_old_comment"] = $this->fields["comment"];
        $input["_old_sensitize_security"] = $this->fields["sensitize_security"];
        $input["_old_read_chart"] = $this->fields["read_chart"];

        return $input;
    }

    /**
     * Actions done before the UPDATE of the item in the database
     *
     * @return nothing
     **/
    public function pre_updateInDB()
    {
        $Resource_Item = new Resource_Item();
        //if leaving field is updated  && isset($this->input["withtemplate"]) && $this->input["withtemplate"]!=1

        $this->input["checkbadge"] = 0;

        if (isset($this->input["is_leaving"])
            && $this->input["is_leaving"] == 1
            && in_array("is_leaving", $this->updates)) {
            if ((!(isset($this->input["date_end"]))
                    || $this->input["date_end"] == 'NULL')
                || (!(isset($this->fields["date_end"]))
                    || $this->fields["date_end"] == 'NULL')) {
                Session::addMessageAfterRedirect(
                    __('End date was not completed. Please try again.', 'resources'),
                    false,
                    ERROR,
                );
                Html::back();
            } else {
                $this->fields["users_id_recipient_leaving"] = Session::getLoginUserID();
                $this->fields["date_declaration_leaving"] = date('Y-m-d H:i:s');
                $this->updates[] = "users_id_recipient_leaving";
                $this->updates[] = "date_declaration_leaving";

                $resources_checklist = Checklist::checkIfChecklistExist(
                    $this->fields["id"],
                    Checklist::RESOURCES_CHECKLIST_OUT,
                );
                if (!$resources_checklist) {
                    $Checklistconfig = new Checklistconfig();
                    $Checklistconfig->addChecklistsFromRules($this, Checklist::RESOURCES_CHECKLIST_OUT);
                }
            }
        } elseif (isset($this->input["is_leaving"])
            && $this->input["is_leaving"] == 0
            && in_array("is_leaving", $this->updates)) {
            $this->fields["users_id_recipient_leaving"] = 0;
            $this->fields["date_declaration_leaving"] = 'NULL';
            $this->fields["date_end"] = 'NULL';
            $this->fields["plugin_resources_leavingreasons_id"] = 0;
            $this->updates[] = "users_id_recipient_leaving";
            $this->updates[] = "date_declaration_leaving";
            $this->updates[] = "plugin_resources_leavingreasons_id";
            $this->updates[] = "date_end";
        }

        //if location field is updated
        if (isset($this->fields["locations_id"])
            && isset($this->input["_old_locations_id"])
            && !isset($this->input["_UpdateFromUser_"])
            && $this->fields["locations_id"] != $this->input["_old_locations_id"]) {
            $Resource_Item->updateLocation($this->fields, Resource::class);
        }

        $this->input["addchecklist"] = 0;
        if (isset($this->fields["plugin_resources_contracttypes_id"])
            && isset($this->input["_old_plugin_resources_contracttypes_id"])
            && $this->fields["plugin_resources_contracttypes_id"] != $this->input["_old_plugin_resources_contracttypes_id"]
        ) {
            $config = new Config();
            $config->getFromDB(1);
            if ($config->fields["reaffect_checklist_change"] == 1) {
                $this->input["addchecklist"] = 1;
            }
        }

        if (isset($this->input['plugin_resources_departments_id']) && isset($this->oldvalues['plugin_resources_departments_id'])
            && $this->input['plugin_resources_departments_id'] != $this->oldvalues['plugin_resources_departments_id']) {
            $plugin_resources_department_service = new Department_Service();
            $service_id = $this->input['plugin_resources_services_id'] ?? $this->fields['plugin_resources_services_id'];
            if (!$plugin_resources_department_service->getFromDBByCrit(
                [
                    'plugin_resources_departments_id' => $this->input['plugin_resources_departments_id'],
                    'plugin_resources_services_id' => $service_id,
                ],
            )) {
                $this->fields["plugin_resources_services_id"] = 0;
                $this->updates[] = "plugin_resources_services_id";
                $this->fields["plugin_resources_roles_id"] = 0;
                $this->updates[] = "plugin_resources_roles_id";
            }
        }

        if (isset($this->input['plugin_resources_services_id']) && isset($this->oldvalues['plugin_resources_services_id'])
            && $this->input['plugin_resources_services_id'] != $this->oldvalues['plugin_resources_services_id']) {
            $plugin_resources_service_role = new Role_Service();
            $role_id = $this->input['plugin_resources_roles_id'] ?? $this->fields['plugin_resources_roles_id'];
            if (!$plugin_resources_service_role->getFromDBByCrit(
                [
                    'plugin_resources_services_id' => $this->input['plugin_resources_services_id'],
                    'plugin_resources_roles_id' => $role_id,
                ],
            )) {
                $this->fields["plugin_resources_roles_id"] = 0;
                $this->updates[] = "plugin_resources_roles_id";
            }
        }
    }

    /**
     * Actions done after the UPDATE of the item in the database
     *
     * @param $history store changes history ? (default 1)
     *
     * @return nothing
     **/
    public function post_updateItem($history = 1)
    {
        global $CFG_GLPI, $DB;

        $Checklist = new Checklist();
        $config = new Config();
        $config->getFromDB(1);
        if ($config->fields["mandatory_adcreation"] == 1) {
            if (isset($this->input["addchecklist"])
                && $this->input["addchecklist"] == 1) {
                $Checklist->deleteByCriteria(['plugin_resources_resources_id' => $this->fields["id"]]);

                $Checklistconfig = new Checklistconfig();
                $Checklistconfig->addChecklistsFromRules(
                    $this,
                    Checklist::RESOURCES_CHECKLIST_IN,
                );
                $Checklistconfig->addChecklistsFromRules(
                    $this,
                    Checklist::RESOURCES_CHECKLIST_OUT,
                );
                $Checklistconfig->addChecklistsFromRules(
                    $this,
                    Checklist::RESOURCES_CHECKLIST_TRANSFER,
                );
            }
        }
        $status = "update";
        if (isset($this->fields["is_leaving"])
            && !empty($this->fields["is_leaving"])) {
            $status = "LeavingResource";
            $Resource_Item = new Resource_Item();
            $badge = $Resource_Item->searchAssociatedBadge($this->fields["id"]);
            if ($badge) {
                $this->input["checkbadge"] = 1;
            }

            //when a resource is leaving, current employment get default state
            if (isset($this->input['date_end'])) {
                $Employment = new Employment();
                $default = EmploymentState::getDefault();
                // only current employment
                //                $restrict = "`plugin_resources_resources_id` = '" . $this->input["id"] . "'
                //                        AND ((`begin_date` < '" . $this->input['date_end'] . "'
                //                              OR `begin_date` IS NULL)
                //                              AND (`end_date` > '" . $this->input['date_end'] . "'
                //                                    OR `end_date` IS NULL)) ";
                //

                $criteria = [
                    'SELECT' => ['glpi_plugin_resources_employments.*'],
                    'FROM' => 'glpi_plugin_resources_employments',
                    'WHERE' => [
                        'plugin_resources_resources_id' => $this->input["id"],
                        'begin_date' => ['<', $this->input['date_end']],
                        [
                            "OR" => [
                                ['begin_date' => null],
                            ],
                        ],
                        'end_date' => ['>', $this->input['date_end']],
                        [
                            "OR" => [
                                ['end_date' => null],
                            ],
                        ],
                    ],
                ];

                $iterator = $DB->request($criteria);

                foreach ($iterator as $employment) {
                    $values = [
                        'plugin_resources_employmentstates_id' => $default,
                        'end_date' => $this->input['date_end'],
                        'id' => $employment['id'],
                    ];
                    $Employment->update($values);
                }
            }
        }

        $picture = [0 => "picture", 1 => "date_mod"];
        if (count($this->updates)
            && array_diff($this->updates, $picture)
            && isset($this->input["withtemplate"])
            && $this->input["withtemplate"] != 1
        ) {
            if ($CFG_GLPI["notifications_mailing"]
                && isset($this->input['send_notification'])
                && $this->input['send_notification'] == 1
            ) {
                NotificationEvent::raiseEvent($status, $this);
            }
        }
    }

    /**
     * Actions done before the DELETE of the item in the database /
     * Maybe used to add another check for deletion
     *
     * @return bool : true if item need to be deleted else false
     **/
    public function pre_deleteItem()
    {
        global $CFG_GLPI;

        if (!empty($this->input['_purge'])) {
            // Use the stored file name only: a posted 'picture' value is not bound to the
            // resource being purged and would let the caller remove another one's picture.
            $this->deletePictureFile();
        }
        if ($CFG_GLPI["notifications_mailing"]
            && $this->fields["is_template"] != 1
            && isset($this->input['_delete'])
            && isset($this->input['send_notification'])
            && $this->input['send_notification'] == 1
        ) {
            NotificationEvent::raiseEvent("delete", $this);
        }

        return true;
    }

    /**
     * @param     $name
     * @param int $value
     *
     * @return int|string
     */
    public static function dropdownTemplate($name, $value = 0, $skip_profiles = false)
    {
        $dbu = new DbUtils();
        $self = new self();
        $restrict = ["is_template" => 1]
            + $dbu->getEntitiesRestrictCriteria($self->getTable(), '', '', $self->maybeRecursive())
            + ["ORDER" => "template_name"]
            + ["GROUPBY" => "template_name"];

        $dbu = new DbUtils();
        $templates = $dbu->getAllDataFromTable($self->getTable(), $restrict);

        $config = new Config();
        $config->getFromDB(1);
        $option = [];
        if ($config->fields['allow_without_contract'] == 0) {
            $option[-1] = __('Without contract', 'resources');
        }
        if ($value == 0) {
            $value = $config->fields['plugin_resources_resourcetemplates_id'];
        }
        $available_contracttype = false;
        $contracttypeprofile = new Contracttypeprofile();
        if ($contracttypeprofile->getFromDBByCrit(['profiles_id' => $_SESSION['glpiactiveprofile']['id']])) {
            $available_contracttype = json_decode($contracttypeprofile->fields['plugin_resources_contracttypes_id']);
        }
        $skip = false;

        if ($skip_profiles == true || $available_contracttype === false || !is_array($available_contracttype)) {
            $skip = true;
        }
        if (!empty($templates)) {
            foreach ($templates as $template) {
                if ($skip == false) {
                    if (!in_array($template['plugin_resources_contracttypes_id'], $available_contracttype)) {
                        continue;
                    }
                }
                $id_display = "";
                if ($_SESSION["glpiis_ids_visible"] || empty($template["template_name"])) {
                    $id_display = " (" . $template["id"] . ")";
                }
                $option[$template["id"]] = $template["template_name"] . $id_display;
            }
        }
        Dropdown::showFromArray($name, $option, ['value' => $value]);
    }

    /**
     * Return the SQL command to retrieve linked object
     *
     * @return a SQL command which return a set of (itemtype, items_id)
     */
    public function getSelectLinkedItem()
    {
        return "SELECT `itemtype`, `items_id`
              FROM `glpi_plugin_resources_resources_items`
              WHERE `plugin_resources_resources_id`='" . $this->fields['id'] . "'";
    }

    /**
     * @param       $ID
     * @param array $options
     *
     * @return bool
     */
    /**
     * Whether the resource forms are locked because the information has been validated
     * (validation module on and "freeze form after validation" enabled).
     *
     * @return bool
     */
    public function isFrozenAfterValidation(): bool
    {
        $config = new Config();

        return $config->fields['use_module_validation']
            && $config->fields['freeze_form_after_validation']
            && !empty($this->fields['valid_resource_information']);
    }

    public function showForm($ID, $options = [])
    {
        $this->initForm($ID, $options);

        $config = new Config();

        $input = [
            'entities_id'                       => $this->fields["entities_id"] ?? $_SESSION['glpiactive_entity'],
            'plugin_resources_contracttypes_id' => $this->fields["plugin_resources_contracttypes_id"],
            'plugin_resources_profiletypes_id'  => $_SESSION["glpiactiveprofile"]['id'],
            'plugin_resources_grouptypes_id'    => $_SESSION["glpigroups"],
            'plugin_resources_users_id'         => Session::getLoginUserID(),
            'plugin_resources_users_id_reel'    => $this->fields['users_id'],
        ];

        // The rule collections return flat lists of field names. Flipping them into maps lets the
        // template test them with "hidden.<field> is defined", the way the wizard already does.
        $hidden    = array_flip($this->getHiddenFields($input));
        $readonly  = array_flip($this->getReadonlyFields($input));
        $mandatory = array_flip($this->checkRequiredFields($input));

        $is_central   = Session::getCurrentInterface() == 'central';
        $withtemplate = $options['withtemplate'] ?? 0;

        $contracttype     = new ContractType();
        $second_matricule = false;
        $display_employee = false;
        $condition_emp    = ['second_list' => 0];
        if ($contracttype->getFromDB($this->fields["plugin_resources_contracttypes_id"])) {
            $second_matricule = $contracttype->fields["use_second_matricule"] > 0;
            $display_employee = $contracttype->fields["use_employee_wizard"] > 0;
            if ($contracttype->fields["use_second_list_employer"] > 0) {
                $condition_emp = ['second_list' => 1];
            }
        }

        // The employer is not a resource column: it is carried by the Employee relation. On a
        // creation from a template (withtemplate=2), the row is the one of the template, which
        // the new resource gets a copy of: posted back unchanged, it writes nothing
        // (Employee::getEmployerWrite()).
        $employers_id      = 0;
        $employer_readonly = false;
        $can_read_employee = Session::haveRight('plugin_resources_employee_core_form', READ) && !$display_employee;
        if ($can_read_employee) {
            $employers_id = Employee::getEmployerOf((int) $this->getID());
            // A change the Employee right of the session does not allow would only end on an
            // access denied page, losing the rest of the form: the field is shown read-only.
            $employer_readonly = !Employee::canChangeEmployer($withtemplate == 2 ? 0 : (int) $this->getID());
        }

        $use_secondary_services = $config->useSecondaryService() && $config->useServiceDepartmentAD();
        $secondary_services     = [];
        $secondary_values       = [];
        if ($use_secondary_services) {
            $usercategory = new UserCategory();
            foreach ($usercategory->find() as $category) {
                $secondary_services[$category['id']] = $category['name'];
            }
            $decoded          = json_decode($this->fields['secondary_services'] ?? '', true);
            $secondary_values = is_array($decoded) ? $decoded : [];
        }

        // Shared by the whole department/service/role chain: the AJAX handlers target
        // "dropdown_<name><rand>", so the PHP dropdowns and the template must agree on it.
        $rand = mt_rand();

        $rank                = new Rank();
        $can_view_rank       = $rank->canView();
        $situation_dropdown  = '';
        $contractnature_html = '';
        $rank_dropdown       = '';
        $speciality_html     = '';
        if ($can_view_rank) {
            $situation = self::buildChainedDropdownPair(
                ResourceSituation::class,
                'plugin_resources_resourcesituations_id',
                $this->fields['plugin_resources_resourcesituations_id'],
                $this->fields["entities_id"],
                'dropdownContractnature.php',
                'span_contractnature',
                'glpi_plugin_resources_contractnatures',
                $this->fields["plugin_resources_contractnatures_id"],
            );
            $situation_dropdown  = $situation['dropdown'];
            $contractnature_html = $situation['span'];

            $rank_pair = self::buildChainedDropdownPair(
                Rank::class,
                'plugin_resources_ranks_id',
                $this->fields['plugin_resources_ranks_id'],
                $this->fields["entities_id"],
                'dropdownSpeciality.php',
                'span_speciality',
                'glpi_plugin_resources_resourcespecialities',
                $this->fields["plugin_resources_resourcespecialities_id"],
            );
            $rank_dropdown   = $rank_pair['dropdown'];
            $speciality_html = $rank_pair['span'];
        }

        $organisation = $this->buildOrganisationFields($readonly, $rand, (bool) $config->useServiceDepartmentAD());

        $resource_managers = null;
        if ($config->getField('resource_manager') != "") {
            $resource_managers = self::getManagerDropdownValues(
                $config->getField('resource_manager'),
                $this->fields["entities_id"],
            );
        }
        $sales_managers = null;
        if ($config->getField('sales_manager') != "") {
            $sales_managers = self::getManagerDropdownValues(
                $config->getField('sales_manager'),
                $this->fields["entities_id"],
            );
        }

        $picture_url = '';
        if (!empty($this->fields["picture"])
            && file_exists(GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $this->fields["picture"])
        ) {
            $picture_url = PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $this->fields["picture"];
        }

        // "By <user> - <date>", appended to the "Declared as leaving" field
        $leaving_by = '';
        if ($ID != -1 && $withtemplate != 1 && $this->fields["is_leaving"] == 1
            && isset($this->fields["users_id_recipient_leaving"])
        ) {
            $leaving_by = htmlescape(
                __('By') . " " . getUserName($this->fields["users_id_recipient_leaving"]),
            );
            if (!empty($this->fields["date_declaration_leaving"])) {
                $leaving_by .= " - " . htmlescape(Html::convDateTime($this->fields["date_declaration_leaving"]));
            }
        }

        $params = $options;
        if (!$is_central) {
            $params['candel'] = false;
        }
        $can_save = !$this->isFrozenAfterValidation();
        $params['hidden_fields']    = $hidden;
        $params['readonly_fields']  = $readonly;
        $params['mandatory_fields'] = $mandatory;

        TemplateRenderer::getInstance()->display('@resources/resource_form.html.twig', [
            'item'                       => $this,
            'params'                     => $params,
            'no_form_buttons'            => !$can_save,
            'is_central'                 => $is_central,
            'plugin_rand'                => $rand,
            'root_resources'             => PLUGIN_RESOURCES_WEBDIR,
            'user_class'                 => \User::class,
            'location_class'             => Location::class,
            'resourcestate_class'        => ResourceState::class,
            'contracttype_class'         => ContractType::class,
            'resourcefunction_class'     => ResourceFunction::class,
            'team_class'                 => Team::class,
            'employer_class'             => Employer::class,
            'leavingreason_class'        => LeavingReason::class,
            'genders'                    => self::getGenders(),
            'quota_value'                => Html::formatNumber($this->fields["quota"], true, 4),
            'can_view_rank'              => $can_view_rank,
            'can_read_employee'          => $can_read_employee,
            'can_set_recipient'          => self::canSetRecipient(),
            'second_matricule'           => $second_matricule,
            'use_security'               => (bool) $config->useSecurity(),
            'use_services_deparments_ad' => (bool) $config->useServiceDepartmentAD(),
            'use_secondary_services'     => $use_secondary_services,
            'secondary_services'         => $secondary_services,
            'secondary_values'           => $secondary_values,
            'condition_emp'              => $condition_emp,
            'employers_id'               => $employers_id,
            'employer_readonly'          => $employer_readonly,
            'resource_managers'          => $resource_managers,
            'sales_managers'             => $sales_managers,
            'has_leavingreasons'         => countDistinctElementsInTable(LeavingReason::getTable(), 'id') > 0,
            'picture_url'                => $picture_url,
            'empty_picture'              => PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png",
            'max_upload_size'            => Document::getMaxUploadSize(),
            'situation_dropdown'         => $situation_dropdown,
            'contractnature_html'        => $contractnature_html,
            'rank_dropdown'              => $rank_dropdown,
            'speciality_html'            => $speciality_html,
            'department_dropdown'        => $organisation['department'],
            'service_dropdown'           => $organisation['service'],
            'role_dropdown'              => $organisation['role'],
            'organisation_script'        => $organisation['script'],
            'declaration_date'           => Html::convDate($this->fields["date_declaration"]),
            'recipient_name'             => getUserName($this->fields["users_id_recipient"]),
            'date_end_display'           => Html::convDate($this->fields["date_end"]),
            'leaving_by'                 => $leaving_by,
            'remove_url'                 => PLUGIN_RESOURCES_WEBDIR . "/front/resource.remove.php?resource_id=" . (int) $ID,
            'created_on'                 => sprintf(__('Created on %s'), Html::convDateTime($_SESSION["glpi_currenttime"])),
        ]);

        return true;
    }

    /**
     * Build the users list of a "manager" dropdown, restricted to the profiles selected in the
     * plugin configuration and to the entities the given entity gives access to.
     *
     * @param string $profiles_json Raw configuration value, a JSON encoded list of profile IDs
     * @param int    $entities_id   Entity the resource belongs to
     *
     * @return array<int, string> Users indexed by their ID
     */
    public static function getManagerDropdownValues($profiles_json, $entities_id): array
    {
        $table    = Profile_User::getTable();
        $decoded  = json_decode($profiles_json, true);
        $profiles = [];
        foreach (is_array($decoded) ? $decoded : [] as $profile) {
            $profiles[$profile] = $profile;
        }

        $restrict = getEntitiesRestrictCriteria($table, 'entities_id', $entities_id, true);
        $restrict = array_merge([$table . ".profiles_id" => [join("','", $profiles)]], $restrict);

        $profile_user = new Profile_User();
        $managers     = [];
        foreach ($profile_user->find($restrict) as $line) {
            $user = new \User();
            if ($user->getFromDB($line["users_id"])) {
                $managers[$line["users_id"]] = $user->getFriendlyName();
            }
        }

        return $managers;
    }

    /**
     * Build a "chained dropdown + target span" pair, as used by the situation/contract nature and
     * the rank/speciality couples. showGenericDropdown() echoes both the dropdown and its wiring
     * script, so its output has to be captured to be handed over to fields.htmlField().
     *
     * @param string $itemtype   Dropdown itemtype
     * @param string $name       Input name of the dropdown
     * @param int    $value      Current dropdown value
     * @param int    $entity     Entity restriction
     * @param string $ajax_file  File name, inside the plugin ajax/ directory, feeding the span
     * @param string $span_id    DOM id of the span refreshed on change
     * @param string $span_table Table the span label is read from
     * @param int    $span_value Current value of the field displayed in the span
     *
     * @return array{dropdown: string, span: string}
     */
    private static function buildChainedDropdownPair(
        $itemtype,
        $name,
        $value,
        $entity,
        $ajax_file,
        $span_id,
        $span_table,
        $span_value,
    ): array {
        ob_start();
        self::showGenericDropdown($itemtype, [
            'name'   => $name,
            'value'  => $value,
            'entity' => $entity,
            'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/" . $ajax_file,
            'span'   => $span_id,
        ]);
        $dropdown = (string) ob_get_clean();

        $label = htmlescape($span_value > 0
            ? Dropdown::getDropdownName($span_table, $span_value)
            : __('None'));

        return [
            'dropdown' => $dropdown,
            'span'     => "<span id='" . $span_id . "' name='" . $span_id . "'>" . $label . "</span>",
        ];
    }

    /**
     * Build the department / service / role dropdowns and the scripts chaining them.
     *
     * When the departments and services are read from the directory, they are plain UserTitle and
     * UserCategory dropdowns and only the department drives a refresh; otherwise the plugin
     * dropdowns are used and the service drives the role list as well.
     *
     * @param array $readonly Readonly fields, as a map of field names
     * @param int   $rand     Random suffix shared by the whole chain
     * @param bool  $from_ad  Whether departments and services come from the directory
     *
     * @return array{department: string, service: string, role: string, script: string}
     */
    private function buildOrganisationFields(array $readonly, $rand, bool $from_ad): array
    {
        $department_options = [
            'name'    => "plugin_resources_departments_id",
            'value'   => $this->fields["plugin_resources_departments_id"],
            'rand'    => $rand,
            'display' => false,
        ];
        $service_options = [
            'name'    => "plugin_resources_services_id",
            'value'   => $this->fields["plugin_resources_services_id"],
            'rand'    => $rand,
            'display' => false,
        ];
        if (isset($readonly['plugin_resources_departments_id'])) {
            $department_options['readonly'] = true;
        }
        if (isset($readonly['plugin_resources_services_id'])) {
            $service_options['readonly'] = true;
        }

        $script = '';
        if ($from_ad) {
            $department = (string) UserTitle::dropdown($department_options);
            $service    = (string) UserCategory::dropdown($service_options);
        } else {
            $department_options['entity'] = $this->fields["entities_id"];
            $department                   = (string) Dropdown::show(Department::class, $department_options);

            $service_options['entity'] = $_SESSION['glpiactiveentities'];
            $service                   = (string) Service::dropdownFromDepart(
                $this->fields["plugin_resources_departments_id"],
                $service_options,
            );

            ob_start();
            Ajax::updateItemOnSelectEvent(
                "dropdown_plugin_resources_services_id" . $rand,
                "show_roles",
                PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownRole.php",
                ['plugin_resources_services_id' => '__VALUE__', 'rand' => $rand],
            );
            $script .= (string) ob_get_clean();
        }

        $role_options = [
            'name'    => "plugin_resources_roles_id",
            'value'   => $this->fields["plugin_resources_roles_id"],
            'entity'  => $_SESSION['glpiactiveentities'],
            'rand'    => $rand,
            'display' => false,
        ];
        if (isset($readonly['plugin_resources_roles_id'])) {
            $role_options['readonly'] = true;
        }
        $role = (string) Role::dropdownFromService(
            $this->fields['plugin_resources_services_id'],
            $role_options,
        );

        ob_start();
        Ajax::updateItemOnSelectEvent(
            "dropdown_plugin_resources_departments_id" . $rand,
            "show_services",
            PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownService.php",
            ['plugin_resources_departments_id' => '__VALUE__', 'rand' => $rand],
        );
        $script .= (string) ob_get_clean();

        return [
            'department' => $department,
            'service'    => $service,
            'role'       => $role,
            'script'     => $script,
        ];
    }

    /**
     * Send the report notifications of this resource.
     *
     * Works on the resource the caller loaded and authorised: reading an id from the request
     * here would let it differ from the one checked.
     *
     * @return bool
     */
    public function sendReport(): bool
    {
        global $CFG_GLPI;

        if ($this->isNewItem()) {
            return false;
        }

        if ($CFG_GLPI["notifications_mailing"]) {
            // The report configuration is the one of the authorized resource, never the posted
            // reports_id: it is not bound to the resource, and the notification would otherwise
            // carry the HR information and comments of a resource of another entity.
            $report = new ReportConfig();
            if (!$report->getFromDBByResource($this->getID())) {
                return false;
            }
            $reports_id = $report->getID();

            if ($report->fields['send_report_notif']) {
                $notification = new Notification();
                $notification->add([
                    'users_id' => Session::getLoginUserID(),
                    'plugin_resources_resources_id' => $this->getID(),
                    'type' => 'report',
                ]);
                NotificationEvent::raiseEvent('report', $this, ['reports_id' => $reports_id]);
            }

            if ($report->fields['send_other_notif']) {
                $notification = new Notification();
                $notification->add([
                    'users_id' => Session::getLoginUserID(),
                    'plugin_resources_resources_id' => $this->getID(),
                    'type' => 'other',
                ]);
                NotificationEvent::raiseEvent('other', $this, ['reports_id' => $reports_id]);
            }
        }

        return true;
    }

    /**
     * Raise the creation notification of this resource again.
     *
     * Works on the resource the caller loaded and authorised, see sendReport().
     *
     * @return bool
     */
    public function reSendResourceCreation(): bool
    {
        global $CFG_GLPI;

        if ($this->isNewItem()) {
            return false;
        }

        if ($CFG_GLPI["notifications_mailing"]) {
            $status = "new";
            NotificationEvent::raiseEvent($status, $this);
        }

        return true;
    }

    /**
     * @param $options
     */
    public static function showReportForm($options)
    {
        $reportconfig = new ReportConfig();
        $reportconfig->getFromDBByResource($options['id']);

        if ($reportconfig->fields['send_report_notif'] || $reportconfig->fields['send_other_notif']) {
            TemplateRenderer::getInstance()->display('@resources/resource_report_form.html.twig', [
                'form_action' => $options['target'],
                'title'       => ReportConfig::getTypeName(2),
                'label_send'  => __('Send a notification'),
                'id'          => (int) $options['id'],
                'reports_id'  => (int) $reportconfig->fields["id"],
            ]);
        }

        $notification = new Notification();
        $notification->listItems($options['id']);
    }

    public static function showAddFormForItem(CommonDBTM $item, $withtemplate = 0, $options = [])
    {
        global $DB;

        //default options
        $params['rand'] = mt_rand();
        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $params[$key] = $val;
            }
        }

        if (!$item->can($item->fields['id'], READ)) {
            return false;
        }

        if (empty($withtemplate)) {
            $withtemplate = 0;
        }

        // find documents already associated to the item
        $doc_item   = new Document_Item();
        $used_found = $doc_item->find([
            'items_id'  => $item->getID(),
            'itemtype'  => $item::class,
        ]);
        $used       = array_keys($used_found);
        $used       = array_combine($used, $used);

        if (
            $item->canAddItem('Document')
            && $withtemplate < 2
        ) {
            // Restrict entity for knowbase
            $entities = "";
            $entity   = $_SESSION["glpiactive_entity"];

            if ($item->isEntityAssign()) {
                // Case of personal items : entity = -1 : create on active entity (Reminder case))
                if ($item->getEntityID() >= 0) {
                    $entity = $item->getEntityID();
                }

                if ($item->isRecursive()) {
                    $entities = getSonsOf('glpi_entities', $entity);
                } else {
                    $entities = $entity;
                }
            }

            $count = $DB->request([
                'COUNT'     => 'cpt',
                'FROM'      => 'glpi_documents',
                'WHERE'     => [
                    'is_deleted' => 0,
                ] + getEntitiesRestrictCriteria('glpi_documents', '', $entities, true),
            ])->current();
            $nb = $count['cpt'];

            if ($item::class === Document::class) {
                $used[$item->getID()] = $item->getID();
            }

            $target = PLUGIN_RESOURCES_WEBDIR . "/front/wizard.form.php";

            TemplateRenderer::getInstance()->display('@resources/document_item.html.twig', [
                'canview' => Document::canView(),
                'item' => $item,
                'used' => $used,
                'entity' => $entity,
                'entities' => $entities,
                'nb' => $nb,
                'target' => $target,
                'rand' => mt_rand(),
            ]);
        }

        return true;
    }

    /**
     * @param     $ID
     * @param int $link
     *
     * @return array|string
     */
    public static function getResourceName($ID, $link = 0)
    {
        global $DB, $CFG_GLPI;

        $user = "";
        if ($link == 2) {
            $user = [
                "name" => "",
                "link" => "",
                "comment" => "",
            ];
        }

        if ($ID) {

            $criteria = [
                'SELECT' => ['glpi_plugin_resources_resources.*',
                    'glpi_users.registration_number',
                    'glpi_users.name AS username'],
                'FROM' => 'glpi_plugin_resources_resources',
                'LEFT JOIN'       => [
                    'glpi_plugin_resources_resources_items' => [
                        'ON' => [
                            'glpi_plugin_resources_resources_items' => 'plugin_resources_resources_id',
                            'glpi_plugin_resources_resources'          => 'id',
                        ],
                    ],
                    'glpi_users' => [
                        'ON' => [
                            'glpi_users' => 'id',
                            'glpi_plugin_resources_resources_items'                  => 'items_id', [
                                'AND' => [
                                    'glpi_plugin_resources_resources_items.itemtype' => 'User',
                                ],
                            ],
                        ],
                    ],
                ],
                'WHERE' => [
                    'glpi_plugin_resources_resources.id' => $ID,
                ],
                'GROUPBY'   => 'glpi_plugin_resources_resources.id',
            ];

            $iterator = $DB->request($criteria);

            if ($link == 2) {
                $user = [
                    "name" => "",
                    "comment" => "",
                    "link" => "",
                ];
            }

            $dbu = new DbUtils();

            if (count($iterator) == 1) {
                foreach ($iterator as $data) {

                    $username = $dbu->formatUserName(
                        $data["id"],
                        $data["username"],
                        $data["name"],
                        $data["firstname"],
                    );

                    if ($link == 2) {
                        $user["name"] = $username;
                        $user["link"] = PLUGIN_RESOURCES_WEBDIR . "/front/resource.form.php?id=" . $ID;

                        // Raw values: resource_comment.html.twig escapes them on output. The
                        // picture file name goes through urlencode() because it is a query
                        // string parameter.
                        $comment_data = [
                            'picture_url' => '',
                            'nobody_url'  => PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png",
                            'rows'        => [
                                ['label' => __('Name'), 'value' => $username],
                            ],
                        ];
                        if (!empty($data["picture"])
                            && file_exists(GLPI_PLUGIN_DOC_DIR . "/resources/pictures/" . $data["picture"])) {
                            $comment_data['picture_url'] = PLUGIN_RESOURCES_WEBDIR
                                . "/front/picture.send.php?file=" . urlencode($data["picture"]);
                        }

                        if ($data["plugin_resources_ranks_id"] > 0) {
                            $comment_data['rows'][] = [
                                'label' => Rank::getTypeName(1),
                                'value' => Dropdown::getDropdownName(
                                    "glpi_plugin_resources_ranks",
                                    $data["plugin_resources_ranks_id"],
                                ),
                            ];
                        }

                        if ($data["locations_id"] > 0) {
                            $comment_data['rows'][] = [
                                'label' => __('Location'),
                                'value' => Dropdown::getDropdownName("glpi_locations", $data["locations_id"]),
                            ];
                        }

                        if ($data["registration_number"] > 0) {
                            $comment_data['rows'][] = [
                                'label' => _x('user', 'Administrative number'),
                                'value' => (string) $data["registration_number"],
                            ];
                        }

                        $user["comment_data"] = $comment_data;
                        // HTML fragment for Html::showToolTip(), which inserts it verbatim.
                        $user["comment"] = TemplateRenderer::getInstance()->render(
                            '@resources/resource_comment.html.twig',
                            $comment_data,
                        );
                    } else {
                        $user = $username;
                    }
                }
            }
        }
        return $user;
    }

    /**
     * Permet l'affichage dynamique des ressources avec info bulle
     *
     * @static
     *
     * @param array $options
     */

    public static function dropdown($options = [])
    {
        global $CFG_GLPI;

        $params['value'] = 0;
        $params['valuename'] = Dropdown::EMPTY_VALUE;
        $params['customcomments'] = true;
        $params['comments'] = false;
        $params['entity'] = $_SESSION['glpiactive_entity'];
        $params['name'] = 'plugin_resources_resources_id';
        $params['rand'] = mt_rand();
        $params['display'] = false;
        $params['showHabilitations'] = false;
        if (!empty($options)) {
            foreach ($options as $key => $val) {
                $params[$key] = $val;
            }
        }

        $params['value2'] = $params['value'];
        // The preselected value may come from the request (employment, holiday, resting
        // forms...): the identity card of the resource, name and tooltip, is only built for a
        // resource the session may read. Any other value is shown as its id.
        $value_id = (int) $params['value'];
        $value_readable = $value_id > 0 && (new self())->can($value_id, READ);
        $user = $value_readable
            ? self::getResourceName($value_id, 2)
            : ['name' => '', 'link' => '', 'comment' => ''];
        if (!empty($params['value'])) {
            $params['valuename'] = $value_readable ? $user['name'] : '(' . $value_id . ')';
        }

        $field_id = Html::cleanId("dropdown_" . $params['name'] . $params['rand']);

        $item = new self();
        $output = "<span class='no-wrap'>";
        $output .= Html::jsAjaxDropdown(
            $params['name'],
            $field_id,
            PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownResources.php",
            $params,
        );
        if (class_exists(Position::class)) {
            $output .= Position::showGeolocLink(Resource::class, $value_readable ? $value_id : 0);
        }
        // Display comment
        if ($params['customcomments']) {
            $table = $item->getTable();

            $comment_id = Html::cleanId("comment_" . $params['name'] . $params['rand']);
            $link_id = Html::cleanId("comment_link_" . $params['name'] . $params['rand']);

            if (empty($user["link"])) {
                $user["link"] = PLUGIN_RESOURCES_WEBDIR . "/front/resource.php";
            }

            $output .= "&nbsp;" . Html::showToolTip(
                $user["comment"],
                [
                    'contentid' => $comment_id,
                    'link' => $user["link"],
                    'linkid' => $link_id,
                    'linktarget' => '_blank',
                    'display' => false,
                ],
            );

            $paramscomment = [
                'value' => '__VALUE__',
                'table' => $table,
            ];
            if ($item->canView()) {
                $paramscomment['withlink'] = $link_id;
            }

            $output .= Ajax::updateItemOnSelectEvent(
                $field_id,
                $comment_id,
                PLUGIN_RESOURCES_WEBDIR . "/ajax/comments.php",
                $paramscomment,
                false,
            );
        }
        $config = new Config();
        if ($params['showHabilitations'] && $config->getField('display_habilitations_txt')) {
            $output .= "<p id='habilitationsTxt'></p>";
            $output .= Ajax::updateItemOnSelectEvent(
                $field_id,
                'habilitationsTxt',
                PLUGIN_RESOURCES_WEBDIR . "/ajax/showHabilitations.php",
                ['value' => '__VALUE__', 'metademands_id' => $_GET['metademands_id'] ?? 0],
                false,
            );
        }
        $output .= Ajax::commonDropdownUpdateItem($params, false);
        $output .= "</span>";
        // Same display/return contract as the core Dropdown helpers.
        if ($params['display']) {
            echo $output;
            return $params['rand'];
        }
        return $output;
    }

    /**
     * Massive action sub form of "Generate resources": collects the few fields
     * fastResourceAdd() needs to build a resource out of each selected user.
     *
     * @return void
     */
    public static function fastResourceAddForm()
    {
        // The dropdowns echo their markup and return their rand by default: ask for the
        // string instead, so the template stays in charge of the layout.
        $contracttype_dropdown = (string) Dropdown::show(ContractType::class, [
            'name'    => 'plugin_resources_contracttypes_id',
            'display' => false,
        ]);

        $recipient_dropdown = (string) \User::dropdown([
            'name'        => 'users_id_recipient',
            'entity'      => $_SESSION['glpiactive_entity'],
            'entity_sons' => true,
            'right'       => 'all',
            'display'     => false,
        ]);

        $department_dropdown = (string) Dropdown::show(Department::class, [
            'name'    => 'plugin_resources_departments_id',
            'display' => false,
        ]);

        TemplateRenderer::getInstance()->display('@resources/resource_fast_add_form.html.twig', [
            'contracttype_label'    => __('Contract type'),
            'contracttype_dropdown' => $contracttype_dropdown,
            'recipient_label'       => __('Resource manager', 'resources'),
            'recipient_dropdown'    => $recipient_dropdown,
            'department_label'      => Department::getTypeName(1),
            'department_dropdown'   => $department_dropdown,
        ]);
    }

    /**
     * @param $userId
     * @param $options
     *
     * @return array
     */
    public static function fastResourceAdd($userId, $options)
    {
        global $DB;

        // Only the fields of the sub form (fastResourceAddForm()) are read from the posted
        // massive action input. The entity, in particular, is the active one: add() writes the
        // entity it is given without checking it, and the posted one used to land as is.
        $params = [
            'plugin_resources_contracttypes_id' => 0,
            'plugin_resources_departments_id'   => 0,
            'users_id_recipient'                => 0,
        ];
        foreach (array_keys($params) as $key) {
            if (isset($options[$key])) {
                $params[$key] = (int) $options[$key];
            }
        }

        $message = null;
        $idResource = 0;
        $error['right'] = 0;
        $error['error'] = 0;

        $user = new \User();
        if ($user->getFromDB($userId)) {
            // This message reaches Session::addMessageAfterRedirect() through
            // MassiveAction::addMessage(), a sink the core documents as unescaped HTML
            // (@psalm-taint-sink html $msg, src/Session.php). The user name is free text, so each
            // part is escaped on its own: escaping the assembled string would print the <br/>
            // separator as literal text.
            $user_label = htmlescape($user->fields['realname'] ?? '')
                . " " . htmlescape($user->fields['firstname'] ?? '') . "<br/>";

            $resource = new Resource();
            $resource->getFromDBByCrit([
                'name' => $user->fields['realname'],
                'firstname' => $user->fields['firstname'],
                'is_deleted' => 0,
            ]);

            if (!isset($resource->fields['id']) || $resource->fields['id'] <= 0) {
                $input = [
                    'entities_id'                              => (int) $_SESSION['glpiactive_entity'],
                    'name'                                     => $user->fields['realname'] ?? '',
                    'firstname'                                => $user->fields['firstname'] ?? '',
                    'plugin_resources_contracttypes_id'        => $params['plugin_resources_contracttypes_id'],
                    'users_id_recipient'                       => Session::getLoginUserID(),
                    'users_id'                                 => $params['users_id_recipient'],
                    'users_id_sales'                           => 0,
                    'date_declaration'                         => date('Y-m-d'),
                    'date_begin'                               => null,
                    'date_end'                                 => null,
                    'plugin_resources_departments_id'          => $params['plugin_resources_departments_id'],
                    'locations_id'                             => 0,
                    'is_leaving'                               => 0,
                    'users_id_recipient_leaving'               => 0,
                    'comment'                                  => '',
                    // Set: prepareInputForAdd() skips the mandatory fields of the forms
                    'is_template'                              => 0,
                    'template_name'                            => '',
                    'is_deleted'                               => 0,
                    'is_helpdesk_visible'                      => 1,
                    'plugin_resources_resourcestates_id'       => 0,
                    'is_recursive'                             => 0,
                    'quota'                                    => 1,
                    'plugin_resources_resourcesituations_id'   => 0,
                    'plugin_resources_contractnatures_id'      => 0,
                    'plugin_resources_ranks_id'                => 0,
                    'plugin_resources_resourcespecialities_id' => 0,
                    'plugin_resources_leavingreasons_id'       => 0,
                    'sensitize_security'                       => 0,
                    'read_chart'                               => 0,
                ];

                // A resource is created: CREATE on resources, in the entity of the input. The
                // former test, Resource_Item::can() handed the Resource object as its input,
                // was a TypeError (can() takes an array), ending the action on an error.
                if ($resource->can(-1, CREATE, $input)) {
                    $idResource = $resource->add($input);
                    if ($idResource) {
                        // The caller checked that the session may read this user
                        $resourceItem = new Resource_Item();
                        $idResourceItem = $resourceItem->add([
                            'plugin_resources_resources_id' => $idResource,
                            'items_id'                      => $user->getID(),
                            'itemtype'                      => \User::class,
                        ]);
                        if ($idResourceItem) {
                            // Cochage des checklist en mode "JOB DONE"
                            $pChecklist = new Checklist();

                            if ($DB->update(
                                $pChecklist->getTable(),
                                ['is_checked' => 1],
                                ['plugin_resources_resources_id' => (int) $idResource],
                            )) {
                                $message = $user_label;
                            }
                        } else {
                            $error['error'] = 1;
                            $message = $user_label;
                            $resource->delete(['id' => $idResource], 1);
                        }
                    } else {
                        $error['error'] = 1;
                    }
                } else {
                    $error['right'] = 1;
                }
            } else {
                $error['error'] = 1;
                $message = $user_label;
            }
        } else {
            $error['error'] = 1;
        }

        return [$idResource, $error, $message];
    }

    /**
     * @param bool $count
     * @param int $entity_restrict
     * @param int $value
     * @param array $used
     * @param string $search
     * @param bool $showOnlyLinkedResources
     *
     * @return \Glpi\DBAL\DBmysqlIterator
     */
    public static function getSqlSearchResult(
        $count = true,
        $entity_restrict = -1,
        $value = 0,
        $used = [],
        $search = '',
        $showOnlyLinkedResources = false,
        $isNotLeavingOnly = false
    ) {
        global $DB, $CFG_GLPI;

        // No entity define : use active ones
        if ($entity_restrict < 0) {
            $entity_restrict = $_SESSION["glpiactiveentities"];
        }

        $dbu = new DbUtils();

        $where = [
            'glpi_plugin_resources_resources.is_deleted' => 0,
        ];
        if (!$isNotLeavingOnly) {
            $where[] = [
                'OR' => [
                    'glpi_plugin_resources_resources.is_leaving' => 0,
                    'glpi_plugin_resources_resources.date_end'   => ['>', QueryFunction::now()],
                ],
            ];
        } else {
            $where['glpi_plugin_resources_resources.is_leaving'] = 0;
        }

        $where['glpi_plugin_resources_resources.is_template'] = 0;

        $entities_crit = $dbu->getEntitiesRestrictCriteria(
            'glpi_plugin_resources_resources',
            '',
            $entity_restrict,
            true,
        );
        if (count($entities_crit)) {
            $where[] = $entities_crit;
        }

        if ((is_numeric($value) && $value)
            || count($used)
        ) {
            $exclude = [0];
            if (is_numeric($value)) {
                $exclude[] = (int) $value;
            }
            if (is_array($used)) {
                foreach ($used as $val) {
                    $exclude[] = (int) $val;
                }
            }
            $where[] = ['NOT' => ['glpi_plugin_resources_resources.id' => $exclude]];
        }

        if (!Session::haveRight("plugin_resources_all", READ)) {
            $who = (int) Session::getLoginUserID();
            $where[] = [
                'OR' => [
                    'glpi_plugin_resources_resources.users_id_recipient' => $who,
                    'glpi_plugin_resources_resources.users_id'           => $who,
                ],
            ];
        }

        if ($count) {
            $criteria = [
                'SELECT' => new QueryExpression(
                    'COUNT(DISTINCT ' . $DB->quoteName('glpi_plugin_resources_resources.id')
                    . ') AS ' . $DB->quoteName('cpt'),
                ),
                'FROM'   => 'glpi_plugin_resources_resources',
                'WHERE'  => $where,
            ];
        } else {
            $contracttypeprofile = new Contracttypeprofile();
            if ($contracttypeprofile->getFromDBByCrit(["profiles_id" => $_SESSION['glpiactiveprofile']['id']])) {
                $contracttypeprofiles = json_decode($contracttypeprofile->fields['plugin_resources_contracttypes_id']);
                if ($contracttypeprofiles !== false && is_array(
                    $contracttypeprofiles,
                ) && !empty($contracttypeprofiles)) {
                    $where['glpi_plugin_resources_resources.plugin_resources_contracttypes_id']
                        = array_map('intval', $contracttypeprofiles);
                }
            }
            if (strlen($search) > 0 && $search != $CFG_GLPI["ajax_wildcard"]) {
                $search_sql = Search::makeTextSearch($search);
                $where[] = [
                    'OR' => [
                        new QueryExpression(
                            $DB->quoteName('glpi_plugin_resources_resources.name') . ' ' . $search_sql,
                        ),
                        new QueryExpression(
                            $DB->quoteName('glpi_plugin_resources_resources.firstname') . ' ' . $search_sql,
                        ),
                        new QueryExpression(
                            $DB->quoteName('glpi_users.registration_number') . ' ' . $search_sql,
                        ),
                        new QueryExpression(
                            $DB->quoteName('glpi_users.name') . ' ' . $search_sql,
                        ),
                        new QueryExpression(
                            'CONCAT(' . $DB->quoteName('glpi_plugin_resources_resources.name') . ', '
                            . $DB->quoteValue(' ') . ', '
                            . $DB->quoteName('glpi_plugin_resources_resources.firstname') . ', '
                            . $DB->quoteValue(' ') . ', '
                            . $DB->quoteName('glpi_users.registration_number') . ', '
                            . $DB->quoteValue(' ') . ', '
                            . $DB->quoteName('glpi_users.name') . ') ' . $search_sql,
                        ),
                    ],
                ];
            }

            $join = [
                'glpi_plugin_resources_resources_items' => [
                    'ON' => [
                        'glpi_plugin_resources_resources_items' => 'plugin_resources_resources_id',
                        'glpi_plugin_resources_resources'       => 'id',
                        [
                            'AND' => [
                                'glpi_plugin_resources_resources_items.itemtype' => 'User',
                            ],
                        ],
                    ],
                ],
                'glpi_users' => [
                    'ON' => [
                        'glpi_users'                            => 'id',
                        'glpi_plugin_resources_resources_items' => 'items_id',
                        [
                            'AND' => [
                                'glpi_plugin_resources_resources_items.itemtype' => 'User',
                            ],
                        ],
                    ],
                ],
            ];

            $criteria = [
                'SELECT'   => [
                    'glpi_plugin_resources_resources.*',
                    'glpi_users.registration_number',
                    'glpi_users.name AS username',
                    'glpi_users.id AS userid',
                ],
                'DISTINCT' => true,
                'FROM'     => 'glpi_plugin_resources_resources',
                ($showOnlyLinkedResources ? 'INNER JOIN' : 'LEFT JOIN') => $join,
                'WHERE'    => $where,
                'ORDER'    => [
                    'glpi_plugin_resources_resources.firstname',
                    'glpi_plugin_resources_resources.name',
                ],
            ];

            if ($search != $CFG_GLPI["ajax_wildcard"]) {
                $criteria['START'] = 0;
                $criteria['LIMIT'] = (int) $CFG_GLPI["dropdown_max"];
            }
        }
        return $DB->request($criteria);
    }

    /**
     * List the resource templates.
     *
     * Rendering is delegated to the core template list view, which already brings the
     * multi entity column, the purge button and its CSRF token. Template names are
     * escaped here: they are free text and used to be echoed raw inside the link.
     *
     * @param string $target
     * @param int    $add
     */
    public function listOfTemplates($target, $add = 0)
    {
        $dbu = new DbUtils();

        $restrict = ["is_template" => 1]
            + $dbu->getEntitiesRestrictCriteria($this->getTable(), '', '', $this->maybeRecursive())
            + ["ORDER" => "name"];

        $templates = $dbu->getAllDataFromTable($this->getTable(), $restrict);

        $can_purge = self::canPurge();
        $entries   = [];

        if ($add) {
            $entries[] = [
                'name' => '<a href="' . htmlescape($target . '?id=-1&withtemplate=2') . '">'
                    . __s('Blank Template') . '</a>',
            ];
        }

        // Picking a template creates a resource out of it (withtemplate=2), while the
        // management screen edits the template itself (withtemplate=1).
        $withtemplate = $add ? 2 : 1;

        foreach ($templates as $template) {
            $templname = $template["template_name"];
            if ($_SESSION["glpiis_ids_visible"] || empty($template["template_name"])) {
                $templname = sprintf(__('%1$s (%2$s)'), $templname, $template["id"]);
            }

            $url = $target . '?id=' . $template["id"] . '&withtemplate=' . $withtemplate;

            $entry = [
                'id'   => $template["id"],
                'name' => '<a href="' . htmlescape($url) . '">' . htmlescape($templname) . '</a>',
            ];

            if (!$add) {
                if (Session::isMultiEntitiesMode()) {
                    $entry['entity'] = Dropdown::getDropdownName("glpi_entities", $template['entities_id']);
                }
                $entry['can_delete'] = $can_purge && $this->can($template["id"], PURGE);
            }

            $entries[] = $entry;
        }

        TemplateRenderer::getInstance()->display('pages/assets/template_list.html.twig', [
            'add_mode'      => (bool) $add,
            'templates'     => $entries,
            'target'        => $target,
            'can_delete'    => $can_purge,
            'add_template'  => !$add && self::canCreate(),
            'target_create' => $target . '?id=-1&withtemplate=1',
        ]);
    }

    /**
     * Show the helpdesk form used to declare the departure of a resource.
     *
     * @return void
     */
    public function showResourcesToRemove()
    {
        $dbu = new DbUtils();

        if ($dbu->countElementsInTable($this->getTable()) == 0) {
            TemplateRenderer::getInstance()->display('@resources/resource_remove_form.html.twig', [
                'has_resources' => false,
            ]);
            return;
        }

        $preselected_id = isset($_GET['resource_id']) ? (int) $_GET['resource_id'] : 0;

        // Only the contract types granted to the current profile can be declared as leaving.
        // Resources with no contract type at all (0) stay reachable.
        $available_contracttype = false;
        $contracttypeprofile    = new Contracttypeprofile();
        if ($contracttypeprofile->getFromDBByCrit(['profiles_id' => $_SESSION['glpiactiveprofile']['id']])) {
            $available_contracttype = json_decode(
                $contracttypeprofile->fields['plugin_resources_contracttypes_id'],
            );
        }

        $cond = ['is_not_leaving_only' => 'is_not_leaving_only'];
        if (is_array($available_contracttype)) {
            $available_contracttype[] = 0;
            $cond['plugin_resources_contracttypes_id'] = $available_contracttype;
        }

        // The dropdowns echo their markup and return their rand by default: ask for the
        // string instead, so the template stays in charge of the layout.
        $rand = mt_rand();
        $resource_dropdown = (string) self::dropdown([
            'name'      => 'plugin_resources_resources_id',
            'display'   => false,
            'entity'    => $_SESSION['glpiactiveentities'],
            'condition' => $cond,
            'rand'      => $rand,
            'value'     => $preselected_id,
            'toupdate'  => [
                [
                    'value_fieldname' => 'plugin_resources_resources_id',
                    'to_update'       => 'resource_pdf',
                    'url'             => PLUGIN_RESOURCES_WEBDIR . '/ajax/pdfresource.php',
                ],
                [
                    'value_fieldname' => 'plugin_resources_resources_id',
                    'to_update'       => 'leaving_input',
                    'url'             => PLUGIN_RESOURCES_WEBDIR . '/ajax/leavingform.php',
                    'moreparams'      => ['rand' => $rand],
                ],
            ],
        ]);

        $manager_dropdown = (string) User::dropdown([
            'name'    => 'remove_manager',
            'right'   => 'all',
            'display' => false,
        ]);

        TemplateRenderer::getInstance()->display('@resources/resource_remove_form.html.twig', [
            'has_resources'     => true,
            'header_title'      => __('Declare a departure', 'resources'),
            'header_img'        => PLUGIN_RESOURCES_WEBDIR . "/pics/removeresource.png",
            'form_action'       => PLUGIN_RESOURCES_WEBDIR . "/front/resource.remove.php",
            'resource_label'    => self::getTypeName(1),
            'resource_dropdown' => $resource_dropdown,
            'date_end'          => $_POST["date_end"] ?? '',
            'manager_dropdown'  => $manager_dropdown,
            // A pre-selected resource never fires a change event: its leaving block is loaded
            // as soon as the page is shown (public/scripts/fragments.js).
            'leaving_url'       => PLUGIN_RESOURCES_WEBDIR . '/ajax/leavingform.php',
            'leaving_params'    => $preselected_id > 0
                ? ['plugin_resources_resources_id' => $preselected_id, 'rand' => $rand]
                : [],
        ]);
    }

    /**
     * Show the helpdesk form used to declare a change on a resource.
     *
     * @param array $options previously posted values, used to report the empty ones
     *
     * @return void
     */
    public function showResourcesToChange($options = [])
    {
        $dbu = new DbUtils();

        if ($dbu->countElementsInTable($this->getTable()) == 0) {
            TemplateRenderer::getInstance()->display('@resources/resource_change_form.html.twig', [
                'has_resources' => false,
            ]);
            return;
        }

        // The dropdowns echo their markup and return their rand by default: ask for the
        // string instead, so the template stays in charge of the layout.
        $resource_dropdown = (string) self::dropdown([
            'name'      => 'plugin_resources_resources_id',
            'display'   => false,
            'entity'    => $_SESSION['glpiactiveentities'],
        ]);

        // Only offer the actions granted to the current profile, the list the form handler
        // checks the posted action against.
        $actions = [0 => Dropdown::EMPTY_VALUE] + Resource_Change::getAllowedActions();

        $action_dropdown = (string) Dropdown::showFromArray('change_action', $actions, [
            'display' => false,
        ]);

        // Fields the previous submit left empty, reported back above the action block.
        $msg = [];
        if (isset($options['plugin_resources_resources_id']) && $options['plugin_resources_resources_id'] == 0) {
            $msg[] = self::getTypeName(1);
        }
        if (isset($options['change_action']) && $options['change_action'] == 0) {
            $msg[] = __('Actions to taken');
        }

        TemplateRenderer::getInstance()->display('@resources/resource_change_form.html.twig', [
            'has_resources'     => true,
            'header_title'      => __('Declare a change', 'resources'),
            'header_img'        => PLUGIN_RESOURCES_WEBDIR . "/pics/recap.png",
            'form_action'       => PLUGIN_RESOURCES_WEBDIR . "/front/resource.change.php",
            'fields_url'        => PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcechange.php',
            'resource_label'    => self::getTypeName(1),
            'resource_dropdown' => $resource_dropdown,
            'action_label'      => __('Actions to be taken', 'resources'),
            'action_dropdown'   => $action_dropdown,
            'error_message'     => count($msg) > 0
                ? sprintf(__("Please correct: %s", 'resources'), implode(', ', $msg))
                : '',
        ]);
    }

    /**
     * Show the helpdesk form used to declare the transfer of a resource to another entity.
     *
     * @param int $plugin_resources_resources_id
     *
     * @return void
     */
    public function showResourcesToTransfer($plugin_resources_resources_id)
    {
        $resource = new self();

        // can() loads the row and enforces both the read right and the entity boundary,
        // which the bare getFromDB() it replaces did not: the name and the current entity
        // of any resource were readable from the id alone.
        if (!$resource->can((int) $plugin_resources_resources_id, READ)) {
            TemplateRenderer::getInstance()->display('@resources/resource_transfer_form.html.twig', [
                'has_resource' => false,
            ]);
            return;
        }

        // Only the entities opened to transfers, and only those the current user may reach:
        // front/resource.transfer.php checks the posted one again anyway.
        $elements = [Dropdown::EMPTY_VALUE];
        foreach ((new TransferEntity())->find() as $val) {
            if (!Session::haveAccessToEntity($val['entities_id'])) {
                continue;
            }
            $elements[$val['entities_id']] = Dropdown::getDropdownName('glpi_entities', $val['entities_id']);
        }

        TemplateRenderer::getInstance()->display('@resources/resource_transfer_form.html.twig', [
            'has_resource'                  => true,
            'header_title'                  => __('Declare a transfer', 'resources'),
            'header_img'                    => PLUGIN_RESOURCES_WEBDIR . "/pics/transferresource.png",
            'form_action'                   => PLUGIN_RESOURCES_WEBDIR . "/front/resource.transfer.php",
            'resource_label'                => self::getTypeName(1),
            'resource_name'                 => self::getResourceName($resource->getID()),
            'current_entity'                => Dropdown::getDropdownName(
                'glpi_entities',
                $resource->fields['entities_id'],
            ),
            'entity_dropdown'               => (string) Dropdown::showFromArray('entities_id', $elements, [
                'display' => false,
            ]),
            'plugin_resources_resources_id' => $resource->getID(),
        ]);
    }

    /**
     * Massive actions to be added
     *
     * @param string $type
     *
     * @return array
     */
    public function massiveActions($type)
    {
        $action = [];
        $prefix = $this->getType() . MassiveAction::CLASS_ACTION_SEPARATOR;
        if (Session::haveRightsOr('plugin_resources', [CREATE, UPDATE])) {
            $action[$prefix . "plugin_resources_add_item"] = __('Associate a resource', 'resources');
        }

        // Both entries write plugin records from a list the plugin does not own (a Resource
        // for the first, a ResourceHabilitation for the second), so the right they need is the
        // plugin's, not the one that opened the User list. Same test as above.
        if ($type == "User" && Session::haveRightsOr('plugin_resources', [CREATE, UPDATE])) {
            $action[$prefix . "plugin_resources_generate_resources"] = __('Generate resources', 'resources');
            $action[$prefix . "plugin_resources_add_habilitation"] = __('Add habiliation', 'resources');
        }
        return $action;
    }

    /**
     * Get the specific massive actions
     *
     * @param $checkitem link item to check right   (default NULL)
     *
     * @return an array of massive actions
     * *@since version 0.84
     *
     */
    public function getSpecificMassiveActions($checkitem = null)
    {
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if ($isadmin && Session::getCurrentInterface() == 'central') {
            $actions['GlpiPlugin\Resources\Resource' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Install'] = _x(
                'button',
                'Associate',
            );
            $actions['GlpiPlugin\Resources\Resource' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Desinstall'] = _x(
                'button',
                'Dissociate',
            );

            if (Session::haveRight('transfer', READ)
                && Session::isMultiEntitiesMode()
            ) {
                $actions['GlpiPlugin\Resources\Resource' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Transfert'] = __(
                    'Transfer',
                );
            }
            $actions['GlpiPlugin\Resources\Resource' . MassiveAction::CLASS_ACTION_SEPARATOR . 'AddHabilitation'] = __(
                'Add additional habilitation',
                'resources',
            );
            $actions['GlpiPlugin\Resources\Resource' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Send'] = __(
                'Send a notification',
            );
        }
        return $actions;
    }

    /**
     * Class-specific method used to show the fields to specify the massive action
     *
     * @param MassiveAction $ma the current massive action object
     *
     * @return bool false if parameters displayed ?
     **@since 0.85
     *
     */
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        $itemtype = $ma->getItemtype(false);
        switch ($ma->getAction()) {
            case "Install":
                Dropdown::showSelectItemFromItemtypes([
                    'items_id_name' => "item_item",
                    'itemtypes' => self::getTypes(),
                ]);
                break;
            case "Desinstall":
                Dropdown::showSelectItemFromItemtypes([
                    'items_id_name' => "item_item",
                    'itemtypes' => self::getTypes(),
                ]);
                break;
            case "Transfert":
                Dropdown::show('Entity');
                break;
            case "plugin_resources_add_item":
                // showMassiveActionsSubForm() is a printing core hook.
                echo Html::hidden('itemtype', ['value' => $itemtype]);
                self::dropdown(['display' => true]);
                break;
            case "plugin_resources_generate_resources":
                echo Html::hidden('itemtype', ['value' => $itemtype]);
                self::fastResourceAddForm();
                break;
            case "AddHabilitation":
            case "plugin_resources_add_habilitation":
                Dropdown::show(
                    Habilitation::class,
                    ['entity' => $_SESSION['glpiactiveentities']],
                );
                break;
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     * */
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        $input = $ma->getInput();
        $resource_item = new Resource_Item();
        $resource = new Resource();
        $itemtype = $ma->getItemtype(false);

        // Every branch checks a key with can() and then writes or reads with it: only keys
        // that are plain ids go on, so that both use the same integer.
        $ids = self::getMassiveActionIds($ma, $item, $ids);
        if ($ids === []) {
            return;
        }

        // Every branch below that links or unlinks an item reads the itemtype from the
        // posted form and hands it to Resource_Item. Validate the value against the types
        // the plugin actually exposes, the way ajax/linkItems.php does: the per-item can()
        // answers for the resource, never for the class named alongside it.
        $link_itemtype = (string) ($input['itemtype'] ?? '');
        // On the lists of the linked types, the ids processed are those of the list's type, the
        // one the per-item can() below answers for: the link is stored under that type, never
        // under the itemtype posted by the sub form, which designated another object with the
        // same id (a user instead of a computer).
        if ($ma->getAction() === 'plugin_resources_add_item') {
            $link_itemtype = $item->getType();
        } elseif ($ma->getAction() === 'plugin_resources_generate_resources') {
            $link_itemtype = \User::class;
        }
        $link_actions = ['Install', 'Desinstall', 'plugin_resources_add_item', 'plugin_resources_generate_resources'];
        if (in_array($ma->getAction(), $link_actions, true)
            && !in_array($link_itemtype, self::getTypes(true), true)) {
            $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
            $ma->addMessage(__('Unsupported item type', 'resources'));
            return;
        }

        // The object being linked or unlinked is posted too, and the per-item can() below
        // answers for the resource, never for it. Its name, serial and inventory number are
        // displayed back on the resource afterwards, so require read access on it, which
        // covers the right bit and the entity boundary alike — the same guard the additem
        // branch of front/resource.form.php poses.
        $link_items_id = (int) ($input['item_item'] ?? 0);
        if (in_array($ma->getAction(), ['Install', 'Desinstall'], true)) {
            $linked_item = getItemForItemtype($link_itemtype);
            if (!$linked_item || !$linked_item->can($link_items_id, READ)) {
                $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                return;
            }
        }

        // Same reasoning for the habilitation the two AddHabilitation branches attach: it is a
        // CommonTreeDropdown value carrying its own entity, and the per-item can() answers for
        // the resource row, never for the value posted next to it. is_recursive is part of the
        // test, otherwise an inherited habilitation would be rejected.
        $habilitations_id = (int) ($input['plugin_resources_habilitations_id'] ?? 0);
        if (in_array($ma->getAction(), ['AddHabilitation', 'plugin_resources_add_habilitation'], true)) {
            $habilitation_value = new Habilitation();
            if ($habilitations_id <= 0
                || !$habilitation_value->getFromDB($habilitations_id)
                || !Session::haveAccessToEntity(
                    $habilitation_value->fields['entities_id'],
                    $habilitation_value->fields['is_recursive'],
                )) {
                $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                return;
            }
        }

        switch ($ma->getAction()) {
            case "Transfert":
                if ($itemtype == Resource::class) {
                    // getSpecificMassiveActions() only offers the action with the transfer
                    // right, in multi-entity mode: the core endpoint runs any posted action, so
                    // the same test has to stand here.
                    if (!Session::haveRight(\Transfer::$rightname, READ)
                        || !Session::isMultiEntitiesMode()) {
                        $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        break;
                    }
                    // The destination entity is posted with the form and nothing below validates
                    // it: without this test the action moved resources into any entity of the
                    // instance. The per-item check that follows scopes the source side, which
                    // update() never does on its own.
                    $entities_id = (int) ($input['entities_id'] ?? -1);
                    if (!Session::haveAccessToEntity($entities_id)) {
                        $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        break;
                    }
                    foreach ($ids as $key => $val) {
                        if (!$item->can($key, UPDATE)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                            continue;
                        }
                        if ($item->transferResource($item->getID(), $entities_id)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    }
                }
                break;

            case "Install":
                foreach ($ids as $key => $val) {
                    if ($item->can($key, UPDATE)) {
                        $values = [
                            'plugin_resources_resources_id' => $item->getID(),
                            'items_id' => $link_items_id,
                            'itemtype' => $link_itemtype,
                        ];
                        if ($resource_item->add($values)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                }
                break;

            case "Desinstall":
                foreach ($ids as $key => $val) {
                    // Same contract as the "Install" branch above: the posted ids are not
                    // filtered by the massive action engine, so each one has to answer for
                    // itself before a link is removed.
                    if (!$item->can($key, UPDATE)) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        continue;
                    }
                    if ($resource_item->deleteItemByResourcesAndItem($item->getID(), $link_items_id, $link_itemtype)) {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                    }
                }
                break;

            case "Send":
                if ($resource->sendEmail($ids)) {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_OK);
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
                }
                break;

            case "plugin_resources_add_item":
                // Both ends of the relation must be authorized: the loop below checks the
                // source item, but the destination resource comes straight from the sub form.
                // can() covers the right bit and the entity boundary in one call.
                $target_resources_id = (int) ($input['plugin_resources_resources_id'] ?? 0);
                if ($target_resources_id <= 0 || !$resource->can($target_resources_id, UPDATE)) {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                    $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    break;
                }

                $messages = [];
                foreach ($ids as $key => $val) {
                    if ($item->can($key, UPDATE)) {
                        // Do not reassign $input here: the loop reads it again on the next
                        // iteration, so shadowing it made the branch depend on its own
                        // output.
                        $values = [
                            'plugin_resources_resources_id' => $target_resources_id,
                            'items_id' => $item->getID(),
                            'itemtype' => $link_itemtype,
                        ];

                        if ($resource_item->add($values)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                            $messages[] = _n(
                                "This resource has been added",
                                "These resources have been added",
                                2,
                                "resources",
                            );
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            $messages[] = _n(
                                "This resource aldready exists",
                                "These resources aldready exist",
                                2,
                                "resources",
                            );
                        }
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        $messages[] = $item->getErrorMessage(ERROR_RIGHT);
                    }
                }
                $ma->addMessage(implode("<br>", array_unique($messages)));
                break;

            case "plugin_resources_generate_resources":
                $messages = [];
                // The sub form posts itemtype as a scalar ("User"): sizeof() on a string is a
                // TypeError since PHP 8.0, so this guard used to abort the whole action. Only
                // check that the hidden field made it through.
                if (!empty($input['itemtype'])) {
                    foreach ($ids as $key => $val) {
                        // The ids come from the posted list, untouched by the engine: the
                        // identity of the user is copied into the resource, so the session has
                        // to be allowed to read that user.
                        if (!$item instanceof \User || !$item->can($key, READ)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            $messages[] = $item->getErrorMessage(ERROR_RIGHT);
                            continue;
                        }
                        [$id, $error, $message] = self::fastResourceAdd($key, $input);
                        if ($error['right']) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            $messages[] = $item->getErrorMessage(ERROR_RIGHT);
                        } elseif ($error['error']) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            $messages[] = _n(
                                "This resource aldready exists",
                                "These resources aldready exist",
                                2,
                                "resources",
                            ) . "<br>" . $message;
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                            $messages[] = _n(
                                "This resource has been added",
                                "These resources have been added",
                                2,
                                "resources",
                            ) . "<br>" . $message;
                        }
                    }
                }
                $ma->addMessage(implode("<br>", array_unique($messages)));
                break;

            case "AddHabilitation":
                $habilitation = new ResourceHabilitation();
                foreach ($ids as $key => $val) {
                    // $item is the Resource here, so can(UPDATE) already covers the plugin
                    // right and the entity of the row. The posted habilitation was validated
                    // before the switch.
                    if ($item->can($key, UPDATE)) {
                        $resources_id = $item->getID();
                        //check if habilitation already added
                        if (!$habilitation->getFromDBByCrit([
                            'plugin_resources_resources_id' => $resources_id,
                            'plugin_resources_habilitations_id' => $habilitations_id,
                        ])) {
                            if ($resource->getFromDB($resources_id)) {
                                $values = [
                                    'plugin_resources_resources_id' => $resources_id,
                                    'plugin_resources_habilitations_id' => $habilitations_id,
                                ];
                                if ($habilitation->add($values)) {
                                    $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                                } else {
                                    $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                                }
                            } else {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            }
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                }
                break;
            case "plugin_resources_add_habilitation":
                $habilitation = new ResourceHabilitation();
                foreach ($ids as $key => $val) {
                    if ($item->can($key, UPDATE)) {
                        // The link of the core user to its resource. User::getType() named the
                        // plugin class GlpiPlugin\Resources\User here, an itemtype no link
                        // carries, so the action never found any resource. getFromDBByCrit()
                        // throws on several rows: a user linked to more than one resource is
                        // left out, as it was meant to be, instead of guessing which one.
                        $user_links = (new Resource_Item())->find(
                            ['items_id' => (int) $item->getID(), 'itemtype' => \User::class],
                            [],
                            2,
                        );
                        if (count($user_links) === 1) {
                            $resource_id = (int) reset($user_links)['plugin_resources_resources_id'];
                            // Check if the habilitation is already added (a count: duplicates
                            // left by an older version would make getFromDBByCrit() throw)
                            if (countElementsInTable($habilitation->getTable(), [
                                'plugin_resources_resources_id' => $resource_id,
                                'plugin_resources_habilitations_id' => $habilitations_id,
                            ]) === 0) {
                                // $item is a User on this branch, so the can(UPDATE) above is
                                // the User write right and nothing more: it says nothing about
                                // the plugin right, and nothing about the entity of the
                                // resource the habilitation lands on. ResourceHabilitation is
                                // owned by plugin_resources and add() checks no right of its
                                // own, so this call was the only remaining gate. can(UPDATE)
                                // on the resource covers both, and loads it, which is all
                                // getFromDB() did here.
                                if ($resource->can($resource_id, UPDATE)) {
                                    $values = [
                                        'plugin_resources_resources_id' => $resource_id,
                                        'plugin_resources_habilitations_id' => $habilitations_id,
                                    ];
                                    if ($habilitation->add($values)) {
                                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                                    } else {
                                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                                    }
                                } else {
                                    $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                                    $ma->addMessage($resource->getErrorMessage(ERROR_RIGHT));
                                }
                            } else {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            }
                        } else {
                            // No resource, or several, linked to this user
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                        }
                    } else {
                        // The outcomes were swapped: a refused right reported a failure, a
                        // user without resource reported a refused right.
                        $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                }
                break;
        }
    }

    /**
     * Transfer a resource, and the rows attached to it, to another entity.
     *
     * Only the resource named by $resources_id is moved, and only when the session may update
     * it: the check is made here, on the row loaded, whatever the caller already tested.
     *
     * The former "users-<id>" form of the id, which created the resource or replaced it with
     * a posted link_resources_id, is gone: no caller sends it (only the former addUnlinkedUsers
     * branch of ajax/dropdownResources.php emitted it, and that branch is gone too), and it
     * moved a resource nobody had been authorised on.
     *
     * @param int                  $resources_id id of the resource
     * @param int                  $entities_id  destination entity
     * @param array<string, mixed> $options      users_id: user to link to the resource
     *
     * @return bool
     */
    public function transferResource(int $resources_id, int $entities_id, array $options = []): bool
    {
        global $DB;

        $dbu = new DbUtils();

        $resource_item = new Resource_Item();

        $resourceOk = $resources_id > 0 && $this->can($resources_id, UPDATE);
        $source_entity = $this->fields['entities_id'] ?? 0;

        if ($resourceOk) {
            // Link to a user if needed. The user is displayed on the resource afterwards, so
            // it has to be readable as well.
            $users_id = (int) ($options['users_id'] ?? 0);
            if ($users_id > 0) {
                $input = [
                    'plugin_resources_resources_id' => $resources_id,
                    'items_id' => $users_id,
                    'itemtype' => \User::class,
                ];
                $user = new \User();
                if ($user->can($users_id, READ) && $resource_item->can(-1, CREATE, $input)) {
                    $resource_item->add($input);
                }
            }

            $contracttype = ContractType::transfer($this->fields["plugin_resources_contracttypes_id"], $entities_id);
            if ($contracttype > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_contracttypes_id"] = $contracttype;
                $this->updateFromWorkflow($values);
            }

            unset($values);

            $resourcestate = ResourceState::transfer($this->fields["plugin_resources_resourcestates_id"], $entities_id);
            if ($resourcestate > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_resourcestates_id"] = $resourcestate;
                $this->updateFromWorkflow($values);
            }

            unset($values);

            $department = Department::transfer($this->fields["plugin_resources_departments_id"], $entities_id);
            if ($department > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_departments_id"] = $department;
                $this->updateFromWorkflow($values);
            }

            unset($values);

            $situation = ResourceSituation::transfer(
                $this->fields["plugin_resources_resourcesituations_id"],
                $entities_id,
            );
            if ($situation > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_resourcesituations_id"] = $situation;
                $this->updateFromWorkflow($values);
            }

            unset($values);

            $contractnature = ContractNature::transfer(
                $this->fields["plugin_resources_contractnatures_id"],
                $entities_id,
            );
            if ($contractnature > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_contractnatures_id"] = $contractnature;
                $this->updateFromWorkflow($values);
            }
            unset($values);

            $rank = Rank::transfer($this->fields["plugin_resources_ranks_id"], $entities_id);
            if ($rank > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_ranks_id"] = $rank;
                $this->updateFromWorkflow($values);
            }

            unset($values);

            $speciality = ResourceSpeciality::transfer(
                $this->fields["plugin_resources_resourcespecialities_id"],
                $entities_id,
            );
            if ($speciality > 0) {
                $values["id"] = $resources_id;
                $values["plugin_resources_resourcespecialities_id"] = $speciality;
                $this->updateFromWorkflow($values);
            }
            unset($values);

            $Task = new Task();
            $restrict = ["plugin_resources_resources_id" => $resources_id];
            $tasks = $dbu->getAllDataFromTable("glpi_plugin_resources_tasks", $restrict);
            if (!empty($tasks)) {
                foreach ($tasks as $task) {
                    $Task->getFromDB($task["id"]);
                    $tasktype = TaskType::transfer($Task->fields["plugin_resources_tasktypes_id"], $entities_id);
                    if ($tasktype > 0) {
                        $values["id"] = $task["id"];
                        $values["plugin_resources_tasktypes_id"] = $tasktype;
                        $Task->update($values);
                    }
                    $values["id"] = $task["id"];
                    $values["entities_id"] = $entities_id;
                    // An ordinary task update drops entities_id (see Task::prepareInputForUpdate()):
                    // the tasks follow the resource, whose transfer the caller authorised.
                    $Task->allow_entity_change = true;
                    $Task->update($values);
                }
            }

            unset($values);

            $Employment = new Employment();
            $restrict = ["plugin_resources_resources_id" => $resources_id];
            $employments = $dbu->getAllDataFromTable("glpi_plugin_resources_employments", $restrict);
            if (!empty($employments)) {
                foreach ($employments as $employment) {
                    $Employment->getFromDB($employment["id"]);
                    $rank = Rank::transfer($Employment->fields["plugin_resources_ranks_id"], $entities_id);
                    if ($rank > 0) {
                        $values["id"] = $employment["id"];
                        $values["plugin_resources_ranks_id"] = $rank;
                        $Employment->update($values);
                    }
                    $Employment->getFromDB($employment["id"]);
                    $profession = Profession::transfer(
                        $Employment->fields["plugin_resources_professions_id"],
                        $entities_id,
                    );
                    if ($profession > 0) {
                        $values["id"] = $employment["id"];
                        $values["plugin_resources_professions_id"] = $profession;
                        $Employment->update($values);
                    }
                    $values["id"] = $employment["id"];
                    $values["entities_id"] = $entities_id;
                    $Employment->update($values);
                }
            }

            unset($values);

            $values["id"] = $resources_id;
            $values["entities_id"] = $entities_id;
            // An ordinary update drops entities_id (see prepareInputForUpdate()): this is the
            // one write meant to move the resource, after its own checks and the caller's.
            $this->allow_structural_fields = ['entities_id'];
            $updated = $this->updateFromWorkflow($values);
            $this->allow_structural_fields = [];
            if ($updated) {
                // The employer and the client are copied into the destination entity. They are
                // written once the resource is there: Employee checks them against the entity
                // of the resource (Employee::getAllowedLinkedId()).
                unset($values);
                $Employee = new Employee();

                $restrict = ["plugin_resources_resources_id" => $resources_id];
                $employees = $dbu->getAllDataFromTable("glpi_plugin_resources_employees", $restrict);
                if (!empty($employees)) {
                    foreach ($employees as $employee) {
                        $employer = Employer::transfer($employee["plugin_resources_employers_id"], $entities_id);
                        if ($employer > 0) {
                            $values["id"] = $employee["id"];
                            $values["plugin_resources_employers_id"] = $employer;
                            $Employee->update($values);
                        }

                        $client = Client::transfer($employee["plugin_resources_clients_id"], $entities_id);
                        if ($client > 0) {
                            $values["id"] = $employee["id"];
                            $values["plugin_resources_clients_id"] = $client;
                            $Employee->update($values);
                        }
                    }
                }

                // Check list
                $checklist_exist = Checklist::checkIfChecklistExist(
                    $resources_id,
                    Checklist::RESOURCES_CHECKLIST_TRANSFER,
                );
                $checklistconfig = new Checklistconfig();
                if ($checklist_exist) {
                    $checklist = new Checklist();
                    $checklist->deleteByCriteria([
                        'plugin_resources_resources_id' => $resources_id,
                        'checklist_type' => Checklist::RESOURCES_CHECKLIST_TRANSFER,
                    ]);
                }
                // The checklist rows take the entity of their resource, which can() checks on
                // them (see Checklist::prepareInputForAdd()), and only move here: an ordinary
                // update leaves it alone. Whatever the transfer checklist, they follow.
                $DB->update(
                    'glpi_plugin_resources_checklists',
                    ['entities_id' => (int) $entities_id],
                    ['plugin_resources_resources_id' => (int) $resources_id],
                );
                $checklistconfig->addChecklistsFromRules($this, Checklist::RESOURCES_CHECKLIST_TRANSFER);

                // Notification
                $restrict = [
                    "itemtype" => 'User',
                    "plugin_resources_resources_id" => $resources_id,
                ];

                $data = $dbu->getAllDataFromTable('glpi_plugin_resources_resources_items', $restrict);

                if (!empty($data)) {
                    $linkeduser = [];
                    foreach ($data as $val) {
                        $linkeduser[$val['items_id']] = $val['items_id'];
                    }
                    $reportconfig = new ReportConfig();
                    if ($reportconfig->getFromDBByResource($resources_id)) {
                        if ($reportconfig->fields['send_other_notif']) {
                            NotificationEvent::raiseEvent('other', $this, ['reports_id' => $reportconfig->fields['id']]);
                        }
                        if ($reportconfig->fields['send_transfer_notif']) {
                            NotificationEvent::raiseEvent(
                                'transfer',
                                $this,
                                [
                                    'reports_id' => $reportconfig->fields['id'],
                                    'users_id' => $linkeduser,
                                    'source_entity' => $source_entity,
                                    'target_entity' => $entities_id,
                                ],
                            );
                        }
                    }
                } else {
                    Session::addMessageAfterRedirect(
                        __('The notification is not sent because the resource is not linked with a user', 'resources'),
                        true,
                        ERROR,
                    );
                }

                Session::addMessageAfterRedirect(__('Declaration of resource transfer OK', 'resources'), true);
                return true;
            }
        }

        return false;
    }

    // Cron action

    /**
     * @param $name
     *
     * @return array
     */
    public static function cronInfo($name)
    {
        switch ($name) {
            case 'Resources':
                return [
                    'description' => __('Resources not declaring leaving', 'resources'),
                ];   // Optional
                break;
            case 'AlertCommercialManager':
                return [
                    'description' => __('Resources list of commercial manager', 'resources'),
                ];   // Optional
                break;
            case 'UpdateResourcesState':
                return [
                    'description' => __('Update Resources state', 'resources'),
                ];   // Optional
                break;
        }
        return [];
    }

    /**
     * @return array
     */
    public function queryAlert()
    {
        $date = date("Y-m-d H:i:s");
        $query
            = [
                'SELECT' => [
                    '*',
                ],
                'FROM' => $this->getTable(),
                'WHERE' => [
                    'NOT'       => ['date_end' => null, 'is_leaving' => 1],
                    'date_end'    => ['<=', $date],
                    'is_deleted'    => 0,
                    'is_template'    => 0,
                ],
            ];

        return $query;
    }

    /**
     * Cron action on tasks : LeavingResources
     *
     * @param $task for log, if NULL display
     *
     **/
    public static function cronResources($task = null)
    {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["notifications_mailing"]) {
            return 0;
        }

        $message = [];
        $cron_status = 0;

        $resource = new self();
        $query_expired = $resource->queryAlert();

        $querys = [Alert::END => $query_expired];

        $task_infos = [];
        $task_messages = [];

        foreach ($querys as $type => $query) {
            $task_infos[$type] = [];
            foreach ($DB->request($query) as $data) {
                $entity = $data['entities_id'];
                // Same sink as fastResourceAdd(): this line is accumulated into the message the
                // loop below hands to addMessageAfterRedirect(). Escaped leaf by leaf so the <br>
                // that separates the entries stays markup.
                $message = htmlescape($data["name"]) . " " . htmlescape($data["firstname"]) . " : "
                    . Html::convDate($data["date_end"]) . "<br>\n";
                $task_infos[$type][$entity][] = $data;

                if (!isset($task_messages[$type][$entity])) {
                    $task_messages[$type][$entity] = __(
                        'These resources have normally left the company',
                        'resources',
                    ) . "<br />";
                }
                $task_messages[$type][$entity] .= $message;
            }
        }

        foreach ($querys as $type => $query) {
            foreach ($task_infos[$type] as $entity => $resources) {
                Plugin::loadLang('resources');

                // The entity name is free text and the message it prefixes is HTML: escaped once
                // here, for the cron log and for the session message alike.
                $entity_label = htmlescape(Dropdown::getDropdownName("glpi_entities", $entity));

                if (NotificationEvent::raiseEvent(
                    "AlertLeavingResources",
                    new Resource(),
                    [
                        'entities_id' => $entity,
                        'resources' => $resources,
                    ],
                )
                ) {
                    $message = $task_messages[$type][$entity];
                    $cron_status = 1;
                    if ($task) {
                        $task->log($entity_label . ":  $message\n");
                        $task->addVolume(1);
                    } else {
                        Session::addMessageAfterRedirect($entity_label . ":  $message");
                    }
                } else {
                    if ($task) {
                        $task->log($entity_label . ":  Send leaving resources alert failed\n");
                    } else {
                        Session::addMessageAfterRedirect(
                            $entity_label . ":  Send leaving resources alert failed",
                            false,
                            ERROR,
                        );
                    }
                }
            }
        }

        return $cron_status;
    }

    /**
     * Cron action on tasks : AlertCommercialManager
     *
     * @param $task for log, if NULL display
     *
     **/
    public static function cronAlertCommercialManager($task = null)
    {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["notifications_mailing"]) {
            return 0;
        }

        $message = [];
        $cron_status = 0;

        $query_commercial = [
            'SELECT' => [
                'users_id_sales',
            ],
            'DISTINCT' => true,
            'FROM' => 'glpi_plugin_resources_resources',
            'WHERE' => [
                'is_deleted' => 0,
                'users_id_sales' => ['<>', 0],
            ],
        ];

        foreach ($DB->request($query_commercial) as $commercial) {
            $query = [
                'SELECT' => [
                    '*',
                ],
                'FROM' => 'glpi_plugin_resources_resources',
                'WHERE' => [
                    'is_deleted' => 0,
                    'users_id_sales' => $commercial['users_id_sales'],
                ],
            ];

            $resources = [];
            foreach ($DB->request($query) as $data) {
                $resources[] = $data;
            }
            $resource = new Resource();
            $resource->fields['id'] = $resources[0]['id'] ?? 0;

            $dbu = new DbUtils();
            // Same sink again, and the same reason to escape once: the name is free text. The
            // method also mixed the global getUserName() with the DbUtils one, which returned the
            // very same unescaped value; the object is used on every branch now.
            $sales_label = htmlescape($dbu->getUserName($commercial['users_id_sales']));

            if (count($resources) > 0 && NotificationEvent::raiseEvent(
                "AlertCommercialManager",
                $resource,
                [
                    'resources' => $resources,
                    'users_id_sales' => $commercial['users_id_sales'],
                ],
            )
            ) {
                $cron_status = 1;
                if ($task) {
                    $task->log(
                        $sales_label . ": "
                        . __('Send alert to the commercial manager', 'resources') . "\n",
                    );
                    $task->addVolume(1);
                } else {
                    Session::addMessageAfterRedirect(
                        $sales_label . ": "
                        . __('Send alert to the commercial manager', 'resources') . "\n",
                    );
                }
            } else {
                if ($task) {
                    $task->log(
                        $sales_label . ": "
                        . __('Failed to Send alert to the commercial manager', 'resources') . "\n",
                    );
                } else {
                    Session::addMessageAfterRedirect(
                        $sales_label . ": "
                        . __('Failed to Send alert to the commercial manager', 'resources') . "\n",
                    );
                }
            }
        }

        return $cron_status;
    }

    /**
     * Cron action on tasks : UpdateResourcesState
     *
     * @param $task for log, if NULL display
     *
     **/
    public static function cronUpdateResourcesState($task = null)
    {
        global $DB, $CFG_GLPI;

        $resource = new Resource();
        $config = new Config();
        $config->getFromDB(1);

        $message = [];
        $cron_status = 1;

        $query_arrival = [
            'SELECT' => [
                '*',
            ],
            'FROM' => 'glpi_plugin_resources_resources',
            'WHERE' => [
                'is_deleted' => 0,
                'NOT' => ['date_begin' => 'NULL'],
                'date_begin' => ['<=', QueryFunction::now()],
                [
                    'OR' => ['date_end' => 'NULL'],
                    'date_end' => ['>', QueryFunction::now()],
                ],
            ],
        ];

        foreach ($DB->request($query_arrival) as $resourceD) {
            if ($resourceD['plugin_resources_resourcestates_id'] != $config->fields['plugin_resources_resourcestates_id_arrival']) {
                $input = [];
                $input['id'] = $resourceD['id'];
                $input["plugin_resources_resourcestates_id"] = $config->fields['plugin_resources_resourcestates_id_arrival'];
                // Scheduled state change: it applies to validated resources as well
                $resource->updateFromWorkflow($input);
                $task->addVolume(1);
            }
        }

        $query_departure = [
            'SELECT' => [
                '*',
            ],
            'FROM' => 'glpi_plugin_resources_resources',
            'WHERE' => [
                'is_deleted' => 0,
                'NOT' => ['date_begin' => 'NULL'],
                'date_begin' => ['<=', QueryFunction::now()],
                'date_end' => ['<', QueryFunction::now()],
            ],
        ];

        foreach ($DB->request($query_departure) as $resourceD) {
            if ($resourceD['plugin_resources_resourcestates_id'] != $config->fields['plugin_resources_resourcestates_id_departure']) {
                $input = [];
                $input['id'] = $resourceD['id'];
                $input["plugin_resources_resourcestates_id"] = $config->fields['plugin_resources_resourcestates_id_departure'];
                $resource->updateFromWorkflow($input);
                $task->addVolume(1);
            }
        }

        return $cron_status;
    }

    /**
     * Maximum number of resources listed under a contract type node.
     *
     * The tree has no paging: past that count, a trailing node links to the filtered
     * search list instead.
     */
    public const TREE_CHILDREN_LIMIT = 50;

    /**
     * Search criteria restricting the contract type tree to what the user may see.
     *
     * Mirrors plugin_resources_addDefaultWhere() (hook.php) so the tree exposes exactly
     * what the resource list would: entity scope, plus the "own resources only"
     * restriction when the profile lacks the plugin_resources_all right. The tree is
     * opened from a page gated on that right, but its endpoint is reachable on its own.
     *
     * @return array
     */
    private static function getTreeVisibilityCriteria()
    {
        $table = self::getTable();

        // Kept as an AND list rather than merged: getEntitiesRestrictCriteria() returns
        // an 'OR' key of its own when the entity is recursive, which would collide with
        // the ownership clause below.
        $criteria = [
            'AND' => [
                ["$table.is_deleted" => 0],
                ["$table.is_template" => 0],
                getEntitiesRestrictCriteria($table, '', '', true),
            ],
        ];

        if (!Session::haveRight('plugin_resources_all', READ)) {
            $who = Session::getLoginUserID();
            $criteria['AND'][] = [
                'OR' => [
                    "$table.users_id_recipient" => $who,
                    "$table.users_id"           => $who,
                ],
            ];
        }

        return $criteria;
    }

    /**
     * URL of the resource list filtered on a contract type.
     *
     * Search option 37 is a dropdown on glpi_plugin_resources_contracttypes, so the
     * "equals" search type matches on the identifier: no name is interpolated into
     * the URL any more.
     *
     * @param int $contracttypes_id
     *
     * @return string
     */
    private static function getTreeSearchUrl($contracttypes_id)
    {
        return PLUGIN_RESOURCES_WEBDIR . '/front/resource.php?' . http_build_query([
            'criteria' => [
                [
                    'field'      => 37,
                    'searchtype' => 'equals',
                    'value'      => (int) $contracttypes_id,
                ],
            ],
            'start' => 0,
        ]);
    }

    /**
     * Build the fancytree nodes of the contract type browser.
     *
     * The root level lists the contract types holding at least one visible resource;
     * expanding one lazy-loads its resources. Leaf nodes carry their target URL in
     * data.url, which the script opens on activation, so no event handler is built
     * server side any more.
     *
     * @param string $node fancytree key of the node being expanded, '-1' for the root
     *
     * @return array
     */
    public static function getTreeNodes($node)
    {
        if ((string) $node === '-1') {
            return self::getContractTypeTreeNodes();
        }

        // Child keys are built below as "contracttype-<id>"; nothing else has children.
        if (preg_match('/^contracttype-(\d+)$/', (string) $node, $matches) !== 1) {
            return [];
        }

        return self::getResourceTreeNodes((int) $matches[1]);
    }

    /**
     * Root level of the tree: the contract types holding at least one visible resource.
     *
     * Contract types carry no URL on purpose: activating one only unfolds it. The
     * filtered list is reachable from the "Show all" child node built below.
     *
     * @return array
     */
    private static function getContractTypeTreeNodes()
    {
        global $DB;

        $table = self::getTable();
        $types = ContractType::getTable();

        $iterator = $DB->request([
            'SELECT'     => [
                "$types.id AS id",
                "$types.name AS name",
                QueryFunction::count("$table.id", false, 'nb'),
            ],
            'FROM'       => $types,
            'INNER JOIN' => [
                $table => [
                    'FKEY' => [
                        $types => 'id',
                        $table => 'plugin_resources_contracttypes_id',
                    ],
                ],
            ],
            'WHERE'      => self::getTreeVisibilityCriteria(),
            'GROUPBY'    => ["$types.id", "$types.name"],
            'ORDER'      => "$types.name",
        ]);

        $nodes = [];
        foreach ($iterator as $contract) {
            $nodes[] = [
                'key'     => 'contracttype-' . $contract['id'],
                'title'   => sprintf(__('%1$s (%2$s)'), $contract['name'], $contract['nb']),
                'folder'  => true,
                'lazy'    => true,
                'tooltip' => ContractType::getTypeName(1) . ' - ' . $contract['name'],
            ];
        }

        return $nodes;
    }

    /**
     * Second level of the tree: the resources of a contract type.
     *
     * The listing is capped by TREE_CHILDREN_LIMIT; the leading "Show all" node links
     * to the filtered search list, which is both the way to reach the whole set and
     * the way to reach the resources the cap left out.
     *
     * @param int $contracttypes_id
     *
     * @return array
     */
    private static function getResourceTreeNodes($contracttypes_id)
    {
        global $DB;

        $table = self::getTable();

        $criteria = self::getTreeVisibilityCriteria();
        $criteria['AND'][] = ["$table.plugin_resources_contracttypes_id" => $contracttypes_id];

        $total = countElementsInTable($table, $criteria);

        $nodes = [
            [
                'key'   => 'contracttype-' . $contracttypes_id . '-all',
                'title' => sprintf(__('%1$s (%2$s)'), __('Show all'), $total),
                'icon'  => 'ti ti-list-search',
                'data'  => ['url' => self::getTreeSearchUrl($contracttypes_id)],
            ],
        ];

        $iterator = $DB->request([
            'SELECT' => ["$table.id AS id", "$table.name AS name", "$table.firstname AS firstname"],
            'FROM'   => $table,
            'WHERE'  => $criteria,
            'ORDER'  => ["$table.name", "$table.firstname"],
            'LIMIT'  => self::TREE_CHILDREN_LIMIT,
        ]);

        foreach ($iterator as $resource) {
            $nodes[] = [
                'key'   => 'resource-' . $resource['id'],
                'title' => trim($resource['name'] . ' ' . $resource['firstname']),
                'icon'  => 'ti ti-user',
                'data'  => [
                    'url' => PLUGIN_RESOURCES_WEBDIR . '/front/resource.form.php?id=' . $resource['id'],
                ],
            ];
        }

        return $nodes;
    }

    /**
     * Show the contract type tree used to filter the resource list.
     *
     * Rendered inside the iframe of a modal, in a page emitted by Html::popHeader():
     * the GLPI stylesheet and the core bundles are already there, only the tree own
     * assets are pulled here.
     *
     * @param string $target front page the "Show all" link points back to
     *
     * @return void
     */
    public static function showSelector($target)
    {
        Plugin::loadLang('resources');

        // The page goes through Html::popHeader(), which already brings the whole GLPI
        // stylesheet and the core bundles carrying fancytree: only the tree own assets
        // are left to pull.
        $assets = Html::css(PLUGIN_RESOURCES_WEBDIR . "/css/resourcetree.css", [], false)
            . Html::script(PLUGIN_RESOURCES_WEBDIR . "/scripts/resourcetree.js", ['type' => 'module'], false);

        TemplateRenderer::getInstance()->display('@resources/resource_tree.html.twig', [
            'assets'         => $assets,
            'rand'           => mt_rand(),
            'target'         => $target,
            'root_doc'       => PLUGIN_RESOURCES_WEBDIR,
            'no_data_text'   => __('No item found'),
            'search_label'   => __('Search'),
        ]);
    }

    /**
     * @param $items
     *
     * @return bool
     */
    public function sendEmail($items)
    {
        $users = [];
        foreach ($items as $key => $val) {
            // The ids come straight from the massive-action form: a resource the session
            // cannot read must not disclose the mail addresses of the users behind it. The
            // users are then looked up with the id checked, so a key that is not a plain id
            // is skipped rather than cast.
            try {
                $resources_id = self::getPostedId($key);
            } catch (BadRequestHttpException) {
                continue;
            }
            if ($resources_id <= 0 || !$this->can($resources_id, READ)) {
                continue;
            }
            $restrict = [
                "itemtype" => 'User',
                "plugin_resources_resources_id" => $this->getID(),
            ];
            $dbu = new DbUtils();
            $resources = $dbu->getAllDataFromTable("glpi_plugin_resources_resources_items", $restrict);

            if (!empty($resources)) {
                foreach ($resources as $resource) {
                    $users[] = $resource["items_id"];
                }
            }
        }

        $User = new \User();
        $mail = "";
        $first = true;
        foreach ($users as $key => $val) {
            if ($User->getFromDB($val)) {
                $email = $User->getDefaultEmail();
                if (!empty($email)) {
                    if (!$first) {
                        $mail .= ";";
                    } else {
                        $first = false;
                    }
                    $mail .= $email;
                }
            }
        }

        // addMessageAfterRedirect() is rendered with |raw by the core toast template, and
        // the address list is built from user-editable fields: escape it here, and quote the
        // attribute so a stray apostrophe cannot break out of it either.
        $send = '<a href="mailto:' . htmlspecialchars($mail, ENT_QUOTES, 'UTF-8') . '">'
            . __('Click here to send your email', 'resources') . '</a>';
        Session::addMessageAfterRedirect($send);

        return true;
    }

    /**
     * Send a file (not a document) to the navigator
     * See Document->send();
     *
     * @param $file string: storage filename
     * @param $filename string: file title
     *
     * @return Response
     **/
    public static function sendFile($file, $filename): Response
    {
        // $filename comes from a file name built out of the employee identity, so it is
        // not a safe literal: it used to be interpolated into a hand-written
        // Content-disposition header. The core helper does the DOC_DIR check, the
        // encoding of the file name and the inline/attachment decision on the MIME type.
        // The response is returned, not sent: Response::send() closes the output buffer
        // opened by LegacyFileLoadController, which logs "output buffer has been
        // unexpectedly closed" and "Unexpected output detected" on every picture.
        return Toolbox::getFileAsResponse($file, $filename);
    }

    /**
     * Permet l'affichage dynamique d'une liste déroulante imbriquee
     *
     * @static
     *
     * @param string $itemtype
     * @param array  $options
     */
    public static function showGenericDropdown($itemtype, $options = [])
    {

        if (isset($options['name'])) {
            $options['entity'] = $_SESSION['glpiactive_entity'];
            $options['addicon'] = true;
            // The AJAX endpoints read the rand back to build the ids of the nested dropdown.
            $options['rand'] ??= mt_rand();

            // Reload the nested span when the value changes, if needed
            if (isset($options['action']) && isset($options['span'])) {
                $params = $options;
                unset($params[$options['name']]);
                $params['entity_restrict'] = $_SESSION['glpiactive_entity'];
                $options['toupdate'] = [
                    'value_fieldname' => $options['name'],
                    'to_update'       => $options['span'],
                    'url'             => $options['action'],
                    'moreparams'      => $params,
                ];
            }
            Dropdown::show($itemtype, $options);
        }
    }

    /**
     * Build the Profession/Rank pair shared by the Budget, Cost and Employment forms.
     *
     * The Profession dropdown wires an AJAX refresh of the rank span through
     * showGenericDropdown(), and Rank is displayed read-only because it follows the
     * selected profession. Neither fits a plain fields.dropdownField macro, so both
     * are returned as ready-to-render HTML for fields.htmlField().
     *
     * @param array $fields  Item fields, holding plugin_resources_professions_id,
     *                       plugin_resources_ranks_id and entities_id
     * @param bool  $sort    Whether the profession dropdown is sorted
     *
     * @return array{profession_dropdown: string, rank_label: string}
     */
    public static function getProfessionRankFields(array $fields, bool $sort = true): array
    {
        ob_start();
        self::showGenericDropdown(Profession::class, [
            'name'   => 'plugin_resources_professions_id',
            'value'  => $fields['plugin_resources_professions_id'],
            'entity' => $fields['entities_id'],
            'action' => PLUGIN_RESOURCES_WEBDIR . "/ajax/dropdownRank.php",
            'span'   => 'span_rank',
            'sort'   => $sort,
        ]);
        $profession_dropdown = (string) ob_get_clean();

        if ($fields['plugin_resources_ranks_id'] > 0) {
            $rank_label = Dropdown::getDropdownName(
                'glpi_plugin_resources_ranks',
                $fields['plugin_resources_ranks_id'],
            );
        } else {
            $rank_label = __('None');
        }

        return [
            'profession_dropdown' => $profession_dropdown,
            // The label alone, never markup. Returned as a fragment it reached
            // fields.htmlField(), whose macro renders with |raw, so a rank named with a tag
            // executed in the browser of anyone opening a budget, a cost or an employment. The
            // three templates now build the span themselves, where Twig escapes on output.
            'rank_label'          => $rank_label,
        ];
    }

    /**
     * Display information on treeview plugin
     *
     * @params itemtype, id, pic, url, name
     *
     * @return params
     **/
    public static function showResourceTreeview($params)
    {
        global $CFG_GLPI;

        if ($params['itemtype'] == Resource::class) {
            $params['pic'] = "../resources/pics/miniresources.png";

            $item = new $params['itemtype']();
            if ($item->getFromDB($params['id'])) {
                $params['name'] = self::getResourceName($params['id']);

                if (isset($item->fields["picture"])) {
                    $params['pic'] = PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $item->fields["picture"];
                }
            }
        }
        return $params;
    }

    /**
     * @param $input
     *
     * @return bool
     */
    public function checkTransferMandatoryFields($input)
    {
        $msg = [];
        $checkKo = false;

        $mandatory_fields = ['entities_id' => __('Entity'), 'plugin_resources_resources_id' => self::getTypeName(1)];

        foreach ($input as $key => $value) {
            if (array_key_exists($key, $mandatory_fields)) {
                if (empty($value)) {
                    $msg[] = $mandatory_fields[$key];
                    $checkKo = true;
                }
            }
        }

        if ($checkKo) {
            Session::addMessageAfterRedirect(
                sprintf(__("Mandatory fields are not filled. Please correct: %s"), implode(', ', $msg)),
                false,
                ERROR,
            );
            return false;
        }
        return true;
    }

    /**
     * Get picture URL from picture field
     *
     * @param $picture picture field
     *
     * @return string URL to show picture
     **@since version 0.85
     *
     */
    public static function getThumbnailURLForPicture($picture)
    {
        global $CFG_GLPI;

        if (!empty($picture)) {
            $tmp = explode(".", $picture);
            if (count($tmp) == 2) {
                return PLUGIN_RESOURCES_WEBDIR . "/front/picture.send.php?file=" . $tmp[0] . '.' . $tmp[1];
            }
            return PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png";
        }
        return PLUGIN_RESOURCES_WEBDIR . "/pics/nobody.png";
    }

    /**
     * List, in the client tab, the resources whose employee record points at that client.
     *
     * @param int $client_id
     *
     * @return void
     */
    public function showListResourcesForClient($client_id)
    {
        global $DB;

        $dbu = new DbUtils();

        // The join is what filters on the client, so it must be an inner one. The entity
        // restriction is ours to add: the client tab does not scope the linked resources.
        $iterator = $DB->request([
            'SELECT'     => [self::getTable() . '.*'],
            'FROM'       => self::getTable(),
            'INNER JOIN' => [
                Employee::getTable() => [
                    'ON' => [
                        Employee::getTable() => 'plugin_resources_resources_id',
                        self::getTable()     => 'id',
                    ],
                ],
            ],
            'WHERE'      => [
                self::getTable() . '.is_deleted'                      => 0,
                Employee::getTable() . '.plugin_resources_clients_id' => (int) $client_id,
            ] + $dbu->getEntitiesRestrictCriteria(self::getTable(), '', '', $this->maybeRecursive()),
            'ORDER'      => self::getTable() . '.name',
        ]);

        $resource = new self();
        $entries  = [];
        foreach ($iterator as $data) {
            $resource->getFromResultSet($data);
            $entries[] = [
                'name'       => $resource->getLink(),
                'firstname'  => $data['firstname'],
                'state'      => Dropdown::getDropdownName(
                    ResourceState::getTable(),
                    $data['plugin_resources_resourcestates_id'],
                ),
                'location'   => Dropdown::getDropdownName('glpi_locations', $data['locations_id']),
                'department' => Dropdown::getDropdownName(
                    Department::getTable(),
                    $data['plugin_resources_departments_id'],
                ),
            ];
        }

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'is_tab'          => true,
            'nofilter'        => true,
            'nosort'          => true,
            'super_header'    => __('Resources list', 'resources'),
            'columns'         => [
                'name'       => __('Surname'),
                'firstname'  => __('First name'),
                'state'      => ResourceState::getTypeName(1),
                'location'   => __('Location'),
                'department' => Department::getTypeName(1),
            ],
            // getLink() returns an anchor, everything else is plain text Twig escapes.
            'formatters'      => ['name' => 'raw_html'],
            'entries'         => $entries,
            'total_number'    => count($entries),
            'filtered_number' => count($entries),
        ]);
    }

    /**
     * Each identifiers must be formatted as follow:
     * - name
     * - value
     * - type
     * - resource_column
     *
     * @param array $identifiers
     *
     * @return int|null
     */
    public function isExistingResourceByIdentifier($identifiers = [])
    {
        global $DB;

        $tableResourceCriterias = [];
        $tableResourceImportCriterias = [];

        foreach ($identifiers as $identifier) {
            if (is_string($identifier['value']) && empty($identifier['value'])) {
                $identifier['value'] = null;
            }

            switch ($identifier['resource_column']) {
                case 10:
                    $tableResourceImportCriterias[] = [
                        'name' => $identifier['name'],
                        'value' => $identifier['value'],
                        'type' => $identifier['type'],
                    ];
                    break;
                default:
                    $tableResourceCriterias[] = [
                        'name' => $this->getColumnName($identifier['resource_column']),
                        'value' => $identifier['value'],
                        'type' => $identifier['type'],
                    ];
                    break;
            }
        }

        $criteria = [
            'SELECT' => 'r.*',
            'FROM'   => self::getTable() . ' AS r',
        ];
        $where = [];

        if (count($tableResourceImportCriterias) > 0) {
            $criteria['INNER JOIN'] = [
                ResourceImport::getTable() . ' AS ri' => [
                    'ON' => [
                        'ri' => 'plugin_resources_resources_id',
                        'r'  => 'id',
                    ],
                ],
            ];

            foreach ($tableResourceImportCriterias as $tableResourceImportCriteria) {
                $where[] = [
                    'ri.name'  => $tableResourceImportCriteria['name'],
                    'ri.value' => $tableResourceImportCriteria['value'],
                ];
            }
        }

        if (count($tableResourceCriterias) > 0) {
            foreach ($tableResourceCriterias as $tableResourceCriteria) {
                $where[] = ['r.' . $tableResourceCriteria['name'] => $tableResourceCriteria['value']];
            }
        }

        $criteria['WHERE'] = $where;

        $iterator = $DB->request($criteria);

        foreach ($iterator as $data) {
            return $data['id'];
        }
        return null;
    }

    /**
     * Test if a resource exist in database by testing (1st and 2nd level) identifiers of importResource
     *
     * @param int $importResourceID
     *
     * @return int|false
     */
    public function isExistingResourceByImportResourceID($importResourceID)
    {
        $pluginResourcesImportResourceData = new ImportResourceData();

        // First level identifier
        $firstLevelIdentifiers = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel(
            $importResourceID,
            1,
        );

        $resourceID = $this->isExistingResourceByIdentifier($firstLevelIdentifiers);

        if (!is_null($resourceID)) {
            return $resourceID;
        }

        // Second level identifier
        $secondLevelIdentifiers = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel(
            $importResourceID,
            2,
        );

        $resourceID = $this->isExistingResourceByIdentifier($secondLevelIdentifiers);

        if (!is_null($resourceID)) {
            return $resourceID;
        }

        return false;
    }

    /**
     * Test if datas from csv file are different to resource field and resourceimports
     *
     * @param $resourceID
     * @param $datas
     *
     * @return bool
     */
    public function isDifferentFromImportResourceDatas($resourceID, $datas)
    {
        foreach ($datas as $data) {
            if (self::isDifferentFromImportResourceData($resourceID, $data)) {
                return true;
            }
        }
        return false;
    }

    public function isDifferentFromImportResourceData($resourceID, $data)
    {
        $result = self::hasDifferenciesWithValueByDataNameID(
            $resourceID,
            $data['resource_column'],
            $data['name'],
            $data['value'],
        );

        return $result;
    }

    /**
     * Test if resource and importresources are differents
     *
     * @param $resourceID
     * @param $importResourceID
     *
     * @return bool
     */
    public function isDifferentFromImportResource($resourceID, $importResourceID)
    {
        $pluginResourcesResource = new self();
        if (!$pluginResourcesResource->getFromDB($resourceID)) {
            throw new BadRequestHttpException("No resource for id " . $resourceID);
        }

        $pluginResourcesImportResource = new ImportResource();
        if (!$pluginResourcesImportResource->getFromDB($importResourceID)) {
            throw new BadRequestHttpException("No importResource for id " . $importResourceID);
        }

        $pluginResourcesImportResourceData = new ImportResourceData();

        // Get all import data
        $datas = $pluginResourcesImportResourceData->getFromParentAndIdentifierLevel(
            $importResourceID,
            null,
            ['resource_column'],
        );

        return $this->isDifferentFromImportResourceDatas($resourceID, $datas);
    }

    /**
     * Get resourceimports value by name
     *
     * @param $resourceID
     * @param $name
     *
     * @return mixed|string
     */
    public function getResourceImportValueByName($resourceID, $name)
    {
        $pluginResourcesResource = new self();
        $pluginResourcesResource->getFromDB($resourceID);

        $pluginResourcesResourceImport = new ResourceImport();

        $crit = [
            ResourceImport::$items_id => $pluginResourcesResource->getID(),
            'name' => $name,
        ];

        if (!$pluginResourcesResourceImport->getFromDBByCrit($crit)) {
            return "";
        }

        return $pluginResourcesResourceImport->getField('value');
    }

    /**
     * Get resource field matching dataNameID
     *
     * @param $dataNameID
     *
     * @return mixed|null
     */
    public function getFieldByDataNameID($dataNameID)
    {
        if (is_null($dataNameID)) {
            return null;
        }

        $resourceFieldName = $this->getResourceColumnNameFromDataNameID($dataNameID);

        return $this->getField($resourceFieldName);
    }

    /**
     * Test if value in resource fields or resourceimports have differences
     * with passed pair of name and value
     *
     * @param $resourceID
     * @param $dataNameID
     * @param $name
     * @param $value
     *
     * @return bool
     */
    public function hasDifferenciesWithValueByDataNameID($resourceID, $dataNameID, $name, $value)
    {
        $pluginResourcesResource = new self();
        if (!$pluginResourcesResource->getFromDB($resourceID)) {
            throw new BadRequestHttpException("No resource for id " . $resourceID);
        }

        switch ($dataNameID) {
            case 10:
                // Find in Resource Import
                $pluginResourcesResourceImport = new ResourceImport();

                $crit = [
                    ResourceImport::$items_id => $resourceID,
                    'name' => $name,
                ];

                // Resource doesn't have the data
                if (!$pluginResourcesResourceImport->getFromDBByCrit($crit)) {
                    return true;
                }

                // Data are different
                if ($pluginResourcesResourceImport->getField('value') != $value) {
                    return true;
                }
                break;
            default:
                // Find in Resource Fields
                $resourceFieldName = $pluginResourcesResource->getResourceColumnNameFromDataNameID($dataNameID);
                $resourceValue = $pluginResourcesResource->getField($resourceFieldName);

                // When firstname and lastname
                if ($dataNameID == 0 || $dataNameID == 1) {
                    $result = strcasecmp($resourceValue, $value) == 0;

                    return !$result;
                } else {
                    return $resourceValue != $value;
                }

                break;
        }
        return false;
    }

    public static function getGenders()
    {
        return [
            Dropdown::EMPTY_VALUE,
            __('Male', 'resources'),
            __('Female', 'resources'),
            __('Other', 'resources'),
        ];
    }

    public static function getGenderByValue($value)
    {
        switch ($value) {
            case 1 :
                return __('Male', 'resources');
                break;
            case 2 :
                return __('Female', 'resources');
                break;
            case 3 :
                return __('Other', 'resources');
                break;
            default:
                return '';
        }
    }

    /**
     * @param $field
     * @param $name (DEFAULT '')
     * @param $values (DEFAULT '')
     * @param $options   array
     *
     * @return string
     **@since version 0.84
     *
     */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        switch ($field) {
            case 'gender':
                return Dropdown::showFromArray($name, self::getGenders(), ['display' => false]);
                break;
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * display a value according to a field
     *
     * @param $field     String         name of the field
     * @param $values    String / Array with the value to display
     * @param $options   Array          of option
     *
     * @return a string
     **@since version 0.83
     *
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'gender':
                if (empty($values[$field])) {
                    $values[$field] = 0;
                }
                $gender = self::getGenders();
                return $gender[$values[$field]];
                //            break;
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    //   public function getCloneRelations(): array {
    //      return [
    ////         Task::class,
    ////         Employee::class,
    ////         ReportConfig::class,
    ////         Resource_Item::class,
    ////         ResourceHabilitation::class,
    ////         Choice::class,
    //         Document_Item::class,
    //         Notepad::class
    //      ];
    //   }

    public static function jsGetElementbyID($id)
    {
        return "$('#$id')";
    }

    public static function joinDropdownTranslations($alias, $table, $itemtype, $field)
    {
        global $DB;
        return "LEFT JOIN " . $DB::quoteName('glpi_dropdowntranslations') . " AS " . $DB::quoteName($alias) . "
                  ON (" . $DB::quoteName($alias . '.itemtype') . " = " . $DB->quote($itemtype) . "
                    AND " . $DB::quoteName($alias . '.items_id') . " = " . $DB::quoteName($table . '.id') . "
                    AND " . $DB::quoteName($alias . '.language') . " = " . $DB->quote($_SESSION['glpilanguage']) . "
                    AND " . $DB::quoteName($alias . '.field') . " = " . $DB->quote($field) . ")";
    }

    public static function supportHelpdeskDisplayPreferences(): bool
    {
        return true;
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
                        `id`                                        int {$default_key_sign} NOT NULL auto_increment,
                        `entities_id`                               int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `is_recursive`                              tinyint        NOT NULL                 DEFAULT '0',
                        `name`                                      varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `firstname`                                 varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `plugin_resources_contracttypes_id`         int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_contracttypes (id)',
                        `users_id`                                  int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_users (id)',
                        `users_id_sales`                            int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_users (id)',
                        `users_id_recipient`                        int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_users (id)',
                        `date_declaration`                          timestamp      NULL                     DEFAULT NULL,
                        `date_begin`                                timestamp      NULL                     DEFAULT NULL,
                        `date_end`                                  timestamp      NULL                     DEFAULT NULL,
                        `quota`                                     decimal(10, 4) NOT NULL                 DEFAULT '1.0000',
                        `plugin_resources_departments_id`           int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_departments (id)',
                        `plugin_resources_resourcestates_id`        int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_resourcestates (id)',
                        `plugin_resources_resourcesituations_id`    int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_contractnatures_id`       int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_ranks_id`                 int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_resourcespecialities_id`  int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `locations_id`                              int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_locations (id)',
                        `is_leaving`                                int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_workprofiles_id`          int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_leavingreasons_id`        int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `date_declaration_leaving`                  timestamp      NULL                     DEFAULT NULL,
                        `date_agreement_candidate`                  timestamp      NULL                     DEFAULT NULL,
                        `date_of_last_contract_type`                timestamp      NULL                     DEFAULT NULL,
                        `date_of_last_location`                     timestamp      NULL                     DEFAULT NULL,
                        `users_id_recipient_leaving`                int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_users (id)',
                        `picture`                                   varchar(100) COLLATE utf8mb4_unicode_ci default NULL,
                        `is_helpdesk_visible`                       int {$default_key_sign}   NOT NULL                 DEFAULT '1',
                        `date_mod`                                  timestamp      NULL                     DEFAULT NULL,
                        `comment`                                   TEXT COLLATE utf8mb4_unicode_ci,
                        `is_template`                               tinyint        NOT NULL                 DEFAULT '0',
                        `template_name`                             varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `is_deleted`                                tinyint        NOT NULL                 DEFAULT '0',
                        `sensitize_security`                        tinyint        NOT NULL                 DEFAULT '0',
                        `read_chart`                                tinyint        NOT NULL                 DEFAULT '0',
                        `contract_type_change`                      tinyint        NOT NULL                 DEFAULT '0',
                        `reconversion`                              tinyint        NOT NULL                 DEFAULT '0',
                        `plugin_resources_roles_id`                 int {$default_key_sign}   NOT NULL                 DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_roles (id)',
                        `matricule`                                 varchar(255)   NOT NULL                 DEFAULT '',
                        `plugin_resources_functions_id`             int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_teams_id`                 int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_services_id`              int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_degreegroups_id`          int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_recruitingsources_id`     int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `last_contract_type`                        int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `last_location`                             int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `yearsexperience`                           int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_candidateorigins_id`      int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `plugin_resources_workprofiles_id_entrance` int {$default_key_sign}   NOT NULL                 DEFAULT '0',
                        `matricule_second`                          varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `secondary_services`                        varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `gender`                                    varchar(3) COLLATE utf8mb4_unicode_ci   DEFAULT NULL,
                        `phone`                                     varchar(20) COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
                        `cellphone`                                 varchar(20) COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
                        `remove_manager`                            int {$default_key_sign} NOT NULL DEFAULT '0',
                        `remove_order`                              TEXT COLLATE utf8mb4_unicode_ci,
                        `computer_phone_equipment`                  TEXT COLLATE utf8mb4_unicode_ci,
                        `softwares_requirements`                    TEXT COLLATE utf8mb4_unicode_ci,
                        `furnitures_needs`                          TEXT COLLATE utf8mb4_unicode_ci,
                        `other_needs`                               TEXT COLLATE utf8mb4_unicode_ci,
                        `valid_resource_information`                tinyint NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`),
                        KEY `name` (`name`),
                        KEY `entities_id` (`entities_id`),
                        KEY `is_recursive` (`is_recursive`),
                        KEY `users_id` (`users_id`),
                        KEY `users_id_sales` (`users_id_sales`),
                        KEY `users_id_recipient` (`users_id_recipient`),
                        KEY `locations_id` (`locations_id`),
                        KEY `is_leaving` (`is_leaving`),
                        KEY `users_id_recipient_leaving` (`users_id_recipient_leaving`),
                        KEY `date_mod` (`date_mod`),
                        KEY `is_helpdesk_visible` (`is_helpdesk_visible`),
                        KEY `is_deleted` (`is_deleted`),
                        KEY `is_template` (`is_template`),
                        KEY `plugin_resources_contracttypes_id` (`plugin_resources_contracttypes_id`),
                        KEY `plugin_resources_departments_id` (`plugin_resources_departments_id`),
                        KEY `plugin_resources_resourcestates_id` (`plugin_resources_resourcestates_id`),
                        KEY `plugin_resources_resourcesituations_id` (`plugin_resources_resourcesituations_id`),
                        KEY `plugin_resources_contractnatures_id` (`plugin_resources_contractnatures_id`),
                        KEY `plugin_resources_ranks_id` (`plugin_resources_ranks_id`),
                        KEY `plugin_resources_resourcespecialities_id` (`plugin_resources_resourcespecialities_id`),
                        KEY `plugin_resources_leavingreasons_id` (`plugin_resources_leavingreasons_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 2,
                    'rank' => 1,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 3,
                    'rank' => 2,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 4,
                    'rank' => 3,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 5,
                    'rank' => 4,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 6,
                    'rank' => 5,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

            $DB->insert(
                'glpi_displaypreferences',
                ['itemtype' => self::class,
                    'num' => 8,
                    'rank' => 6,
                    'users_id' => 0,
                    'interface' => 'central'],
            );

        }
    }
}
