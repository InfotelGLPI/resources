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
 * Rights of the public sector dropdowns (costs, ranks, professions, situations...).
 *
 * They answer to their own right and to the "Dropdown management" line of the profile
 * (plugin_resources_dropdown_public). Only canView() and canCreate() used to test the latter,
 * canCreate() with its UPDATE bit, so a profile kept read-only on it could still edit or purge
 * the values. Every operation now requires the bit of the same name on both rights, creation
 * excepted (see canCreate()).
 */
trait DropdownPublicRightTrait
{
    private static function haveDropdownPublicRight(int $right): bool
    {
        return Session::haveRight(self::$rightname, $right)
            && Session::haveRight('plugin_resources_dropdown_public', $right);
    }

    public static function canView(): bool
    {
        return self::haveDropdownPublicRight(READ);
    }

    /**
     * The CREATE bit that matters is the one of the "Dropdown management" line, the right of
     * these values. The CREATE bit of the plugin right stands for declaring a resource: a
     * profile that edits resources without declaring them used to create values, and
     * Profile::migrateEnforcedRights() leaves that bit alone on purpose. The plugin right only
     * has to be a writing one, as the former test required it (DELETE alone no longer counts).
     */
    public static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [CREATE, UPDATE])
            && Session::haveRight('plugin_resources_dropdown_public', CREATE);
    }

    public static function canUpdate(): bool
    {
        return self::haveDropdownPublicRight(UPDATE);
    }

    public static function canDelete(): bool
    {
        return self::haveDropdownPublicRight(DELETE);
    }

    public static function canPurge(): bool
    {
        return self::haveDropdownPublicRight(PURGE);
    }
}
