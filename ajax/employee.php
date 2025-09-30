<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2022 by the resources Development Team.

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

use GlpiPlugin\Resources\Client;
use GlpiPlugin\Resources\Resource_Change;

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

$resource_change = new Resource_Change();

if (isset($_POST['plugin_resources_clients_id'])) {
    if (Client::isSecurityCompliance($_POST['plugin_resources_clients_id'])) {
        $img = "<i style='color:green' class='ti ti-circle-check' alt=\"" . __('OK') . "\"></i>";
        $color = "color: green;";
    } else {
        $img = "<i style='color:red' class='ti ti-circle-x' alt=\"" . __('KO') . "\"></i>";
        $color = "color: red;";
    }
    echo "<span style='$color'>";
    echo __('Security compliance', 'resources') . "&nbsp;";
    echo $img;
    echo "</span>";
}
