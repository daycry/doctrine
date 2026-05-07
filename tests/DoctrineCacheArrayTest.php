<?php

declare(strict_types=1);

namespace Tests;

use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\EntityManager;
use Psr\Cache\CacheItemPoolInterface;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class DoctrineCacheArrayTest extends TestCase
{
    public function testDoctrineWithArrayCache()
    {
        $cacheConf          = config('Cache');
        $cacheConf->handler = 'dummy'; // Fallback to ArrayAdapter
        $doctrine           = new Doctrine($this->config, $cacheConf);
        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testDoctrineArrayCachePersistsData()
    {
        $cacheConf          = config('Cache');
        $cacheConf->handler = 'dummy';
        $doctrine           = new Doctrine($this->config, $cacheConf);
        $cache              = $doctrine->em->getConfiguration()->getQueryCache();
        $key                = 'test_doctrine_array_cache';
        $value              = ['foo' => 'bar', 'baz' => 123];
        $this->assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $cache->deleteItem($key);
        $cache->save($cache->getItem($key)->set($value));
        $item = $cache->getItem($key);
        $this->assertTrue($item->isHit(), 'El valor debería estar en caché');
        $this->assertSame($value, $item->get());
        $cache->deleteItem($key);
    }
}
