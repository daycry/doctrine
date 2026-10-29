<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4;

use CodeIgniter\Exceptions\ConfigException;
use Config\Cache;
use Config\Database;
use Daycry\Doctrine\Cache\CachePools;
use Daycry\Doctrine\CodeIgniter4\Config\DatabaseGroup;
use Daycry\Doctrine\CodeIgniter4\Config\Doctrine as DoctrineConfig;
use Daycry\Doctrine\CodeIgniter4\Config\Services;
use Daycry\Doctrine\CodeIgniter4\Debug\Toolbar\Collectors\DoctrineQueryMiddleware;
use Daycry\Doctrine\CodeIgniter4\Libraries\Memcached;
use Daycry\Doctrine\CodeIgniter4\Libraries\Redis;
use Daycry\Doctrine\Doctrine as CoreDoctrine;
use Doctrine\DBAL\Tools\DsnParser;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface as Psr6AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class Doctrine extends CoreDoctrine
{
    protected ?object $sharedRedisClient     = null;
    protected ?object $sharedMemcachedClient = null;
    protected ?string $sharedFilesystemPath  = null;

    public function __construct(?DoctrineConfig $doctrineConfig = null, ?Cache $cacheConfig = null, ?string $dbGroup = null)
    {
        $doctrineConfig ??= config('Doctrine');
        $cacheConfig ??= config('Cache');
        if (! $doctrineConfig instanceof DoctrineConfig || ! $cacheConfig instanceof Cache) {
            throw new ConfigException('Doctrine and Cache configuration must use the CodeIgniter adapter config classes.');
        }
        /** @var Database $database */
        $database = config('Database');
        $dbGroup  = DatabaseGroup::resolve($dbGroup);
        $suffix   = $dbGroup === $database->defaultGroup ? '' : '_' . DatabaseGroup::key($dbGroup);
        $options  = $doctrineConfig->toCoreConfig($this->convertDbConfig($database->{$dbGroup}));
        $options->secondLevelCacheTtl ??= $cacheConfig->ttl;
        $options->secondLevelCacheLockPath ??= WRITEPATH . 'cache/doctrine/locks' . $suffix;
        if ($this->shouldInstrumentQueries()) {
            $options->dbalMiddlewares[] = new DoctrineQueryMiddleware(Services::doctrineCollector());
        }
        $pools = new CachePools(
            query: $this->createCachePool($cacheConfig, $options->queryCacheNamespace . $suffix, $cacheConfig->ttl),
            result: $this->createCachePool($cacheConfig, $options->resultsCacheNamespace . $suffix, 0, true),
            metadata: $this->createCachePool($cacheConfig, $options->metadataCacheNamespace . $suffix, $cacheConfig->ttl),
            secondLevel: $options->secondLevelCache
                ? $this->createSecondLevelCachePool($cacheConfig, $options->secondLevelCacheTtl, $suffix)
                : new ArrayAdapter(),
        );
        parent::__construct($options, $pools, $options->queryLogging ? $this->logger() : null);
    }

    protected function shouldInstrumentQueries(): bool
    {
        return defined('CI_DEBUG') && CI_DEBUG;
    }

    protected function logger(): LoggerInterface
    {
        $logger = service('logger');
        if (! $logger instanceof LoggerInterface) {
            throw new ConfigException('The logger service must implement Psr\\Log\\LoggerInterface.');
        }

        return $logger;
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
        return $this->createCachePool($cacheConfig, 'doctrine_slc' . $groupSuffix, $ttl, true);
    }

    /**
     * Build independent pools while sharing native clients within this instance.
     */
    protected function createCachePool(Cache $cacheConfig, string $namespace, int $ttl, bool $volatile = false): Psr6AdapterInterface
    {
        $namespace = $cacheConfig->prefix . $namespace;

        switch ($cacheConfig->handler) {
            case 'file':
                $this->sharedFilesystemPath ??= $cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine';

                return $volatile
                    ? new FilesystemAdapter($namespace, $ttl, $this->sharedFilesystemPath)
                    : new PhpFilesAdapter($namespace, $ttl, $this->sharedFilesystemPath);

            case 'redis':
                $this->sharedRedisClient ??= new Redis($cacheConfig)->getInstance();

                return new RedisAdapter($this->sharedRedisClient, $namespace, $ttl);

            case 'memcached':
                $this->sharedMemcachedClient ??= new Memcached($cacheConfig)->getInstance();

                return new MemcachedAdapter($this->sharedMemcachedClient, $namespace, $ttl);

            default:
                return new ArrayAdapter($ttl);
        }
    }
}
