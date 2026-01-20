<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2026 by the resources Development Team.

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
use RuleCollection;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}


/**
 * Class RuleContracttypeReadonlyCollection
 */
class RuleContracttypeReadonlyCollection extends RuleCollection {

    static $rightname = 'plugin_resources';

    // From RuleCollection
    public $stop_on_first_match=true;
    public $menu_option='contracttypereadonlys';

    /**
     * Get title used in list of rules
     *
     * @return Title of the rule collection
     **/
    function getTitle() {

        return __('Assignment rule of read only fields to a contract type', 'resources');
    }

    /**
     * PluginResourcesRuleContracttypeCollection constructor.
     *
     * @param int $entity
     */
    function __construct($entity = 0) {
        $this->entity = $entity;
    }

    /**
     * @return bool
     */
    function showInheritedTab() {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]) && ($this->entity);
    }

    /**
     * @return bool
     */
    function showChildrensTab() {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, DELETE]) && (count($_SESSION['glpiactiveentities']) > 1);
    }
}

