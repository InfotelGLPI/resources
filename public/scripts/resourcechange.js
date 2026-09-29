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
 * Text fields of the resource forms.
 *
 * - An input carrying data-resources-case="upper" is uppercased while typed, one carrying
 *   data-resources-case="capitalize" gets its first letter uppercased once left.
 * - The free text change actions rendered by templates/resource_change_fields.html.twig
 *   reload their start button each time one of their fields changes: every named field of
 *   the wrapper is posted to the URL of its data-resources-change-url attribute.
 * - An input carrying data-resources-check-url checks, once left, whether the name and
 *   firstname of its form already belong to a resource.
 *
 * The listeners are delegated on the document because those forms are loaded
 * asynchronously, after the module has run.
 */

const applyCase = (input) => {
    if (input.dataset.resourcesCase === 'upper') {
        input.value = input.value.toUpperCase();
    } else if (input.dataset.resourcesCase === 'capitalize' && input.value !== '') {
        input.value = input.value.charAt(0).toUpperCase() + input.value.slice(1).toLowerCase();
    }
};

const reloadChangeButton = (wrapper) => {
    const target = document.getElementById('plugin_resources_buttonchangeresources');
    if (target === null) {
        return;
    }

    const body = new FormData();
    wrapper.querySelectorAll('input[name], textarea[name], select[name]').forEach((field) => {
        body.append(field.name, field.value);
    });
    body.append('load_button_changeresources', '1');
    body.append('action', wrapper.dataset.resourcesChangeAction);

    fetch(wrapper.dataset.resourcesChangeUrl, {
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
            return response.text();
        })
        .then((html) => {
            // The start button template holds no script, plain markup is enough.
            target.innerHTML = html;
        })
        .catch((error) => {
            console.error(error);
        });
};

/**
 * Creation wizard (templates/wizard_secondstep_resource.html.twig): once both the name and the
 * firstname are filled, warns when they already belong to a resource and hides the button
 * leading to the next step.
 */
const checkExistingResource = (field) => {
    const form = field.form;
    const name = form?.querySelector('[name="name"]');
    const firstname = form?.querySelector('[name="firstname"]');
    if (!name || !firstname || name.value === '' || firstname.value === '') {
        return;
    }

    const body = new FormData();
    body.append('name', name.value);
    body.append('firstname', firstname.value);

    fetch(field.dataset.resourcesCheckUrl, {
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
            return response.json();
        })
        .then((count) => {
            const exists = Number(count) > 0;
            const error = document.getElementById('resource-exists-error');
            const button = form.querySelector('button[name="third_step"]');
            if (error !== null) {
                error.style.display = exists ? 'block' : 'none';
            }
            if (button !== null) {
                button.style.display = exists ? 'none' : 'inline-block';
            }
        })
        .catch((error) => {
            console.error(error);
        });
};

const onFieldEvent = (event) => {
    const field = event.target;
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)) {
        return;
    }

    // Uppercasing follows the typing, capitalizing waits for the field to be left.
    if (field.dataset.resourcesCase === 'upper' || event.type === 'change') {
        applyCase(field);
    }

    const wrapper = field.closest('[data-resources-change-action]');
    if (wrapper !== null) {
        reloadChangeButton(wrapper);
    }

    if (event.type === 'change' && field.dataset.resourcesCheckUrl !== undefined) {
        checkExistingResource(field);
    }
};

document.addEventListener('input', onFieldEvent);
document.addEventListener('change', onFieldEvent);

/**
 * "Declare a change" form (templates/resource_change_form.html.twig): once both the resource
 * and the action are picked, the fields of the action are loaded below them and the start
 * button, which depends on those fields, is emptied.
 */
const loadChangeFields = (form) => {
    const actions = document.getElementById('plugin_resources_actions');
    const button = document.getElementById('plugin_resources_buttonchangeresources');
    if (actions === null) {
        return;
    }
    if (button !== null) {
        button.replaceChildren();
    }

    const resource = form.querySelector('select[name="plugin_resources_resources_id"]');
    const action = form.querySelector('select[name="change_action"]');
    if (resource === null || action === null || Number(resource.value) <= 0 || Number(action.value) <= 0) {
        actions.replaceChildren();
        return;
    }

    const body = new FormData();
    body.append('id', action.value);
    body.append('plugin_resources_resources_id', resource.value);

    fetch(form.dataset.resourcesChangeForm, {
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
            return response.text();
        })
        .then((html) => {
            // The fields come with the init scripts of their dropdowns: a contextual fragment
            // runs them once inserted, where innerHTML would leave them inert.
            actions.replaceChildren(document.createRange().createContextualFragment(html));
        })
        .catch((error) => {
            console.error(error);
        });
};

// Select2 only fires jQuery "change" events, which native listeners never receive: this
// delegated binding is the bridge, as the core does for its own select2 fields.
$(document).on(
    'change',
    'form[data-resources-change-form] select[name="plugin_resources_resources_id"], form[data-resources-change-form] select[name="change_action"]',
    (event) => {
        loadChangeFields(event.target.closest('form'));
    },
);
