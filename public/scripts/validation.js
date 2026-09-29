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

/**
 * Resource information validation: the confirm button of the modal rendered by
 * templates/resource_validation_form.html.twig posts to ajax/validinformation.php,
 * whose URL and resource id are read from the button's data attributes.
 *
 * The listener is delegated on the document because the validation tab is loaded
 * asynchronously, after the module has run.
 */
document.addEventListener('click', (event) => {
    const button = event.target instanceof Element
        ? event.target.closest('[data-resources-validate]')
        : null;
    if (button === null) {
        return;
    }

    const body = new FormData();
    body.append('plugin_resources_resources_id', button.dataset.resourcesId);
    body.append('validSaisie', '1');

    button.disabled = true;
    fetch(button.dataset.resourcesValidate, {
        method: 'POST',
        body,
        headers: {
            'X-Glpi-Csrf-Token': getAjaxCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            window.location.reload();
        })
        .catch((error) => {
            console.error(error);
            button.disabled = false;
        });
});
