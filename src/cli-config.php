<?php
/**
 * Part of CodeIgniter Doctrine
 *
 * @author     Daycry <https://github.com/daycry>
 * @license    MIT License
 * @copyright  2015 Kenji Suzuki
 * @link       https://github.com/kenjis/codeigniter-doctrine
 */

error_reporting( E_ALL );

// Path to the front controller (this file)
if( !defined( 'FCPATH' ) )
{
    define( 'FCPATH', __DIR__ . DIRECTORY_SEPARATOR );
}

// Location of the Paths config file.
// This is the line that might need to be changed, depending on your folder structure.
$pathsPath = FCPATH . 'app/Config/Paths.php';
// ^^^ Change this if you move your application folder

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// Ensure the current directory is pointing to the front controller's directory
chdir( __DIR__ );

// Load our paths config file
require $pathsPath;
$paths = new Config\Paths();

// Location of the framework bootstrap file.
$app = require rtrim( $paths->systemDirectory, '/ ' ) . '/bootstrap.php';

use Doctrine\ORM\Tools\Console\ConsoleRunner;

$doctrine = new \Daycry\Doctrine\Doctrine();

return ConsoleRunner::createHelperSet( $doctrine->em );
