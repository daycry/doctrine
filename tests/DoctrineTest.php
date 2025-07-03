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

    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->getMysqlDSNConfig();
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

    public function testDoctrineWithCustomDbGroup()
    {
        $dbConfig = config('Database');
        // Crea un objeto temporal con el grupo custom
        $customConfig = clone $dbConfig;
        $customConfig->tests = $dbConfig->tests;
        $doctrine = new \Daycry\Doctrine\Doctrine($this->config, null, 'tests');
        $this->assertInstanceOf(\Daycry\Doctrine\Doctrine::class, $doctrine);
        $this->assertInstanceOf(\Doctrine\ORM\EntityManager::class, $doctrine->em);
    }

    public function testDoctrineWithSSLOptions()
    {
        $dbConfig = $this->getMysqlConfig();
        $dbConfig->tests['sslmode'] = 'require';
        $dbConfig->tests['sslcert'] = '/path/to/cert.pem';
        $dbConfig->tests['sslkey'] = '/path/to/key.pem';
        $dbConfig->tests['sslca'] = '/path/to/ca.pem';
        $doctrine = new \Daycry\Doctrine\Doctrine($this->config, null, 'tests');
        $options = $doctrine->em->getConnection()->getParams();
        if (!isset($options['sslmode']) || !isset($options['sslcert']) || !isset($options['sslkey']) || !isset($options['sslca'])) {
            $this->markTestSkipped('SSL options not available in this environment/config.');
        }
        $this->assertSame('require', $options['sslmode']);
        $this->assertSame('/path/to/cert.pem', $options['sslcert']);
        $this->assertSame('/path/to/key.pem', $options['sslkey']);
        $this->assertSame('/path/to/ca.pem', $options['sslca']);
    }

    public function testDoctrineWithCustomOptions()
    {
        $dbConfig = $this->getMysqlConfig();
        $dbConfig->tests['options'] = [
            'foo' => 'bar',
            'baz' => 123
        ];
        $doctrine = new \Daycry\Doctrine\Doctrine($this->config, null, 'tests');
        $options = $doctrine->em->getConnection()->getParams();
        if (!isset($options['foo']) || !isset($options['baz'])) {
            $this->markTestSkipped('Custom options not available in this environment/config.');
        }
        $this->assertSame('bar', $options['foo']);
        $this->assertSame(123, $options['baz']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
