<?php

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

// Bootstrap GLPI's test environment (DB connection, session, etc.)
require_once dirname(__DIR__, 3) . '/tests/bootstrap.php';

// Register resources classes into the already-loaded Composer autoloader
$loader = require dirname(__DIR__, 3) . '/vendor/autoload.php';
$loader->addPsr4('GlpiPlugin\\Resources\\', dirname(__DIR__) . '/src/');
$loader->addPsr4('GlpiPlugin\\Resources\\Tests\\Integration\\', dirname(__DIR__) . '/tests/Integration/');

if (!defined('PLUGIN_RESOURCES_VERSION')) {
    require_once dirname(__DIR__) . '/setup.php';
}

// The CI installs and activates the plugin through bin/console before the tests; install
// it here too when the suite is run against a database that does not have it yet.
global $DB;
if (!$DB->tableExists('glpi_plugin_resources_resources')) {
    require_once dirname(__DIR__) . '/hook.php';
    plugin_resources_install();
}

// The test environment does not load the plugin, so its Twig namespace is missing: the
// wizard steps render @resources templates.
$twig_loader = \Glpi\Application\View\TemplateRenderer::getInstance()->getEnvironment()->getLoader();
if ($twig_loader instanceof \Twig\Loader\FilesystemLoader && !in_array('resources', $twig_loader->getNamespaces(), true)) {
    $twig_loader->addPath(dirname(__DIR__) . '/templates', 'resources');
}
