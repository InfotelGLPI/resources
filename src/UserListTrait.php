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

/**
 * Search lists built over the core users table (Directory, Recap).
 *
 * Their getTable() returns glpi_users so that the search engine can join the resources onto
 * the users. Being CommonDBTM classes, they are also itemtypes the core can load, update or
 * delete through its generic entry points (legacy API, massive actions), under the plugin
 * rights and without any of the safeguards of User: password hashes and tokens were readable
 * and every column of any account, administrators included, was writable. Only the list is
 * meant to exist, so every item operation is refused here; canView() stays with each class
 * and only gates the list.
 */
trait UserListTrait
{
    use NoStandardMassiveActionTrait;

    // The rows are users: keep out of any output what User keeps out of it (its own list
    // cannot be referenced in a declaration), plus the password reset token User only shows
    // to whoever may update the account.
    public static $undisclosedFields = [
        'password',
        'password_history',
        'personal_token',
        'api_token',
        'cookie_token',
        '2fa',
        'password_forget_token',
        'password_forget_token_date',
    ];

    /**
     * Columns of glpi_users the list displays: the only ones it may disclose.
     *
     * @return string[]
     */
    abstract public static function getDisclosedFields(): array;

    /**
     * Keep the columns the list displays, rather than drop the secrets only.
     *
     * The list endpoint of the legacy API selects "glpi_users.*" under the sole canView() of
     * the list, and only removes what this method removes (its search filters go through it
     * too). Dropping the secrets left every other column of the accounts readable with the
     * directory right alone: directory DN, authentication source, last login, preferences...
     */
    public static function unsetUndisclosedFields(&$fields)
    {
        $disclosed = static::getDisclosedFields();
        foreach (array_keys($fields) as $key) {
            if (!in_array($key, $disclosed, true)) {
                unset($fields[$key]);
            }
        }
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canUpdate(): bool
    {
        return false;
    }

    public static function canDelete(): bool
    {
        return false;
    }

    public static function canPurge(): bool
    {
        return false;
    }

    /**
     * No form shows a single row of these lists: an id only ever reaches can() from a generic
     * entry point, which must not disclose the user behind it.
     */
    public function canViewItem(): bool
    {
        return false;
    }

    public function canCreateItem(): bool
    {
        return false;
    }

    public function canUpdateItem(): bool
    {
        return false;
    }

    public function canDeleteItem(): bool
    {
        return false;
    }

    public function canPurgeItem(): bool
    {
        return false;
    }

    // The three hooks below also stop a direct add(), update() or delete() that would not go
    // through can() first: only User may write glpi_users.

    public function prepareInputForAdd($input)
    {
        return false;
    }

    public function prepareInputForUpdate($input)
    {
        return false;
    }

    public function pre_deleteItem()
    {
        return false;
    }
}
