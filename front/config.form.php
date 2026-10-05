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

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Resources\Config;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\ResourceBadge;
use GlpiPlugin\Resources\TicketCategory;
use GlpiPlugin\Resources\TransferEntity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

Session::checkRight(\Config::$rightname, UPDATE);

if (Plugin::isPluginActive("resources")) {
    $cat = new TicketCategory();
    $transferEntity = new TransferEntity();
    $resourceBadge = new ResourceBadge();
    $config = new Config();

    if (!empty($_POST['hide_fieds_arrival_form'])) {
        $_POST['hide_fieds_arrival_form'] = json_encode($_POST['hide_fieds_arrival_form']);
    }

    if (isset($_POST["add_ticket"])) {
        $cat->check(-1, CREATE, $_POST);
        // The posted category is stored and later applied to the created tickets: it must
        // exist and belong to an entity the current user can reach, as the dropdown offers.
        $itilcategories_id = (int) ($_POST['ticketcategories_id'] ?? 0);
        $itilcategory = new ITILCategory();
        if ($itilcategories_id > 0) {
            if (
                !$itilcategory->getFromDB($itilcategories_id)
                || !Session::haveAccessToEntity($itilcategory->getEntityID(), $itilcategory->isRecursive())
            ) {
                throw new NotFoundHttpException();
            }
            $cat->addTicketCategory($itilcategories_id);
        }
        Html::back();
    } elseif (isset($_POST["delete_ticket"])) {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $cat->check($id, PURGE);
            $cat->delete(['id' => $id], true);
        }
        Html::back();
    } elseif (isset($_POST["add_transferentity"])) {
        $transferEntity->check(-1, UPDATE, $_POST);
        $transferEntity->add($_POST);
        Html::back();
    } elseif (isset($_POST["update_setup"])) {
        $config->check(-1, UPDATE, $_POST);
        $config->update($_POST);
        Html::back();
    } else {
        Html::header(Resource::getTypeName(2), '', "admin", Menu::class);
        //setup
        $config->display($_GET);
    }
} else {
    Html::header(__s('Setup'), '', "config", "plugin");
    TemplateRenderer::getInstance()->display('@resources/alert_warning.html.twig', [
        'message' => __('Please activate the plugin', 'resources'),
    ]);
    Html::footer();
}

Html::footer();
