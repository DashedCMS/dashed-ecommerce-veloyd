<?php

/**
 * Bootstrap for tests/Integration: runs this package's integration tests inside
 * the dashed-ecommerce-core test harness (this package has no app harness).
 *
 * Run from a dashed-ecommerce-core checkout with its vendor installed:
 *
 *   cd <dashed-ecommerce-core>
 *   DB_TEST_CONNECTION=mysql DB_TEST_DATABASE=<own test db> vendor/bin/phpunit \
 *     --bootstrap <this package>/tests/Integration/bootstrap.php \
 *     <this package>/tests/Integration/VeloydParcelTypeTest.php
 *
 * The ec-core checkout is taken from DASHED_EC_CORE_PATH, else the current
 * working directory.
 */
$ecCore = rtrim((string) (getenv('DASHED_EC_CORE_PATH') ?: getcwd()), '/');

$loader = require $ecCore . '/vendor/autoload.php';
$loader->addPsr4('Dashed\\DashedEcommerceVeloyd\\', dirname(__DIR__, 2) . '/src');
$loader->addPsr4('Dashed\\DashedEcommerceVeloyd\\Tests\\', dirname(__DIR__));

require_once $ecCore . '/tests/Stubs/AppUserStub.php';
require_once $ecCore . '/tests/Stubs/MediaHelperStub.php';
