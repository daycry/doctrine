<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Doctrine\Config\Services;
use Daycry\Doctrine\Debug\Filters\DoctrineSlcReset;
use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\Cache\EntityCacheKey;
use stdClass;
use Tests\Support\TestCase;

/**
 * Covers DoctrineSlcReset::before() and DoctrineSlcReset::after().
 *
 * The filter is intentionally tolerant: it must never throw, even when the
 * Doctrine service is not bootstrapped or the SLC logger is not configured.
 * When the logger is available, before() must reset its counters.
 *
 * @internal
 */
final class DoctrineSlcResetTest extends TestCase
{
    public function testBeforeReturnsNullAndDoesNotThrowWhenServiceUnavailable(): void
    {
        $filter  = new DoctrineSlcReset();
        $request = $this->createStub(IncomingRequest::class);

        $result = $filter->before($request);

        $this->assertNull($result);
    }

    public function testAfterReturnsNull(): void
    {
        $filter   = new DoctrineSlcReset();
        $request  = $this->createStub(IncomingRequest::class);
        $response = $this->createStub(Response::class);

        $result = $filter->after($request, $response);

        $this->assertNotInstanceOf(ResponseInterface::class, $result);
        $this->assertNull($result);
    }

    public function testBeforeResetsSlcStatisticsWhenLoggerPresent(): void
    {
        // Use SQLite in-memory so tests don't require a live database connection.
        $db                    = config('Database');
        $db->tests['DBDriver'] = 'SQLite3';
        $db->tests['database'] = ':memory:';

        $this->config->secondLevelCache           = true;
        $this->config->secondLevelCacheStatistics = true;

        $doctrine = new Doctrine($this->config, config('Cache'), 'tests');
        $logger   = $doctrine->getSecondLevelCacheLogger();

        if ($logger === null) {
            $this->markTestSkipped('SLC stats logger not configured in this environment.');
        }

        $key = new EntityCacheKey(stdClass::class, ['id' => 1]);
        $logger->entityCacheHit('test', $key);
        $logger->entityCacheMiss('test', $key);
        $logger->entityCachePut('test', $key);

        $this->assertSame(1, $logger->getHitCount());
        $this->assertSame(1, $logger->getMissCount());
        $this->assertSame(1, $logger->getPutCount());

        // Inject the doctrine service so the filter resets the same logger.
        Services::injectMock('doctrine_default', $doctrine);

        $filter = new DoctrineSlcReset();
        $filter->before($this->createStub(IncomingRequest::class));

        $this->assertSame(0, $logger->getHitCount());
        $this->assertSame(0, $logger->getMissCount());
        $this->assertSame(0, $logger->getPutCount());
    }
}
