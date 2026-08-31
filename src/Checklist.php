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
use CommonDBTM;
use CommonGLPI;
use DBConnection;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Entity;
use Html;
use Log;
use MassiveAction;
use Migration;
use NotificationEvent;
use Plugin;
use PluginPdfSimplePDF;
use RuleTicketCollection;
use Session;
use Ticket;
use TicketTemplate;
use TicketTemplatePredefinedField;
use Toolbox;
use User;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Checklist
 */
class Checklist extends CommonDBTM
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
        return _n('Checklist', 'Checklists', $nb, 'resources');
    }

    public static function getIcon()
    {
        return "ti ti-checkbox";
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
            if ($item->getID() && $this->canView()) {
                if ($_SESSION['glpishow_count_on_tabs']) {
                    return self::createTabEntry(self::getTypeName(2), self::countForItem($item));
                }
                return self::createTabEntry(self::getTypeName(2));
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
        if (self::checkifChecklistExist($ID, 0)) {
            $checklist = new self();
            if ($checklist->canCreate()) {
                self::showFromResources($ID, self::RESOURCES_CHECKLIST_IN, $withtemplate);
            }
            self::showFromResources($ID, self::RESOURCES_CHECKLIST_OUT, $withtemplate);
            if ($checklist->canCreate()) {
                self::showFromResources($ID, self::RESOURCES_CHECKLIST_TRANSFER, $withtemplate);
            }
        } else {
            self::showAddForm($ID);
        }
        return true;
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
     * @param $ID
     *
     * @param $type_checklist
     *
     * @return bool
     */
    public static function checkifChecklistExist($ID, $type_checklist)
    {
        $restrict = ["plugin_resources_resources_id" => $ID];
        if ($type_checklist > 0) {
            $restrict[] = ["checklist_type" => $type_checklist];
        }
        $dbu = new DbUtils();
        $checklists = $dbu->getAllDataFromTable("glpi_plugin_resources_checklists", $restrict);

        if (!empty($checklists)) {
            foreach ($checklists as $checklist) {
                return $checklist["id"];
            }
        } else {
            return false;
        }
    }

    /**
     * @param $input
     *
     * @return bool
     */
    public static function checkifChecklistFinished($input)
    {
        if (isset($input['plugin_resources_resources_id'])
            && isset($input['checklist_type'])) {
            $restrict = [
                "plugin_resources_resources_id" => $input['plugin_resources_resources_id'],
                "checklist_type" => $input['checklist_type'],
            ];
            $dbu = new DbUtils();
            $checklists = $dbu->getAllDataFromTable("glpi_plugin_resources_checklists", $restrict);

            $nok = 0;
            if (!empty($checklists)) {
                foreach ($checklists as $checklist) {
                    if ($checklist["is_checked"] < 1) {
                        $nok += 1;
                    }
                }
                if ($nok > 0) {
                    return false;
                } else {
                    return true;
                }
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * @param $input
     *
     * @return bool
     */
    public function openFinishedChecklist($input)
    {
        $restrict = [
            "plugin_resources_resources_id" => $input['plugin_resources_resources_id'],
            "checklist_type" => $input['checklist_type'],
        ];
        $dbu = new DbUtils();
        $checklists = $dbu->getAllDataFromTable("glpi_plugin_resources_checklists", $restrict);

        if (!empty($checklists)) {
            foreach ($checklists as $checklist) {
                $this->update([
                    "id" => $checklist["id"],
                    "is_checked" => 0,
                ]);
            }
        } else {
            return false;
        }
    }

    /**
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
            // Set date to creation date
            $createtime = date('Y-m-d H:i:s');
            $input['date'] = $createtime;
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
            $input['actiontime'] = $data['actiontime'];
            $res = new Resource();

            $default_use_notif = Entity::getUsedConfig('is_notif_enable_default', $input['entities_id'], '', 1);

            if ($res->getFromDB($data['plugin_resources_resources_id'])) {
                $input['users_id_recipient'] = $res->fields['users_id_recipient'];
                $input['_users_id_requester'] = [$res->fields['users_id_recipient']];
                $input['_users_id_requester_notif']['use_notification'] = [$default_use_notif];
                $alternativeEmail = '';
                if (filter_var(Session::getLoginUserID(), FILTER_VALIDATE_EMAIL) !== false) {
                    $alternativeEmail = Session::getLoginUserID();
                }
                $input['_users_id_requester_notif']['alternative_email'] = [$alternativeEmail];

                if (isset($res->fields['users_id'])) {
                    $input['_users_id_observer'] = $res->fields['users_id'];
                    $input['_users_id_observer_notif'] = [];
                }

                if (isset($data['users_id'])) {
                    $input['_users_id_assign'] = $data['users_id'];
                } else {
                    $input['_users_id_assign'] = Session::getLoginUserID();
                }

                $input["items_id"] = [Resource::class => [$data['plugin_resources_resources_id']]];
                $input["name"] .= addslashes(" " . Resource::getResourceName($data['plugin_resources_resources_id']));
            }

            //TODO : ADD checklist lists or add config into plugin ?
            $input["content"] .= addslashes("\n\n");
            $input['status'] = Ticket::CLOSED;
            $input['id'] = 0;
            $ticket = new Ticket();

            if ($tid = $ticket->add($input)) {
                $msg = __('Create a end treatment ticket', 'resources') . " OK - ($tid)"; // Success
                $result = true;
            } else {
                $msg = __('Failed operation'); // Failure
            }
        } else {
            $msg = __('No selected element or badly defined operation'); // Not defined
        }
        if ($tid) {
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

    /**
     * @param     $name
     * @param int $value
     *
     * @return bool|int|string
     */
    public function dropdownChecklistType($name, $value = 0)
    {
        $checklists = [
            self::RESOURCES_CHECKLIST_IN => __('At the arriving of a resource', 'resources'),
            self::RESOURCES_CHECKLIST_OUT => __('At the leaving of a resource', 'resources'),
            self::RESOURCES_CHECKLIST_TRANSFER => __('At the transfer of a resource', 'resources'),
        ];

        if (!empty($checklists)) {
            return Dropdown::showFromArray($name, $checklists, ['value' => $value]);
        } else {
            return false;
        }
    }

    /**
     * @param $value
     *
     * @return string
     */
    public static function getChecklistType($value)
    {
        switch ($value) {
            case self::RESOURCES_CHECKLIST_IN:
                return __('At the arriving of a resource', 'resources');
            case self::RESOURCES_CHECKLIST_OUT:
                return __('At the leaving of a resource', 'resources');
            case self::RESOURCES_CHECKLIST_TRANSFER:
                return __('At the transfer of a resource', 'resources');
            default:
                return "";
        }
    }

    /**
     * Prepare input datas for adding the item
     *
     * @param array $input datas used to add the item
     *
     * @return array the modified $input array
     **/
    public function prepareInputForAdd($input)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => new QueryExpression(
                'MAX(' . $DB->quoteName('rank') . ') AS ' . $DB->quoteName('maxrank'),
            ),
            'FROM'   => $this->getTable(),
            'WHERE'  => [
                'checklist_type'                     => (int) $input['checklist_type'],
                'plugin_resources_contracttypes_id'  => (int) $input['plugin_resources_contracttypes_id'],
                'plugin_resources_resources_id'      => (int) $input['plugin_resources_resources_id'],
                'entities_id'                        => (int) $input['entities_id'],
            ],
        ]);
        $input["rank"] = ((int) $iterator->current()['maxrank']) + 1;

        return $input;
    }

    /**
     * @param $ID
     */
    public static function showAddForm($ID)
    {
        TemplateRenderer::getInstance()->display('@resources/checklist_add_form.html.twig', [
            'form_action' => Toolbox::getItemTypeFormURL(Resource::class),
            'title'       => __('Create checklists', 'resources'),
            'id'          => (int) $ID,
        ]);
    }

    /**
     * Modify checklist's ranking and automatically reorder all checklists
     *
     * @param $ID the checklist ID whose ranking must be modified
     * @param $checklist_type IN or OUT
     * @param $plugin_resources_resources_id the resources ID
     * @param $action up or down
     * */
    public function changeRank($input)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => 'rank',
            'FROM'   => $this->getTable(),
            'WHERE'  => ['id' => (int) $input['id']],
        ]);

        if (count($iterator) == 1) {
            $current_rank = (int) $iterator->current()['rank'];
            // Search rules to switch
            $criteria = [
                'SELECT' => ['id', 'rank'],
                'FROM'   => $this->getTable(),
                'WHERE'  => [
                    'checklist_type'                => (int) $input['checklist_type'],
                    'plugin_resources_resources_id' => (int) $input['plugin_resources_resources_id'],
                ],
                'LIMIT'  => 1,
            ];

            switch ($input['action']) {
                case "up":
                    $criteria['WHERE']['rank'] = ['<', $current_rank];
                    $criteria['ORDER']         = 'rank DESC';
                    break;

                case "down":
                    $criteria['WHERE']['rank'] = ['>', $current_rank];
                    $criteria['ORDER']         = 'rank ASC';
                    break;

                default:
                    return false;
            }

            $iterator2 = $DB->request($criteria);
            if (count($iterator2) == 1) {
                $row      = $iterator2->current();
                $other_ID = $row['id'];
                $new_rank = $row['rank'];

                return ($this->update([
                    'id' => $input['id'],
                    'rank' => $new_rank,
                ]) && $this->update([
                    'id' => $other_ID,
                    'rank' => $current_rank,
                ]));
            }
        }
        return false;
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

        TemplateRenderer::getInstance()->display('@resources/checklist_form.html.twig', [
            'item'             => $this,
            'params'           => $options,
            'resources_id'     => $plugin_resources_resources_id,
            'contracttypes_id' => $ID > 0
                ? $this->fields["plugin_resources_contracttypes_id"]
                : $plugin_resources_contracttypes_id,
            'checklist_type'   => $ID > 0 ? $this->fields["checklist_type"] : $checklist_type,
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
    public static function showFromResources($plugin_resources_resources_id, $checklist_type, $withtemplate = '')
    {
        if (!self::canView()) {
            return false;
        }

        $target = "./resource.form.php";
        $targetchecklist = "./checklist.form.php";
        $targettask = "./task.form.php";

        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);
        $canedit = $resource->can($plugin_resources_resources_id, UPDATE);
        $entities_id = $resource->fields["entities_id"];
        $plugin_resources_contracttypes_id = $resource->fields["plugin_resources_contracttypes_id"];
        $rand = mt_rand();

        // Check type values
        $viewId = '';
        $viewId_finished = '';
        $addLinkName = '';
        switch ($checklist_type) {
            case self::RESOURCES_CHECKLIST_IN:
                $viewId = 'checklist_view_in_mode';
                $viewId_finished = 'checklist_finished_view_in_mode';
                $addLinkName = __('Add a task at the arriving checklist', 'resources');
                break;
            case self::RESOURCES_CHECKLIST_OUT:
                $viewId = 'checklist_view_out_mode';
                $viewId_finished = 'checklist_finished_view_out_mode';
                $addLinkName = __('Add a task at the leaving checklist', 'resources');
                break;
            case self::RESOURCES_CHECKLIST_TRANSFER:
                $viewId = 'checklist_view_transfer_mode';
                $viewId_finished = 'checklist_finished_view_transfer_mode';
                $addLinkName = __('Add a task at the transfer checklist', 'resources');
                break;
        }

        // Is check list finished ?
        $isfinished = self::checkifChecklistFinished([
            "checklist_type" => $checklist_type,
            "plugin_resources_resources_id" => $plugin_resources_resources_id,
            "plugin_resources_contracttypes_id" => $plugin_resources_contracttypes_id,
            "entities_id" => $entities_id,
        ]);

        $title = '';
        if ($isfinished) {
            $title = "<i style='color:green' class='ti ti-circle-check fa-2x'></i>";
        }
        $title .= htmlescape(self::getChecklistType($checklist_type));
        if ($isfinished) {
            $title .= " - " . htmlescape(__('Check list done', 'resources'));
        }

        $can_add = self::canCreate() && $canedit;
        if ($can_add) {
            $js = "function viewAddChecklistTask{$rand}(){\n";
            $js .= Ajax::updateItemJsCode(
                "viewchecklisttask" . $rand,
                PLUGIN_RESOURCES_WEBDIR . "/ajax/viewchecklisttask.php",
                [
                    'type' => self::class,
                    'target' => $targetchecklist,
                    'plugin_resources_contracttypes_id' => $plugin_resources_contracttypes_id,
                    'plugin_resources_resources_id' => $plugin_resources_resources_id,
                    'checklist_type' => $checklist_type,
                    'id' => -1,
                ],
                '',
                false,
            );
            $js .= "};";
            echo Html::scriptBlock($js);
        }

        // Get check list
        $restrict = [
            "entities_id" => $entities_id,
            "plugin_resources_resources_id" => $plugin_resources_resources_id,
            "checklist_type" => $checklist_type,
        ] + ["ORDER" => "rank"];
        $dbu = new DbUtils();
        $checklists = $dbu->getAllDataFromTable("glpi_plugin_resources_checklists", $restrict);
        $numrows = count($checklists);

        $can_massive = !$isfinished
            && self::canCreate()
            && $canedit
            && Session::getCurrentInterface() == "central";
        $show_close_panel = $isfinished && self::canCreate() && $canedit;

        // Capture GLPI helpers that echo directly, so they can be injected as |raw.
        $capture = static function (callable $renderer): string {
            ob_start();
            $renderer();
            return (string) ob_get_clean();
        };

        // The form is only opened when there is something to submit; emit the matching
        // closing part in the same condition so no orphan </form> or CSRF token is left.
        $form_open  = '';
        $form_close = '';
        $check_all  = '';
        if (!empty($checklists)) {
            $massiveactionparams = ['item' => self::class, 'container' => 'masschecklist' . $rand];
            if ($can_massive) {
                $form_open = $capture(static function () use ($rand, $massiveactionparams) {
                    Html::openMassiveActionsForm('masschecklist' . $rand);
                    Html::showMassiveActions($massiveactionparams);
                });
                $form_close = $capture(static function () use ($massiveactionparams) {
                    $params = $massiveactionparams;
                    $params['ontop'] = false;
                    Html::showMassiveActions($params);
                    Html::closeForm();
                });
                $check_all = Html::getCheckAllAsCheckbox('masschecklist' . $rand);
            } elseif ($isfinished) {
                $form_open = "<form name='form' method='post' action='"
                    . htmlescape(Toolbox::getItemTypeFormURL(Resource::class)) . "'>";
                $form_close = $capture(static fn() => Html::closeForm());
            }
        }

        $show_task_column = Session::haveRight("plugin_resources_task", UPDATE) && $canedit;

        Session::initNavigateListItems(
            self::class,
            Resource::getTypeName(1) . " = " . $resource->fields['name'],
        );

        $entries = [];
        $i = 0;
        foreach ($checklists as $checklist) {
            $ID = $checklist["id"];
            Session::addToNavigateListItems(self::class, $ID);

            $name = '<a href="' . htmlescape(
                $targetchecklist . "?id=" . $ID
                . "&plugin_resources_resources_id=" . $plugin_resources_resources_id
                . "&plugin_resources_contracttypes_id=" . $plugin_resources_contracttypes_id
                . "&checklist_type=" . $checklist_type,
            ) . '">' . htmlescape($checklist["name"]) . '</a>&nbsp;';
            if (!empty($checklist["address"])) {
                $name .= '&nbsp;' . $capture(static fn() => Html::showToolTip(
                    $checklist["address"],
                    ['link' => $checklist["address"], 'linktarget' => '_blank'],
                ));
            }

            $task = '';
            if ($show_task_column) {
                $has_task = !empty($checklist["plugin_resources_tasks_id"]);
                if ($has_task) {
                    $task = '<a href="' . htmlescape(
                        $targettask . "?id=" . $checklist["plugin_resources_tasks_id"]
                        . "&plugin_resources_resources_id=" . $plugin_resources_resources_id
                        . "&central=1",
                    ) . '">';
                }
                $task .= htmlescape(Dropdown::getYesNo($checklist["plugin_resources_tasks_id"]));
                if ($has_task) {
                    $task .= '</a>';
                }
            }

            $move_up = '&nbsp;';
            if ($i != 0 && self::canCreate() && $canedit && !$isfinished) {
                $move_up = Html::getSimpleForm($target, 'move', __('Bring up'), [
                    'action' => 'up',
                    'id' => $ID,
                    'plugin_resources_resources_id' => $plugin_resources_resources_id,
                    'checklist_type' => $checklist_type,
                ], 'fa-angle-double-up fa-1x');
            }

            $move_down = '&nbsp;';
            if ($i != $numrows - 1 && self::canCreate() && $canedit && !$isfinished) {
                $move_down = Html::getSimpleForm($target, 'move', __('Bring down'), [
                    'action' => 'down',
                    'id' => $ID,
                    'plugin_resources_resources_id' => $plugin_resources_resources_id,
                    'checklist_type' => $checklist_type,
                ], 'fa-angle-double-down fa-1x');
            }

            $entries[] = [
                'id'               => $ID,
                'massive_checkbox' => $can_massive
                    ? $capture(static fn() => Html::showMassiveActionCheckBox(self::class, $ID))
                    : '',
                'name'             => $name,
                'tag'              => (bool) $checklist["tag"],
                'comment'          => nl2br(htmlescape((string) $checklist["comment"])),
                'task'             => $task,
                'is_checked'       => (bool) $checklist["is_checked"],
                'move_up'          => $move_up,
                'move_down'        => $move_down,
            ];

            $i++;
        }

        TemplateRenderer::getInstance()->display('@resources/checklist_from_resources.html.twig', [
            'rand'                => $rand,
            'view_id'             => $viewId,
            'view_id_finished'    => $viewId_finished,
            'title'               => $title,
            'finished_title'      => self::getTypeName(0),
            'can_add'             => $can_add,
            'add_link_name'       => $addLinkName,
            'is_finished'         => $isfinished,
            'show_close_panel'    => $show_close_panel,
            'show_task_column'    => $show_task_column,
            'resources_id'        => $plugin_resources_resources_id,
            'checklist_type'      => $checklist_type,
            'contracttypes_id'    => $plugin_resources_contracttypes_id,
            'entities_id'         => $entities_id,
            'form_open'           => $form_open,
            'form_close'          => $form_close,
            'check_all'           => $check_all,
            'entries'             => $entries,
            'template_dropdown'   => $show_close_panel ? $capture(static fn() => Dropdown::show(
                'TicketTemplate',
                ['name' => 'tickettemplates_id', 'entities_id' => $entities_id],
            )) : '',
            'user_dropdown'       => $show_close_panel ? $capture(static fn() => User::dropdown(
                ['name' => "users_id", 'right' => 'interface'],
            )) : '',
            'actiontime_dropdown' => $show_close_panel ? $capture(static fn() => Dropdown::showTimeStamp(
                'actiontime',
                ['addfirstminutes' => true],
            )) : '',
        ]);

        return true;
    }

    /**
     * Get the specific massive actions
     *
     * @param $checkitem link item to check right   (default NULL)
     *
     * @return array array of massive actions
     * *@since version 0.84
     *
     */
    public function getSpecificMassiveActions($checkitem = null)
    {
        $actions = parent::getSpecificMassiveActions($checkitem);

        if (Session::haveRight("plugin_resources_checklist", UPDATE)) {
            $actions['GlpiPlugin\Resources\Checklist' . MassiveAction::CLASS_ACTION_SEPARATOR . 'do_checklist'] = __(
                'Mark as finished',
                'resources',
            );
        }

        if (Session::haveRight("plugin_resources_checklist", UPDATE)) {
            $actions['GlpiPlugin\Resources\Checklist' . MassiveAction::CLASS_ACTION_SEPARATOR . 'undo_checklist'] = __(
                'Mark as unfinished',
                'resources',
            );
        }

        if (Session::haveRight("plugin_resources_task", UPDATE)) {
            $actions['GlpiPlugin\Resources\Checklist' . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_task'] = __(
                'Link a task',
                'resources',
            );
        }

        if (Session::haveRight("ticket", Ticket::READALL)) {
            $actions['GlpiPlugin\Resources\Checklist' . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_ticket'] = __(
                'Add ticket',
                'resources',
            );
        }

        return $actions;
    }

    /**
     * @param \MassiveAction $ma
     *
     * @return bool
     */
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        $input = $ma->getInput();
        foreach ($input as $key => $val) {
            if (!is_array($val)) {
                echo Html::hidden($key, ['value' => $val]);
            }
        }

        switch ($ma->getAction()) {
            case "add_task":
                echo "&nbsp;" . __('Assigned to') . "&nbsp;";
                User::dropdown(['name' => "users_id", 'right' => 'interface']);
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
        global $CFG_GLPI;

        $input = $ma->getInput();
        $isfinished = self::checkifChecklistFinished($input);

        switch ($ma->getAction()) {
            case "do_checklist":
                if (!$isfinished) {
                    foreach ($ids as $key => $val) {
                        if ($item->can($key, UPDATE, $input)) {
                            if ($item->update([
                                "id" => $key,
                                "is_checked" => 1,
                            ])) {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            }
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        }
                    }
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                    Session::addMessageAfterRedirect(__('The checklist is finished', 'resources'), true, ERROR);
                }
                break;
            case "undo_checklist":
                if (!$isfinished) {
                    foreach ($ids as $key => $val) {
                        if ($item->can($key, UPDATE, $input)) {
                            if ($item->update([
                                "id" => $key,
                                "is_checked" => 0,
                            ])) {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            }
                        } else {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                        }
                    }
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                    Session::addMessageAfterRedirect(__('The checklist is finished', 'resources'), true, ERROR);
                }
                break;
            case "add_ticket":
                if (!$isfinished) {
                    unset($input["id"]);
                    if (Session::haveRight("ticket", Ticket::READALL)) {
                        $cat = new TicketCategory();
                        $rules = new RuleTicketCollection();
                        $ticket = new Ticket();
                        foreach ($ids as $key => $val) {
                            $item->getFromDB($key);

                            $input2["content"] = $item->fields["comment"];
                            $input2["name"] = $item->fields["name"];
                            $input2["itemtype"] = Resource::class;
                            $input2["items_id"] = [Resource::class => [$item->fields["plugin_resources_resources_id"]]];
                            $input2["requesttypes_id"] = "6";
                            $input2["urgency"] = "3";
                            $input2["_users_id_assign"] = 0;
                            $input2['_groups_id_assign'] = 0;
                            $input2["entities_id"] = $item->fields["entities_id"];

                            if ($cat->getFromDB(1)) {
                                $input2["itilcategories_id"] = $cat->fields["ticketcategories_id"];
                            } else {
                                $input2["itilcategories_id"] = 0;
                            }

                            $input2 = $rules->processAllRules($input2, $input2);

                            if ($ticket->add($input2)) {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                            }
                        }
                    } else {
                        $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                    }
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                    Session::addMessageAfterRedirect(__('The checklist is finished', 'resources'), true, ERROR);
                }
                break;

            case "add_task":
                if (!$isfinished) {
                    unset($input["id"]);
                    $task = new Task();
                    if ($task->canCreate()) {
                        $tasks_id = [];
                        foreach ($ids as $key => $val) {
                            $item->getFromDB($key);
                            if (empty($item->fields["plugin_resources_tasks_id"])) {
                                $input2 = $input;
                                $input2["name"] = addslashes($item->fields["name"]);
                                $input2["comment"] = addslashes($item->fields["comment"]);
                                $input2["entities_id"] = $item->fields["entities_id"];
                                $newID = $task->add($input2);
                                $tasks_id[$newID] = $newID;
                                if ($item->update([
                                    "id" => $key,
                                    "plugin_resources_tasks_id" => $newID,
                                ])) {
                                    $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                                } else {
                                    $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                                }
                            }
                        }
                        //send notifications
                        $Resource = new Resource();
                        if ($CFG_GLPI["notifications_mailing"]) {
                            if ($Resource->getFromDB($item->fields["plugin_resources_resources_id"])) {
                                NotificationEvent::raiseEvent("newtask", $Resource, ['tasks_id' => $tasks_id]);
                            }
                        }
                    } else {
                        $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                    }
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_NORIGHT);
                    Session::addMessageAfterRedirect(__('The checklist is finished', 'resources'), true, ERROR);
                }
                break;
        }
    }

    /**
     * @return array
     */
    public function getForbiddenStandardMassiveAction()
    {
        $forbidden = parent::getForbiddenStandardMassiveAction();

        $forbidden[] = 'update';
        $forbidden[] = 'restore';

        return $forbidden;
    }

    /**
     * @param $is_leaving
     */
    public function showOnCentral($is_leaving)
    {
        global $DB;

        if (!$this->canView()) {
            return;
        }

        $criteria = $is_leaving ? self::queryChecklists(true, 1) : self::queryChecklists(true);
        $iterator = $DB->request($criteria);

        if (count($iterator) === 0) {
            return;
        }

        $date_field = $is_leaving ? "date_end" : "date_begin";
        $list_type  = $is_leaving ? self::RESOURCES_CHECKLIST_OUT : self::RESOURCES_CHECKLIST_IN;

        $entries = [];
        foreach ($iterator as $data) {
            $resource_label = htmlescape($data["resource_name"]) . " " . htmlescape($data["resource_firstname"]);
            if ($_SESSION["glpiis_ids_visible"]) {
                $resource_label .= " (" . $data["plugin_resources_resources_id"] . ")";
            }

            // Past dates are flagged, upcoming ones use the "day" colour.
            $date_class = (!empty($data[$date_field]) && $data[$date_field] <= date('Y-m-d'))
                ? 'deleted'
                : 'plugin_resources_date_day_color';

            $sublist = [];
            foreach ($DB->request(self::queryListChecklists($data["plugin_resources_resources_id"], $list_type)) as $c) {
                $label = htmlescape($c["name"]);
                if ($_SESSION["glpiis_ids_visible"]) {
                    $label .= " (" . $c["id"] . ")";
                }
                $sublist[] = ['label' => $label, 'tag' => (bool) $c["tag"]];
            }

            $entries[] = [
                'resource'  => '<a href="' . PLUGIN_RESOURCES_WEBDIR . '/front/resource.form.php?id='
                    . (int) $data["plugin_resources_resources_id"] . '">' . $resource_label . '</a>',
                'date'      => '<div class="' . $date_class . '">'
                    . Html::convDate($data[$date_field]) . '</div>',
                'entity'    => Dropdown::getDropdownName("glpi_entities", $data['entities_id']),
                'location'  => Dropdown::getDropdownName("glpi_locations", $data['locations_id']),
                'contract'  => Dropdown::getDropdownName(
                    "glpi_plugin_resources_contracttypes",
                    $data['plugin_resources_contracttypes_id'],
                ),
                'checklist' => TemplateRenderer::getInstance()->render(
                    '@resources/checklist_central_sublist.html.twig',
                    ['entries' => $sublist],
                ),
            ];
        }

        $columns = ['resource' => Resource::getTypeName(1)];
        $columns['date'] = $is_leaving
            ? __('Departure date', 'resources')
            : __('Arrival date', 'resources');
        if (Session::isMultiEntitiesMode()) {
            $columns['entity'] = __('Entity');
        }
        $columns['location']  = __('Location');
        $columns['contract']  = ContractType::getTypeName(1);
        $columns['checklist'] = __('Checklist needs to verificated', 'resources');

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'super_header'    => $is_leaving
                ? __('Leaving resource - checklist needs to verificated', 'resources')
                : __('New resource - checklist needs to verificated', 'resources'),
            'columns'         => $columns,
            'formatters'      => [
                'resource'  => 'raw_html',
                'date'      => 'raw_html',
                'checklist' => 'raw_html',
            ],
            'entries'         => $entries,
            'total_number'    => count($entries),
            'filtered_number' => count($entries),
            'nofilter'        => true,
            'nosort'          => true,
        ]);
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
            case 'ResourcesChecklist':
                return [
                    'description' => __('Checklists Verification', 'resources'),
                ];   // Optional
                break;
        }
        return [];
    }

    /**
     * @param     $entity_restrict
     * @param int $is_leaving
     *
     * @return array
     */
    public static function queryChecklists($entity_restrict, $is_leaving = 0)
    {
        $resource = new Resource();

        if ($is_leaving > 0) {
            $field = "date_end";
            $checklist_type = self::RESOURCES_CHECKLIST_OUT;
        } else {
            $field = "date_begin";
            $checklist_type = self::RESOURCES_CHECKLIST_IN;
        }

        $query =
            [
                'SELECT' => [
                    'glpi_plugin_resources_checklists.*',
                    'glpi_plugin_resources_resources.id AS plugin_resources_resources_id',
                    'glpi_plugin_resources_resources.name AS resource_name',
                    'glpi_plugin_resources_resources.firstname AS resource_firstname',
                    'glpi_plugin_resources_resources.entities_id',
                    'glpi_plugin_resources_resources.locations_id',
                    'glpi_plugin_resources_resources.plugin_resources_departments_id',
                    'glpi_plugin_resources_resources.plugin_resources_resourcestates_id',
                    'glpi_plugin_resources_resources.users_id',
                    'glpi_plugin_resources_resources.users_id_sales',
                    'glpi_plugin_resources_resources.users_id_recipient',
                    'glpi_plugin_resources_resources.date_declaration',
                    'glpi_plugin_resources_resources.date_begin',
                    'glpi_plugin_resources_resources.date_end',
                    'glpi_plugin_resources_resources.users_id_recipient_leaving',
                    'glpi_plugin_resources_resources.date_declaration_leaving',
                    'glpi_plugin_resources_resources.is_leaving',
                    'glpi_plugin_resources_resources.is_helpdesk_visible',
                    'glpi_plugin_resources_resources.plugin_resources_contracttypes_id',
                ],
                'FROM' => 'glpi_plugin_resources_checklists',
                'LEFT JOIN'       => [
                    'glpi_plugin_resources_resources' => [
                        'ON' => [
                            'glpi_plugin_resources_checklists' => 'plugin_resources_resources_id',
                            'glpi_plugin_resources_resources'          => 'id',
                        ],
                    ],
                ],
                'WHERE' => [
                    'glpi_plugin_resources_resources.is_leaving'    => $is_leaving,
                    'glpi_plugin_resources_checklists.checklist_type'    => $checklist_type,
                    'glpi_plugin_resources_checklists.is_checked'    => 0,
                    'glpi_plugin_resources_resources.is_deleted'    => 0,
                    'glpi_plugin_resources_resources.is_template'    => 0,
                ],
                'GROUPBY' => ['glpi_plugin_resources_resources.id'],
                'ORDERBY' => 'glpi_plugin_resources_resources.' . $field,
            ];
        $query['WHERE'] = $query['WHERE'] + getEntitiesRestrictCriteria(
            'glpi_plugin_resources_resources',
        );

        return $query;
    }

    /**
     * @param $ID
     * @param $checklist_type
     *
     * @return array
     */
    public static function queryListChecklists($ID, $checklist_type)
    {

        $query =
            [
                'SELECT' => [
                    'glpi_plugin_resources_checklists.*',
                ],
                'FROM' => 'glpi_plugin_resources_checklists',
                'LEFT JOIN'       => [
                    'glpi_plugin_resources_resources' => [
                        'ON' => [
                            'glpi_plugin_resources_checklists' => 'plugin_resources_resources_id',
                            'glpi_plugin_resources_resources'          => 'id',
                        ],
                    ],
                ],
                'WHERE' => [
                    'glpi_plugin_resources_resources.id'    => $ID,
                    'glpi_plugin_resources_checklists.checklist_type'    => $checklist_type,
                    'glpi_plugin_resources_checklists.is_checked'    => 0,
                    'glpi_plugin_resources_resources.is_deleted'    => 0,
                    'glpi_plugin_resources_resources.is_template'    => 0,
                ],
                'ORDERBY' => 'glpi_plugin_resources_checklists.rank ASC',
            ];


        return $query;
    }

    /**
     * Cron action on checklists
     *
     * @param $task for log, if NULL display
     *
     * */
    public static function cronResourcesChecklist($task = null)
    {
        global $DB, $CFG_GLPI;

        if (!$CFG_GLPI["notifications_mailing"]) {
            return 0;
        }

        $message = [];
        $cron_status = 0;
        $query_arrival = self::queryChecklists(false);
        $query_leaving = self::queryChecklists(false, 1);

        $querys = [Alert::NOTICE => $query_arrival, Alert::END => $query_leaving];

        $checklist_infos = [];
        $checklist_messages = [];

        foreach ($querys as $type => $query) {
            $checklist_infos[$type] = [];
            foreach ($DB->request($query) as $data) {
                $entity = $data['entities_id'];
                $message = "checklists" . ": " . htmlescape($data["resource_name"]) . " " . htmlescape($data["resource_firstname"]) . "<br>\n";
                $checklist_infos[$type][$entity][] = $data;

                if (!isset($checklist_messages[$type][$entity])) {
                    $checklist_messages[$type][$entity] = __('Checklists Verification', 'resources') . "<br />";
                }
                $checklist_messages[$type][$entity] .= $message;
            }
        }

        foreach ($querys as $type => $query) {
            foreach ($checklist_infos[$type] as $entity => $checklists) {
                Plugin::loadLang('resources');

                if (NotificationEvent::raiseEvent(
                    ($type == Alert::NOTICE ? "AlertArrivalChecklists" : "AlertLeavingChecklists"),
                    new Resource(),
                    [
                        'entities_id' => $entity,
                        'checklists' => $checklists,
                        'tasklists' => $checklists,
                    ],
                )) {
                    $message = $checklist_messages[$type][$entity];
                    $cron_status = 1;
                    if ($task) {
                        $task->log(Dropdown::getDropdownName("glpi_entities", $entity) . ":  $message\n");
                        $task->addVolume(1);
                    } else {
                        Session::addMessageAfterRedirect(
                            Dropdown::getDropdownName("glpi_entities", $entity) . ":  $message",
                        );
                    }
                } else {
                    if ($task) {
                        $task->log(
                            Dropdown::getDropdownName("glpi_entities", $entity) .
                            ":  Send checklists resources alert failed\n",
                        );
                    } else {
                        Session::addMessageAfterRedirect(
                            Dropdown::getDropdownName("glpi_entities", $entity) .
                            ":  Send checklists resources alert failed",
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
     * @param \PluginPdfSimplePDF $pdf
     * @param \CommonGLPI $item
     * @param                     $tab
     *
     * @return bool
     */
    public static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab)
    {
        if ($item->getType() == Resource::class) {
            self::pdfForResource($pdf, $item, self::RESOURCES_CHECKLIST_IN);
            self::pdfForResource($pdf, $item, self::RESOURCES_CHECKLIST_OUT);
            self::pdfForResource($pdf, $item, self::RESOURCES_CHECKLIST_TRANSFER);
        } else {
            return false;
        }
        return true;
    }

    /**
     * Show for PDF an resources : checklists informations
     *
     * @param $pdf object for the output
     * @param $ID of the resources
     */
    public static function pdfForResource(PluginPdfSimplePDF $pdf, Resource $appli, $checklist_type)
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
            'FROM'  => 'glpi_plugin_resources_checklists',
            'WHERE' => [
                'plugin_resources_resources_id' => (int) $ID,
                'checklist_type'                => (int) $checklist_type,
            ],
            'ORDER' => 'rank',
        ]);
        $number = count($iterator);

        $pdf->setColumnsSize(100);
        if ($number > 0) {
            $pdf->displayTitle('<b>' . self::getChecklistType($checklist_type) . '</b>');
            $pdf->setColumnsSize(85, 10, 5);
            $pdf->displayTitle(
                '<b><i>' .
                __('Name'),
                __('Linked task', 'resources'),
                __('Checked', 'resources') . '</i></b>',
            );

            foreach ($iterator as $data) {
                if ($data['is_checked'] == 1) {
                    $checked = __('Yes');
                } else {
                    $checked = __('No');
                }
                $pdf->displayLine(
                    $data['name'],
                    Dropdown::getYesNo($data['plugin_resources_tasks_id']),
                    $checked,
                );
            }
        } else {
            $pdf->displayLine(__('No checklist found', 'resources'));
        }

        $pdf->displaySpace();
    }

    /**
     * @param $menu
     *
     * @return mixed
     */
    public static function getMenuOptions($menu)
    {
        $plugin_page = PLUGIN_RESOURCES_WEBDIR . '/front/checklistconfig.php';
        $itemtype = self::getType();

        //Menu entry in admin
        $menu['options'][$itemtype]['title'] = self::getTypeName();
        $menu['options'][$itemtype]['page'] = $plugin_page;
        $menu['options'][$itemtype]['links']['search'] = $plugin_page;
        $menu['options'][$itemtype]['links']['lists'] = "";
        $menu['options'][$itemtype]['lists_itemtype'] = self::getType();

        // Add
        if (Session::haveright(self::$rightname, UPDATE)) {
            $menu['options'][$itemtype]['links']['add'] = PLUGIN_RESOURCES_WEBDIR . '/front/checklistconfig.form.php?new=1';
        }

        // Config
        if (Session::haveRight("config", UPDATE)) {
            $menu['options'][$itemtype]['links']['config'] = PLUGIN_RESOURCES_WEBDIR . '/front/config.form.php';
        }

        return $menu;
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
                        `name`                              varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `entities_id`                       int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `plugin_resources_resources_id`     int {$default_key_sign} NOT NULL                   DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_resources (id)',
                        `plugin_resources_tasks_id`         int {$default_key_sign} NOT NULL                   DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_tasks (id)',
                        `plugin_resources_contracttypes_id` int {$default_key_sign} NOT NULL                   DEFAULT '0' COMMENT 'RELATION to glpi_plugin_resources_contracttypes (id)',
                        `checklist_type`                    int {$default_key_sign} NOT NULL                   DEFAULT '0',
                        `tag`                               tinyint      NOT NULL                   DEFAULT '0',
                        `is_checked`                        tinyint      NOT NULL                   DEFAULT '0',
                        `address`                           varchar(255) COLLATE utf8mb4_unicode_ci default NULL,
                        `rank`                              smallint     NOT NULL                   DEFAULT '0',
                        `comment`                           TEXT COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`),
                        KEY `name` (`name`),
                        KEY `entities_id` (`entities_id`),
                        KEY `plugin_resources_resources_id` (`plugin_resources_resources_id`),
                        KEY `plugin_resources_tasks_id` (`plugin_resources_tasks_id`),
                        KEY `plugin_resources_contracttypes_id` (`plugin_resources_contracttypes_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
