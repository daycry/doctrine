<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$loader = require $root . '/build/isolation-adapter/vendor/autoload.php';
$loader->addPsr4('Tests\\Standalone\\', $root . '/tests/Standalone');

use Daycry\Doctrine\CodeIgniter4\Config\Doctrine as AdapterConfig;
use Daycry\Doctrine\CodeIgniter4\Config\Services;
use Daycry\Doctrine\CodeIgniter4\Doctrine as Adapter;
use Daycry\Doctrine\Doctrine;

if (! is_subclass_of(Adapter::class, Doctrine::class)
    || ! is_subclass_of(AdapterConfig::class, CodeIgniter\Config\BaseConfig::class)
    || ! is_subclass_of(Services::class, CodeIgniter\Config\BaseService::class)
    || ! function_exists('Daycry\\Doctrine\\CodeIgniter4\\Helpers\\getFromCacheOrQuery')) {
    throw new RuntimeException('The installed adapter contract is incomplete.');
}
echo "Installed adapter, configuration, services and helper load successfully.\n";

$consumer = $root . '/build/isolation-adapter/';
$framework = $consumer . 'vendor/codeigniter4/framework/';
define('HOMEPATH', $consumer);
define('CONFIGPATH', $framework . 'app/Config/');
define('PUBLICPATH', $framework . 'public/');
define('TESTPATH', $root . '/tests/');
define('COMPOSER_PATH', $consumer . 'vendor/autoload.php');
require $framework . 'system/Test/bootstrap.php';
$config = config('Doctrine');
if (! $config instanceof AdapterConfig) {
    throw new RuntimeException('CodeIgniter did not discover the installed adapter config.');
}
$config->entities = [$root . '/tests/Standalone/Fixtures'];
$database = config('Database');
$database->tests['DSN'] = '';
$database->tests['DBDriver'] = 'SQLite3';
$database->tests['database'] = ':memory:';
$doctrine = Services::doctrine(true, 'tests');
if ((int) $doctrine->getEm()->getConnection()->fetchOne('SELECT 1') !== 1
    || ! service('doctrine') instanceof Adapter
    || ! isset(service('commands')->getCommands()['doctrine:doctor'])) {
    throw new RuntimeException('The installed adapter did not boot its connection and Spark commands.');
}
echo "Installed adapter boots CodeIgniter services, SQLite and Spark discovery.\n";
