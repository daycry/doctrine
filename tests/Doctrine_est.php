<?php

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\TestSeeder;
use Doctrine\ORM\EntityManager;
use Daycry\Doctrine\Doctrine;
use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;
use Tests\Support\TestCase;
use Config\Cache;

class DoctrineTest extends TestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seedOnce = false;
    protected $seed = TestSeeder::class;

    protected $config;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var DoctrineConfig $config */
        $this->config = config('Doctrine');
        $this->config->entities = [SUPPORTPATH . 'Models/Entities'];
        $this->config->proxies = SUPPORTPATH . 'Models/Proxies';
    }

    public function testInstanceDoctrine()
    {
        $doctrine = new Doctrine();

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testInstanceDoctrineAsAHelper()
    {
        helper('doctrine_helper');

        $doctrine = doctrine_instance();

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testInstanceDoctrineCustomConfig()
    {
        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testInstanceDoctrineRedis()
    {
        /** @var Cache $cacheConf */
        $cacheConf = config('Cache');
        $cacheConf->handler = 'redis';

        $cache = \Config\Services::cache($cacheConf);

        if ($cache->isSupported()) {
            $doctrine = new Doctrine($this->config, $cacheConf);

            $this->assertInstanceOf(Doctrine::class, $doctrine);
            $this->assertInstanceOf(EntityManager::class, $doctrine->em);
        }
    }

    public function testInstanceDoctrineFile()
    {
        /** @var Cache $cacheConf */
        $cacheConf = config('Cache');
        $cacheConf->handler = 'file';

        $doctrine = new Doctrine($this->config, $cacheConf);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testInstanceDoctrineMemcached()
    {
        /** @var Cache $cacheConf */
        $cacheConf = config('Cache');
        $cacheConf->handler = 'memcached';

        $doctrine = new Doctrine($this->config, $cacheConf);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testInstanceDoctrineArray()
    {
        /** @var Cache $cacheConf */
        $cacheConf = config('Cache');
        $cacheConf->handler = 'dummy';

        $doctrine = new Doctrine($this->config, $cacheConf);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testDoctrineReOpen()
    {
        $doctrine = new \Daycry\Doctrine\Doctrine($this->config);

        $this->assertInstanceOf(\Doctrine\ORM\EntityManager::class, $doctrine->em);
        $doctrine->reOpen();
        $this->assertInstanceOf(\Daycry\Doctrine\Doctrine::class, $doctrine);
        $this->assertInstanceOf(\Doctrine\ORM\EntityManager::class, $doctrine->em);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
