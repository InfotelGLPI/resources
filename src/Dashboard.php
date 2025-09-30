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

namespace GlpiPlugin\Resources;

use AllowDynamicProperties;
use CommonGLPI;
use Dropdown;
use GlpiPlugin\Mydashboard\Datatable;
use GlpiPlugin\Mydashboard\Menu as DashboardMenu;
use GlpiPlugin\Mydashboard\Widget;
use Toolbox;

/**
 * Class Dashboard
 */
#[AllowDynamicProperties]
class Dashboard extends CommonGLPI
{
    public $widgets = [];
    private $options;
    private $datas;
    private $form;

    /**
     * Dashboard constructor.
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        $this->options = $options;
        $this->interfaces = ["central"];
    }


    /**
     * @return \array[][]
     */
    public function getWidgetsForItem()
    {
        $widgets = [
            DashboardMenu::$USERS => [
                $this->getType() . "1" => [
                    "title" => __('New resource - checklist needs to verificated', 'resources'),
                    "type" => Widget::$TABLE,
                    "comment" => ""
                ],
                $this->getType() . "2" => [
                    "title" => __('Leaving resource - checklist needs to verificated', 'resources'),
                    "type" => Widget::$TABLE,
                    "comment" => ""
                ],
            ],
        ];

        return $widgets;
    }

    /**
     * @param $widgetId
     *
     * @return Datatable
     */
    public function getWidgetContentForItem($widgetId)
    {
        global $CFG_GLPI, $DB;

        switch ($widgetId) {
            case $this->getType() . "1":
                $query = Checklist::queryChecklists(true);
                $checklists = $DB->doQuery($query);
                $link = Toolbox::getItemTypeFormURL(Resource::class);
                $datas = [];

                if (!empty($checklists)) {
                    foreach ($checklists as $key => $checklist) {
                        $name = "<a href='" . $link . "?id=" . $checklist["plugin_resources_resources_id"] . "' target='_blank'>";
                        $name .= $checklist["resource_name"] . " " . $checklist["resource_firstname"] . "</a>";
                        $data["name"] = $name;

                        if ($checklist["date_begin"] <= date('Y-m-d') && !empty($checklist["date_begin"])) {
                            $data["date"] = "<div class='deleted'>" . $checklist["date_begin"] . "</div>";
                        } else {
                            $data["date"] = "<div class='plugin_resources_date_day_color'>";
                            $data["date"] .= $checklist["date_begin"];
                            $data["date"] .= "</div>";
                        }

                        $data["entity"] = Dropdown::getDropdownName("glpi_entities", $checklist['entities_id']);
                        $data["location"] = Dropdown::getDropdownName("glpi_locations", $checklist['locations_id']);
                        $data["contracttypes"] = Dropdown::getDropdownName(
                            "glpi_plugin_resources_contracttypes",
                            $checklist['plugin_resources_contracttypes_id']
                        );

                        $datas[] = $data;
                    }
                }

                $headers = [
                    Resource::getTypeName(1),
                    __('Arrival date', 'resources'),
                    __('Entity'),
                    __('Location'),
                    ContractType::getTypeName(1)
                ];

                $widget = new Datatable();
                $widget->setTabNames($headers);
                $widget->setTabDatas($datas);
                //               $widget->setOption("bSort", false);
                $widget->toggleWidgetRefresh();
                $widget->setWidgetTitle(
                    __('New resource - checklist needs to verificated', 'resources') . " : " . count($datas)
                );
                return $widget;
                break;

            case $this->getType() . "2":
                $query = Checklist::queryChecklists(true, 1);
                $checklists = $DB->doQuery($query);
                $link = Toolbox::getItemTypeFormURL(Resource::class);
                $datas = [];
                if (!empty($checklists)) {
                    foreach ($checklists as $key => $checklist) {
                        $name = "<a href='" . $link . "?id=" . $checklist["plugin_resources_resources_id"] . "' target='_blank'>";
                        $name .= $checklist["resource_name"] . " " . $checklist["resource_firstname"] . "</a>";
                        $data["name"] = $name;

                        if ($checklist["date_end"] <= date('Y-m-d') && !empty($checklist["date_end"])) {
                            $data["date"] = "<div class='deleted'>" . $checklist["date_end"] . "</div>";
                        } else {
                            $data["date"] = "<div class='plugin_resources_date_day_color'>";
                            $data["date"] .= $checklist["date_end"];
                            $data["date"] .= "</div>";
                        }

                        $data["entity"] = Dropdown::getDropdownName("glpi_entities", $checklist['entities_id']);
                        $data["location"] = Dropdown::getDropdownName("glpi_locations", $checklist['locations_id']);
                        $data["contracttypes"] = Dropdown::getDropdownName(
                            "glpi_plugin_resources_contracttypes",
                            $checklist['plugin_resources_contracttypes_id']
                        );

                        $datas[] = $data;
                    }
                }
                $headers = [
                    Resource::getTypeName(1),
                    __('Departure date', 'resources'),
                    __('Entity'),
                    __('Location'),
                    ContractType::getTypeName(1)
                ];

                $widget = new Datatable();
                $widget->setTabNames($headers);
                $widget->setTabDatas($datas);
                //               $widget->setOption("bSort", false);
                $widget->toggleWidgetRefresh();
                $widget->setWidgetTitle(
                    __('Leaving resource - checklist needs to verificated', 'resources') . " : " . count($datas)
                );
                return $widget;
                break;
        }
    }
}
