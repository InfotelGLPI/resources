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


use GlpiPlugin\Resources\ImportResource;

header('Content-Type: application/json');
Html::header_nocache();

Session::checkLoginUser();

if (isset($_GET['page']) && isset($_GET['file'])) {
    $ImportResource = new ImportResource();

    $absoluteFilePath = $ImportResource::getLocationOfVerificationFiles() . "/" . $_GET['file'];

    $temp = $ImportResource->readCSVLines($absoluteFilePath, 0, 1);
    $header = array_shift($temp);

    $importId = $ImportResource->checkHeader($header);

    $listParams = $ImportResource->fillVerifyParams(
        1,
        INF,
        $_GET['page'],
        $absoluteFilePath,
        $importId,
        $_GET['file'],
        $ImportResource::DISPLAY_STATISTICS,
        true
    );

    switch ($_GET['page']) {
        case ImportResource::VERIFY_FILE:
            $ImportResource->showVerificationFileList($listParams);
            break;
        case ImportResource::VERIFY_GLPI:
            $ImportResource->showVerificationGLPIFromFileList($listParams);
            break;
    }
}
