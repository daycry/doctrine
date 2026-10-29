<?php

declare(strict_types=1);

$loader = require getenv('DOCTRINE_TEST_AUTOLOAD') ?: dirname(__DIR__, 2) . '/vendor/autoload.php';
$loader->addPsr4('Tests\\Standalone\\', __DIR__);
