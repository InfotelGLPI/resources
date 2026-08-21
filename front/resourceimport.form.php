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
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\ResourceImport;

Session::checkRight(ResourceImport::$rightname, READ);

$import = new Import();

$pluginResourcesResourceImport = new ResourceImport();

if (isset($_POST['save'])) {
    foreach ($_POST['select'] as $key => $selected) {
        if ($selected) {
            // Update
            if ($_POST['resource'][$key]) {
                // Authorize the Resource actually overwritten (global right + entity),
                // otherwise a POST could rewrite any resource of any entity by id.
                $resource = new Resource();
                $resource->check((int) $_POST['resource'][$key], UPDATE);

                $input = [
                    'resourceID' => $_POST['resource'][$key],
                    'datas' => $_POST['import'][$key]
                ];

                $pluginResourcesResourceImport->update($input);
                $pluginResourcesImportResource = new ImportResource();
                $pluginResourcesImportResource->delete(['id' => $key]);
            } //New
            else {
                $import->check(-1, CREATE, $_POST);
                $input = [
                    'importID' => $key,
                    'datas' => $_POST['import'][$key]
                ];

                $pluginResourcesResourceImport->add($input);
                $pluginResourcesImportResource = new ImportResource();
                $pluginResourcesImportResource->delete(['id' => $key]);
            }
        }
    }
    redirectWithParameters(ImportResource::getIndexUrl(), $_GET);
} elseif (isset($_POST["purge"])) {
    $import->check($_POST['id'], PURGE);
    $pluginResourcesResourceImport->delete($_POST);
    redirectWithParameters(ImportResource::getIndexUrl(), $_GET);
} elseif (isset($_POST["delete"])) {
    foreach ($_POST['select'] as $key => $selected) {
        if ($selected) {
            $pluginResourcesImportResource = new ImportResource();
            // Authorize the deletion of each staged import row (global right + entity)
            // instead of purging arbitrary ids straight from $_POST.
            $pluginResourcesImportResource->check((int) $key, PURGE);

            $input = [
                ImportResource::getIndexName() => $key
            ];

            $pluginResourcesImportResource->delete($input);
        }
    }
    redirectWithParameters(ImportResource::getIndexUrl(), $_GET);
}
throw new BadRequestHttpException();

function redirectWithParameters($url, array $parameters)
{
    // Build the query string with http_build_query() so values containing & or =
    // are URL-encoded instead of breaking the redirect target.
    $params = count($parameters) ? '?' . http_build_query($parameters) : '';
    Html::redirect($url . $params);
}
