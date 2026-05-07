<?php

declare(strict_types=1);

namespace Tests;

use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\TestCase;

/**
 * Covers the `RuntimeException` paths added in PR #15:
 *
 *  - Doctrine::getEm() throws when $em is null.
 *  - Doctrine::reOpen() goes through getEm() and therefore inherits the guard.
 *  - Doctrine::getSecondLevelCacheLogger() returns null when the configuration
 *    has no `StatisticsCacheLogger` attached (the `instanceof` false branch).
 *
 * @internal
 */
final class DoctrineNullGuardsTest extends TestCase
{
    private Doctrine $doctrine;

    protected function setUp(): void
    {
        parent::setUp();

        // Use SQLite in-memory so the constructor succeeds without MySQL.
        $db                    = config('Database');
        $db->tests['DBDriver'] = 'SQLite3';
        $db->tests['database'] = ':memory:';

        $this->doctrine = new Doctrine($this->config, config('Cache'), 'tests');
    }

    public function testGetEmThrowsWhenEntityManagerIsNull(): void
    {
        // Force the public property to null without calling reOpen().
        $emProp = new ReflectionProperty($this->doctrine, 'em');
        $emProp->setValue($this->doctrine, null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('EntityManager has not been initialized.');

        $this->doctrine->getEm();
    }

    public function testReOpenThrowsWhenEntityManagerIsNull(): void
    {
        $emProp = new ReflectionProperty($this->doctrine, 'em');
        $emProp->setValue($this->doctrine, null);

        $this->expectException(RuntimeException::class);

        $this->doctrine->reOpen();
    }

    public function testGetSecondLevelCacheLoggerReturnsNullWhenStatsDisabled(): void
    {
        // SLC enabled but statistics disabled → no StatisticsCacheLogger attached.
        $this->config->secondLevelCache           = true;
        $this->config->secondLevelCacheStatistics = false;

        $doctrine = new Doctrine($this->config, config('Cache'), 'tests');

        $this->assertNotInstanceOf(StatisticsCacheLogger::class, $doctrine->getSecondLevelCacheLogger());
    }

    public function testGetSecondLevelCacheLoggerReturnsNullWhenSlcDisabled(): void
    {
        // SLC fully disabled → getConfiguration()->getSecondLevelCacheConfiguration() is null.
        $this->config->secondLevelCache = false;
        $doctrine                       = new Doctrine($this->config, config('Cache'), 'tests');

        $this->assertNotInstanceOf(StatisticsCacheLogger::class, $doctrine->getSecondLevelCacheLogger());
    }

    public function testResetSecondLevelCacheStatisticsIsNoOpWhenLoggerMissing(): void
    {
        $this->config->secondLevelCache           = true;
        $this->config->secondLevelCacheStatistics = false;
        $doctrine                                 = new Doctrine($this->config, config('Cache'), 'tests');

        // Must not throw even though there is no logger to reset.
        $doctrine->resetSecondLevelCacheStatistics();

        $this->assertNotInstanceOf(StatisticsCacheLogger::class, $doctrine->getSecondLevelCacheLogger());
    }
}
