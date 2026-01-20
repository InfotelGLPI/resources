<?php
/**
 * -------------------------------------------------------------------------
 * Resources plugin for GLPI
 * Copyright (C) 2009-2026 by the Resources Development Team.
 *
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Resources.
 *
 * Resources is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Resources is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Resources. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */


include('../../../inc/includes.php');

echo '<div>

<table>
  <thead>
    <tr>
      <th>' . __('Variable', 'resources') . '</th>
      <th>' . __('Description', 'resources') . '</th>
    </tr>
  </thead>

  <tbody>';

foreach (PluginResourcesConfig::getAvailablevariable() as $item => $description) {
    echo '<tr>';
    echo '<td>' . $item . '</td>';
    echo '<td>' . $description . '</td>';
    echo '</tr>';
}
echo '
  </tbody>
</table>
</div>';