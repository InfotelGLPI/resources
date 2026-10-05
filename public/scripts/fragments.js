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
 * Click behaviours declared by data attributes, in place of the per item JS functions the
 * PHP classes used to emit. On a clicked element:
 *
 * - data-resources-hide / data-resources-show: comma separated selectors hidden / shown;
 * - data-resources-remove: selector of the elements removed;
 * - data-resources-load-url + data-resources-load-target (element id): the URL is posted the
 *   JSON object of data-resources-load-params and its answer replaces the target content;
 * - data-resources-toggle (element id) + data-resources-toggle-icon (element id) and
 *   data-resources-toggle-closed / data-resources-toggle-open (classes): folds the element
 *   through the showHideDiv() of the core;
 * - data-resources-confirm: the message a submit button asks to confirm before going on.
 *
 * An element carrying data-resources-autoload-url fills itself on page load in the same way,
 * with its own data-resources-load-params.
 *
 * The listener is delegated on the document because those elements mostly come from tabs
 * and fragments loaded after the module has run.
 */

const forEachMatch = (selectors, callback) => {
    if (selectors === undefined || selectors === '') {
        return;
    }
    document.querySelectorAll(selectors).forEach(callback);
};

const loadFragment = (url, target, params_json) => {
    if (target === null) {
        return;
    }

    const body = new FormData();
    const params = JSON.parse(params_json || '{}');
    Object.entries(params).forEach(([key, value]) => {
        body.append(key, String(value));
    });

    fetch(url, {
        method: 'POST',
        body,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.text();
        })
        .then((html) => {
            // The forms come with the init scripts of their fields: a contextual fragment runs
            // them once inserted, where innerHTML would leave them inert.
            target.replaceChildren(document.createRange().createContextualFragment(html));
        })
        .catch((error) => {
            console.error(error);
        });
};

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    // Submit buttons asking for a confirmation first (templates/import_selector_form.html.twig).
    const confirmed = event.target.closest('[data-resources-confirm]');
    if (confirmed !== null) {
        // eslint-disable-next-line no-alert
        if (!window.confirm(confirmed.dataset.resourcesConfirm)) {
            event.preventDefault();
        }
        return;
    }

    const trigger = event.target.closest(
        '[data-resources-load-url], [data-resources-toggle], [data-resources-hide], [data-resources-show], [data-resources-remove]',
    );
    if (trigger === null) {
        return;
    }
    // Links only carry href="#": the page must neither scroll nor change its hash.
    event.preventDefault();

    const data = trigger.dataset;
    forEachMatch(data.resourcesRemove, (element) => element.remove());
    forEachMatch(data.resourcesHide, (element) => {
        element.style.display = 'none';
    });
    forEachMatch(data.resourcesShow, (element) => {
        element.style.display = '';
    });

    if (data.resourcesToggle !== undefined) {
        showHideDiv(
            data.resourcesToggle,
            data.resourcesToggleIcon ?? '',
            data.resourcesToggleClosed ?? '',
            data.resourcesToggleOpen ?? '',
        );
    }

    if (data.resourcesLoadUrl !== undefined) {
        loadFragment(
            data.resourcesLoadUrl,
            document.getElementById(data.resourcesLoadTarget),
            data.resourcesLoadParams,
        );
    }
});

const autoload = () => {
    document.querySelectorAll('[data-resources-autoload-url]').forEach((element) => {
        loadFragment(element.dataset.resourcesAutoloadUrl, element, element.dataset.resourcesLoadParams);
    });
};

// Modules are deferred: the document may already be parsed when this runs.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoload);
} else {
    autoload();
}
