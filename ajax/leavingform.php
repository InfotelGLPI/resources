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

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Resources\LeavingInformation;
use GlpiPlugin\Resources\Resource;
use GlpiPlugin\Resources\ContractType;
use GlpiPlugin\Resources\Config;

if (strpos($_SERVER['PHP_SELF'], "leavingform.php")) {
    header("Content-Type: text/html; charset=UTF-8");
    Html::header_nocache();
}
Session::checkLoginUser();

if ($_POST['plugin_resources_resources_id'] > 0) {
    $contracttype = new ContractType();
    $resource = new Resource();
    $resource->getFromDB($_POST['plugin_resources_resources_id']);
    $contracttype->getFromDB($resource->fields['plugin_resources_contracttypes_id']);
    if (isset($contracttype->fields['use_resignation_form']) && $contracttype->fields['use_resignation_form']) {
        $leavinginformation = new LeavingInformation();
        $config = new Config();
        if (($config->getField('sales_manager') != "")) {
            $tableProfileUser = Profile_User::getTable();
            $tableUser = User::getTable();
            $profile_User = new  Profile_User();
            $prof = [];
            foreach (json_decode($config->getField('sales_manager')) as $profs) {
                $prof[$profs] = $profs;
            }

            $ids = join("','", $prof);
            $restrict = getEntitiesRestrictCriteria(
                $tableProfileUser,
                'entities_id',
                $resource->fields["entities_id"],
                true
            );
            $restrict = array_merge([$tableProfileUser . ".profiles_id" => [$ids]], $restrict);
            $profiles_User = $profile_User->find($restrict);
            $used = [];
            foreach ($profiles_User as $profileUser) {
                $user = new User();
                $user->getFromDB($profileUser["users_id"]);
                $used[$profileUser["users_id"]] = $user->getFriendlyName();
            }
            TemplateRenderer::getInstance()->display('@resources/leavinginformation.html.twig', [
                'item' => $leavinginformation,
                'params' => [
                    'plugin_resources_resources_id' => $_POST['plugin_resources_resources_id'],
                    'default_button' => true,
                    'element_sales' => $used,

                ],
            ]);
//            Dropdown::showFromArray("users_id_sales", $used, ['value' => $resource->fields["users_id_sales"], 'display_emptychoice' => true]);;
        } else {
            TemplateRenderer::getInstance()->display('@resources/leavinginformation.html.twig', [
                'item' => $leavinginformation,
                'params' => [
                    'plugin_resources_resources_id' => $_POST['plugin_resources_resources_id'],
                    'default_button' => true,
                    'right_sales' => true,
                ],
            ]);

//            User::dropdown(['value'       => $resource->fields["users_id_sales"],
//                'name'        => "users_id_sales",
//                'entity'      => $resource->fields["entities_id"],
//                'entity_sons' => true,
//                'right'       => 'all']);
        }
    } else {
        echo "<div class='row'>";

        echo "<div class='col-md-4 mb-2'>";
        echo __('Departure date', 'resources');
        echo "</div>";
        echo "<div class='col-md-4 mb-2'>";
        Html::showDateField("date_end", ['value' => date("Y-m-d")]);
        echo "</div>";
        echo "</div>";

        echo "<div class='row'>";

        echo "<div class='col-md-4 mb-2'>";
        echo __('Resource manager', 'resources');
        echo "</div>";
        echo "<div class='col-md-4 mb-2'>";
        User::dropdown(['name' => 'remove_manager', 'right' => 'all']);
        echo "</div>";
        echo "</div>";
    }
}

