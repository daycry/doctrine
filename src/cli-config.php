<?php

/**
 * Part of CodeIgniter Doctrine
 *
 * @license    MIT License
 * @copyright  2022 Daycry
 * @see       https://github.com/daycry/doctrine
 */

require_once 'vendor/autoload.php';

use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

error_reporting(E_ALL);

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

defined('ENVIRONMENT') || define('ENVIRONMENT', 'development');
/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// Ensure the current directory is pointing to the front controller's directory
chdir(__DIR__);

// Load our paths config file
// This is the line that might need to be changed, depending on your folder structure.

if (! $pathPaths = realpath(FCPATH . 'app/Config/Paths.php')) {
    $pathPaths = realpath(FCPATH . '../vendor/codeigniter4/framework/app/Config/Paths.php');
}

require $pathPaths;

$paths = new Config\Paths();

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

// Load environment settings from .env files into $_SERVER and $_ENV
$response = Daycry\Doctrine\Boot::bootDoctrine($paths);

$doctrine = new Doctrine(config('Doctrine'));

$commands = [
    // If you want to add your own custom console commands,
    // you can do so here.
];

ConsoleRunner::run(
    new SingleManagerProvider($doctrine->em),
    $commands,
);
