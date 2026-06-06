<?php

/**
 * Part of CodeIgniter Doctrine
 *
 * @license    MIT License
 * @copyright  2022 Daycry
 * @see       https://github.com/daycry/doctrine
 */

require_once __DIR__ . '/vendor/autoload.php';

use Config\Paths;
use Daycry\Doctrine\Boot;
use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;
use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

defined('ENVIRONMENT') || define('ENVIRONMENT', 'development');

// Surface warnings and notices in non-production environments. Production keeps
// CodeIgniter's own error handling (set up via Boot::bootDoctrine below).
error_reporting(E_ALL);
/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// Load our paths config file
// This is the line that might need to be changed, depending on your folder structure.

if (! $pathPaths = realpath(FCPATH . 'app/Config/Paths.php')) {
    $pathPaths = realpath(FCPATH . '../vendor/codeigniter4/framework/app/Config/Paths.php');
}

require $pathPaths;

$paths = new Paths();

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

// Load environment settings from .env files into $_SERVER and $_ENV
Boot::bootDoctrine($paths);

// Lazily build one EntityManager per CodeIgniter database group, so the Doctrine
// ORM CLI can target any group with `--em=<group>` (the default group is used
// when the option is omitted). This avoids accidentally running schema/DDL
// commands against the wrong database in multi-group applications.
$provider = new class (config('Doctrine')) implements EntityManagerProvider {
    /**
     * @var array<string, EntityManagerInterface>
     */
    private array $managers = [];

    public function __construct(private readonly DoctrineConfig $config)
    {
    }

    public function getDefaultManager(): EntityManagerInterface
    {
        return $this->getManager(config('Database')->defaultGroup);
    }

    public function getManager(string $name): EntityManagerInterface
    {
        return $this->managers[$name] ??= (new Doctrine($this->config, null, $name))->getEm();
    }
};

$commands = [
    // If you want to add your own custom console commands,
    // you can do so here.
];

ConsoleRunner::run($provider, $commands);
