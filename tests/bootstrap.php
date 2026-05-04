<?php

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