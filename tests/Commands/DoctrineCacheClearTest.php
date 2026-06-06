<?php

declare(strict_types=1);

namespace Tests\Commands;

use Daycry\Doctrine\Config\Services;
use Psr\Cache\CacheItemPoolInterface;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class DoctrineCacheClearTest extends TestCase
{
    public function testCacheClearEmptiesTheResultCachePool(): void
    {
        $db                    = config('Database');
        $db->tests['DBDriver'] = 'SQLite3';
        $db->tests['database'] = ':memory:';

        Services::resetDoctrine('tests');
        $pool = Services::doctrine(true, 'tests')->getEm()->getConfiguration()->getResultCache();
        $this->assertInstanceOf(CacheItemPoolInterface::class, $pool);

        $item = $pool->getItem('audit_cache_probe');
        $item->set('value');
        $pool->save($item);
        $this->assertTrue($pool->getItem('audit_cache_probe')->isHit(), 'pre-condition: item is cached');

        command('doctrine:cache:clear --group tests');

        $this->assertFalse($pool->getItem('audit_cache_probe')->isHit(), 'cache:clear must empty the pool');

        Services::resetDoctrine('tests');
    }
}
