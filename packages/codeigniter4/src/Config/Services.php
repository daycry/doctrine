<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4\Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Exceptions\ConfigException;
use Daycry\Doctrine\CodeIgniter4\Debug\Toolbar\Collectors\DoctrineCollector;
use Daycry\Doctrine\CodeIgniter4\Doctrine;

class Services extends BaseService
{
    private static string $prefix = 'doctrine';

    public static function doctrine(bool $getShared = true, ?string $dbGroup = null): Doctrine
    {
        $dbGroup = DatabaseGroup::resolve($dbGroup);
        $key     = self::$prefix . '_' . DatabaseGroup::key($dbGroup);

        $mock = static::$mocks[$key] ?? null;
        if ($getShared && $mock !== null) {
            if (! $mock instanceof Doctrine) {
                throw new ConfigException('The shared Doctrine mock must be a Doctrine instance.');
            }

            return $mock;
        }

        if ($getShared) {
            static::$instances[$key] ??= static::createDoctrineInstance($dbGroup);

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
     * Remove the shared Doctrine instance from the framework service registry.
     *
     * Examples:
     *   Services::resetDoctrine();            // clears the effective default group
     *   Services::resetDoctrine('reporting'); // clears only the reporting group
     */
    public static function resetDoctrine(?string $dbGroup = null): void
    {
        $key = self::$prefix . '_' . DatabaseGroup::key(DatabaseGroup::resolve($dbGroup));

        static::resetSingle($key);
    }

    /**
     * Return an existing instance without initializing a connection.
     */
    public static function peekDoctrine(?string $dbGroup = null): ?Doctrine
    {
        $key = self::$prefix . '_' . DatabaseGroup::key(DatabaseGroup::resolve($dbGroup));

        $instance = static::$mocks[$key] ?? static::$instances[$key] ?? null;
        if ($instance !== null && ! $instance instanceof Doctrine) {
            throw new ConfigException('The shared Doctrine service must be a Doctrine instance.');
        }

        return $instance;
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
