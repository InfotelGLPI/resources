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

namespace GlpiPlugin\Resources;

use Session;

/**
 * Rows that only exist under a resource (needs, linked items, habilitations, leaving
 * information, report setup...) and share its right.
 *
 * Adding one of them modifies the resource, it does not create a new HR record: the CREATE
 * bit of plugin_resources stands for declaring a resource, which a profile allowed to edit
 * resources may well be denied. Creating such a row therefore asks for UPDATE, and for that
 * bit only: the former canCreate() also accepted CREATE or DELETE alone.
 *
 * This is the global test only: it does not answer for the resource the row is attached to.
 */
trait ResourceChildRightTrait
{
    public static function canCreate(): bool
    {
        return Session::haveRight(Resource::$rightname, UPDATE);
    }
}
