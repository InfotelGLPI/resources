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
use Glpi\Toolbox\DataExport;
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
            $values = is_array($val) ? $val : [$key => $val];
            foreach ($values as $k => $v) {
                $name = is_array($val) ? $key . '[' . $k . ']' : (string) $key;
                $hidden_fields[] = ['name' => $name, 'value' => (string) $v];
                // The pager links are plain GET: the CSRF token has nothing to do there and
                // would leak through the URL. Names are encoded like the values.
                if ($key !== '_glpi_csrf_token') {
                    $param[] = urlencode($name) . '=' . urlencode((string) $v);
                }
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

    /**
     * Display one cell of a report.
     *
     * The HTML output writes the value into the cell as it is: a plain value is therefore
     * escaped, so that a label read from the database cannot carry markup into the page.
     * Markup built by the caller (getLink(), a link whose parts are escaped) is passed with
     * $is_html, and only that markup. The spreadsheet outputs (PDF, CSV, ODS, XLSX) write
     * text: the cell is reduced to its text here, entities decoded and tags dropped.
     *
     * @param int|string $type       display type (Search::*_OUTPUT)
     * @param mixed      $value      cell content, plain text unless $is_html
     * @param int        $num        column counter
     * @param int        $row        row number
     * @param string     $extraparam attributes of the cell, static markup
     * @param bool       $is_html    the value is trusted HTML, written as it is
     *
     * @return string
     */
    public static function showItem($type, $value, &$num, $row, $extraparam = '', bool $is_html = false)
    {
        $value = is_scalar($value) ? (string) $value : '';
        if (!$is_html) {
            $value = htmlescape($value);
        }

        if (!self::isSpreadsheet($type)) {
            return Search::showItem($type, $value, $num, $row, $extraparam);
        }
        // Same conversion as the core applies to its own search results: the escaped value
        // gets its characters back, a trusted link only keeps its label.
        self::$current_row[] = DataExport::normalizeValueForTextExport($value);
        $num++;
        return '';
    }

    /**
     * Sort link of a column header of a report.
     *
     * Built from the parameters of the report own criteria (AutoReport::getRequestParameters()),
     * not from the whole request: $_REQUEST may hold cookies and any parameter name a link
     * carries. The URL is returned unescaped, the core header escapes it as an attribute;
     * joining the parameters with "&amp;" here made it print "&amp;amp;" and broke the link.
     *
     * @param string               $target   URL of the report
     * @param array<string, mixed> $criteria parameters of the report criteria, by name
     * @param string               $column   column to sort on
     * @param string               $order    sort direction
     *
     * @return string
     */
    public static function getSortLink(string $target, array $criteria, string $column, string $order): string
    {
        $params = [];
        foreach ($criteria as $name => $value) {
            if (in_array($name, ['sort', 'order'], true) || !(is_scalar($value) || is_array($value))) {
                continue;
            }
            $params[$name] = is_bool($value) ? (int) $value : $value;
        }
        $params['sort']  = $column;
        $params['order'] = $order === 'DESC' ? 'DESC' : 'ASC';

        return $target . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
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
                // A string, already text: the core only converts the array cells of a search
                // result, those whose column carries a search option, and writes any other
                // value into the cell as it is (an array makes the export fail).
                $cells[$itemtype . '_' . ($index + 1)] = $value;
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
