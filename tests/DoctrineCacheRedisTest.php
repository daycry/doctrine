<?php

declare(strict_types=1);

namespace Tests;

use Doctrine\ORM\EntityManager;
use Psr\Cache\CacheItemPoolInterface;
use Daycry\Doctrine\Doctrine;
use Config\Cache;
use Tests\Support\TestCase;

final class DoctrineCacheRedisTest extends TestCase
{
    public function testDoctrineWithRedisCache()
    {
        $cacheConf = config('Cache');
        $cacheConf->handler = 'redis';
        $doctrine = new Doctrine($this->config, $cacheConf);
        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testDoctrineRedisCachePersistsData()
    {
        $cacheConf = config('Cache');
        $cacheConf->handler = 'redis';
        $doctrine = new Doctrine($this->config, $cacheConf);
        $cache = $doctrine->em->getConfiguration()->getQueryCache();
        $key = 'test_doctrine_redis_cache';
        $value = ['foo' => 'bar', 'baz' => 123];
        $this->assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $cache->deleteItem($key);
        $cache->save($cache->getItem($key)->set($value));
        $item = $cache->getItem($key);
        $this->assertTrue($item->isHit(), 'El valor debería estar en caché');
        $this->assertEquals($value, $item->get());
        $cache->deleteItem($key);
    }
}
