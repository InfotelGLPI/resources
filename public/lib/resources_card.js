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

$(window).load(function () {

    var navigation = $('nav').find('ul'),
        navigationWidth = navigation.width();

    navigation.css({width: navigationWidth, float: 'none', margin: '0 auto', visibility: 'visible'});

});

$(document).ready(function () {

    var containerHeight = $('#container').height(),
        header = $('header');

    $(window).resize(function () {

        var windowHeight = $(this).height(),
            calculate = (274 + (windowHeight - containerHeight) + 12) / 2;

        if (calculate > 274) {
            calculate = 274
        } else if (calculate < 42) {
            calculate = 42
        }

        header.css({height: calculate});

    }).trigger('resize');


    var contentAnimating = false,
        initialLoad = true;

    $('nav').delegate('a', 'click', function () {

        if (!contentAnimating) {

            contentAnimating = true;

            $(this).parent('li').siblings().removeClass('active').end().addClass('active');

            $.address.value($(this).attr('href'));

            if (initialLoad) {
                $('#plugin_resources_card-content-wrap').css({marginLeft: ($(this).parent('li').index() * 590) * -1});
                contentAnimating = false;
                initialLoad = false;
            } else {
                $('#plugin_resources_card-content-wrap').animate({marginLeft: ($(this).parent('li').index() * 590) * -1}, 500, 'easeOutExpo', function () {
                    contentAnimating = false;
                });
            }

        }

        return false;

    });

    $('.scrollable').jScrollPane();

});
