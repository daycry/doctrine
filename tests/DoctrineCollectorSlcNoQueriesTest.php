<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector;
use Doctrine\ORM\Cache\EntityCacheKey;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;

final class DoctrineCollectorSlcNoQueriesTest extends CIUnitTestCase
{
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

    public function testCollectorNotEmptyWhenOnlySlcStats(): void
    {
        $collector = new DoctrineCollector();
        $collector->setSecondLevelCacheLogger(self::makeLogger(10, 2, 12));

        $this->assertFalse($collector->isEmpty(), 'Collector should not be empty when SLC stats are enabled.');
        $html = $collector->display();
        $this->assertStringContainsString('Second-Level Cache', $html);
        $this->assertStringContainsString('All results served from Second-Level Cache', $html);
        $this->assertStringContainsString('>10</td>', $html);
        $this->assertStringContainsString('>2</td>', $html);
        $this->assertStringContainsString('>12</td>', $html);
    }
}
