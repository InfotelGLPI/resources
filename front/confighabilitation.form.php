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

use GlpiPlugin\Resources\ConfigHabilitation;
use GlpiPlugin\Servicecatalog\Main;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

if (Session::getCurrentInterface() == 'central') {
    //from central
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class);
} else {
    //from helpdesk
    if (Plugin::isPluginActive('servicecatalog')) {
        Main::showDefaultHeaderHelpdesk(Menu::getTypeName(2));
    } else {
        Html::helpHeader(Resource::getTypeName(2));
    }
}

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}

$habilitation = new ConfigHabilitation();

if (isset($_POST['add_metademand'])) {
    $habilitation->check(-1, UPDATE, $_POST);
    if ($_POST['plugin_metademands_metademands_id']
        && isset($_POST['action'])
        && $_POST['action']) {
        $habilitation->add($_POST);
    }
    Html::back();

} elseif (isset($_GET['menu'])) {
    if ($habilitation->canView() || Session::haveRight("config", UPDATE)) {
        $habilitation->showMenu();
    }
} elseif (isset($_GET['config'])) {
    if (Plugin::isPluginActive("metademands")) {
        if ($habilitation->canView()) {
            $habilitation->showFormHabilitation();
        }
    } else {
        Html::header(__('Setup'), '', "config", "plugin");
        echo "<div class='alert alert-important alert-warning d-flex'>";
        echo "<b>" . __('Please activate the plugin metademand', 'resources') . "</b></div>";
    }
} elseif (isset($_GET['new'])) {
    if (Plugin::isPluginActive("metademands")) {
        $data = $habilitation->find([
            'entities_id' => $_SESSION['glpiactive_entity'],
            'action' => ConfigHabilitation::ACTION_ADD
        ]);
        $data = array_shift($data);
        if (!empty($data["plugin_metademands_metademands_id"])) {
            Html::redirect(
                PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?metademands_id=" . $data["plugin_metademands_metademands_id"] . "&tickets_id=0&step=2"
            );
        } else {
            echo "<div class='center'><br><br>";
            echo "<b>" . __('No advanced request found', 'resources') . "</b></div>";
        }
    } else {
        Html::header(__('Setup'), '', "config", "plugin");
        echo "<div class='alert alert-important alert-warning d-flex'>";
        echo "<b>" . __('Please activate the plugin metademand', 'resources') . "</b></div>";
    }
} elseif (isset($_GET['delete'])) {
    if (Plugin::isPluginActive("metademands")) {
        $data = $habilitation->find([
            'entities_id' => $_SESSION['glpiactive_entity'],
            'action' => ConfigHabilitation::ACTION_ADD
        ]);
        $data = array_shift($data);

        if (!empty($data["plugin_metademands_metademands_id"])) {
            Html::redirect(
                PLUGIN_METADEMANDS_WEBDIR . "/front/wizard.form.php?metademands_id=" . $data["plugin_metademands_metademands_id"] . "&tickets_id=0&step=2"
            );
        } else {
            echo "<div class='center'><br><br>";
            echo "<b>" . __('No advanced request found', 'resources') . "</b></div>";
        }
    } else {
        Html::header(__('Setup'), '', "config", "");
        echo "<div class='alert alert-important alert-warning d-flex'>";
        echo "<b>" . __('Please activate the plugin metademand', 'resources') . "</b></div>";
    }
}

if (Session::getCurrentInterface() != 'central'
    && Plugin::isPluginActive('servicecatalog')) {
    Main::showNavBarFooter('resources');
}

if (Session::getCurrentInterface() == 'central') {
    Html::footer();
} else {
    Html::helpFooter();
}
