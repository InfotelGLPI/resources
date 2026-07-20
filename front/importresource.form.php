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

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Resources\Import;
use GlpiPlugin\Resources\ImportResource;
use GlpiPlugin\Resources\ImportResourceData;

Session::checkLoginUser();

// checkLoginUser() is not authorization on GLPI 11, and none of these mutations
// (create/update/purge import rows, launch an import, wipe the staging database)
// are guarded by CommonDBTM. Gate every branch on the import feature right
// (plugin_resources_import), the same right the list controller enforces via
// Import::checkGlobal(). ImportResource::$rightname is never granted to any profile,
// so it must not be used here.
$importResource = new ImportResource();
if (isset($_POST["add"])) {
    Session::checkRight(Import::$rightname, CREATE);
    $importResource->add($_POST);
    Html::back();
} elseif (isset($_POST["purge"])) {
    Session::checkRight(Import::$rightname, PURGE);
    $importResource->delete($_POST);
    Html::back();
} elseif (isset($_POST["update"])) {
    Session::checkRight(Import::$rightname, UPDATE);
    $importResource->update($_POST);
    Html::back();
} elseif (isset($_POST["import-file"])) {
    Session::checkRight(Import::$rightname, CREATE);
    $importResource->importFileToVerify($_POST);
    Html::back();
} elseif (isset($_POST["verify-file"])) {
    Session::checkRight(Import::$rightname, UPDATE);
    $importResource->setFileVerify($_POST);
    Html::back();
} elseif (isset($_POST["reset-imports"])) {
    // POST-only (was GET): the CheckCsrfListener validates the token on POST, so the
    // destructive purge can no longer be triggered by a crafted link (CSRF).
    Session::checkRight(Import::$rightname, PURGE);
    $importResource->purgeDatabase();

    $importResourceDataDBTM = new ImportResourceData();
    $importResourceDataDBTM->purgeDatabase();
    Html::back();
}
throw new BadRequestHttpException();
