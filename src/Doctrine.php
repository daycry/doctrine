<?php

namespace Daycry\Doctrine;

use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;
use Config\Cache;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Daycry\Doctrine\Libraries\Redis;
use Daycry\Doctrine\Libraries\Memcached;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Exception;

/**
 * Class General
 *
 */
class Doctrine
{
    public $em = null;
    private $cache;

    public function __construct(DoctrineConfig $doctrineConfig = null, Cache $cacheConfig = null)
    {
        if ($doctrineConfig === null) {
            $doctrineConfig = config('Doctrine');
        }

        if ($cacheConfig === null) {
            $cacheConfig = config('Cache');
        }

        $db = \Config\Database::connect();

        $devMode = (ENVIRONMENT == "development") ? true : false;

        switch ($cacheConfig->handler) {
            case 'file':
                $cacheQuery = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl, $cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine');
                $cacheResult = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl, $cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine');
                $cacheMetadata = new PhpFilesAdapter($cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl, $cacheConfig->file['storePath'] . DIRECTORY_SEPARATOR . 'doctrine');
                break;
            case 'redis':
                $redis = new Redis($cacheConfig);
                $redis = $redis->getInstance();
                $redis->select($cacheConfig->redis[ 'database' ]);
                $cacheQuery = new RedisAdapter($redis, $cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl);
                $cacheResult = new RedisAdapter($redis, $cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl);
                $cacheMetadata = new RedisAdapter($redis, $cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl);
                break;
            case 'memcached':
                $memcached = new Memcached($cacheConfig);
                $cacheQuery = new MemcachedAdapter($memcached->getInstance(), $cacheConfig->prefix . $doctrineConfig->queryCacheNamespace, $cacheConfig->ttl);
                $cacheResult = new MemcachedAdapter($memcached->getInstance(), $cacheConfig->prefix . $doctrineConfig->resultsCacheNamespace, $cacheConfig->ttl);
                $cacheMetadata = new MemcachedAdapter($memcached->getInstance(), $cacheConfig->prefix . $doctrineConfig->metadataCacheNamespace, $cacheConfig->ttl);
                break;
            default:
                $cacheQuery = $cacheResult = $cacheMetadata = new ArrayAdapter($cacheConfig->ttl);
        }

        $dataConfig = [$doctrineConfig->entities, $devMode, $doctrineConfig->proxies, null];

        $config = new Configuration();

        $config->setProxyDir($doctrineConfig->proxies);
        $config->setProxyNamespace($doctrineConfig->proxiesNamespace);
        $config->setAutoGenerateProxyClasses($doctrineConfig->setAutoGenerateProxyClasses);

        if($doctrineConfig->queryCache) {
            $config->setQueryCache($cacheQuery);
        }

        if($doctrineConfig->resultsCache) {
            $config->setResultCache($cacheResult);
        }

        if($doctrineConfig->metadataCache) {
            $config->setMetadataCache($cacheMetadata);
        }

        switch ($doctrineConfig->metadataConfigurationMethod) {
            case 'yaml':
                $config->setMetadataDriverImpl(new YamlDriver($doctrineConfig->entities));
                break;
            case 'xml':
                $config->setMetadataDriverImpl(new XmlDriver($doctrineConfig->entities, XmlDriver::DEFAULT_FILE_EXTENSION, $doctrineConfig->isXsdValidationEnabled));
                break;
            case 'attribute':
                $config->setMetadataDriverImpl(new AttributeDriver($doctrineConfig->entities));
                break;
            default:
                $config->setMetadataDriverImpl(ORMSetup::createDefaultAnnotationDriver($doctrineConfig->entities));
        }

        // Database connection information
        $connectionOptions = $this->convertDbConfig($db);

        $connection = DriverManager::getConnection($connectionOptions, $config);

        // Create EntityManager
        $this->em = new EntityManager($connection, $config);

        $this->em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('set', 'string');
        $this->em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }

    public function reOpen()
    {
        $this->em = new EntityManager($this->em->getConnection(), $this->em->getConfiguration(), $this->em->getEventManager());
    }

    /**
     * Convert CodeIgniter database config array to Doctrine's
     *
     * See http://www.codeigniter.com/user_guide/database/configuration.html
     * See http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html
     *
     * @param object $db
     * @return array
     * @throws Exception
     *
     */

    public function convertDbConfig($db)
    {
        $connectionOptions = [];

        if ($db->DBDriver === 'pdo') {
            return $this->convertDbConfigPdo($db);
        } else {
            $connectionOptions = [
                'driver'   => strtolower($db->DBDriver),
                'user'     => $db->username,
                'password' => $db->password,
                'host'     => $db->hostname,
                'dbname'   => $db->database,
                'charset'  => $db->charset,
                'port'     => $db->port,
                'servicename' => $db->servicename //OCI8
            ];
        }

        return $connectionOptions;
    }

    /**
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
                'port'     => $db->port
            ];
        } else {
            throw new Exception('Your Database Configuration is not confirmed by CodeIgniter Doctrine');
        }

        return $connectionOptions;
    }
}
