<?php
/**
 * Part of CodeIgniter Doctrine
 *
 * @author     Daycry <https://github.com/daycry>
 * @license    MIT License
 * @copyright  2022 Daycry
 * @link       https://github.com/daycry/doctrine
 */

error_reporting( E_ALL );

// Load our paths config file
// This is the line that might need to be changed, depending on your folder structure.
defined('FCPATH') || define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

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

if(!class_exists('Config\Paths')) {
    require realpath(FCPATH . '../app/Config/Paths.php') ?: FCPATH . '../app/Config/Paths.php';
}

$paths = new Config\Paths();

// Location of the framework bootstrap file.
$bootstrap = rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';
$app       = require realpath($bootstrap) ?: $bootstrap;

use Doctrine\ORM\Tools\Console\ConsoleRunner;

$doctrine = new \Daycry\Doctrine\Doctrine();

return ConsoleRunner::createHelperSet( $doctrine->em );
