<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Exceptions\ConfigException;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector;
use Daycry\Doctrine\Doctrine;

class Services extends BaseService
{
    private static string $prefix       = 'doctrine';
    private static string $defaultGroup = 'default';

    public static function doctrine(bool $getShared = true, ?string $dbGroup = null): Doctrine
    {
        $key = $dbGroup === null ? self::$prefix . '_' . self::$defaultGroup : self::$prefix . '_' . strtolower($dbGroup);

        if ($getShared) {
            if (! isset(static::$instances[$key])) {
                static::$instances[$key] = static::createDoctrineInstance($dbGroup);
            }

            /** @var Doctrine $instance */
            $instance = static::getSharedInstance($key);

            return $instance;
        }

        return static::createDoctrineInstance($dbGroup);
    }

    protected static function createDoctrineInstance(?string $dbGroup): Doctrine
    {
        $config      = config('Doctrine');
        $cacheConfig = config('Cache');

        if ($config === null) {
            throw new ConfigException('Doctrine config not found. Run `php spark doctrine:publish` to generate it.');
        }

        if ($cacheConfig === null) {
            throw new ConfigException('Cache config not found. Ensure Config\\Cache exists in your application.');
        }

        return new Doctrine($config, $cacheConfig, $dbGroup);
    }

    /**
     * Remove one or all shared Doctrine instances from the framework service registry.
     *
     * Examples:
     *   Services::resetDoctrine();            // clears default (doctrine_default)
     *   Services::resetDoctrine('reporting'); // clears only doctrine_reporting
     */
    public static function resetDoctrine(?string $dbGroup = null): void
    {
        $key = $dbGroup !== null
            ? self::$prefix . '_' . strtolower($dbGroup)
            : self::$prefix . '_' . self::$defaultGroup;

        static::resetSingle($key);
    }

    public static function doctrineCollector(bool $getShared = true): DoctrineCollector
    {
        if ($getShared) {
            /** @var DoctrineCollector $instance */
            $instance = static::getSharedInstance('doctrineCollector');

            return $instance;
        }

        return new DoctrineCollector();
    }
}
