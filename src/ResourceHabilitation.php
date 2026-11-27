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
use Glpi\Exception\Http\BadRequestHttpException;
use Log;
use Migration;
use PluginPdfSimplePDF;
use Session;
use Toolbox;

/**
 * Class ResourceHabilitation
 */
class ResourceHabilitation extends CommonDBTM
{
    // Rows of a resource: creating one is an update of the resource
    use ResourceChildRightTrait;
    // No entity of its own: the item rights are those of the resource
    use ResourceChildItemRightTrait;

    public static $rightname = 'plugin_resources';
    public $dohistory = true;

    public function prepareInputForAdd($input)
    {
        // The form, the wizard step and the template clone all come through here: the
        // habilitation posted has to exist and be one of those of the entity of the resource
        // (its own entity, or a parent one when the habilitation is recursive), the scope the
        // dropdown of the form offers. post_addItem() writes its name to the history.
        $habilitations_id = self::getAllowedHabilitationId(
            $input['plugin_resources_habilitations_id'] ?? null,
            $input['plugin_resources_resources_id'] ?? null,
        );
        if ($habilitations_id === 0) {
            Session::addMessageAfterRedirect(__('Item not found'), false, ERROR);
            return false;
        }
        $input['plugin_resources_habilitations_id'] = $habilitations_id;

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        // canUpdateItem() answered for the resource the row is stored under, not for the one
        // an update would move it to
        if (!$this->canMoveToParentResource($input)) {
            return false;
        }

        // A new habilitation written on the row is checked as on creation, against the
        // resource the row ends up under
        if (array_key_exists('plugin_resources_habilitations_id', $input)) {
            $habilitations_id = self::getAllowedHabilitationId(
                $input['plugin_resources_habilitations_id'],
                $input['plugin_resources_resources_id'] ?? $this->fields['plugin_resources_resources_id'] ?? null,
            );
            if ($habilitations_id === 0) {
                Session::addMessageAfterRedirect(__('Item not found'), false, ERROR);
                return false;
            }
            $input['plugin_resources_habilitations_id'] = $habilitations_id;
        }

        return $input;
    }

    /**
     * Habilitation that may be attached to a resource.
     *
     * @param mixed $habilitations_id habilitation, as received
     * @param mixed $resources_id     resource the row belongs to, as received
     *
     * @return int the habilitation id, 0 when it does not exist or is out of the scope of the
     *             entity of the resource
     */
    public static function getAllowedHabilitationId(mixed $habilitations_id, mixed $resources_id): int
    {
        try {
            $habilitations_id = Resource::getPostedId($habilitations_id);
            $resources_id     = Resource::getPostedId($resources_id);
        } catch (BadRequestHttpException) {
            return 0;
        }

        $resource = new Resource();
        if ($habilitations_id <= 0 || $resources_id <= 0 || !$resource->getFromDB($resources_id)) {
            return 0;
        }

        $dbu   = new DbUtils();
        $table = Habilitation::getTable();
        $found = $dbu->countElementsInTable(
            $table,
            ['id' => $habilitations_id] + $dbu->getEntitiesRestrictCriteria(
                $table,
                '',
                (int) $resource->fields['entities_id'],
                true,
            ),
        );

        return $found > 0 ? $habilitations_id : 0;
    }

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
        return _n('Habilitation', 'Habilitations', $nb, 'resources');
    }

    public static function getIcon()
    {
        return "ti ti-lock";
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
        if ($item->getType() == Resource::class
            && $this->canView()) {
            if ($_SESSION['glpishow_count_on_tabs']) {
                return self::createTabEntry(self::getTypeName(2), self::countForResource($item));
            }
            return self::createTabEntry(self::getTypeName(2));
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
            $self->showItem($item);
        }
        //        if ($item->getType() == Resource::class) {
        //            $wizard = new Wizard();
        //            $wizard->wizardSixStep($item->getField('id'), ['default_button' => true, 'target' => 'item']);
        //        }
        return true;
    }

    /**
     * @param Resource $item
     *
     * @return int
     */
    public static function countForResource(Resource $item)
    {
        $restrict = ["plugin_resources_resources_id" => $item->getField('id')];
        $dbu = new DbUtils();
        $nb = $dbu->countElementsInTable(['glpi_plugin_resources_resourcehabilitations'], $restrict);

        return $nb;
    }

    /**
     * @param $item
     *
     * @return bool
     */
    public function showItem($item)
    {
        if (!$this->canView()) {
            return false;
        }

        $canedit = $this->canCreate();

        $data = $this->find(['plugin_resources_resources_id' => $item->getField('id')]);

        if ($canedit) {
            $used = [];
            foreach ($data as $habilitation) {
                $used[] = $habilitation['plugin_resources_habilitations_id'];
            }
            // Capture the GLPI dropdown, which echoes directly, for the template.
            ob_start();
            Dropdown::show(Habilitation::class, [
                'used'   => $used,
                'entity' => $item->getField("entities_id"),
            ]);
            $habilitation_dropdown = (string) ob_get_clean();

            TemplateRenderer::getInstance()->display('@resources/resourcehabilitation_add_form.html.twig', [
                'form_action'           => Toolbox::getItemTypeFormURL(ResourceHabilitation::class),
                'habilitation_label'    => self::getTypeName(1),
                'habilitation_dropdown' => $habilitation_dropdown,
                'resources_id'          => $item->getField('id'),
            ]);
        }
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
                    'glpi_plugin_resources_habilitations',
                    $field['plugin_resources_habilitations_id'],
                ),
            ];
        }

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'super_header'        => self::getTypeName(),
            'columns'             => ['name' => __('Name')],
            'entries'             => $entries,
            'total_number'        => count($entries),
            'filtered_number'     => count($entries),
            'showmassiveactions'  => $canedit,
            'massiveactionparams' => [
                'num_displayed' => count($entries),
                'container'     => 'masshabil' . mt_rand(),
            ],
        ]);
    }

    /**
     * Duplicate item resources from an item template to its clone
     *
     * @param $oldid        ID of the item to clone
     * @param $newid        ID of the item cloned
     *
     * @since version 0.84
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
                'FROM' => 'glpi_plugin_resources_resourcehabilitations',
                'WHERE' => [
                    'plugin_resources_resources_id' => $oldid,
                ],
            ];

        foreach ($DB->request($query) as $data) {
            $habilitation = new self();
            $habilitation->add([
                'plugin_resources_resources_id' => $newid,
                'plugin_resources_habilitations_id' => $data["plugin_resources_habilitations_id"],
            ]);
        }
    }

    public function post_addItem()
    {
        $changes[0] = 0;
        $changes[1] = '';
        $changes[2] = sprintf(
            __('Adding the habilitation: %s', 'resources'),
            Dropdown::getDropdownName(
                'glpi_plugin_resources_habilitations',
                $this->input['plugin_resources_habilitations_id'],
            ),
        );
        Log::history(
            $this->input['plugin_resources_resources_id'],
            Resource::class,
            $changes,
            '',
            Log::HISTORY_LOG_SIMPLE_MESSAGE,
        );
    }

    /**
     * @return void
     */
    public function post_deleteFromDB()
    {
        $changes[0] = 0;
        $changes[1] = '';
        $changes[2] = sprintf(
            __('Suppression of the habilitation: %s', 'resources'),
            Dropdown::getDropdownName(
                'glpi_plugin_resources_habilitations',
                $this->fields['plugin_resources_habilitations_id'],
            ),
        );
        Log::history(
            $this->fields['plugin_resources_resources_id'],
            Resource::class,
            $changes,
            '',
            Log::HISTORY_LOG_SIMPLE_MESSAGE,
        );
    }



    /**
     * Adding habilitations to the resource via the wizard
     *
     * The habilitations of the resource are replaced: the deletion and the new rows are keyed
     * on the single id the caller checked. It is validated here too, since an array or an
     * operator form in the deletion criteria would reach the rows of other resources.
     *
     * @param array $params plugin_resources_resources_id, habilitation_<level> values
     *
     * @return bool
     */
    public function addResourceHabilitation($params)
    {
        $resources_id = Resource::getPostedId($params['plugin_resources_resources_id'] ?? null);
        if ($resources_id <= 0) {
            return false;
        }

        $habilitation_level = new HabilitationLevel();

        $ressourcehabilitation = new ResourceHabilitation();
        $ressourcehabilitation->deleteByCriteria(["plugin_resources_resources_id" => $resources_id]);

        foreach ($params as $key => $val) {
            // Only the habilitation_<level> fields of the form: the other keys (the resource
            // id among them) were looked up as levels too
            if (preg_match('/^habilitation_([0-9]+)$/', (string) $key, $matches) === 1) {
                $id = (int) $matches[1];
                if (is_array($val)
                    && ($habilitation_level->getFromDB($id))) {
                    foreach ($val as $v) {
                        $this->addResourceHabilitationInDb($v, $resources_id);
                    }
                } elseif ($habilitation_level->getFromDB($id)) {
                    $this->addResourceHabilitationInDb($val, $resources_id);
                }
            }
        }

        return true;
    }

    /**
     * @param mixed $id           habilitation
     * @param int   $resources_id id of the resource the caller checked
     */
    public function addResourceHabilitationInDb($id, int $resources_id)
    {
        // The empty choice of a level left blank: nothing to attach, nothing to report
        if (empty($id)) {
            return;
        }
        // prepareInputForAdd() checks the habilitation (existence and entity scope of the
        // resource), the same way for the wizard as for the form
        (new self())->add([
            'plugin_resources_habilitations_id' => $id,
            'plugin_resources_resources_id'     => $resources_id,
        ]);
    }

    /**
     * Verification if level of mandatory habilitations
     * return true if required fields are completed correctly
     * false if not
     *
     * @param array $params
     *
     * @return bool
     */
    public function checkRequiredFields($params = [])
    {
        // The mandatory levels are those of the entity of the resource being filled
        $resource = new Resource();
        $resources_id = Resource::getPostedId($params['plugin_resources_resources_id'] ?? null);
        if ($resources_id <= 0 || !$resource->getFromDB($resources_id)) {
            return false;
        }
        $dbu = new DbUtils();

        $habilitation_level = new HabilitationLevel();
        $condition = ['is_mandatory_creating_resource' => 1] + $dbu->getEntitiesRestrictCriteria(
            $habilitation_level->getTable(),
            'entities_id',
            $resource->getEntityID(),
            $habilitation_level->maybeRecursive(),
        );
        $levels = $habilitation_level->find($condition, "name");

        foreach ($levels as $level) {
            if (!isset($params['habilitation_' . $level['id']])
                || (isset($params['habilitation_' . $level['id']])
                    && (empty($params['habilitation_' . $level['id']])))) {
                return false;
            }
        }
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

        if (!Session::haveRight("plugin_resources", READ)) {
            return false;
        }

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_resources_resourcehabilitations',
            'WHERE' => ['plugin_resources_resources_id' => (int) $ID],
        ]);
        $number = count($iterator);
        // The pdf plugin writes every argument as HTML (TCPDF writeHTMLCell): only the static
        // markup below is kept, every label and stored value is escaped.
        $pdf->setColumnsSize(100);

        $pdf->displayTitle('<b>' . htmlescape(self::getTypeName(2)) . '</b>');

        if (!$number) {
            $pdf->displayLine(htmlescape(__('No results found')));
        } else {
            foreach ($iterator as $data) {
                $pdf->displayLine(htmlescape(Dropdown::getDropdownName(
                    "glpi_plugin_resources_habilitations",
                    $data["plugin_resources_habilitations_id"],
                )));
            }
        }

        $pdf->displaySpace();
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
                        `plugin_resources_resources_id`     int unsigned NOT NULL DEFAULT '0',
                        `plugin_resources_habilitations_id` int unsigned NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`),
                        KEY `plugin_resources_resources_id` (`plugin_resources_resources_id`),
                        KEY `glpi_plugin_resources_habilitations_id` (`plugin_resources_habilitations_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

            $DB->doQuery($query);
        }
    }
}
