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
 * Import lists: the header box ticks or unticks every line of its own list.
 *
 * The listener is delegated on the document because the header is re-rendered by the
 * pager without a full page load. Only the checkboxes of the form the header belongs
 * to are touched: the previous inline helper walked every input of the page, which
 * also reached the boxes of the surrounding screens.
 */
document.addEventListener('change', (event) => {
    const master = event.target;
    if (!(master instanceof HTMLInputElement) || master.id !== 'checkall_imports') {
        return;
    }

    const form = master.form || master.closest('form');
    if (form === null) {
        return;
    }

    form.querySelectorAll('input[type="checkbox"]').forEach((box) => {
        if (box !== master) {
            box.checked = master.checked;
        }
    });
});

/**
 * Import header: the #calculate button fetches the counters of the selected file from
 * ajax/verifyCSVStatistics.php (URL and import type in its data attributes) and writes
 * each returned value into the <span> carrying the same key in data-resources-stat.
 */
document.addEventListener('click', (event) => {
    const button = event.target instanceof Element
        ? event.target.closest('#calculate[data-resources-stats-url]')
        : null;
    if (button === null) {
        return;
    }

    const counters = document.querySelectorAll('[data-resources-stat]');
    counters.forEach((counter) => {
        counter.textContent = '?';
    });

    const selected = document.querySelector('[name="selected-file"]');
    const option = selected instanceof HTMLSelectElement ? selected.selectedOptions[0] : null;
    const params = new URLSearchParams({
        page: button.dataset.resourcesPage,
        file: option ? option.text : '',
    });

    const loader = document.getElementById('ajax_loader');
    if (loader !== null) {
        loader.style.display = 'block';
    }

    fetch(`${button.dataset.resourcesStatsUrl}?${params}`, {
        headers: {'X-Requested-With': 'XMLHttpRequest'},
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then((results) => {
            counters.forEach((counter) => {
                const key = counter.dataset.resourcesStat;
                if (results !== null && key in results) {
                    counter.textContent = String(results[key]);
                }
            });
        })
        .catch((error) => console.error(error))
        .finally(() => {
            if (loader !== null) {
                loader.style.display = 'none';
            }
        });
});
