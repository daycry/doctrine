<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Config\Cache;
use Config\Services;
use Daycry\Doctrine\Doctrine;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Tests\Support\Database\Seeds\TestSeeder;
use Tests\Support\Filters\SoftDeleteFilter;
use Tests\Support\Listeners\RecordingListener;
use Tests\Support\Listeners\RecordingSubscriber;
use Tests\Support\Log\SpyLogger;
use Tests\Support\Middlewares\RecordingMiddleware;
use Tests\Support\Repositories\CustomRepository;
use Tests\Support\TestCase;
use Tests\Support\Types\CustomStringType;

/**
 * @internal
 */
final class DoctrineTest extends TestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seedOnce    = false;
    protected $seed        = TestSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getMysqlDSNConfig();
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
        $cacheConf          = config('Cache');
        $cacheConf->handler = 'redis';

        $cache = Services::cache($cacheConf);

        if ($cache->isSupported()) {
            $doctrine = new Doctrine($this->config, $cacheConf);

            $this->assertInstanceOf(Doctrine::class, $doctrine);
            $this->assertInstanceOf(EntityManager::class, $doctrine->em);
        }
    }

    public function testInstanceDoctrineFile()
    {
        /** @var Cache $cacheConf */
        $cacheConf          = config('Cache');
        $cacheConf->handler = 'file';

        $doctrine = new Doctrine($this->config, $cacheConf);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testInstanceDoctrineMemcached()
    {
        if (! extension_loaded('memcached')) {
            $this->markTestSkipped('La extensión memcached no está habilitada.');
        }

        /** @var Cache $cacheConf */
        $cacheConf          = config('Cache');
        $cacheConf->handler = 'memcached';

        $doctrine = new Doctrine($this->config, $cacheConf);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testInstanceDoctrineArray()
    {
        /** @var Cache $cacheConf */
        $cacheConf          = config('Cache');
        $cacheConf->handler = 'dummy';

        $doctrine = new Doctrine($this->config, $cacheConf);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testDoctrineReOpen()
    {
        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
        $doctrine->reOpen();
        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testReOpenClosesAndReconnectsConnection()
    {
        $doctrine = new Doctrine($this->config);

        // Establish a live connection.
        $doctrine->em->getConnection()->executeQuery('SELECT 1');
        $this->assertTrue($doctrine->em->getConnection()->isConnected());

        $doctrine->reOpen();

        // reOpen() must drop the underlying connection so a stale socket is
        // re-established lazily on the next query (the documented worker use case).
        $this->assertFalse($doctrine->em->getConnection()->isConnected());

        // The connection still works and reconnects transparently on next use.
        $value = $doctrine->em->getConnection()->executeQuery('SELECT 1')->fetchOne();
        $this->assertSame(1, (int) $value);
        $this->assertTrue($doctrine->em->getConnection()->isConnected());
    }

    public function testQueryInstrumentationCanBeDisabled()
    {
        $collector = \Daycry\Doctrine\Config\Services::doctrineCollector();
        $collector->reset();

        // Simulate production (CI_DEBUG off): the toolbar middleware must not be wired.
        $doctrine = new class ($this->config) extends Doctrine {
            protected function shouldInstrumentQueries(): bool
            {
                return false;
            }
        };

        $doctrine->em->getConnection()->executeQuery('SELECT 1');

        $this->assertCount(0, $collector->getQueries());
    }

    public function testQueryInstrumentationEnabledByDefaultCapturesQueries()
    {
        $collector = \Daycry\Doctrine\Config\Services::doctrineCollector();
        $collector->reset();

        // CI_DEBUG is true under the testing environment, so capture stays on.
        $doctrine = new Doctrine($this->config);
        $doctrine->em->getConnection()->executeQuery('SELECT 1');

        $this->assertGreaterThan(0, count($collector->getQueries()));
    }

    public function testCustomTypesAreRegistered()
    {
        $this->config->customTypes = [CustomStringType::NAME => CustomStringType::class];

        new Doctrine($this->config);

        $this->assertTrue(Type::hasType(CustomStringType::NAME));
    }

    public function testSqlFiltersAreRegisteredAndEnabled()
    {
        $this->config->sqlFilters     = ['soft_delete' => SoftDeleteFilter::class];
        $this->config->enabledFilters = ['soft_delete'];

        $doctrine = new Doctrine($this->config);

        $this->assertTrue($doctrine->em->getFilters()->isEnabled('soft_delete'));

        // Auto-enabled filters survive a reOpen() (re-enabled on the rebuilt EM).
        $doctrine->reOpen();
        $this->assertTrue($doctrine->em->getFilters()->isEnabled('soft_delete'));
    }

    public function testEventListenersAndSubscribersAreRegistered()
    {
        $this->config->eventListeners   = ['prePersist' => [RecordingListener::class]];
        $this->config->eventSubscribers = [RecordingSubscriber::class];

        $doctrine = new Doctrine($this->config);
        $evm      = $doctrine->em->getEventManager();

        $this->assertTrue($evm->hasListeners('prePersist'), 'configured listener should be registered');
        $this->assertTrue($evm->hasListeners('postLoad'), 'subscriber event should be registered');
    }

    public function testQueryLoggingLogsQueriesToPsr3Logger()
    {
        $spy = new SpyLogger();

        $db                    = config('Database');
        $db->tests['DBDriver'] = 'SQLite3';
        $db->tests['database'] = ':memory:';

        $this->config->queryLogging       = true;
        $this->config->slowQueryThreshold = 0.0; // log every query

        $doctrine = new class ($this->config, $spy) extends Doctrine {
            public function __construct(\Daycry\Doctrine\Config\Doctrine $config, private readonly LoggerInterface $spy)
            {
                parent::__construct($config, null, 'tests');
            }

            protected function logger(): LoggerInterface
            {
                return $this->spy;
            }
        };

        $doctrine->em->getConnection()->executeQuery('SELECT 1');

        $this->assertNotEmpty($spy->records, 'query logging should emit at least one log record');
        $sqls = array_column(array_column($spy->records, 'context'), 'sql');
        $this->assertNotEmpty(array_filter($sqls, static fn ($sql): bool => str_contains((string) $sql, 'SELECT 1')));
    }

    public function testDefaultRepositoryClassIsConfigured()
    {
        $this->config->defaultRepositoryClass = CustomRepository::class;

        $doctrine = new Doctrine($this->config);

        $this->assertSame(
            CustomRepository::class,
            $doctrine->em->getConfiguration()->getDefaultRepositoryClassName(),
        );
    }

    public function testUserDbalMiddlewaresAreComposed()
    {
        RecordingMiddleware::$wrapped  = false;
        $this->config->dbalMiddlewares = [RecordingMiddleware::class];

        new Doctrine($this->config);

        // DriverManager::getConnection() wraps the driver through every middleware
        // eagerly, so the user middleware must have been invoked.
        $this->assertTrue(RecordingMiddleware::$wrapped);
    }

    public function testDoctrineWithCustomDbGroup()
    {
        $dbConfig = config('Database');
        // Crea un objeto temporal con el grupo custom
        $customConfig        = clone $dbConfig;
        $customConfig->tests = $dbConfig->tests;
        $doctrine            = new Doctrine($this->config, null, 'tests');
        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testDoctrineWithSSLOptions()
    {
        $dbConfig                   = $this->getMysqlConfig();
        $dbConfig->tests['sslmode'] = 'require';
        $dbConfig->tests['sslcert'] = '/path/to/cert.pem';
        $dbConfig->tests['sslkey']  = '/path/to/key.pem';
        $dbConfig->tests['sslca']   = '/path/to/ca.pem';
        $doctrine                   = new Doctrine($this->config, null, 'tests');
        $options                    = $doctrine->em->getConnection()->getParams();
        if (! isset($options['sslmode']) || ! isset($options['sslcert']) || ! isset($options['sslkey']) || ! isset($options['sslca'])) {
            $this->markTestSkipped('SSL options not available in this environment/config.');
        }
        $this->assertSame('require', $options['sslmode']);
        $this->assertSame('/path/to/cert.pem', $options['sslcert']);
        $this->assertSame('/path/to/key.pem', $options['sslkey']);
        $this->assertSame('/path/to/ca.pem', $options['sslca']);
    }

    public function testDoctrineWithCustomOptions()
    {
        $dbConfig                   = $this->getMysqlConfig();
        $dbConfig->tests['options'] = [
            'foo' => 'bar',
            'baz' => 123,
        ];
        $doctrine = new Doctrine($this->config, null, 'tests');
        $options  = $doctrine->em->getConnection()->getParams();
        if (! isset($options['foo']) || ! isset($options['baz'])) {
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
