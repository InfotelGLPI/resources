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

use MassiveAction;

/**
 * Itemtypes that no search list may edit: their rows are written by a dedicated screen only.
 */
trait NoStandardMassiveActionTrait
{
    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        // "MassiveAction:*" covers every action the core offers to any itemtype (update, clone,
        // notes, comment, delete, purge...), including the ones a later core version adds.
        // This only trims the actions offered: running one still goes through can(), which the
        // classes using this trait refuse on their own.
        $forbidden[] = MassiveAction::class . MassiveAction::CLASS_ACTION_SEPARATOR . '*';

        return $forbidden;
    }
}
