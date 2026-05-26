<?php

/*
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2015-2026 by the resources Development Team.

 https://github.com/InfotelGLPI/resources
 -------------------------------------------------------------------------

 LICENSE

 This file is part of resources.

 resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

// GLPI_ROOT est requis par les fichiers du plugin avant leur chargement
if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', dirname(__DIR__, 3));
}

// Stubs des fonctions globales GLPI (i18n) absentes hors contexte GLPI complet
if (!function_exists('__')) {
    function __(string $str, string $domain = 'glpi'): string { return $str; }
}
if (!function_exists('_n')) {
    function _n(string $singular, string $plural, int $nb, string $domain = 'glpi'): string
    {
        return $nb > 1 ? $plural : $singular;
    }
}

$loader = require dirname(__DIR__, 3) . '/vendor/autoload.php';

$loader->addPsr4('GlpiPlugin\\Resources\\', dirname(__DIR__) . '/src/');
$loader->addPsr4('GlpiPlugin\\Resources\\Tests\\Unit\\', dirname(__DIR__) . '/tests/Unit/');