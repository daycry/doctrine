<?php

declare(strict_types=1);

namespace Daycry\Doctrine;

use CodeIgniter\Cache\Exceptions\CacheException;
use CodeIgniter\Exceptions\ConfigException;
use Config\Cache;
use Config\Database;
use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;
use Daycry\Doctrine\Config\Services;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineQueryMiddleware;
use Daycry\Doctrine\Libraries\Memcached;
use Daycry\Doctrine\Libraries\Redis;
use Daycry\Doctrine\Logging\QueryLoggerMiddleware;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Cache\CacheConfiguration as ORMCacheConfiguration;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Psr\Log\LoggerInterface;
use RuntimeException;
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

        /** @var Database $dbConfig */
        $dbConfig = config('Database');
        if ($dbGroup === null) {
            $dbGroup = (ENVIRONMENT === 'testing') ? 'tests' : $dbConfig->defaultGroup;
        }
        // Suffix cache namespaces with the (non-default) DB group so that multiple
        // groups sharing one cache backend never collide on the same keys.
        $cacheGroupSuffix = $dbGroup === $dbConfig->defaultGroup ? '' : '_' . strtolower($dbGroup);

        switch ($cacheConfig->handler) {
            case 'file':
                $this->sharedFilesystemPath = $cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine';
                $cacheQuery                 = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->queryCacheNamespace . $cacheGroupSuffix, $cacheConfig->ttl, $this->sharedFilesystemPath);
                $cacheResult                = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace . $cacheGroupSuffix, $cacheConfig->ttl, $this->sharedFilesystemPath);
                $cacheMetadata              = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace . $cacheGroupSuffix, $cacheConfig->ttl, $this->sharedFilesystemPath);
                break;

            case 'redis':
                $redisLib                = new Redis($cacheConfig);
                $this->sharedRedisClient = $redisLib->getInstance();
                $cacheQuery              = new RedisAdapter($this->sharedRedisClient, $cacheConfig->prefix . $doctrineConfig->queryCacheNamespace . $cacheGroupSuffix, $cacheConfig->ttl);
                $cacheResult             = new RedisAdapter($this->sharedRedisClient, $cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace . $cacheGroupSuffix, $cacheConfig->ttl);
                $cacheMetadata           = new RedisAdapter($this->sharedRedisClient, $cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace . $cacheGroupSuffix, $cacheConfig->ttl);
                break;

            case 'memcached':
                $memcachedLib                = new Memcached($cacheConfig);
                $this->sharedMemcachedClient = $memcachedLib->getInstance();
                $cacheQuery                  = new MemcachedAdapter($this->sharedMemcachedClient, $cacheConfig->prefix . $doctrineConfig->queryCacheNamespace . $cacheGroupSuffix, $cacheConfig->ttl);
                $cacheResult                 = new MemcachedAdapter($this->sharedMemcachedClient, $cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace . $cacheGroupSuffix, $cacheConfig->ttl);
                $cacheMetadata               = new MemcachedAdapter($this->sharedMemcachedClient, $cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace . $cacheGroupSuffix, $cacheConfig->ttl);
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

            $psr6Pool = $this->createSecondLevelCachePool($cacheConfig, (int) $slcTtl, $cacheGroupSuffix);

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

        match ($doctrineConfig->metadataConfigurationMethod) {
            'xml'   => $config->setMetadataDriverImpl(new XmlDriver($doctrineConfig->entities, XmlDriver::DEFAULT_FILE_EXTENSION, $doctrineConfig->isXsdValidationEnabled)),
            default => $config->setMetadataDriverImpl(new AttributeDriver($doctrineConfig->entities)),
        };

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

        // Register SQL Filters (soft-delete, multi-tenant, …) on the configuration.
        foreach ($doctrineConfig->sqlFilters as $name => $class) {
            $config->addFilter($name, $class);
        }

        // Default repository class for entities that don't declare their own.
        if ($doctrineConfig->defaultRepositoryClass !== null) {
            $config->setDefaultRepositoryClassName($doctrineConfig->defaultRepositoryClass);
        }

        // DBAL middleware chain: user-defined middlewares (retry/logging/metrics)
        // wrap the driver first (outermost); the toolbar capture middleware is
        // applied last so it sees the final SQL closest to the driver. The toolbar
        // middleware is only wired when query instrumentation is enabled (CI_DEBUG):
        // in production the toolbar never renders, so wrapping every statement is
        // pure dead weight.
        $dbalConfig  = new \Doctrine\DBAL\Configuration();
        $middlewares = [];

        foreach ($doctrineConfig->dbalMiddlewares as $userMiddleware) {
            $middlewares[] = is_string($userMiddleware) ? new $userMiddleware() : $userMiddleware;
        }

        // Production query logging (slow-query log) — independent of CI_DEBUG.
        if ($doctrineConfig->queryLogging) {
            $middlewares[] = new QueryLoggerMiddleware(
                $this->logger(),
                $doctrineConfig->slowQueryThreshold,
                $doctrineConfig->queryLogLevel,
            );
        }

        if ($this->shouldInstrumentQueries()) {
            $collector     = Services::doctrineCollector();
            $middlewares[] = new DoctrineQueryMiddleware($collector);
        }

        if ($middlewares !== []) {
            $dbalConfig->setMiddlewares($middlewares);
        }

        // Database connection information ($dbGroup and $dbConfig resolved above).
        $connectionOptions = $this->convertDbConfig($dbConfig->{$dbGroup});
        $connection        = DriverManager::getConnection($connectionOptions, $dbalConfig);
        // Create EntityManager con la conexión original (middleware ya captura queries)
        $this->em = new EntityManager($connection, $config, $this->buildEventManager($doctrineConfig));

        $this->registerTypeMappings($doctrineConfig);
        $this->enableConfiguredFilters($doctrineConfig);
    }

    /**
     * Whether DBAL queries should be instrumented for the Debug Toolbar.
     *
     * Defaults to CodeIgniter's debug flag: the toolbar only renders when
     * CI_DEBUG is true, so production skips the per-query capture overhead.
     * Override in a subclass to force instrumentation on or off.
     */
    protected function shouldInstrumentQueries(): bool
    {
        return defined('CI_DEBUG') && CI_DEBUG;
    }

    /**
     * PSR-3 logger used for production query logging. Defaults to CodeIgniter's
     * logger service; override in a subclass to route logs elsewhere.
     */
    protected function logger(): LoggerInterface
    {
        return service('logger');
    }

    /**
     * Get the EntityManager. Throws if it has not been initialized.
     */
    public function getEm(): EntityManager
    {
        if ($this->em === null) {
            throw new RuntimeException('EntityManager has not been initialized.');
        }

        return $this->em;
    }

    /**
     * Reopen the EntityManager, recovering from a stale or dropped database
     * connection (e.g. "MySQL server has gone away" after an idle timeout in a
     * long-running CLI worker or queue consumer).
     *
     * The existing DBAL connection is closed first so DBAL re-establishes the
     * underlying socket lazily on the next query; a fresh EntityManager is then
     * built over that connection with the same configuration, and custom type
     * mappings are re-applied.
     *
     * Note: closing the connection discards an in-memory SQLite database. This
     * method is intended for server-backed connections (MySQL/Postgres/…), which
     * is the documented worker-recovery use case.
     */
    public function reOpen(): void
    {
        $em         = $this->getEm();
        $connection = $em->getConnection();

        if ($connection->isConnected()) {
            $connection->close();
        }

        /** @var DoctrineConfig $doctrineConfig */
        $doctrineConfig = config('Doctrine');
        $this->em       = new EntityManager($connection, $em->getConfiguration(), $this->buildEventManager($doctrineConfig));
        $this->registerTypeMappings($doctrineConfig);
        $this->enableConfiguredFilters($doctrineConfig);
    }

    /**
     * Build an EventManager from the configured listeners/subscribers, or null
     * when none are configured (so the EntityManager keeps its default).
     * Class-strings are instantiated with no constructor arguments; ready-made
     * instances are used as-is.
     */
    protected function buildEventManager(DoctrineConfig $doctrineConfig): ?EventManager
    {
        if ($doctrineConfig->eventListeners === [] && $doctrineConfig->eventSubscribers === []) {
            return null;
        }

        $eventManager = new EventManager();

        foreach ($doctrineConfig->eventListeners as $event => $listeners) {
            foreach ($listeners as $listener) {
                $eventManager->addEventListener($event, is_string($listener) ? new $listener() : $listener);
            }
        }

        foreach ($doctrineConfig->eventSubscribers as $subscriber) {
            $eventManager->addEventSubscriber(is_string($subscriber) ? new $subscriber() : $subscriber);
        }

        return $eventManager;
    }

    /**
     * Enable the SQL filters listed in Config\Doctrine::$enabledFilters on the
     * current EntityManager. FilterCollection is per-EntityManager, so this must
     * run on construction and again after reOpen() rebuilds the EM.
     */
    protected function enableConfiguredFilters(DoctrineConfig $doctrineConfig): void
    {
        if ($doctrineConfig->enabledFilters === []) {
            return;
        }

        $filters = $this->getEm()->getFilters();

        foreach ($doctrineConfig->enabledFilters as $name) {
            // Only enable filters that were actually registered, to avoid a
            // misconfigured name throwing during construction.
            if (array_key_exists($name, $doctrineConfig->sqlFilters)) {
                $filters->enable($name);
            }
        }
    }

    /**
     * Register custom DBAL type mappings on the current connection's platform.
     */
    protected function registerTypeMappings(DoctrineConfig $doctrineConfig): void
    {
        // Register custom DBAL Types first (process-global, idempotent).
        foreach ($doctrineConfig->customTypes as $name => $class) {
            if (! Type::hasType($name)) {
                Type::addType($name, $class);
            }
        }

        $platform = $this->getEm()->getConnection()->getDatabasePlatform();

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
            if (str_contains((string) $db->DSN, 'SQLite')) {
                // Lower-case only the scheme (everything before the first ':'); the
                // rest of the DSN — notably a case-sensitive SQLite file path — must
                // be preserved verbatim.
                $db->DSN = preg_replace_callback('~^[^:]+~', static fn (array $m): string => strtolower($m[0]), (string) $db->DSN);
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
     * Create PSR-6 cache pool for Doctrine SLC based on configured adapter.
     */
    protected function createSecondLevelCachePool(Cache $cacheConfig, int $ttl, string $groupSuffix = ''): Psr6AdapterInterface
    {
        switch ($cacheConfig->handler) {
            case 'file':
                $dir = $this->sharedFilesystemPath ?? ($cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine');

                return new PhpFilesAdapter($cacheConfig->prefix . 'doctrine_slc' . $groupSuffix, $ttl, $dir);

            case 'redis':
                $client = $this->sharedRedisClient;
                if ($client === null) {
                    $redisLib                = new Redis($cacheConfig);
                    $client                  = $redisLib->getInstance();
                    $this->sharedRedisClient = $client;
                }

                return new RedisAdapter($client, $cacheConfig->prefix . 'doctrine_slc' . $groupSuffix, $ttl);

            case 'memcached':
                $client = $this->sharedMemcachedClient;
                if ($client === null) {
                    $memcachedLib                = new Memcached($cacheConfig);
                    $client                      = $memcachedLib->getInstance();
                    $this->sharedMemcachedClient = $client;
                }

                return new MemcachedAdapter($client, $cacheConfig->prefix . 'doctrine_slc' . $groupSuffix, $ttl);

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
