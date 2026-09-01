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
use CommonDBTM;
use CommonGLPI;
use CommonITILActor;
use DBConnection;
use DbUtils;
use Dropdown;
use GlpiPlugin\Badges\Badge;
use GlpiPlugin\Metademands\Metademand;
use GlpiPlugin\Metademands\Metademand_Resource;
use Group_Ticket;
use Html;
use ITILCategory;
use Log;
use Migration;
use Plugin;
use Session;
use Ticket;
use TicketTemplate;
use TicketTemplatePredefinedField;
use Toolbox;
use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class ResourceBadge
 */
class ResourceBadge extends CommonDBTM
{
    public static $rightname = 'plugin_resources';
    public $dohistory = true;

    /**
     * Return the localized name of the current Type
     * Should be overloaded in each new class
     *
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Badge management', 'Badges management', 2, 'resources');
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
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return self::createTabEntry(self::getTypeName());
    }

    public static function getIcon()
    {
        return "ti ti-id";
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
            $self->showFormBadge();
        }
        return true;
    }

    /**
     * Choose link with metademand
     *
     * @return bool
     */
    public function showFormBadge()
    {
        if (!$this->canView()) {
            return false;
        }
        if (!$this->canCreate()) {
            return false;
        }

        $used_data = [];
        $data = $this->find();

        $is_present = false;

        if ($data) {
            foreach ($data as $field) {
                $used_data[] = $field['plugin_metademands_metademands_id'];

                if ($field['entities_id'] == $_SESSION['glpiactive_entity']) {
                    $is_present = true;
                }
            }
        }
        $canedit = $this->canCreate();

        if ($canedit) {
            $tpl = ['already_linked' => $is_present];
            if ($is_present) {
                $tpl['already_linked_message'] = __('The current entity is already linked to a meta-demand', 'resources');
            } else {
                // Capture the GLPI meta-demand dropdown as an HTML fragment for the template.
                ob_start();
                Dropdown::show(Metademand::class, [
                    'name' => 'plugin_metademands_metademands_id',
                    'used' => $used_data,
                    'entity' => $_SESSION['glpiactive_entity'],
                ]);
                $metademand_dropdown = ob_get_clean();

                $tpl += [
                    'form_action'         => Toolbox::getItemTypeFormURL(ResourceBadge::class),
                    'title'               => Metademand_Resource::getTypeName(1),
                    'metademand_label'    => Metademand::getTypeName(1),
                    'metademand_dropdown' => $metademand_dropdown,
                    'entities_id'         => $_SESSION['glpiactive_entity'],
                ];
            }
            TemplateRenderer::getInstance()->display('@resources/resourcebadge_form.html.twig', $tpl);
        }
        //list metademands
        $this->listItems($data, $canedit);
    }

    /**
     * List of metademands
     *
     * @param $fields
     * @param $canedit
     */
    private function listItems($fields, $canedit)
    {
        if (empty($fields)) {
            return;
        }

        $entries = [];
        foreach ($fields as $field) {
            $entries[] = [
                'itemtype' => self::class,
                'id'       => $field['id'],
                'name'     => Dropdown::getDropdownName(
                    'glpi_plugin_metademands_metademands',
                    $field['plugin_metademands_metademands_id'],
                ),
                'entity'   => Dropdown::getDropdownName('glpi_entities', $field['entities_id']),
            ];
        }

        // Backslashes of the namespaced class would break the jQuery container selector.
        $container = 'mass' . str_replace('\\', '', self::class) . mt_rand();

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'super_header'        => __('Meta-demands linked', 'metademands'),
            'columns'             => [
                'name'   => __('Name'),
                'entity' => __('Entity'),
            ],
            'entries'             => $entries,
            'total_number'        => count($entries),
            'filtered_number'     => count($entries),
            'showmassiveactions'  => $canedit,
            'massiveactionparams' => [
                'num_displayed' => count($entries),
                'container'     => $container,
            ],
        ]);
    }

    /**
     * Display Menu
     */
    public function showMenu()
    {
        ob_start();
        Wizard::WizardHeader(_n('Badge management', 'Badges management', 2, 'resources'));
        $wizard_header = (string) ob_get_clean();

        $tiles = [];
        if (Session::haveright('plugin_resources', UPDATE)) {
            $colspan = 1;
            if (Plugin::isPluginActive("metademands")) {
                $tiles[] = [
                    'url'   => './resourcebadge.form.php?new',
                    'icon'  => 'ti ti-id-badge-2',
                    'label' => __('Request new badge', 'resources'),
                ];
            } else {
                $colspan = 2;
            }
            $tiles[] = [
                'url'     => './resourcebadge.form.php',
                'icon'    => 'ti ti-circle-arrow-left',
                'label'   => __('Badge restitution', 'resources'),
                'colspan' => $colspan,
            ];
        }

        TemplateRenderer::getInstance()->display('@resources/wizard_tiles_menu.html.twig', [
            'wizard_header' => $wizard_header,
            'tiles'         => $tiles,
        ]);
    }

    /**
     * Show form from helpdesk to badge restitution of a resource
     */
    public function showWizardForm()
    {
        // Capture the wizard header and the resource dropdown as HTML fragments; keep the
        // dropdown rand to wire the AJAX loader script to its change event.
        ob_start();
        Wizard::WizardHeader(__('Badge restitution', 'resources'));
        $wizard_header = ob_get_clean();

        ob_start();
        $rand = Resource::dropdown([
            'name' => 'plugin_resources_resources_id',
            'display' => true,
            'on_change' => 'plugin_resources_load_badge()',
            'entity' => $_SESSION['glpiactiveentities'],
        ]);
        $resource_dropdown = ob_get_clean();

        //display list of badges
        $params = ['action' => 'loadBadge', 'plugin_resources_resources_id' => '__VALUE__'];
        $load_badge = Ajax::updateItemJsCode(
            'plugin_resources_badge',
            PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcebadge.php',
            $params,
            'dropdown_plugin_resources_resources_id' . $rand,
            false,
        );
        $params = ['action' => 'cleanButtonRestitution'];
        $clean_button = Ajax::updateItemJsCode(
            'plugin_resources_button_restitution',
            PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcebadge.php',
            $params,
            'dropdown_plugin_resources_resources_id' . $rand,
            false,
        );
        $load_script = "<script type='text/javascript'>function plugin_resources_load_badge(){"
            . $load_badge . ";" . $clean_button . "}</script>";

        TemplateRenderer::getInstance()->display('@resources/resourcebadge_wizard.html.twig', [
            'wizard_header'     => $wizard_header,
            'form_action'       => PLUGIN_RESOURCES_WEBDIR . "/front/resourcebadge.form.php",
            'resource_label'    => Resource::getTypeName(1),
            'resource_dropdown' => $resource_dropdown,
            'load_script'       => $load_script,
            'badges_list_url'   => PLUGIN_BADGES_WEBDIR . "/front/badge.php",
            'badges_list_label' => __('List of badges', 'resources'),
        ]);
    }

    /**
     * List of badges linked to the user
     *
     * @param $plugin_resources_resources_id
     */
    public function loadBadge($plugin_resources_resources_id)
    {
        $dbu   = new DbUtils();
        $infos = $dbu->getAllDataFromTable('glpi_plugin_resources_resources_items', [
            "plugin_resources_resources_id" => $plugin_resources_resources_id,
            "itemtype"                      => 'User',
        ]);

        $users_id = [];
        foreach ($infos as $info) {
            if ((int) $info['items_id'] > 0) {
                $users_id[(int) $info['items_id']] = (int) $info['items_id'];
            }
        }

        $badge_dropdown   = '';
        $load_restitution = '';

        // Without a linked user there is no badge to give back: an empty criterion would let
        // the dropdown list every badge of the entity instead.
        if (count($users_id) > 0) {
            // A single IN criterion: one criterion per user would be ANDed together and could
            // never match as soon as the resource is linked to more than one user.
            // Capture the badge dropdown as an HTML fragment; keep its rand to wire the
            // restitution button loader to the dropdown change event.
            ob_start();
            $rand = Badge::dropdown([
                'name'      => 'badges_id',
                'condition' => ['users_id' => array_values($users_id)],
                'on_change' => 'plugin_resources_load_badge_restitution()',
            ]);
            $badge_dropdown = (string) ob_get_clean();

            $load_restitution = Html::scriptBlock(
                'function plugin_resources_load_badge_restitution(){' . Ajax::updateItemJsCode(
                    'plugin_resources_button_restitution',
                    PLUGIN_RESOURCES_WEBDIR . '/ajax/resourcebadge.php',
                    ['action' => 'loadBadgeRestitution'],
                    'dropdown_badges_id' . $rand,
                    false,
                ) . '}',
            );
        }

        TemplateRenderer::getInstance()->display('@resources/resourcebadge_list.html.twig', [
            'badge_label'    => Badge::getTypeName(1),
            'badge_dropdown' => $badge_dropdown,
            'load_script'    => $load_restitution,
            'has_user'       => count($users_id) > 0,
        ]);
    }

    /**
     * Button display
     */
    public function loadBadgeRestitution()
    {
        echo Html::submit(
            _sx('button', 'Save'),
            ['name' => 'plugin_resources_badge_restitution', 'class' => 'btn btn-primary'],
        );
    }

    /**
     * Creation of ticket for restitution badge
     *
     * @param $data
     *
     * @return bool
     */
    public static function createTicket($plugin_resources_resources_id, $options = [])
    {
        $resource = new Resource();
        $resource->getFromDB($plugin_resources_resources_id);

        //Preparation of ticket data
        $data = [];
        $data['itilcategories_id'] = 0;
        $data['tickettemplates_id'] = 0;

        //Search for the entity-related category for that action
        $resource_change = new Resource_Change();
        if ($resource_change->getFromDBByCrit([
            'actions_id' => Resource_Change::BADGE_RESTITUTION,
            'entities_id' => $resource->fields['entities_id'],
        ])) {
            $data['itilcategories_id'] = $resource_change->fields['itilcategories_id'];

            //Search of the ticket template
            $itil_category = new ITILCategory();
            if ($itil_category->getFromDB($data['itilcategories_id'])) {
                $data['tickettemplates_id'] = $itil_category->fields['tickettemplates_id_demand'];
            }
        }

        $result = false;
        $tt = new TicketTemplate();

        // Create ticket based on ticket template and entity informations of ticketrecurrent
        if ($tt->getFromDB($data['tickettemplates_id'])) {
            // Get default values for ticket
            $input = Ticket::getDefaultValues($resource->fields['entities_id']);
            // Apply tickettemplates predefined values
            $ttp = new TicketTemplatePredefinedField();
            $predefined = $ttp->getPredefinedFields($data['tickettemplates_id'], true);

            if (count($predefined)) {
                foreach ($predefined as $predeffield => $predefvalue) {
                    $input[$predeffield] = $predefvalue;
                }
            }
        } else {
        }

        // Set date to creation date
        $createtime = date('Y-m-d H:i:s');
        $input['date'] = $createtime;
        $input['type'] = Ticket::DEMAND_TYPE;
        $input['entities_id'] = $resource->fields['entities_id'];
        $input['plugin_resources_resources_id'] = $plugin_resources_resources_id;
        $input['itilcategories_id'] = $data['itilcategories_id'];
        $input['tickettemplates_id'] = $data['tickettemplates_id'];

        $input['users_id_recipient'] = Session::getLoginUserID();
        $input['_users_id_requester'] = Session::getLoginUserID();
        $input["items_id"] = [
            Resource::class => [$plugin_resources_resources_id],
            Badge::class => [$options['badges_id']],
        ];

        // Compute time_to_resolve if predefined based on create date
        if (isset($predefined['time_to_resolve'])) {
            $input['time_to_resolve'] = Html::computeGenericDateTimeSearch(
                $predefined['time_to_resolve'],
                false,
                strtotime($createtime),
            );
        }

        $input["name"] = __('Badge restitution', 'resources') . ' : ' . " " . Resource::getResourceName(
            $plugin_resources_resources_id,
        );
        $input["content"] = __('Badge restitution', 'resources') . ' :' . " " . Resource::getResourceName(
            $plugin_resources_resources_id,
        ) . "\n";
        $input["content"] .= Badge::getTypeName(1) . ' : ' . " " . Dropdown::getDropdownName(
            'glpi_plugin_badges_badges',
            $options['badges_id'],
        );
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
                $input['plugin_resources_resources_id'],
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
                        `entities_id`                       int {$default_key_sign} NOT NULL DEFAULT '0',
                        `plugin_metademands_metademands_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`),
                        KEY `entities_id` (`entities_id`),
                        KEY `plugin_metademands_metademands_id` (`plugin_metademands_metademands_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
