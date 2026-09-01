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

/* global $ */

/**
 * Contract type tree of the resource list.
 *
 * Served inside an iframe modal, in a page loaded outside of the usual GLPI header: the
 * plugin scripts registered through the ADD_JAVASCRIPT hook are not present there, so
 * resource_tree.html.twig pulls this file explicitly and the tree reads its parameters
 * from the data attributes of its container rather than from an inline script block.
 */
(function () {
    function initTree(container) {
        var rootDoc = container.dataset.rootDoc;
        var typesUrl = rootDoc + '/ajax/resourcetreetypes.php';

        $.getScript(rootDoc + '/lib/jstree/jstree.js', function () {
            $(container).jstree({
                plugins: ['search', 'qload'],
                search: {
                    case_insensitive: true,
                    show_only_matches: true,
                    ajax: {
                        type: 'POST',
                        url: typesUrl,
                    },
                },
                qload: {
                    prevLimit: 50,
                    nextLimit: 30,
                    moreText: container.dataset.moreText,
                },
                core: {
                    animation: 0,
                    data: {
                        url: function (node) {
                            return typesUrl + '?node=' + (node.id === '#' ? '-1' : node.id);
                        },
                    },
                },
            });
        });
    }

    function initAll() {
        document.querySelectorAll('[data-plugin-resources-tree]').forEach(initTree);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
