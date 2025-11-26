<?php

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector;

final class DoctrineCollectorSlcTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Stub Doctrine service with SLC logger
        $stubLogger = new class {
            public int $cacheHits = 5;
            public int $cacheMisses = 3;
            public int $cachePuts = 7;
        };

        $stubDoctrine = new class($stubLogger) {
            private $logger;
            public function __construct($logger) { $this->logger = $logger; }
            public function getSecondLevelCacheLogger() { return $this->logger; }
        };

        // Inject mock into Services
        if (method_exists(\Config\Services::class, 'injectMock')) {
            \Config\Services::injectMock('doctrine', $stubDoctrine);
        }
    }

    public function testTitleDetailsShowsSlcBadge(): void
    {
        $collector = new DoctrineCollector();
        // Inject logger directly to avoid service dependency
        $collector->setSecondLevelCacheLogger(new class {
            public int $cacheHits = 5;
            public int $cacheMisses = 3;
            public int $cachePuts = 7;
        });
        $details = $collector->getTitleDetails();
        $this->assertStringContainsString('SLC:', $details);
        $this->assertStringContainsString('5/3/7', $details); // hits/misses/puts
        $this->assertMatchesRegularExpression('/\(\d+%\)/', $details); // ratio
    }

    public function testDisplayContainsSlcTable(): void
    {
        $collector = new DoctrineCollector();
        // Inject logger directly
        $collector->setSecondLevelCacheLogger(new class {
            public int $cacheHits = 5;
            public int $cacheMisses = 3;
            public int $cachePuts = 7;
        });
        // Add a dummy query to avoid early return
        $collector->addQuery([
            'sql' => 'SELECT 1',
            'params' => [],
            'duration' => 0.01,
        ]);
        $html = $collector->display();
        // When no queries, display still returns SLC table + No queries message
        $this->assertStringContainsString('<h3>Second-Level Cache</h3>', $html);
        // headers may include classes; assert labels loosely
        $this->assertStringContainsString('Hits', $html);
        $this->assertStringContainsString('Misses', $html);
        $this->assertStringContainsString('Puts', $html);
        // Numeric cells include class attributes; assert loosely
        $this->assertStringContainsString('>5</td>', $html);
        $this->assertStringContainsString('>3</td>', $html);
        $this->assertStringContainsString('>7</td>', $html);
    }
}
