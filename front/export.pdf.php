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

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\Resource_Item;

if (Session::getCurrentInterface()
    && (Session::getCurrentInterface() == "helpdesk")) {
    Session::checkHelpdeskAccess();
} else {
    Session::checkCentralAccess();
}

global $DB;

// Generating the PDF writes a Document and an export row: POST only, so that the
// request goes through the CSRF check (GET is never validated).
if (isset($_POST['generate_pdf']) && isset($_POST['users_id'])) {
    // $_POST['users_id'] is attacker-controlled and was streamed back as a document
    // without any ownership/right check (IDOR). Authorize it:
    //  - simplified (helpdesk) interface: a user may only export their OWN equipment;
    //  - central interface: exporting an arbitrary user's data requires the resources
    //    READ right (checkCentralAccess() only asserts central access, not the plugin right).
    if (Session::getCurrentInterface() === 'helpdesk') {
        $users_id = (int) Session::getLoginUserID();
    } else {
        $users_id = (int) $_POST['users_id'];
        // The global plugin_resources READ right does not enforce entity scope: require
        // ->can($id, READ) (right + checkEntity) on the resource of the button, and that the
        // exported user is the one linked to it (Resource_Item), to prevent exporting an
        // arbitrary user's equipment across entities (horizontal IDOR). The resource used to
        // be looked up on its users_id, which is its manager and not the linked user: the
        // restitution form of a leaving employee was always refused.
        $resource    = new Resource();
        $resource_id = (int) ($_POST['plugin_resources_resources_id'] ?? 0);
        if ($resource_id <= 0
            || !$resource->can($resource_id, READ)
            || countElementsInTable(Resource_Item::getTable(), [
                'plugin_resources_resources_id' => $resource_id,
                'itemtype'                      => User::class,
                'items_id'                      => $users_id,
            ]) === 0) {
            throw new AccessDeniedHttpException();
        }
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
                    'users_id' => $users_id,
                ],
                'ORDERBY' => 'id DESC',
                'LIMIT' => 1,
            ]) as $data
        ) {
            $doc = new Document();
            if ($doc->getFromDB($data['documents_id'])) {
                if (!empty($doc->fields['filepath'])) {
                    $file = GLPI_DOC_DIR . "/" . $doc->fields['filepath'];

                    // File name and MIME type are raw database values that used to be
                    // interpolated into hand-written headers: a quote in the name broke out
                    // of the Content-disposition filename, and an attacker-chosen MIME type
                    // got the file rendered inline instead of downloaded. The core helper
                    // re-checks the path against GLPI_DOC_DIR, encodes the file name and
                    // only inlines images and PDF documents.
                    // Returned to LegacyFileLoadController rather than ->send(), which
                    // closes its output buffer and logs an "unexpected output" warning.
                    return Toolbox::getFileAsResponse(
                        $file,
                        $doc->fields['filename'],
                        $doc->fields['mime'],
                    );
                }
            }
        }
    }
}
