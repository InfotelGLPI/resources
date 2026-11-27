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

use Auth;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Glpi\RichText\RichText;
use Html;
use Phone;
use Plugin;
use PluginFieldsContainer;
use PluginFieldsField;
use PluginFieldsLabelTranslation;
use PluginFieldsProfile;
use PluginPdfSimplePDF;
use Session;
use UserEmail;

class User extends \User
{
    public static function getTable($classname = null)
    {
        return Resource_Item::getTable();
    }

    public static function getTypeName($nb = 0)
    {
        return _n('User', 'Users', $nb);
    }

    public static function getIcon()
    {
        return "ti ti-user";
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        // The tab, and its PDF export, print the linked account: offered only to the
        // profiles allowed to view users.
        if ($item instanceof Resource && \User::canView()) {
            return self::createTabEntry(__('User'));
        }
        return '';
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        // Same conditions as the tab name: the right to view users, and the resource itself
        // readable. showForm() then requires READ on the very account shown.
        if (!$item instanceof Resource || !\User::canView() || !$item->can($item->getID(), READ)) {
            return false;
        }

        $user = new self();
        $resourceItem = new Resource_Item();
        $resourceUsers = $resourceItem->find(
            ['plugin_resources_resources_id' => $item->getID(), 'itemtype' => \User::class],
            'id DESC',
            1,
        );
        // Last user linked to the resource
        foreach ($resourceUsers as $resourceUser) {
            $user->showForm($resourceUser['items_id'], ['resourcesID' => $item->getID()]);
        }

        return true;
    }

    public function showForm($ID, array $options = [])
    {
        // Simplified User form, shown as a tab of a human resource. The account linked to a
        // readable resource may sit in an entity the session has no access to: the right
        // on that very user is required, as the core user form does.
        $user = new \User();
        if (!\User::canView() || (int) $ID <= 0 || !$user->can((int) $ID, READ)) {
            return false;
        }

        $user->initForm($ID, $options);

        $auth_lines = [Auth::getMethodName($user->fields["authtype"], $user->fields["auths_id"])];
        if (!empty($user->fields["date_sync"])) {
            //TRANS: %s is the date of last sync
            $auth_lines[] = sprintf(
                __('Last synchronization on %s'),
                Html::convDateTime($user->fields["date_sync"]),
            );
        }
        if (!empty($user->fields["user_dn"])) {
            $auth_lines[] = sprintf(__('%1$s: %2$s'), __('User DN'), $user->fields["user_dn"]);
        }
        if ($user->fields['is_deleted_ldap']) {
            $auth_lines[] = __('User missing in LDAP directory');
        }

        // showForUser() echoes directly, so capture it for the template.
        ob_start();
        UserEmail::showForUser($user);
        $email_block = (string) ob_get_clean();

        $params = $options;
        // The form is posted to the plugin controller, which turns the update into a
        // solution on the tickets of the resource (front/user.form.php); the core user
        // form URL would silently drop that behaviour.
        $params['target'] = self::getFormURL();
        // Only a couple of fields are editable from this tab: deleting the user here
        // would be surprising, and the core form is one click away for that.
        $params['candel'] = false;

        TemplateRenderer::getInstance()->display('@resources/user_form.html.twig', [
            'item'         => $user,
            'params'       => $params,
            'resources_id' => $options['resourcesID'],
            'auth_lines'   => $auth_lines,
            'phone_label'  => Phone::getTypeName(1),
            'email_label'  => _n('Email', 'Emails', Session::getPluralNumber()),
            'email_block'  => $email_block,
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
     * Build the solution content of the resource tickets from the posted user form.
     *
     * Lists the additional fields, the phone and the emails that were filled in. Every value
     * is client supplied and injected into rich text: escape each one.
     *
     * @param array $post User form values
     *
     * @return string
     */
    public static function buildSolutionContent(array $post): string
    {
        $content = "";
        foreach ($post as $key => $value) {
            if (strpos($key, 'field') > 0 && !is_integer(strpos($key, 'plugin_ldapfields'))) {
                $field = new PluginFieldsField();
                if ($field->getFromDBByCrit(['name' => $key])) {
                    $content .= $field->getField('label') . " : " . htmlescape((string) $value) . '<br />';
                }
            } else {
                switch ($key) {
                    case 'phone':
                        $content .= __('Phone') . " : " . htmlescape((string) $value) . "<br />";
                        break;
                    case '_useremails':
                        if (is_array($value) && !empty($value)) {
                            $content .= _n('Email', 'Emails', 1) . " : ";
                            foreach ($value as $email) {
                                $content .= htmlescape((string) $email) . "<br />";
                            }
                        }
                        break;
                }
            }
        }

        return $content;
    }

    /**
     * Show for PDF a resource: the GLPI account linked to it
     *
     * @param PluginPdfSimplePDF $pdf   object for the output
     * @param Resource           $appli resource exported
     *
     * @return false|void
     */
    public static function pdfForResource(PluginPdfSimplePDF $pdf, Resource $appli)
    {
        global $DB;

        $ID = (int) $appli->fields['id'];

        if (!$appli->can($ID, READ)) {
            return false;
        }

        // The PDF prints the same account as the on-screen tab: the right to view users is
        // required, not only the resource one.
        if (!Session::haveRight(Resource::$rightname, READ) || !\User::canView()) {
            return false;
        }

        $iterator = $DB->request([
            'SELECT' => ['items_id'],
            'FROM'   => 'glpi_plugin_resources_resources_items',
            'WHERE'  => [
                'plugin_resources_resources_id' => $ID,
                'itemtype'                      => \User::class,
            ],
            'ORDER'  => 'id DESC',
            'LIMIT'  => 1,
        ]);
        $users_id = 0;
        foreach ($iterator as $row) {
            $users_id = (int) $row['items_id'];
        }

        // The last account linked, printed only if that very user may be read (entity
        // included), as the core user form requires. A \User instance on purpose: this
        // class reads the resource items table.
        $user = new \User();
        if ($users_id > 0 && !$user->can($users_id, READ)) {
            return false;
        }

        // The pdf plugin writes every argument as HTML (TCPDF writeHTMLCell): only the static
        // markup is kept, every label and stored value is escaped.
        $pdf->setColumnsSize(100);
        $pdf->displayTitle('<b>' . htmlescape(self::getTypeName(1)) . '</b>');

        [$addDisplayTitle, $addContent] = $users_id > 0 ? self::getFieldsForPdf($users_id) : [[], []];
        $titles = [
            __('Authentication'),
            __('Last synchronization'),
            __('User DN'),
            Phone::getTypeName(1),
            _n('Email', 'Emails', Session::getPluralNumber()),
        ];
        $titles = array_merge($titles, $addDisplayTitle);

        $nbTitle = count($titles);
        $columnSize = floor(100 / $nbTitle);
        $arrayColumn = [];
        for ($i = 0; $i < $nbTitle; $i++) {
            if (($columnSize * $nbTitle) + $i < 100) {
                $arrayColumn[] = $columnSize + 1;
            } else {
                $arrayColumn[] = $columnSize;
            }
        }
        call_user_func_array([$pdf, "setColumnsSize"], $arrayColumn);

        call_user_func_array([$pdf, "displayTitle"], self::escapeForPdf($titles));
        if ($users_id <= 0) {
            $pdf->displayLine(htmlescape(__('No results found')));
        } else {
            $auth = Auth::getMethodName($user->fields["authtype"], $user->fields["auths_id"]);
            $lastSync = "";
            $userDN = "";
            if (!empty($user->fields["date_sync"])) {
                $lastSync = Html::convDateTime($user->fields["date_sync"]);
            }
            if (!empty($user->fields["user_dn"])) {
                $userDN = $user->fields["user_dn"];
            }
            if ($user->fields['is_deleted_ldap']) {
                $userDN .= __('User missing in LDAP directory');
            }

            $phone = $user->fields['phone'];

            // Default address of the user: the lookup by user alone found nothing as soon as
            // the user had more than one address.
            $email = UserEmail::getDefaultForUser($users_id);

            $resultsDisplay = array_merge([$auth, $lastSync, $userDN, $phone, $email], $addContent);
            call_user_func_array([$pdf, "displayLine"], self::escapeForPdf($resultsDisplay));
        }

        $pdf->displaySpace();
    }

    /**
     * Labels and values of the Fields plugin container of the linked account, for the PDF.
     *
     * @param int $users_id linked account, already checked readable by the caller
     *
     * @return array{0: array<mixed>, 1: array<mixed>} the labels, then the values
     */
    private static function getFieldsForPdf(int $users_id): array
    {
        // The Fields classes only exist while that plugin is installed.
        if (
            !Plugin::isPluginActive('fields')
            || !class_exists(PluginFieldsContainer::class)
            || !class_exists(PluginFieldsField::class)
            || !class_exists(PluginFieldsLabelTranslation::class)
            || !class_exists(PluginFieldsProfile::class)
        ) {
            return [[], []];
        }

        $fieldContainer = new PluginFieldsContainer();
        if (!$fieldContainer->getFromDBByCrit(['itemtypes' => ['LIKE', '%User%']])) {
            return [[], []];
        }
        $container_id = (int) $fieldContainer->getID();

        // The Fields plugin grants each container per profile, and leaves it out of the user
        // form of a profile without READ on it: the PDF must not print it either.
        $profiles_id = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if (PluginFieldsProfile::getRightOnContainer($profiles_id, $container_id) < READ) {
            return [[], []];
        }

        $nameTable = "PluginFieldsUser" . $fieldContainer->getField('name');
        $itemUser = new $nameTable();
        if (!$itemUser->getFromDBByCrit(['items_id' => $users_id, 'itemtype' => \User::class])) {
            return [[], []];
        }

        $labels = [];
        $values = [];
        foreach ($itemUser->fields as $key => $field) {
            if (in_array($key, ['id', 'items_id', 'itemtype', 'plugin_fields_containers_id'], true)) {
                continue;
            }
            // Only the fields of the container checked above: a label must not come from
            // another container.
            $fieldFields = new PluginFieldsField();
            if (!$fieldFields->getFromDBByCrit(['name' => $key, 'plugin_fields_containers_id' => $container_id])) {
                continue;
            }
            $labels[] = PluginFieldsLabelTranslation::getLabelFor([
                'itemtype' => PluginFieldsField::class,
                'id'       => $fieldFields->getID(),
            ]);
            // A rich text value is stored as HTML: reduced to its text, then escaped with the
            // other values, so that the PDF does not print its tags literally.
            if ($fieldFields->getField('type') === 'richtext') {
                $field = RichText::getTextFromHtml((string) $field, false, true);
            }
            $values[] = $field;
        }

        return [$labels, $values];
    }

    /**
     * Escape values for the pdf plugin, which renders its arguments as HTML.
     *
     * @param array<mixed> $values
     *
     * @return array<string>
     */
    private static function escapeForPdf(array $values): array
    {
        return array_map(
            static fn($value) => htmlescape(is_scalar($value) ? (string) $value : ''),
            array_values($values),
        );
    }
}
