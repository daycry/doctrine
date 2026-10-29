<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Debug\Toolbar;
use Daycry\Doctrine\CodeIgniter4\Config\Services;
use Daycry\Doctrine\CodeIgniter4\Debug\Toolbar\Collectors\DoctrineCollector;
use ReflectionProperty;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class DoctrineToolbarIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $database                                 = config('Database');
        $database->tests['DSN']                   = '';
        $database->tests['DBDriver']              = 'SQLite3';
        $database->tests['database']              = ':memory:';
        $this->config->secondLevelCache           = false;
        $this->config->secondLevelCacheStatistics = false;
    }

    private function toolbar(): Toolbar
    {
        $config             = config('Toolbar');
        $config->collectors = [DoctrineCollector::class];

        return new Toolbar($config);
    }

    private function collector(Toolbar $toolbar): DoctrineCollector
    {
        return new ReflectionProperty($toolbar, 'collectors')->getValue($toolbar)[0];
    }

    public function testToolbarDisplaysQueriesCapturedAfterItsConstruction(): void
    {
        $collector = $this->collector($this->toolbar());
        $this->assertTrue($collector->isEmpty());
        $this->assertSame([], $collector->getData()['queries']);
        $result = Services::doctrine()->getEm()->getConnection()->executeQuery('SELECT ? AS issue_number', [21]);
        $this->assertSame(21, (int) $result->fetchOne());

        $data = $collector->getAsArray();
        $this->assertFalse($data['isEmpty']);
        $this->assertSame(1, $data['badgeValue']);
        $this->assertStringContainsString('issue_number', $collector->display());
        $this->assertSame([1 => 21], $collector->getQueries()[0]['params']);
        $this->assertCount(1, $collector->timelineData());
    }

    public function testToolbarDisplaysQueriesCapturedBeforeItsConstruction(): void
    {
        Services::doctrine()->getEm()->getConnection()->fetchOne('SELECT 42');
        $collector = $this->collector($this->toolbar());

        $this->assertFalse($collector->isEmpty());
        $this->assertSame(1, $collector->getBadgeValue());
        $this->assertStringContainsString('42', $collector->display());
    }

    public function testToolbarResetClearsSharedQueriesAndCachedData(): void
    {
        $toolbar    = $this->toolbar();
        $collector  = $this->collector($toolbar);
        $connection = Services::doctrine()->getEm()->getConnection();
        $connection->fetchOne('SELECT 21');
        $this->assertCount(1, $collector->getData()['queries']);

        $toolbar->reset();

        $this->assertTrue($collector->isEmpty());
        $this->assertSame([], Services::doctrineCollector()->getQueries());
        $this->assertSame([], $collector->getData()['queries']);
        $connection->fetchOne('SELECT 42');
        $this->assertCount(1, $collector->getData()['queries']);
        $this->assertStringNotContainsString('21', $collector->display());
    }

    public function testSharedQueryLimitAppliesToTheRenderedToolbar(): void
    {
        $collector = $this->collector($this->toolbar());
        Services::doctrineCollector()->setMaxQueries(2);
        $connection = Services::doctrine()->getEm()->getConnection();

        foreach ([21, 42, 63] as $value) {
            $connection->executeQuery('SELECT ?', [$value])->fetchOne();
        }

        $this->assertSame(2, $collector->getBadgeValue());
        $this->assertSame([[1 => 42], [1 => 63]], array_column($collector->getQueries(), 'params'));
    }

    public function testToolbarFindsTheAdapterSlcLoggerWithoutSqlQueries(): void
    {
        $this->config->secondLevelCache           = true;
        $this->config->secondLevelCacheStatistics = true;
        Services::doctrine();
        $collector = $this->collector($this->toolbar());

        $this->assertSame([], $collector->getQueries());
        $this->assertFalse($collector->isEmpty());
        $this->assertTrue($collector->getData()['slc']['enabled']);
        $this->assertStringContainsString('Second-Level Cache', $collector->display());
    }

    public function testEmptyToolbarDoesNotInitializeDoctrineAndSeesLaterSlcStatistics(): void
    {
        $collector = $this->collector($this->toolbar());
        $this->assertNull(Services::peekDoctrine());
        $this->assertTrue($collector->isEmpty());
        $this->assertFalse($collector->getData()['slc']['enabled']);
        $this->assertNull(Services::peekDoctrine());

        $this->config->secondLevelCache           = true;
        $this->config->secondLevelCacheStatistics = true;
        Services::doctrine();

        $this->assertSame([], $collector->getQueries());
        $this->assertFalse($collector->isEmpty());
        $this->assertTrue($collector->getData()['slc']['enabled']);
    }
}
