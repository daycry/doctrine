<?php

namespace Daycry\Doctrine;

use Config\Cache;
use Config\Database;
use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineConnectionProxy;
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
     * Proxy connection for logging/debugging queries.
     *
     * @var DoctrineConnectionProxy|null
     */
    public $dbProxy;

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

        $devMode = (ENVIRONMENT === 'development') ? true : false;

        switch ($cacheConfig->handler) {
            case 'file':
                $cacheQuery    = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl, $cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine');
                $cacheResult   = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl, $cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine');
                $cacheMetadata = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl, $cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine');
                break;

            case 'redis':
                $redis = new Redis($cacheConfig);
                $redis = $redis->getInstance();
                $redis->select($cacheConfig->redis['database']);
                $cacheQuery    = new RedisAdapter($redis, $cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl);
                $cacheResult   = new RedisAdapter($redis, $cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl);
                $cacheMetadata = new RedisAdapter($redis, $cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl);
                break;

            case 'memcached':
                $memcached     = new Memcached($cacheConfig);
                $cacheQuery    = new MemcachedAdapter($memcached->getInstance(), $cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl);
                $cacheResult   = new MemcachedAdapter($memcached->getInstance(), $cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl);
                $cacheMetadata = new MemcachedAdapter($memcached->getInstance(), $cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl);
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
        // Proxy solo para logging/debug
        $this->dbProxy = new DoctrineConnectionProxy($connection, $collector);
        // Create EntityManager con la conexión original
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
}
