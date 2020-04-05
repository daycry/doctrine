<?php
/**
 * Part of CodeIgniter Simple and Secure Twig
 *
 * @author     Daycry <https://github.com/daycry>
 * @license    MIT License
 * @copyright  2020 Daycry
 * @link       https://github.com/daycry/doctrine
 */

class Installer
{
    public static function install()
    {
        self::copy( 'vendor/daycry/doctrine/cli-config.php', 'cli-config.php' );
    }

    private static function copy( $src, $dst )
    {
        $success = copy( $src, $dst );
        if ( $success )
        {
            echo 'copied: ' . $dst . PHP_EOL;
        }
    }
}

$installer = new Installer();
$installer->install();