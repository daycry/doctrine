<?php

declare(strict_types=1);

namespace Tests;

use Doctrine\ORM\EntityManager;
use Daycry\Doctrine\Doctrine;
use Config\Cache;
use Tests\Support\TestCase;

final class DoctrineCacheFileTest extends TestCase
{
    public function testDoctrineWithFileCache()
    {
        $cacheConf = config('Cache');
        $cacheConf->handler = 'file';
        $doctrine = new Doctrine($this->config, $cacheConf);
        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testDoctrineFileCachePersistsData()
    {
        $cacheConf = config('Cache');
        $cacheConf->handler = 'file';
        $doctrine = new Doctrine($this->config, $cacheConf);
        $cache = $doctrine->em->getConfiguration()->getQueryCache();
        $key = 'test_doctrine_file_cache';
        $value = ['foo' => 'bar', 'baz' => 123];
        $cache->deleteItem($key); // Limpia antes
        $cache->save($cache->getItem($key)->set($value));
        $item = $cache->getItem($key);
        $this->assertTrue($item->isHit(), 'El valor debería estar en caché');
        $this->assertEquals($value, $item->get());
        $cache->deleteItem($key); // Limpia después
    }
}
