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

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Resources\Import;
use GlpiPlugin\Resources\ImportResource;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\ResourceImport;

Session::checkRight(ResourceImport::$rightname, READ);

$import = new Import();

$pluginResourcesResourceImport = new ResourceImport();

if (isset($_POST['save'])) {
    if (!is_array($_POST['select'] ?? null)) {
        throw new BadRequestHttpException();
    }
    // Whatever the branch taken below, every processed row ends up removed from the
    // staging queue: that is a destructive operation driven by ids that come straight
    // from the client. ImportResource declares a rightname of its own that
    // Profile::getAllRights() never publishes, so ImportResource::check() can never be
    // satisfied by any profile; gate the purge on plugin_resources_import instead, the
    // right that is actually provisioned and that already guards this screen.
    Session::checkRight(ResourceImport::$rightname, PURGE);

    foreach ($_POST['select'] as $key => $selected) {
        if ($selected) {
            // Resolve the staged row before doing anything with the posted id: a key that
            // matches no row must reach neither add()/update() nor delete().
            $pluginResourcesImportResource = new ImportResource();
            if (!$pluginResourcesImportResource->getFromDB((int) $key)) {
                continue;
            }

            // add()/update() iterate over this, so refuse a scalar here instead of
            // letting foreach() fail deeper down.
            $datas = $_POST['import'][$key] ?? [];
            if (!is_array($datas)) {
                throw new BadRequestHttpException();
            }

            // Update
            if (!empty($_POST['resource'][$key])) {
                // Authorize the Resource actually overwritten (global right + entity),
                // otherwise a POST could rewrite any resource of any entity by id.
                $resource = new Resource();
                $resource->check((int) $_POST['resource'][$key], UPDATE);

                $input = [
                    'resourceID' => (int) $_POST['resource'][$key],
                    'datas' => $datas,
                ];

                $pluginResourcesResourceImport->update($input);
                $pluginResourcesImportResource->delete(['id' => (int) $key]);
            } //New
            else {
                $import->check(-1, CREATE, $_POST);
                $input = [
                    'importID' => (int) $key,
                    'datas' => $datas,
                ];

                $pluginResourcesResourceImport->add($input);
                $pluginResourcesImportResource->delete(['id' => (int) $key]);
            }
        }
    }
    redirectWithParameters(ImportResource::getIndexUrl(), $_GET);
} elseif (isset($_POST["purge"])) {
    $import->check($_POST['id'], PURGE);
    $pluginResourcesResourceImport->delete($_POST);
    redirectWithParameters(ImportResource::getIndexUrl(), $_GET);
} elseif (isset($_POST["delete"])) {
    if (!is_array($_POST['select'] ?? null)) {
        throw new BadRequestHttpException();
    }
    // Same rightname caveat as the save branch: ImportResource::$rightname is granted to
    // nobody, so the purge is gated on plugin_resources_import.
    Session::checkRight(ResourceImport::$rightname, PURGE);

    foreach ($_POST['select'] as $key => $selected) {
        if ($selected) {
            $pluginResourcesImportResource = new ImportResource();
            if (!$pluginResourcesImportResource->getFromDB((int) $key)) {
                continue;
            }

            $input = [
                ImportResource::getIndexName() => (int) $key,
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
