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

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Resources\ImportResource;
use GlpiPlugin\Resources\ImportResourceData;

Session::checkLoginUser();

$importResource = new ImportResource();
if (isset($_POST["add"])) {
    $importResource->add($_POST);
    Html::back();
} elseif (isset($_POST["purge"])) {
    $importResource->delete($_POST);
    Html::back();
} elseif (isset($_POST["update"])) {
    $importResource->update($_POST);
    Html::back();
} elseif (isset($_POST["import-file"])) {
    $importResource->importFileToVerify($_POST);
    Html::back();
} elseif (isset($_POST["verify-file"])) {
    $importResource->setFileVerify($_POST);
    Html::back();
} elseif (isset($_GET["reset-imports"])) {
    $importResource->purgeDatabase();

    $importResourceDataDBTM = new ImportResourceData();
    $importResourceDataDBTM->purgeDatabase();
    Html::back();
}
throw new BadRequestHttpException();
