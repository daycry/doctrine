<?php

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector;

final class DoctrineCollectorSlcNoQueriesTest extends CIUnitTestCase
{
    public function testCollectorNotEmptyWhenOnlySlcStats(): void
    {
        // Ensure static queries are cleared (they persist across tests)
        $ref = new \ReflectionProperty(DoctrineCollector::class, 'queries');
        $ref->setAccessible(true);
        $ref->setValue(null, []);

        $collector = new DoctrineCollector();
        // Inject SLC logger with counters and NO queries added.
        $collector->setSecondLevelCacheLogger(new class {
            public int $cacheHits = 10;
            public int $cacheMisses = 2;
            public int $cachePuts = 12;
        });

        $this->assertFalse($collector->isEmpty(), 'Collector should not be empty when SLC stats are enabled.');
        $html = $collector->display();
        $this->assertStringContainsString('Second-Level Cache', $html);
        $this->assertStringContainsString('All results served from Second-Level Cache', $html);
        $this->assertStringContainsString('>10</td>', $html);
        $this->assertStringContainsString('>2</td>', $html);
        $this->assertStringContainsString('>12</td>', $html);
    }
}
