<?php

declare(strict_types=1);

namespace Daycry\Doctrine;

use CodeIgniter\Cache\Exceptions\CacheException;
use CodeIgniter\Exceptions\ConfigException;
use Config\Cache;
use Config\Database;
use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineQueryMiddleware;
use Daycry\Doctrine\Libraries\Memcached;
use Daycry\Doctrine\Libraries\Redis;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\Cache\CacheConfiguration as ORMCacheConfiguration;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
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
     * @var object|null Redis client instance if available
     */
    protected $sharedRedisClient;

    /**
     * @var object|null Memcached client instance if available
     */
    protected $sharedMemcachedClient;

    protected ?string $sharedFilesystemPath = null;

    /**
     * @throws CacheException
     * @throws ConfigException
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

        foreach ($doctrineConfig->entities as $entityPath) {
            if (! is_dir($entityPath)) {
                throw new ConfigException('Doctrine entity path does not exist: ' . $entityPath);
            }
        }

        switch ($cacheConfig->handler) {
            case 'file':
                $this->sharedFilesystemPath = $cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine';
                $cacheQuery                 = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl, $this->sharedFilesystemPath);
                $cacheResult                = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl, $this->sharedFilesystemPath);
                $cacheMetadata              = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl, $this->sharedFilesystemPath);
                break;

            case 'redis':
                $redisLib                = new Redis($cacheConfig);
                $this->sharedRedisClient = $redisLib->getInstance();
                $cacheQuery              = new RedisAdapter($this->sharedRedisClient, $cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl);
                $cacheResult             = new RedisAdapter($this->sharedRedisClient, $cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl);
                $cacheMetadata           = new RedisAdapter($this->sharedRedisClient, $cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl);
                break;

            case 'memcached':
                $memcachedLib                = new Memcached($cacheConfig);
                $this->sharedMemcachedClient = $memcachedLib->getInstance();
                $cacheQuery                  = new MemcachedAdapter($this->sharedMemcachedClient, $cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl);
                $cacheResult                 = new MemcachedAdapter($this->sharedMemcachedClient, $cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl);
                $cacheMetadata               = new MemcachedAdapter($this->sharedMemcachedClient, $cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl);
                break;

            default:
                $cacheQuery = $cacheResult = $cacheMetadata = new ArrayAdapter($cacheConfig->ttl);
        }

        $config = new Configuration();

        $useNativeLazyObjects = $doctrineConfig->proxyFactory;

        if (\PHP_VERSION_ID >= 80400 && $useNativeLazyObjects) {
            $config->enableNativeLazyObjects(true);
        }

        if (! $config->isNativeLazyObjectsEnabled()) {
            $config->setProxyDir($doctrineConfig->proxies);
            $config->setProxyNamespace($doctrineConfig->proxiesNamespace);
            $config->setAutoGenerateProxyClasses($doctrineConfig->setAutoGenerateProxyClasses);
        }

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
        if ($doctrineConfig->secondLevelCache) {
            $slcTtl = $doctrineConfig->secondLevelCacheTtl;
            if ($slcTtl === null) {
                $slcTtl = $cacheConfig->ttl > 0 ? $cacheConfig->ttl : 3600;
            }

            // Symfony Cache adapters interpret 0 as no-expiration
            // Doctrine RegionsConfiguration expects lifetime seconds for regions
            $regionsConfig = new RegionsConfiguration(
                (int) $slcTtl,
                60,
            );

            $psr6Pool = $this->createSecondLevelCachePool($cacheConfig, (int) $slcTtl, $doctrineConfig);

            $slcConfig = new ORMCacheConfiguration();
            $slcConfig->setRegionsConfiguration($regionsConfig);
            $cacheFactory = new DefaultCacheFactory($regionsConfig, $psr6Pool);
            $slcConfig->setCacheFactory($cacheFactory);

            // Optional SLC statistics logger (hits/misses/puts)
            if ($doctrineConfig->secondLevelCacheStatistics) {
                $slcConfig->setCacheLogger(new StatisticsCacheLogger());
            }

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

        // Register custom DQL functions (beberlei/doctrineextensions + user-defined)
        foreach ($doctrineConfig->customStringFunctions as $name => $class) {
            $config->addCustomStringFunction($name, $class);
        }

        foreach ($doctrineConfig->customNumericFunctions as $name => $class) {
            $config->addCustomNumericFunction($name, $class);
        }

        foreach ($doctrineConfig->customDatetimeFunctions as $name => $class) {
            $config->addCustomDatetimeFunction($name, $class);
        }

        // Register JSON DQL functions (scienta/doctrine-json-functions)
        foreach ($doctrineConfig->customJsonFunctions as $name => $class) {
            $config->addCustomStringFunction($name, $class);
        }

        // INTEGRACIÓN DEL COLLECTOR Y MIDDLEWARE
        /** @var DoctrineCollector $collector */
        $collector  = service('doctrineCollector') ?? new DoctrineCollector();
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

        $this->registerTypeMappings($doctrineConfig);
    }

    /**
     * Reopen the EntityManager with the current connection and configuration.
     */
    public function reOpen(): void
    {
        $this->em = new EntityManager($this->em->getConnection(), $this->em->getConfiguration());
        $this->registerTypeMappings(config('Doctrine'));
    }

    /**
     * Register custom DBAL type mappings on the current connection's platform.
     */
    protected function registerTypeMappings(DoctrineConfig $doctrineConfig): void
    {
        $platform = $this->em->getConnection()->getDatabasePlatform();

        foreach ($doctrineConfig->customTypeMappings as $dbType => $doctrineType) {
            $platform->registerDoctrineTypeMapping($dbType, $doctrineType);
        }
    }

    /**
     * Convert CodeIgniter database config to Doctrine's connection options.
     *
     * @param array<string, mixed>|object $db
     *
     * @return array<string, mixed>
     *
     * @throws ConfigException
     */
    public function convertDbConfig(array|object $db): array
    {
        $db = (is_array($db)) ? (object) $db : $db;
        if (! empty($db->DSN)) {
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
                    $driverMap = [
                        'mysqli'  => 'mysqli',
                        'postgre' => 'pdo_pgsql',
                        'oci8'    => 'oci8',
                        'sqlsrv'  => 'sqlsrv',
                    ];
                    $connectionOptions = [
                        'driver'   => $driverMap[strtolower($db->DBDriver)] ?? strtolower($db->DBDriver),
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
     * @return array<string, mixed>
     *
     * @throws ConfigException
     * @codeCoverageIgnore
     */
    protected function convertDbConfigPdo(mixed $db): array
    {
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
            throw new ConfigException('Your Database Configuration is not confirmed by CodeIgniter Doctrine');
        }

        return $connectionOptions;
    }

    /**
     * Create PSR-6 cache pool for Doctrine SLC based on configured adapter.
     */
    protected function createSecondLevelCachePool(Cache $cacheConfig, int $ttl, ?DoctrineConfig $doctrineConfig = null): Psr6AdapterInterface
    {
        switch ($cacheConfig->handler) {
            case 'file':
                $dir = $this->sharedFilesystemPath ?? ($cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine');

                return new PhpFilesAdapter($cacheConfig->prefix . 'doctrine_slc', $ttl, $dir);

            case 'redis':
                $client = $this->sharedRedisClient;
                if ($client === null) {
                    $redisLib                = new Redis($cacheConfig);
                    $client                  = $redisLib->getInstance();
                    $this->sharedRedisClient = $client;
                }

                return new RedisAdapter($client, $cacheConfig->prefix . 'doctrine_slc', $ttl);

            case 'memcached':
                $client = $this->sharedMemcachedClient;
                if ($client === null) {
                    $memcachedLib                = new Memcached($cacheConfig);
                    $client                      = $memcachedLib->getInstance();
                    $this->sharedMemcachedClient = $client;
                }

                return new MemcachedAdapter($client, $cacheConfig->prefix . 'doctrine_slc', $ttl);

            case 'array':
            default:
                return new ArrayAdapter($ttl);
        }
    }

    /**
     * Return Second-Level Cache logger if enabled.
     * Consumers can inspect the logger for stats.
     */
    public function getSecondLevelCacheLogger(): ?StatisticsCacheLogger
    {
        $cfg = $this->em?->getConfiguration()?->getSecondLevelCacheConfiguration();
        if ($cfg === null) {
            return null;
        }
        $logger = $cfg->getCacheLogger();

        return $logger instanceof StatisticsCacheLogger ? $logger : null;
    }

    /**
     * Reset Second-Level Cache statistics counters if available.
     */
    public function resetSecondLevelCacheStatistics(): void
    {
        $logger = $this->getSecondLevelCacheLogger();
        if ($logger === null) {
            return;
        }

        $logger->clearStats();
    }
}
