<?php

namespace Daycry\Doctrine;

use Config\Cache;
use Config\Database;
use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineQueryMiddleware;
use Daycry\Doctrine\Libraries\Memcached;
use Daycry\Doctrine\Libraries\Redis;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Exception;
use Doctrine\ORM\Cache\CacheConfiguration as ORMCacheConfiguration;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Symfony\Component\Cache\Adapter\AdapterInterface as Psr6AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * Doctrine integration for CodeIgniter 4.
 * Handles EntityManager, DBAL connection, and cache configuration.
 */
class Doctrine
{
    /**
     * The Doctrine EntityManager instance.
     */
    public ?EntityManager $em = null;


    /**
     * Shared cache backend clients to avoid duplicate connections.
     */
    /** @var object|null Redis client instance if available */
    protected $sharedRedisClient = null;
    /** @var object|null Memcached client instance if available */
    protected $sharedMemcachedClient = null;
    protected ?string $sharedFilesystemPath = null;

    /**
     * Doctrine constructor.
     *
     * @param DoctrineConfig|null $doctrineConfig Doctrine configuration
     * @param Cache|null          $cacheConfig    Cache configuration
     * @param string|null         $dbGroup        Database group name
     *
     * @throws Exception
     */
    public function __construct(?DoctrineConfig $doctrineConfig = null, ?Cache $cacheConfig = null, ?string $dbGroup = null)
    {
        if ($doctrineConfig === null) {
            /** @var DoctrineConfig $doctrineConfig */
            $doctrineConfig = config('Doctrine');
        }

        if ($cacheConfig === null) {
            /** @var Cache $cacheConfig */
            $cacheConfig = config('Cache');
        }

        $devMode = (ENVIRONMENT === 'development');

        // Validate entity paths exist (prevent silent misconfiguration)
        foreach ($doctrineConfig->entities as $entityPath) {
            if (! is_dir($entityPath)) {
                // Throwing helps surface misconfiguration early; adjust to log() if preferred
                throw new Exception('Doctrine entity path does not exist: ' . $entityPath);
            }
        }

        switch ($cacheConfig->handler) {
            case 'file':
            $this->sharedFilesystemPath = $cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine';
            $cacheQuery    = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl, $this->sharedFilesystemPath);
            $cacheResult   = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl, $this->sharedFilesystemPath);
            $cacheMetadata = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl, $this->sharedFilesystemPath);
                break;

            case 'redis':
                $redisLib = new Redis($cacheConfig);
                $this->sharedRedisClient = $redisLib->getInstance();
                $this->sharedRedisClient->select($cacheConfig->redis['database']);
                $cacheQuery    = new RedisAdapter($this->sharedRedisClient, $cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl);
                $cacheResult   = new RedisAdapter($this->sharedRedisClient, $cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl);
                $cacheMetadata = new RedisAdapter($this->sharedRedisClient, $cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl);
                break;

            case 'memcached':
                $memcachedLib  = new Memcached($cacheConfig);
                $this->sharedMemcachedClient = $memcachedLib->getInstance();
                $cacheQuery    = new MemcachedAdapter($this->sharedMemcachedClient, $cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl);
                $cacheResult   = new MemcachedAdapter($this->sharedMemcachedClient, $cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl);
                $cacheMetadata = new MemcachedAdapter($this->sharedMemcachedClient, $cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl);
                break;

            default:
                $cacheQuery = $cacheResult = $cacheMetadata = new ArrayAdapter($cacheConfig->ttl);
        }

        $config = new Configuration();

        $config->setProxyDir($doctrineConfig->proxies);
        $config->setProxyNamespace($doctrineConfig->proxiesNamespace);
        $config->setAutoGenerateProxyClasses($doctrineConfig->setAutoGenerateProxyClasses);

        if ($doctrineConfig->queryCache) {
            $config->setQueryCache($cacheQuery);
        }

        if ($doctrineConfig->resultsCache) {
            $config->setResultCache($cacheResult);
        }

        if ($doctrineConfig->metadataCache) {
            $config->setMetadataCache($cacheMetadata);
        }

        // Second-Level Cache (SLC): uses the framework cache backend
        if (!empty($doctrineConfig->secondLevelCache)) {
            $regionsConfig = new RegionsConfiguration(
                (int) ($cacheConfig->ttl ?? 3600),
                60
            );

            $psr6Pool = $this->createSecondLevelCachePool($cacheConfig);

            $slcConfig = new ORMCacheConfiguration();
            $slcConfig->setRegionsConfiguration($regionsConfig);
            $cacheFactory = new DefaultCacheFactory($regionsConfig, $psr6Pool);
            $slcConfig->setCacheFactory($cacheFactory);

            $config->setSecondLevelCacheEnabled(true);
            $config->setSecondLevelCacheConfiguration($slcConfig);
        }

        switch ($doctrineConfig->metadataConfigurationMethod) {
            case 'xml':
                $config->setMetadataDriverImpl(new XmlDriver($doctrineConfig->entities, XmlDriver::DEFAULT_FILE_EXTENSION, $doctrineConfig->isXsdValidationEnabled));
                break;

            case 'attribute':
            default:
                $config->setMetadataDriverImpl(new AttributeDriver($doctrineConfig->entities));
                break;
        }

        // INTEGRACIÓN DEL COLLECTOR Y MIDDLEWARE
        $collector  = service('doctrineCollector');
        $dbalConfig = new \Doctrine\DBAL\Configuration();
        $middleware = new DoctrineQueryMiddleware($collector);
        $dbalConfig->setMiddlewares([$middleware]);

        /** @var Database $dbConfig */
        $dbConfig = config('Database');
        if ($dbGroup === null) {
            $dbGroup = (ENVIRONMENT === 'testing') ? 'tests' : $dbConfig->defaultGroup;
        }
        // Database connection information
        $connectionOptions = $this->convertDbConfig($dbConfig->{$dbGroup});
        $connection        = DriverManager::getConnection($connectionOptions, $dbalConfig);
        // Create EntityManager con la conexión original (middleware ya captura queries)
        $this->em = new EntityManager($connection, $config);

        $this->em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('set', 'string');
        $this->em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        // Si la Toolbar está activa, registra el collector manualmente
        // (El método addCollector no existe, así que se elimina esta línea)
        // if (function_exists('service') && service('toolbar')) {
        //     service('toolbar')->addCollector($collector);
        // }
    }

    /**
     * Reopen the EntityManager with the current connection and configuration.
     *
     * @return void
     */
    public function reOpen()
    {
        $this->em = new EntityManager($this->em->getConnection(), $this->em->getConfiguration(), $this->em->getEventManager());
    }

    /**
     * Convert CodeIgniter database config array to Doctrine's connection options.
     *
     * @param object $db
     *
     * @return array
     *
     * @throws Exception
     */
    public function convertDbConfig($db)
    {
        $connectionOptions = [];
        $db                = (is_array($db)) ? json_decode(json_encode($db)) : $db;
        if ($db->DSN) {
            $driverMapper = ['MySQLi' => 'mysqli', 'Postgre' => 'pgsql', 'OCI8' => 'oci8', 'SQLSRV' => 'sqlsrv', 'SQLite3' => 'sqlite3'];
            if (str_contains($db->DSN, 'SQLite')) {
                $db->DSN = strtolower($db->DSN);
            }
            $dsnParser         = new DsnParser($driverMapper);
            $connectionOptions = $dsnParser->parse($db->DSN);
        } else {
            switch (strtolower($db->DBDriver)) {
                case 'sqlite3':
                    if ($db->database === ':memory:') {
                        $connectionOptions = [
                            'driver' => strtolower($db->DBDriver),
                            'memory' => true,
                        ];
                    } else {
                        $connectionOptions = [
                            'driver' => strtolower($db->DBDriver),
                            'path'   => $db->database,
                        ];
                    }
                    break;

                default:
                    $connectionOptions = [
                        'driver'   => strtolower($db->DBDriver),
                        'user'     => $db->username,
                        'password' => $db->password,
                        'host'     => $db->hostname,
                        'dbname'   => $db->database,
                        'charset'  => $db->charset,
                        'port'     => $db->port,
                    ];
                    // Soporte para SSL y opciones avanzadas
                    $sslOptions = ['sslmode', 'sslcert', 'sslkey', 'sslca', 'sslcapath', 'sslcipher', 'sslcrl', 'sslverify', 'sslcompression'];

                    foreach ($sslOptions as $opt) {
                        if (isset($db->{$opt})) {
                            $connectionOptions[$opt] = $db->{$opt};
                        }
                    }
                    // Opciones personalizadas
                    if (isset($db->options) && is_array($db->options)) {
                        foreach ($db->options as $key => $value) {
                            $connectionOptions[$key] = $value;
                        }
                    }
            }
        }

        return $connectionOptions;
    }

    /**
     * Convert CodeIgniter PDO config to Doctrine's connection options.
     *
     * @param mixed $db
     *
     * @return array
     *
     * @throws Exception
     * @codeCoverageIgnore
     */
    protected function convertDbConfigPdo($db)
    {
        $connectionOptions = [];

        if (substr($db->hostname, 0, 7) === 'sqlite:') {
            $connectionOptions = [
                'driver'   => 'pdo_sqlite',
                'user'     => $db->username,
                'password' => $db->password,
                'path'     => preg_replace('/\Asqlite:/', '', $db->hostname),
            ];
        } elseif (substr($db->dsn, 0, 7) === 'sqlite:') {
            $connectionOptions = [
                'driver'   => 'pdo_sqlite',
                'user'     => $db->username,
                'password' => $db->password,
                'path'     => preg_replace('/\Asqlite:/', '', $db->dsn),
            ];
        } elseif (substr($db->dsn, 0, 6) === 'mysql:') {
            $connectionOptions = [
                'driver'   => 'pdo_mysql',
                'user'     => $db->username,
                'password' => $db->password,
                'host'     => $db->hostname,
                'dbname'   => $db->database,
                'charset'  => $db->charset,
                'port'     => $db->port,
            ];
        } else {
            throw new Exception('Your Database Configuration is not confirmed by CodeIgniter Doctrine');
        }

        return $connectionOptions;
    }

    /**
     * Create PSR-6 cache pool for Doctrine SLC based on configured adapter.
     */
    protected function createSecondLevelCachePool(\Config\Cache $cacheConfig): Psr6AdapterInterface
    {
        $ttl = $cacheConfig->ttl;

        switch ($cacheConfig->handler) {
            case 'file':
                $dir = $this->sharedFilesystemPath ?? ($cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine');
                return new PhpFilesAdapter($cacheConfig->prefix . 'doctrine_slc', $ttl, $dir);
            case 'redis':
                $client = $this->sharedRedisClient;
                if ($client === null) {
                    $redisLib = new \Daycry\Doctrine\Libraries\Redis($cacheConfig);
                    $client = $redisLib->getInstance();
                    $client->select($cacheConfig->redis['database']);
                    $this->sharedRedisClient = $client;
                }
                return new RedisAdapter($client, $cacheConfig->prefix . 'doctrine_slc', $ttl);
            case 'memcached':
                $client = $this->sharedMemcachedClient;
                if ($client === null) {
                    $memcachedLib = new \Daycry\Doctrine\Libraries\Memcached($cacheConfig);
                    $client = $memcachedLib->getInstance();
                    $this->sharedMemcachedClient = $client;
                }
                return new MemcachedAdapter($client, $cacheConfig->prefix . 'doctrine_slc', $ttl);
            case 'array':
            default:
                return new ArrayAdapter($ttl);
        }
    }
}
    
