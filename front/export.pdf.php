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

if (Session::getCurrentInterface()
    && (Session::getCurrentInterface() == "helpdesk")) {
    Session::checkHelpdeskAccess();
} else {
    Session::checkCentralAccess();
}

global $DB;

if (isset($_GET['generate_pdf']) && isset($_GET['users_id'])) {
    // $_GET['users_id'] is attacker-controlled and was streamed back as a document
    // without any ownership/right check (IDOR). Authorize it:
    //  - simplified (helpdesk) interface: a user may only export their OWN equipment;
    //  - central interface: exporting an arbitrary user's data requires the resources
    //    READ right (checkCentralAccess() only asserts central access, not the plugin right).
    if (Session::getCurrentInterface() === 'helpdesk') {
        $users_id = (int) Session::getLoginUserID();
    } else {
        Session::checkRight('plugin_resources', READ);
        $users_id = (int) $_GET['users_id'];
    }
    $PluginUseditemsexportExport = new PluginUseditemsexportExport();
    if ($PluginUseditemsexportExport::generatePDF($users_id)) {
        $dbu = new DbUtils();
        $table = $dbu->getTableForItemType('PluginUseditemsexportExport');
        foreach (
            $DB->request([
                'SELECT' => 'documents_id',
                'FROM' => $table,
                'WHERE' => [
                    'users_id' => $users_id
                ],
                'ORDERBY' => 'id DESC',
                'LIMIT' => 1
            ]) as $data
        ) {
            $doc = new Document();
            if ($doc->getFromDB($data['documents_id'])) {
                if (!empty($doc->fields['filepath'])) {
                    $file = GLPI_DOC_DIR . "/" . $doc->fields['filepath'];

                    if (!file_exists($file)) {
                        die("Error file " . $file . " does not exist");
                    }
                    // Now send the file with header() magic
                    header("Expires: Mon, 26 Nov 1962 00:00:00 GMT");
                    header('Pragma: private'); /// IE BUG + SSL
                    header('Cache-control: private, must-revalidate'); /// IE BUG + SSL
                    header("Content-disposition: filename=\"" . $doc->fields['filename'] . "\"");
                    header("Content-type: " . $doc->fields['mime']);

                    readfile($file) or die ("Error opening file $file");
                }
            }
        }
    }
}
