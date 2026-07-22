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
 * PHPStan-only stub for the optional `reports` plugin dependency.
 *
 * resources integrates with the reports plugin only when it is installed. The
 * stub mirrors the public API resources relies on so static analysis stays
 * independent of the deployment layout (marketplace/, plugins/, or absent).
 * It is never loaded at runtime and is stripped from the release archive (tools/).
 */

namespace GlpiPlugin\Reports;

use CommonDBTM;

abstract class AutoCriteria
{
    protected $name = "";

    public function __construct($report, $name, $sql_field = '', $label = null) {}
    public function getReport() {}
    public function getParameter($parameter) {}
    public function getCriteriaLabel($parameter = '') {}
    public function getName() {}
    public function addParameter($name, $value) {}
    public function addCriteriaLabel($name, $label) {}
    abstract public function setDefaultValues();
    abstract public function displayCriteria();
}

class Report extends CommonDBTM
{
    public static function setReportsTitles($reports = []) {}
}
