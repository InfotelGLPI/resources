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

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Resources\Import;
use GlpiPlugin\Resources\ImportResource;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

Session::checkLoginUser();
if (!isset($_GET["type"])) {
    $_GET["type"] = 0;
}

Html::header(Menu::getTypeName(2), '', "admin", Menu::class);

$import = new Import();
$import->checkGlobal(READ);

$importResource = new ImportResource();

if ($import->canView()) {
    if (isset($_POST['delete_file'])) {
        ImportResource::deleteFile($_POST['selected-file']);
    }

    $params = [
        "type" => $_GET['type'],
        "start" => 0
    ];

    if (isset($_GET['start'])) {
        $params['start'] = $_GET['start'];
    }

    if (isset($_GET['filter'])) {
        $params['filter'] = $_GET['filter'];
    }

    if (isset($_POST['glpilist_limit'])) {
        $params['limit'] = $_POST['glpilist_limit'];
    } elseif (isset($_SESSION['glpilist_limit'])) {
        $params['limit'] = $_SESSION['glpilist_limit'];
    } else {
        $params['limit'] = ImportResource::DEFAULT_LIMIT;
    }

    if (isset($_POST['_file_to_compare']) && count($_POST['_file_to_compare']) == 1) {
        $params['filename'] = $_POST['_file_to_compare'][0];
    } elseif (isset($_GET['_file_to_compare']) && count($_GET['_file_to_compare']) == 1) {
        $params['filename'] = $_GET['_file_to_compare'][0];
    }

    $dropdownName = ImportResource::SELECTED_FILE_DROPDOWN_NAME;

    if (isset($_POST[$dropdownName]) && !empty($_POST[$dropdownName])) {
        $params[$dropdownName] = $_POST[$dropdownName];
    } elseif (isset($_GET[$dropdownName]) && !empty($_GET[$dropdownName])) {
        $params[$dropdownName] = $_GET[$dropdownName];
    }

    $dropdownName = ImportResource::SELECTED_IMPORT_DROPDOWN_NAME;

    if (isset($_POST[$dropdownName]) && !empty($_POST[$dropdownName])) {
        $params[$dropdownName] = $_POST[$dropdownName];
    } elseif (isset($_GET[$dropdownName]) && !empty($_GET[$dropdownName])) {
        $params[$dropdownName] = $_GET[$dropdownName];
    }

    $importResource->displayPageByType($params);
} else {
    throw new AccessDeniedHttpException();
}

Html::footer();
