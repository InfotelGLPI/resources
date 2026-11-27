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

use CommonGLPI;
use Dropdown;
use Html;
use PluginPdfCommon;
use PluginPdfSimplePDF;

/**
 * Class ResourcePDF
 */
class ResourcePDF extends PluginPdfCommon
{
    /**
     * ResourcePDF constructor.
     *
     * @param \CommonGLPI|null $obj
     */
    public function __construct(?CommonGLPI $obj = null)
    {
        $this->obj = ($obj ? $obj : new Resource());
    }


    /**
     * @param \PluginPdfSimplePDF $pdf
     * @param Resource $res
     *
     * @return bool
     */
    public static function pdfMain(PluginPdfSimplePDF $pdf, Resource $res)
    {
        $ID = $res->getField('id');
        if (!$res->can($ID, READ)) {
            return false;
        }

        // The pdf plugin renders every argument as HTML (TCPDF writeHTMLCell): the bold and
        // italic labels are the only markup kept, every label and stored value is escaped.
        $label = static fn(string $text): string => '<b><i>' . htmlescape($text) . ' :</i></b> ';

        $pdf->setColumnsSize(50, 50);
        $col1 = '<b>' . htmlescape(__('ID') . ' ' . $res->fields['id']) . '</b>';
        if (isset($res->fields["date_declaration"])) {
            // getUserName() reads glpi_users: the plugin User class of this namespace reads
            // the resource items table instead.
            $col2 = htmlescape(
                __('Request date') . ' : ' . Html::convDate($res->fields["date_declaration"]) . ' '
                . __('Requester') . ' ' . getUserName((int) $res->fields["users_id_recipient"]),
            );
        } else {
            $col2 = '';
        }
        $pdf->displayTitle($col1, $col2);

        $pdf->displayLine(
            $label(__('Surname')) . htmlescape((string) $res->fields['name']),
            $label(__('First name')) . htmlescape((string) $res->fields['firstname']),
        );
        $pdf->displayLine(
            $label(__('Location')) . htmlescape(Dropdown::getDropdownName(
                'glpi_locations',
                $res->fields['locations_id'],
            )),
            $label(ContractType::getTypeName(1)) . htmlescape(Dropdown::getDropdownName(
                'glpi_plugin_resources_contracttypes',
                $res->fields['plugin_resources_contracttypes_id'],
            )),
        );

        $pdf->displayLine(
            $label(__('Resource manager', 'resources')) . htmlescape(getUserName((int) $res->fields["users_id"])),
            $label(Department::getTypeName(1)) . htmlescape(Dropdown::getDropdownName(
                'glpi_plugin_resources_departments',
                $res->fields["plugin_resources_departments_id"],
            )),
        );

        $pdf->displayLine(
            $label(__('Arrival date', 'resources')) . htmlescape(Html::convDate($res->fields["date_begin"])),
            $label(__('Departure date', 'resources')) . htmlescape(Html::convDate($res->fields["date_end"])),
        );

        $pdf->setColumnsSize(100);

        // Plain text field (textarea, no rich text editor), printed with displayLine(), which
        // writes its argument as it is. displayText() is meant for rich text: the pdf plugin
        // decodes the entities of its content after sanitizing it, which would turn the
        // escaped markup back into tags, and embeds the documents its images point to.
        $pdf->displayLine(
            $label(__('Description')) . nl2br(htmlescape((string) $res->fields['comment'])),
        );

        $pdf->displaySpace();
    }

    /**
     * @param array $options
     *
     * @return mixed
     */
    public function defineAllTabsPDF($options = [])
    {
        $onglets = parent::defineAllTabsPDF($options);
        unset($onglets[Choice::class . '####1']);
        unset($onglets[ReportConfig::class . '####1']);
        unset($onglets['Item_Problem$1']); // TODO add method to print linked Problems
        return $onglets;
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
        switch ($tab) {
            case '_main_':
                $item->show_PDF($pdf);
                break;

            default:
                return false;
        }
        return true;
    }
}
