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

use Glpi\Application\View\TemplateRenderer;
use Glpi\Search\Output\Spreadsheet;
use Glpi\Search\SearchEngine;
use Search;

/**
 * Drop-in replacement of the legacy Search::show*() helpers for the reports of the plugin.
 *
 * Since GLPI 11, those helpers only render the HTML and names outputs: the spreadsheet
 * outputs (PDF, CSV, ODS, XLSX) are built from a data array by displayData(). The HTML
 * output is still delegated to the core, the other ones are collected cell by cell and
 * exported by the core output when the footer is reached.
 */
class ReportExport
{
    /** @var string[] */
    private static array $headers = [];

    /** @var array<int, string[]> */
    private static array $rows = [];

    /** @var string[] */
    private static array $current_row = [];

    private static function isSpreadsheet($type): bool
    {
        // The display type is posted by the report form: an unknown value is left to the
        // core helpers instead of reaching getOutputForLegacyKey(), which throws on it.
        $exports = [Search::PDF_OUTPUT_LANDSCAPE, Search::PDF_OUTPUT_PORTRAIT, Search::CSV_OUTPUT, Search::ODS_OUTPUT, Search::XLSX_OUTPUT];
        return in_array((int) $type, $exports, true)
            && SearchEngine::getOutputForLegacyKey((int) $type) instanceof Spreadsheet;
    }

    /**
     * Display the export bar of a report, or its "no results" message when it is empty.
     *
     * @param string $title  report title
     * @param string $target report URL, the form posts back to it
     * @param int    $start  current pager offset
     * @param int    $total  number of result lines
     *
     * @return string the posted criteria as a query string, for Html::printPager()
     */
    public static function showToolbar(string $title, string $target, int $start, int $total): string
    {
        $hidden_fields = [];
        $param = [];
        foreach ($_POST as $key => $val) {
            // The token of the previous request is spent, the template adds a fresh one.
            if ($key === '_glpi_csrf_token') {
                continue;
            }
            $values = is_array($val) ? $val : [$key => $val];
            foreach ($values as $k => $v) {
                $name = is_array($val) ? $key . '[' . $k . ']' : (string) $key;
                $hidden_fields[] = ['name' => $name, 'value' => (string) $v];
                $param[] = $name . '=' . urlencode((string) $v);
            }
        }

        ob_start();
        \Dropdown::showOutputFormat();
        $output_format = (string) ob_get_clean();

        TemplateRenderer::getInstance()->display('@resources/report_toolbar.html.twig', [
            'title'         => $title,
            'action'        => $target . '?start=' . $start,
            'total'         => $total,
            'hidden_fields' => $hidden_fields,
            'output_format' => $output_format,
        ]);

        return implode('&', $param);
    }

    public static function showHeader($type, $rows, $cols, $fixed = 0)
    {
        if (!self::isSpreadsheet($type)) {
            return Search::showHeader($type, $rows, $cols, $fixed);
        }
        self::$headers = [];
        self::$rows = [];
        self::$current_row = [];
        return '';
    }

    public static function showHeaderItem($type, $value, &$num, $linkto = "", $issort = 0, $order = "", $options = "")
    {
        if (!self::isSpreadsheet($type)) {
            return Search::showHeaderItem($type, $value, $num, $linkto, $issort, $order, $options);
        }
        self::$headers[] = (string) $value;
        $num++;
        return '';
    }

    public static function showNewLine($type, $odd = false, $is_deleted = false)
    {
        if (!self::isSpreadsheet($type)) {
            return Search::showNewLine($type, $odd, $is_deleted);
        }
        self::$current_row = [];
        return '';
    }

    public static function showItem($type, $value, &$num, $row, $extraparam = '')
    {
        if (!self::isSpreadsheet($type)) {
            return Search::showItem($type, $value, $num, $row, $extraparam);
        }
        self::$current_row[] = (string) ($value ?? '');
        $num++;
        return '';
    }

    public static function showEndLine($type, bool $is_header_line = false)
    {
        if (!self::isSpreadsheet($type)) {
            return Search::showEndLine($type, $is_header_line);
        }
        if (count(self::$current_row) > 0) {
            self::$rows[] = self::$current_row;
        }
        self::$current_row = [];
        return '';
    }

    public static function showFooter($type, $title = "", $count = null)
    {
        if (!self::isSpreadsheet($type)) {
            return Search::showFooter($type, $title, $count);
        }

        $itemtype = Resource::class;
        $data = SearchEngine::prepareDataForSearch($itemtype, [
            'start'         => 0,
            'is_deleted'    => 0,
            'as_map'        => 0,
            'criteria'      => [],
            'metacriteria'  => [],
            'display_type'  => (int) $type,
            'hide_controls' => true,
        ]);

        $cols = [];
        foreach (self::$headers as $index => $header) {
            $cols[] = ['name' => $header, 'itemtype' => $itemtype, 'id' => $index + 1];
        }
        $rows = [];
        foreach (self::$rows as $row) {
            $cells = [];
            foreach ($row as $index => $value) {
                $cells[$itemtype . '_' . ($index + 1)] = ['displayname' => $value];
            }
            $rows[] = $cells;
        }

        $data['data'] = [
            'totalcount' => count($rows),
            'count'      => count($rows),
            'search'     => '',
            'cols'       => $cols,
            'rows'       => $rows,
        ];

        SearchEngine::getOutputForLegacyKey((int) $type)->displayData($data, []);
        return '';
    }
}
