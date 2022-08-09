<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class DoctrineTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seedOnce = false;
    protected $seed = \Tests\Support\Database\Seeds\TestSeeder::class;

    protected $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config('Doctrine');
        $this->config->namespaceModel = 'Tests/Support/Models';
        $this->config->folderModel = SUPPORTPATH . 'Models';
        $this->config->namespaceProxy = 'Tests/Support/Models/Proxies';
        $this->config->folderProxy = SUPPORTPATH . 'Models/Proxies';
        $this->config->folderEntity = SUPPORTPATH . 'Models/Entities';
    }
    
    public function testInstanceDoctrine()
    {
        $doctrine = new \Daycry\Doctrine\Doctrine();

        $this->assertInstanceOf(\Daycry\Doctrine\Doctrine::class, $doctrine);
        $this->assertInstanceOf(\Doctrine\ORM\EntityManager::class, $doctrine->em);
    }

    public function testInstanceDoctrineAsAHelper()
    {
        helper('doctrine_helper');

        $doctrine = doctrine_instance();

        $this->assertInstanceOf(\Daycry\Doctrine\Doctrine::class, $doctrine);
        $this->assertInstanceOf(\Doctrine\ORM\EntityManager::class, $doctrine->em);
    }

    public function testInstanceDoctrineCustomConfig()
    {
        $doctrine = new \Daycry\Doctrine\Doctrine($this->config);

        $this->assertInstanceOf(\Daycry\Doctrine\Doctrine::class, $doctrine);
        $this->assertInstanceOf(\Doctrine\ORM\EntityManager::class, $doctrine->em);
    }

    public function testInstanceDoctrineRedis()
    {
        $cacheConf = config( 'cache' );
        $cacheConf->handler = 'redis';

        $cache = \Config\Services::cache($cacheConf);

        if( $cache->isSupported() ) {
            $doctrine = new \Daycry\Doctrine\Doctrine($this->config, $cacheConf);

            $this->assertInstanceOf(\Daycry\Doctrine\Doctrine::class, $doctrine);
            $this->assertInstanceOf(\Doctrine\ORM\EntityManager::class, $doctrine->em);
        }
    }

    /*public function testInstanceDoctrineMemcached()
    {
        $cacheConf = config( 'cache' );
        $cacheConf->handler = 'memcached';

        $cache = \Config\Services::cache($cacheConf);

        if( $cache->isSupported() ) {
            $doctrine = new \Daycry\Doctrine\Doctrine($this->config, $cacheConf);

            $this->assertInstanceOf(\Daycry\Doctrine\Doctrine::class, $doctrine);
        }
    }*/

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