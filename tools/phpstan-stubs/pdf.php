<?php

/*
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2015-2026 by the resources Development Team.

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

/**
 * PHPStan-only stub for the optional `pdf` plugin dependency.
 *
 * resources integrates with the pdf plugin only when it is installed. The stub
 * mirrors the public API resources relies on so static analysis stays
 * independent of the deployment layout (marketplace/, plugins/, or absent).
 * It is never loaded at runtime and is stripped from the release archive (tools/).
 *
 * setColumnsSize()/setColumnsAlign()/displayTitle()/displayLine() are variadic
 * at runtime (func_get_args()); they are declared with `...$args` here so the
 * many call sites in resources type-check correctly.
 */

class PluginPdfSimplePDF
{
    public function __construct($format = 'A4', $orient = '') {}
    public function setHeader($msg) {}
    public function render() {}
    public function output($name = false) {}
    public function newPage() {}
    public function setColumnsSize(...$args) {}
    public function setColumnsAlign(...$args) {}
    public function displayBox($gray) {}
    public function displayTitle(...$args) {}
    public function displayLine(...$args) {}
    public function displayLink($name, $URL) {}
    public function displayText($name, $content = '', $minline = 3, $maxline = 100) {}
    public function displaySpace($nb = 1) {}
    public function addPngFromFile($image, $dst_w, $dst_h) {}
}

abstract class PluginPdfCommon extends CommonGLPI
{
    protected $obj = null;
    protected $pdf = null;

    public static $rightname = 'plugin_pdf';

    public function __construct(?CommonGLPI $obj = null) {}
    public function defineAllTabsPDF($options = [])
    {
        return [];
    }
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {}
    public static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab) {}
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {}
    public static function pdfNote(PluginPdfSimplePDF $pdf, CommonDBTM $item) {}
    public static function mainTitle(PluginPdfSimplePDF $pdf, $item) {}
    public static function mainLine(PluginPdfSimplePDF $pdf, $item, $field) {}
}
