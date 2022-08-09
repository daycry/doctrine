<?php

namespace Daycry\Doctrine;

use CodeIgniter\Config\BaseConfig;
use Doctrine\Common\ClassLoader;
//use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Doctrine\Common\Annotations\AnnotationReader;

/**
 * Class General
 *
 */
class Doctrine
{
    public $em = null;

    public function __construct( BaseConfig $configuration = null, BaseConfig $cacheConf = null )
    {
        if( $configuration === null )
        {
            $configuration = config( 'Doctrine' );
        }

        if( $cacheConf === null )
        {
            $cacheConf = config( 'Cache' );
        }

        $db = \Config\Database::connect();

        $entitiesClassLoader = new ClassLoader( $configuration->namespaceModel, $configuration->folderModel );
        $entitiesClassLoader->register();

        $proxiesClassLoader = new ClassLoader( $configuration->namespaceProxy, $configuration->folderProxy );
        $proxiesClassLoader->register();

        $dev_mode = ( ENVIRONMENT == "development" ) ? true : false;

        if( $cacheConf->handler == 'redis' )
        {
            $redis = new \Daycry\Doctrine\Libraries\Redis( $cacheConf );
            $redis = $redis->getClass();
            $redis->select( $cacheConf->redis[ 'database' ] );
            $this->cache = new \Daycry\Doctrine\Cache\RedisCache();
            $this->cache->setRedis( $redis );
            $this->cache->setNamespace( $cacheConf->prefix );

        }else if( $cacheConf->handler == 'memcached' )
        {
            $memcached = new \Daycry\Doctrine\Libraries\Memcached( $cacheConf );
            $this->cache = new \Daycry\Doctrine\Cache\MemcachedCache();
            $this->cache->setMemcached( $memcached->getClass() );

        } else if( $cacheConf->handler == 'file' )
        {
            $this->cache = new \Daycry\Doctrine\Cache\PhpFileCache($cacheConf->storePath . 'doctrine');
        }else{
            $this->cache = new \Daycry\Doctrine\Cache\ArrayCache();
        }

        $reader = new AnnotationReader();
        $driver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver( $reader, array( $configuration->folderEntity ) );

        $config = Setup::createAnnotationMetadataConfiguration( array( $configuration->folderEntity ), $dev_mode, $configuration->folderProxy, $this->cache, true );
        $config->setMetadataCacheImpl( $this->cache );
        $config->setQueryCacheImpl( $this->cache );
        $config->setMetadataDriverImpl( $driver );


        //Force generate proxy classes
        // comand: vendor/bin/doctrine orm:generate-proxies app/Models/Proxies
        $config->setAutoGenerateProxyClasses( $configuration->setAutoGenerateProxyClasses );

        // Set up logger
        if( $configuration->debug )
        {
            //$logger = new EchoSQLLogger;
            //$config->setSQLLogger( $logger );
        }

        // Database connection information
        $connectionOptions = $this->convertDbConfig( $db );

        // Create EntityManager
        $this->em = EntityManager::create( $connectionOptions, $config );

        $this->em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('set', 'string');
        $this->em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }

    public function reOpen()
    {
        $this->em = EntityManager::create( $this->em->getConnection(), $this->em->getConfiguration(), $this->em->getEventManager() );
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
     */
    public function convertDbConfig( $db )
    {
        $connectionOptions = [];

        if ( $db->DBDriver === 'pdo' )
        {
            return $this->convertDbConfigPdo( $db );
        } else
        {
            $connectionOptions = [
                'driver'   => strtolower( $db->DBDriver ),
                'user'     => $db->username,
                'password' => $db->password,
                'host'     => $db->hostname,
                'dbname'   => $db->database,
                'charset'  => $db->charset,
                'port'     => $db->port
            ];
        }

        return $connectionOptions;
    }

    protected function convertDbConfigPdo($db)
    {
        $connectionOptions = [];

        if ( substr($db->hostname, 0, 7) === 'sqlite:' )
        {
            $connectionOptions = [
                'driver'   => 'pdo_sqlite',
                'user'     => $db->username,
                'password' => $db->password,
                'path'     => preg_replace( '/\Asqlite:/', '', $db->hostname ),
            ];
        } elseif( substr($db->dsn, 0, 7) === 'sqlite:' )
        {
            $connectionOptions = [
                'driver'   => 'pdo_sqlite',
                'user'     => $db->username,
                'password' => $db->password,
                'path'     => preg_replace( '/\Asqlite:/', '', $db->dsn ),
            ];
        } elseif( substr($db->dsn, 0, 6) === 'mysql:' )
        {
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
