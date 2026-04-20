<?php

declare(strict_types=1);

namespace Tests;

use Tests\Support\TestCase;
use Daycry\Doctrine\Doctrine;
use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;

/**
 * Covers previously untested Doctrine methods:
 * - getSecondLevelCacheLogger() — null, non-stats-logger, and stats-logger branches
 * - resetSecondLevelCacheStatistics() — null-logger and clearStats branches
 * - createSecondLevelCachePool() — file and dummy(default) branches
 */
final class DoctrineSLCCoverageTest extends TestCase
{
    /** @var string Original cache handler to restore after each test */
    private string $originalCacheHandler = 'file';

    protected function setUp(): void
    {
        parent::setUp();

        $db                    = config('Database');
        $db->tests['DBDriver'] = 'SQLite3';
        $db->tests['database'] = ':memory:';

        // Save original handler so we can restore it in tearDown
        $this->originalCacheHandler = config('Cache')->handler;
    }

    protected function tearDown(): void
    {
        // Restore the cache handler to avoid polluting other tests
        config('Cache')->handler = $this->originalCacheHandler;
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // getSecondLevelCacheLogger() — branch 1: SLC disabled => null
    // -----------------------------------------------------------------------

    public function testGetSecondLevelCacheLoggerReturnsNullWhenSlcDisabled(): void
    {
        $this->config->secondLevelCache = false;

        $doctrine = new Doctrine($this->config, null, 'tests');
        $logger   = $doctrine->getSecondLevelCacheLogger();

        $this->assertNull($logger);
    }

    // -----------------------------------------------------------------------
    // getSecondLevelCacheLogger() — branch 2: SLC enabled, no stats logger
    // -----------------------------------------------------------------------

    public function testGetSecondLevelCacheLoggerReturnsNullWhenStatsDisabled(): void
    {
        $this->config->secondLevelCache           = true;
        $this->config->secondLevelCacheStatistics = false;

        $cacheConfig          = config('Cache');
        $cacheConfig->handler = 'dummy';

        $doctrine = new Doctrine($this->config, $cacheConfig, 'tests');
        $logger   = $doctrine->getSecondLevelCacheLogger();

        // No StatisticsCacheLogger installed → should be null
        $this->assertNull($logger);
    }

    // -----------------------------------------------------------------------
    // getSecondLevelCacheLogger() — branch 3: SLC enabled + statistics=true
    // -----------------------------------------------------------------------

    public function testGetSecondLevelCacheLoggerReturnsStatisticsLogger(): void
    {
        $this->config->secondLevelCache           = true;
        $this->config->secondLevelCacheStatistics = true;

        $cacheConfig          = config('Cache');
        $cacheConfig->handler = 'dummy';

        $doctrine = new Doctrine($this->config, $cacheConfig, 'tests');
        $logger   = $doctrine->getSecondLevelCacheLogger();

        $this->assertInstanceOf(StatisticsCacheLogger::class, $logger);
    }

    // -----------------------------------------------------------------------
    // resetSecondLevelCacheStatistics() — branch 1: null logger (no-op)
    // -----------------------------------------------------------------------

    public function testResetSecondLevelCacheStatisticsWithNoLoggerIsNoop(): void
    {
        $this->config->secondLevelCache = false;

        $doctrine = new Doctrine($this->config, null, 'tests');

        // Must not throw
        $doctrine->resetSecondLevelCacheStatistics();

        $this->assertNull($doctrine->getSecondLevelCacheLogger());
    }

    // -----------------------------------------------------------------------
    // resetSecondLevelCacheStatistics() — branch 2: clears stats
    // -----------------------------------------------------------------------

    public function testResetSecondLevelCacheStatisticsClearsCounters(): void
    {
        $this->config->secondLevelCache           = true;
        $this->config->secondLevelCacheStatistics = true;

        $cacheConfig          = config('Cache');
        $cacheConfig->handler = 'dummy';

        $doctrine = new Doctrine($this->config, $cacheConfig, 'tests');
        $logger   = $doctrine->getSecondLevelCacheLogger();
        $this->assertInstanceOf(StatisticsCacheLogger::class, $logger);

        $doctrine->resetSecondLevelCacheStatistics();

        // After reset all counts should be zero
        $this->assertSame(0, $logger->getHitCount());
        $this->assertSame(0, $logger->getMissCount());
        $this->assertSame(0, $logger->getPutCount());
    }

    // -----------------------------------------------------------------------
    // createSecondLevelCachePool() — 'file' branch
    // -----------------------------------------------------------------------

    public function testCreateSecondLevelCachePoolWithFileHandler(): void
    {
        $this->config->secondLevelCache = true;

        $cacheConfig          = config('Cache');
        $cacheConfig->handler = 'file';
        $cacheConfig->prefix  = 'ci_';

        // Use sys_get_temp_dir() so the path is writable in CI
        $tmpDir                     = sys_get_temp_dir();
        $cacheConfig->file['storePath'] = $tmpDir;

        $doctrine = new Doctrine($this->config, $cacheConfig, 'tests');

        // If Doctrine booted without error, the file pool was created successfully
        $this->assertTrue($doctrine->em->getConfiguration()->isSecondLevelCacheEnabled());
    }

    // -----------------------------------------------------------------------
    // createSecondLevelCachePool() — 'array' (default) branch
    // -----------------------------------------------------------------------

    public function testCreateSecondLevelCachePoolWithArrayHandler(): void
    {
        $this->config->secondLevelCache = true;

        $cacheConfig          = config('Cache');
        $cacheConfig->handler = 'dummy';

        $doctrine = new Doctrine($this->config, $cacheConfig, 'tests');

        $this->assertTrue($doctrine->em->getConfiguration()->isSecondLevelCacheEnabled());
    }

    // -----------------------------------------------------------------------
    // Doctrine constructor — invalid entity path throws ConfigException
    // -----------------------------------------------------------------------

    public function testConstructorThrowsOnNonExistentEntityPath(): void
    {
        $this->expectException(\CodeIgniter\Exceptions\ConfigException::class);

        $this->config->entities = ['/non/existent/path/that/does/not/exist'];
        new Doctrine($this->config, null, 'tests');
    }
}
