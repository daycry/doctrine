<?php

namespace Tests;

use Daycry\Doctrine\Doctrine;
use Config\Cache;
use Tests\Support\TestCase;

class DoctrineCacheMemcachedTest extends TestCase
{
    public function testDoctrineWithMemcachedCache()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('La extensión memcached no está habilitada.');
        }
        $cacheConf = config('Cache');
        $cacheConf->handler = 'memcached';
        $doctrine = new Doctrine($this->config, $cacheConf);
        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(\Doctrine\ORM\EntityManager::class, $doctrine->em);
    }

    public function testDoctrineMemcachedCachePersistsData()
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('La extensión memcached no está habilitada.');
        }
        $cacheConf = config('Cache');
        $cacheConf->handler = 'memcached';
        $doctrine = new Doctrine($this->config, $cacheConf);
        $cache = $doctrine->em->getConfiguration()->getQueryCache();
        $key = 'test_doctrine_memcached_cache';
        $value = ['foo' => 'bar', 'baz' => 123];
        $cache->deleteItem($key);
        $cache->save($cache->getItem($key)->set($value));
        $item = $cache->getItem($key);
        $this->assertTrue($item->isHit(), 'El valor debería estar en caché');
        $this->assertEquals($value, $item->get());
        $cache->deleteItem($key);
    }
}
