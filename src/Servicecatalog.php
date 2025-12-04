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

use CommonGLPI;
use Session;

/**
 * Class Servicecatalog
 */
class Servicecatalog extends CommonGLPI
{
    public static $rightname = 'plugin_resources';

    public $dohistory = false;

    /**
     * @return bool
     */
    public static function canUse()
    {
        return Session::haveRight(self::$rightname, READ);
    }

    /**
     * @return string
     */
    public static function getMenuLink()
    {
        return PLUGIN_RESOURCES_WEBDIR . "/front/menu.php";
    }

    /**
     * @return string
     */
    public static function getNavBarLink()
    {

        return PLUGIN_RESOURCES_WEBDIR . "/front/menu.php";
    }

    /**
     * @return string
     */
    public static function getMenuLogo()
    {
        return Resource::getIcon();
    }

    /**
     * @return string
     */
    public static function getMenuTitle()
    {
        return __('Manage human resources', 'resources');
    }


    /**
     * @return string
     */
    public static function getMenuComment()
    {
        return __('Manage human resources', 'resources');
    }

    /**
     * @return string
     */
    public static function getLinkList()
    {
        return "";
    }

    /**
     * @return string
     */
    public static function getList()
    {
        return "";
    }
}
