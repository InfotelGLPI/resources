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
 * marks all checkboxes inside the given element
 * the given element is usaly a table or a div containing the table or tables
 *
 * @param    container_id    DOM element
 */
function plugin_resources_markCheckboxes(container_id) {
    var checkboxes = document.getElementById(container_id).getElementsByTagName('input');
    for (var j = 0; j < checkboxes.length; j++) {
        checkbox = checkboxes[j];
        if (checkbox && checkbox.type == 'checkbox') {
            if (checkbox.disabled == false) {
                checkbox.checked = true;
            }
        }
    }

    return true;
}


/**
 * marks all checkboxes inside the given element
 * the given element is usaly a table or a div containing the table or tables
 *
 * @param    container_id    DOM element
 */
function plugin_resources_unMarkCheckboxes(container_id) {
    var checkboxes = document.getElementById(container_id).getElementsByTagName('input');
    for (var j = 0; j < checkboxes.length; j++) {
        checkbox = checkboxes[j];
        if (checkbox && checkbox.type == 'checkbox' && checkbox.disabled != true) {
            checkbox.checked = false;
        }
    }

    return true;
}

/**
 * Add comment field into items linked to a resource
 */

function plugin_resources_show_item(id, img, new_src) {
    var el;
    var cur_src = img.src.substring(img.src.lastIndexOf("/") + 1);

    new_src_test = new_src.substring(new_src.lastIndexOf("/") + 1);
    path = new_src.replace(new_src_test, "");

    old_src = path + 'expand.gif';
    if (el = document.getElementById(id)) {

        el.className = (el.className == "plugin_resources_hide") ? "plugin_resources_show" : "plugin_resources_hide";

        if (cur_src == new_src_test) {
            img.src = old_src;
        } else {
            img.src = new_src;
        }
    }
}

/**
 * Add comment field into choices on wizard new resouce
 */

function plugin_resources_show_tab(id) {
    var el;

    if (el = document.getElementById(id)) {

        el.className = (el.className == "plugin_resources_hide") ? "plugin_resources_show" : "plugin_resources_hide";

    }
}

/**
 *
 * @param root_doc
 * @param id
 */
function plugin_resources_pdf_resource(root_doc, id) {
    $.ajax({
        url: root_doc + '/ajax/pdfresource.php',
        type: 'POST',
        data: '&plugin_resources_resources_id=' + id,
        dataType: 'html',
        success: function (code_html, statut) {
            $('#resource_pdf').html(code_html);
        },

    });
}

// The restitution PDF is generated server-side (write), so it is requested by POST, which
// goes through the core CSRF check. The block is reloaded afterwards.
$(document).on('click', '#resource_pdf .plugin-resources-pdf-download', function () {
    var btn = $(this);
    var form = $('<form>', {method: 'post', action: btn.data('url'), target: '_blank'})
        .append($('<input>', {type: 'hidden', name: 'generate_pdf', value: 1}))
        .append($('<input>', {type: 'hidden', name: 'users_id', value: btn.data('users-id')}))
        .appendTo(document.body);
    form.trigger('submit');
    form.remove();
    plugin_resources_pdf_resource(btn.data('root'), btn.data('resources-id'));
});
