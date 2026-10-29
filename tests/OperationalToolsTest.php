<?php

declare(strict_types=1);

namespace Tests;

use Daycry\Doctrine\CodeIgniter4\Commands\DoctrineCacheWarmup;
use Daycry\Doctrine\CodeIgniter4\Commands\DoctrineDoctor;
use Daycry\Doctrine\CodeIgniter4\Doctrine;
use Daycry\Doctrine\Tools\CacheWarmer;
use Daycry\Doctrine\Tools\Diagnostics;
use Psr\Cache\CacheItemInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class OperationalToolsTest extends TestCase
{
    private string $originalHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $db                      = config('Database');
        $db->tests['DSN']        = '';
        $db->tests['DBDriver']   = 'SQLite3';
        $db->tests['database']   = ':memory:';
        $this->originalHandler   = config('Cache')->handler;
        config('Cache')->handler = 'dummy';
    }

    protected function tearDown(): void
    {
        config('Cache')->handler = $this->originalHandler;
        parent::tearDown();
    }

    public function testDiagnosticsProbeDoesNotDeleteApplicationCacheEntries(): void
    {
        $doctrine = new Doctrine($this->config);
        $pool     = $doctrine->getEm()->getConfiguration()->getResultCache();
        $this->assertInstanceOf(ArrayAdapter::class, $pool);
        $pool->save($pool->getItem('application_value')->set('keep'));
        $report = new Diagnostics()->inspect($doctrine);

        $this->assertTrue($report['healthy']);
        $this->assertSame('keep', $pool->getItem('application_value')->get());
        $this->assertSame(['application_value'], array_keys($pool->getValues()));
        $this->assertSame('ok', $report['checks']['database']['status']);
        $this->assertSame('ok', $report['checks']['mapping']['status']);
    }

    public function testDiagnosticsRejectsClosedEntityManager(): void
    {
        $doctrine = new Doctrine($this->config);
        $doctrine->getEm()->close();
        $report = new Diagnostics()->inspect($doctrine);

        $this->assertFalse($report['healthy']);
        $this->assertSame('error', $report['checks']['entity_manager']['status']);
    }

    public function testDiagnosticsMarksFailedCacheWritesAsUnhealthy(): void
    {
        $doctrine = new Doctrine($this->config);
        $doctrine->getEm()->getConfiguration()->setResultCache(new class () extends ArrayAdapter {
            public function save(CacheItemInterface $item): bool
            {
                return false;
            }
        });
        $report = new Diagnostics()->inspect($doctrine);

        $this->assertFalse($report['healthy']);
        $this->assertSame('error', $report['checks']['cache.result']['status']);
    }

    public function testDiagnosticsDoesNotExposeExceptionSecrets(): void
    {
        $doctrine = new Doctrine($this->config);
        $doctrine->getEm()->getConfiguration()->setResultCache(new class () extends ArrayAdapter {
            public function save(CacheItemInterface $item): bool
            {
                throw new RuntimeException('password=doctor-secret');
            }
        });
        $report = new Diagnostics()->inspect($doctrine);

        $this->assertFalse($report['healthy']);
        $this->assertStringNotContainsString('doctor-secret', json_encode($report, JSON_THROW_ON_ERROR));
    }

    public function testDoctorCommandAcceptsGroupAndJsonOutput(): void
    {
        $command = new DoctrineDoctor(service('logger'), service('commands'));
        $this->assertSame(0, $command->run(['group' => 'tests', 'json' => null]));
    }

    public function testDoctorCommandReturnsFailureForUnknownGroup(): void
    {
        $command = new DoctrineDoctor(service('logger'), service('commands'));
        $this->assertSame(1, $command->run(['group' => 'missing_group', 'json' => null]));
    }

    public function testWarmupPopulatesMetadataWithoutCreatingDatabaseTables(): void
    {
        $doctrine = new Doctrine($this->config);
        $em       = $doctrine->getEm();
        $pool     = $em->getConfiguration()->getMetadataCache();
        $this->assertInstanceOf(ArrayAdapter::class, $pool);
        $before = $em->getConnection()->createSchemaManager()->introspectTableNames();
        $report = new CacheWarmer()->warm($doctrine);

        $this->assertSame(2, $report['metadata']);
        $this->assertTrue($report['metadata_cache']);
        $this->assertNotEmpty($pool->getValues());
        $this->assertSame($before, $em->getConnection()->createSchemaManager()->introspectTableNames());
    }

    public function testNativeLazyObjectsNeedNoProxyGeneration(): void
    {
        $doctrine = new Doctrine($this->config);
        $report   = new CacheWarmer()->warm($doctrine);

        $this->assertTrue($doctrine->getEm()->getConfiguration()->isNativeLazyObjectsEnabled());
        $this->assertSame(2, $report['metadata']);
    }

    public function testWarmupFailsWhenMetadataCacheCannotBeWritten(): void
    {
        $doctrine = new Doctrine($this->config);
        $doctrine->getEm()->getConfiguration()->setMetadataCache(new class () extends ArrayAdapter {
            public function save(CacheItemInterface $item): bool
            {
                return false;
            }
        });
        $this->expectException(RuntimeException::class);

        new CacheWarmer()->warm($doctrine);
    }

    public function testWarmupCommandAcceptsGroup(): void
    {
        $command = new DoctrineCacheWarmup(service('logger'), service('commands'));
        $this->assertSame(0, $command->run(['group' => 'tests']));
    }
}
