<?php

// Bootstrap GLPI's test environment (DB connection, session, etc.)
require_once dirname(__DIR__, 3) . '/tests/bootstrap.php';

// Register resources classes into the already-loaded Composer autoloader
$loader = require dirname(__DIR__, 3) . '/vendor/autoload.php';
$loader->addPsr4('GlpiPlugin\\Resources\\', dirname(__DIR__) . '/src/');
$loader->addPsr4('GlpiPlugin\\Resources\\Tests\\', dirname(__DIR__) . '/tests/');

// Install plugin tables in the test DB if they do not yet exist
if (!defined('PLUGIN_RESOURCES_VERSION')) {
    require_once dirname(__DIR__) . '/setup.php';
}
global $DB;
if (!$DB->tableExists('glpi_plugin_resources_resources')) {
    require_once dirname(__DIR__) . '/hook.php';
    plugin_resources_install();
}
