<?php

declare(strict_types=1);

namespace Tests;

use Daycry\Doctrine\Config\Services;
use Daycry\Doctrine\Doctrine;
use Tests\Support\TestCase;

use function Daycry\Doctrine\Helpers\getFromCacheOrQuery;

/**
 * @internal
 */
final class GetFromCacheOrQueryTest extends TestCase
{
    private Doctrine $doctrine;

    protected function setUp(): void
    {
        parent::setUp();

        // Use SQLite3 in-memory so tests don't depend on a running MySQL instance.
        $db                    = config('Database');
        $db->tests['DBDriver'] = 'SQLite3';
        $db->tests['database'] = ':memory:';

        $this->config->resultsCache = true;
        $this->doctrine             = new Doctrine($this->config, config('Cache'), 'tests');
        Services::injectMock('doctrine_default', $this->doctrine);
    }

    protected function tearDown(): void
    {
        // Clear the result cache pool between tests
        $pool = $this->doctrine->em?->getConfiguration()->getResultCache();
        $pool?->clear();

        parent::tearDown();
    }

    public function testQueryRunsOnCacheMiss(): void
    {
        $calls   = 0;
        $queryFn = static function () use (&$calls) {
            $calls++;

            return ['hello', 'world'];
        };

        $result = getFromCacheOrQuery('test_miss_' . uniqid(), 60, $queryFn);

        $this->assertSame(['hello', 'world'], $result);
        $this->assertSame(1, $calls);
    }

    public function testQueryNotCalledOnCacheHit(): void
    {
        $key     = 'test_hit_' . uniqid();
        $calls   = 0;
        $queryFn = static function () use (&$calls) {
            $calls++;

            return 'computed';
        };

        $first  = getFromCacheOrQuery($key, 60, $queryFn);
        $second = getFromCacheOrQuery($key, 60, $queryFn);

        $this->assertSame('computed', $first);
        $this->assertSame('computed', $second);
        $this->assertSame(1, $calls, 'Second call should hit the cache and skip the closure');
    }

    public function testFallsBackToQueryWhenResultCacheDisabled(): void
    {
        // Recreate Doctrine with results cache disabled
        $this->config->resultsCache = false;
        $doctrine                   = new Doctrine($this->config, config('Cache'), 'tests');
        Services::injectMock('doctrine_default', $doctrine);

        $calls   = 0;
        $queryFn = static function () use (&$calls) {
            $calls++;

            return 'always-fresh';
        };

        $first  = getFromCacheOrQuery('test_nocache_' . uniqid(), 60, $queryFn);
        $second = getFromCacheOrQuery('test_nocache_' . uniqid(), 60, $queryFn);

        $this->assertSame('always-fresh', $first);
        $this->assertSame('always-fresh', $second);
        $this->assertSame(2, $calls);
    }

    public function testTtlZeroPersistsWithoutExpiration(): void
    {
        $key     = 'test_ttl0_' . uniqid();
        $calls   = 0;
        $queryFn = static function () use (&$calls) {
            $calls++;

            return 42;
        };

        $first  = getFromCacheOrQuery($key, 0, $queryFn);
        $second = getFromCacheOrQuery($key, 0, $queryFn);

        $this->assertSame(42, $first);
        $this->assertSame(42, $second);
        $this->assertSame(1, $calls);
    }
}
