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

use GlpiPlugin\Resources\Resource_Item;
use GlpiPlugin\Resources\ResourceCard;
use GlpiPlugin\Servicecatalog\Main;
use GlpiPlugin\Resources\Menu;
use GlpiPlugin\Resources\Resource;

if (Session::getCurrentInterface() == 'central') {
    Html::header(Resource::getTypeName(2), '', "admin", Menu::class);
} else {
    if (Plugin::isPluginActive('servicecatalog')) {
        Main::showDefaultHeaderHelpdesk(Menu::getTypeName(2));
    } else {
        Html::helpHeader(Resource::getTypeName(2));
    }
}

if (isset($_POST['plugin_resources_resources_id'])) {
    $plugin_resources_resources_id = $_POST['plugin_resources_resources_id'];
} else {
    $resource_item = new Resource_Item();
    $resource = $resource_item->find([
        'itemtype' => 'User',
        'items_id' => $_SESSION['glpiID']
    ],
        [],
        [1]);

    $resource = reset($resource);
    $plugin_resources_resources_id = isset($resource['plugin_resources_resources_id']) ? $resource['plugin_resources_resources_id'] : 0;
}

if (Session::haveRight("plugin_resources", UPDATE)) {
    echo "<div class='center'>";
    echo "<form name='main' action=\"./resource.card.form.php\" method=\"post\">";
    echo "<table class='tab_cadre' width='31%'>";
    echo "<tr class='tab_bg_2 center'>";
    echo "<td>";
    Resource::dropdown([
        'name' => 'plugin_resources_resources_id',
        'display' => true,
        'entity' => $_SESSION['glpiactiveentities'],
        'value' => $plugin_resources_resources_id,
        'on_change' => 'main.submit();'
    ]);
    echo "</td>";
    echo "</tr>";
    echo "</table>";
    Html::closeForm();
    echo "</div>";
}

if ($plugin_resources_resources_id > 0) {
    ResourceCard::resourceCard($plugin_resources_resources_id);
} else {
    echo "<div class='center'><br><br>" .
        "<i  class='ti ti-info-circle' alt='information'></i>";
    echo "&nbsp;<b>" . __('Please select a user', 'resources') . "</b></div>";
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
