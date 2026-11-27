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

use Glpi\Exception\Http\BadRequestHttpException;

/**
 * Item rights of the rows that only exist under a task and carry no entity of their own
 * (associated items, planning).
 *
 * On a table without entities_id, CommonDBTM::checkEntity() always answers true: can() came
 * down to the task right, so every generic entry point (API, massive actions, including a
 * forged purge the core runs without checking the action was offered) reached the rows of
 * the tasks of all the entities. A row is part of its task: it is read with the task (READ)
 * and written with it (UPDATE), entity included. The global right of the row itself is still
 * tested by CommonDBTM::can() before these methods.
 *
 * The task is the one stored in the row or, for a row being created, the one named in its
 * input: CommonDBTM::can() stores that input in $this->input before calling canCreateItem().
 */
trait TaskChildItemRightTrait
{
    /**
     * Whether the session holds a right on the task the row belongs to.
     *
     * @param int  $right      right required on the task
     * @param bool $from_input read the task from the input of a row being created
     *
     * @return bool
     */
    private function canOnTask(int $right, bool $from_input = false): bool
    {
        $tasks_id = $this->fields['plugin_resources_tasks_id'] ?? 0;
        if ($from_input
            && is_array($this->input)
            && array_key_exists('plugin_resources_tasks_id', $this->input)) {
            $tasks_id = $this->input['plugin_resources_tasks_id'];
        }

        try {
            $tasks_id = Resource::getPostedId($tasks_id);
        } catch (BadRequestHttpException) {
            return false;
        }

        return $tasks_id > 0 && (new Task())->can($tasks_id, $right);
    }

    public function canViewItem(): bool
    {
        return $this->canOnTask(READ);
    }

    public function canCreateItem(): bool
    {
        return $this->canOnTask(UPDATE, true);
    }

    public function canUpdateItem(): bool
    {
        return $this->canOnTask(UPDATE);
    }

    public function canDeleteItem(): bool
    {
        return $this->canOnTask(UPDATE);
    }

    public function canPurgeItem(): bool
    {
        return $this->canOnTask(UPDATE);
    }
}
