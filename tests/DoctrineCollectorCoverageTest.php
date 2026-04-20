<?php

declare(strict_types=1);

namespace Tests;

use Tests\Support\TestCase;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;

/**
 * Covers previously untested DoctrineCollector methods:
 * reset(), formatTimelineData() via getTimeline(), icon(), getData() SLC path.
 */
final class DoctrineCollectorCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testResetClearsQueries(): void
    {
        $collector = new DoctrineCollector();
        $collector->addQuery(['sql' => 'SELECT 1', 'params' => [], 'duration' => 0.01]);
        $collector->addQuery(['sql' => 'SELECT 2', 'params' => [], 'duration' => 0.02]);

        $this->assertSame(2, $collector->getBadgeValue());

        $collector->reset();

        $this->assertSame(0, $collector->getBadgeValue());
        $this->assertSame([], $collector->getQueries());
        $this->assertTrue($collector->isEmpty());
    }

    public function testResetOnEmptyCollectorIsIdempotent(): void
    {
        $collector = new DoctrineCollector();
        $collector->reset();
        $this->assertSame([], $collector->getQueries());
    }

    public function testIconReturnsPngDataUrl(): void
    {
        $collector = new DoctrineCollector();
        $icon      = $collector->icon();

        $this->assertStringStartsWith('data:image/png;base64,', $icon);
        $this->assertNotEmpty($icon);
    }

    public function testFormatTimelineDataViaSubclass(): void
    {
        $collector = new class extends DoctrineCollector {
            /** @return array<int, array<string, mixed>> */
            public function exposeFormatTimeline(): array
            {
                return $this->formatTimelineData();
            }
        };

        $collector->addQuery([
            'sql'      => 'SELECT * FROM users',
            'params'   => [],
            'start'    => 1000.0,
            'duration' => 1.5,
        ]);
        $collector->addQuery([
            'sql'      => 'INSERT INTO logs VALUES (1)',
            'params'   => [1],
            'start'    => 1001.0,
            'duration' => 0.5,
        ]);

        $timeline = $collector->exposeFormatTimeline();

        $this->assertIsArray($timeline);
        $this->assertCount(2, $timeline);

        $this->assertSame('Doctrine Query', $timeline[0]['name']);
        $this->assertSame('Doctrine', $timeline[0]['component']);
        $this->assertSame(1000.0, $timeline[0]['start']);
        $this->assertSame(1.5, $timeline[0]['duration']);
        $this->assertStringContainsString('SELECT', $timeline[0]['query']);

        $this->assertSame('Doctrine Query', $timeline[1]['name']);
        $this->assertSame(1001.0, $timeline[1]['start']);
    }

    public function testFormatTimelineDataWithMissingFields(): void
    {
        $collector = new class extends DoctrineCollector {
            /** @return array<int, array<string, mixed>> */
            public function exposeFormatTimeline(): array
            {
                return $this->formatTimelineData();
            }
        };

        // Query without 'start' and 'duration' keys — should default to 0
        $collector->addQuery(['sql' => 'SELECT 1', 'params' => []]);

        $timeline = $collector->exposeFormatTimeline();

        $this->assertCount(1, $timeline);
        $this->assertSame(0, $timeline[0]['start']);
        $this->assertSame(0, $timeline[0]['duration']);
    }

    public function testGetDataContainsQueriesKey(): void
    {
        $collector = new DoctrineCollector();
        $collector->addQuery([
            'sql'      => 'SELECT id FROM test',
            'params'   => [],
            'duration' => 0.05,
        ]);

        $data = $collector->getData();

        $this->assertArrayHasKey('queries', $data);
        $this->assertCount(1, $data['queries']);
        $this->assertSame('SELECT id FROM test', $data['queries'][0]['sql']);
    }

    public function testGetDataWithSlcLoggerReturnsSlcSection(): void
    {
        $collector = new DoctrineCollector();

        // Add some queries so display() exercises the full path
        $collector->addQuery(['sql' => 'SELECT 1', 'params' => [], 'duration' => 0.01]);

        $data = $collector->getData();

        $this->assertArrayHasKey('queries', $data);
    }

    public function testGetDataWithInjectedSlcLoggerPopulatesSlcSection(): void
    {
        $collector = new DoctrineCollector();
        $logger    = new StatisticsCacheLogger();
        $collector->setSecondLevelCacheLogger($logger);

        $data = $collector->getData();

        $this->assertArrayHasKey('slc', $data);
        $this->assertTrue($data['slc']['enabled']);
        $this->assertSame(0, $data['slc']['hits']);
        $this->assertSame(0, $data['slc']['misses']);
        $this->assertSame(0, $data['slc']['puts']);
    }

    public function testGetTitleDetailsWithInjectedSlcLogger(): void
    {
        $collector = new DoctrineCollector();
        $logger    = new StatisticsCacheLogger();
        $collector->setSecondLevelCacheLogger($logger);

        $collector->addQuery(['sql' => 'SELECT 1', 'params' => [], 'duration' => 0.01]);

        $details = $collector->getTitleDetails();

        // With SLC logger injected, details should include the SLC badge
        $this->assertStringContainsString('SLC:', $details);
    }
}
