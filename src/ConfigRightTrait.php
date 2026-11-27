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
 * Rights of the itemtypes holding plugin settings.
 *
 * Their rows are edited from the setup page, which front/config.form.php gates on core config
 * UPDATE. The generic entry points of the core (tab loader, massive actions, API) only ask the
 * itemtype itself, so it has to require that same right: with the plugin right it used to
 * inherit, any HR user could read or rewrite the setup. The core Config right carries no
 * CREATE / DELETE / PURGE bit, so every operation maps onto that single bit.
 */
trait ConfigRightTrait
{
    public static function canConfigure(): bool
    {
        return Session::haveRight(\Config::$rightname, UPDATE);
    }

    public static function canView(): bool
    {
        return self::canConfigure();
    }

    public static function canCreate(): bool
    {
        return self::canConfigure();
    }

    public static function canUpdate(): bool
    {
        return self::canConfigure();
    }

    public static function canDelete(): bool
    {
        return self::canConfigure();
    }

    public static function canPurge(): bool
    {
        return self::canConfigure();
    }
}
