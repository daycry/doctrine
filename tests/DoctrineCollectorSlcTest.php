<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector;
use Doctrine\ORM\Cache\EntityCacheKey;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;

final class DoctrineCollectorSlcTest extends CIUnitTestCase
{
    /**
     * Build a StatisticsCacheLogger pre-loaded with hit/miss/put counts.
     */
    private static function makeLogger(int $hits, int $misses, int $puts): StatisticsCacheLogger
    {
        $logger = new StatisticsCacheLogger();
        $key    = new EntityCacheKey(stdClass::class, ['id' => 1]);

        for ($i = 0; $i < $hits; $i++) {
            $logger->entityCacheHit('test', $key);
        }

        for ($i = 0; $i < $misses; $i++) {
            $logger->entityCacheMiss('test', $key);
        }

        for ($i = 0; $i < $puts; $i++) {
            $logger->entityCachePut('test', $key);
        }

        return $logger;
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testTitleDetailsShowsSlcBadge(): void
    {
        $collector = new DoctrineCollector();
        $collector->setSecondLevelCacheLogger(self::makeLogger(5, 3, 7));
        $details = $collector->getTitleDetails();
        $this->assertStringContainsString('SLC:', $details);
        $this->assertStringContainsString('5/3/7', $details); // hits/misses/puts
        $this->assertMatchesRegularExpression('/\(\d+%\)/', $details); // ratio
    }

    public function testDisplayContainsSlcTable(): void
    {
        $collector = new DoctrineCollector();
        $collector->setSecondLevelCacheLogger(self::makeLogger(5, 3, 7));
        // Add a dummy query to avoid early return
        $collector->addQuery([
            'sql'      => 'SELECT 1',
            'params'   => [],
            'duration' => 0.01,
        ]);
        $html = $collector->display();
        $this->assertStringContainsString('<h3>Second-Level Cache</h3>', $html);
        $this->assertStringContainsString('Hits', $html);
        $this->assertStringContainsString('Misses', $html);
        $this->assertStringContainsString('Puts', $html);
        $this->assertStringContainsString('>5</td>', $html);
        $this->assertStringContainsString('>3</td>', $html);
        $this->assertStringContainsString('>7</td>', $html);
    }
}
