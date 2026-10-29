<?php

declare(strict_types=1);

use Daycry\Doctrine\Configuration;
use Daycry\Doctrine\Doctrine;

$config             = new Configuration();
$config->connection = ['driver' => 'pdo_sqlite', 'memory' => true];
$config->entities   = [__DIR__ . '/Fixtures'];

return new Doctrine($config);
