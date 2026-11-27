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
use Session;

/**
 * Item rights of the rows that only exist under a resource and carry no entity of their own
 * (habilitations, forced holidays, non contract periods, leaving information, report setup,
 * directory link).
 *
 * On a table without entities_id, CommonDBTM::checkEntity() always answers true: check() and
 * can() came down to the global right, so the massive actions, the forms and every generic
 * entry point reached the rows of all the entities. The boundary lives on the parent resource,
 * so the item rights are answered there: READ on the resource to see the row, UPDATE on it to
 * create, change or remove the row (a row of a resource is part of that resource). The global
 * right of the row itself is still tested by CommonDBTM::can() before these methods.
 *
 * The parent is the one stored in the row or, for a row being created, the one named in its
 * input: CommonDBTM::can() stores that input in $this->input before calling canCreateItem().
 */
trait ResourceChildItemRightTrait
{
    /**
     * Id of the resource the row belongs to.
     *
     * @param bool $from_input read it from the input of a row being created
     *
     * @return int 0 when there is none, or when the value is not a plain id
     */
    public function getParentResourceId(bool $from_input = false): int
    {
        $resources_id = $this->fields['plugin_resources_resources_id'] ?? 0;
        if ($from_input
            && is_array($this->input)
            && array_key_exists('plugin_resources_resources_id', $this->input)) {
            $resources_id = $this->input['plugin_resources_resources_id'];
        }

        try {
            return Resource::getPostedId($resources_id);
        } catch (BadRequestHttpException) {
            return 0;
        }
    }

    /**
     * @param int  $right      right required on the parent resource
     * @param bool $from_input see getParentResourceId()
     */
    private function canOnParentResource(int $right, bool $from_input = false): bool
    {
        $resources_id = $this->getParentResourceId($from_input);

        return $resources_id > 0 && (new Resource())->can($resources_id, $right);
    }

    public function canViewItem(): bool
    {
        return $this->canOnParentResource(READ);
    }

    public function canCreateItem(): bool
    {
        return $this->canOnParentResource(UPDATE, true);
    }

    public function canUpdateItem(): bool
    {
        return $this->canOnParentResource(UPDATE);
    }

    public function canDeleteItem(): bool
    {
        return $this->canOnParentResource(UPDATE);
    }

    public function canPurgeItem(): bool
    {
        return $this->canOnParentResource(UPDATE);
    }

    /**
     * Whether an update may write the parent named in its input.
     *
     * canUpdateItem() answers for the resource the row is stored under: moving the row to
     * another resource changes that other resource too, which may belong to an entity the
     * session cannot write in. A message is left when the move is refused.
     *
     * @param array<string, mixed> $input input of update()
     */
    protected function canMoveToParentResource(array $input): bool
    {
        if (!array_key_exists('plugin_resources_resources_id', $input)
            || Resource::canAttachTo($input['plugin_resources_resources_id'], $this->getParentResourceId())) {
            return true;
        }

        Session::addMessageAfterRedirect(__("You don't have permission to perform this action."), false, ERROR);

        return false;
    }

    /**
     * Drop from an input the columns of the table that a form may not write.
     *
     * Keys that are no column (buttons, "_" options) are left alone: they are never written.
     *
     * @param array<string, mixed> $input   input of add() or update()
     * @param string[]             $allowed columns the input may carry
     *
     * @return array<string, mixed>
     */
    protected static function keepWritableColumns(array $input, array $allowed): array
    {
        global $DB;

        $columns = $DB->listFields(static::getTable());
        foreach (array_keys($input) as $key) {
            if (isset($columns[$key]) && !in_array($key, $allowed, true)) {
                unset($input[$key]);
            }
        }

        return $input;
    }
}
