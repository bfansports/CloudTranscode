<?php

error_reporting(E_ALL | E_STRICT);

// Ensure that composer has installed all dependencies
if (!file_exists(dirname(__DIR__) . '/composer.lock')) {
    die("Dependencies must be installed using composer:\n\nphp composer.phar install --dev\n\n"
        . "See http://getcomposer.org for help with installing composer\n");
}

require_once 'PHPUnit/TextUI/TestRunner.php';

// Include the phar files if testing against the phars
if (get_cfg_var('guzzle_phar')) {
    require get_cfg_var('guzzle_phar');
}

// Include the composer autoloader
$autoloader = require dirname(__DIR__) . '/vendor/autoload.php';

// Add the services file to the default service builder
$servicesFile = __DIR__ . '/Guzzle/Tests/TestData/services/services.json';
Guzzle\Tests\GuzzleTestCase::setServiceBuilder(Guzzle\Service\Builder\ServiceBuilder::factory($servicesFile));
