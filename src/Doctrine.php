<?php

namespace Daycry\Doctrine;

use CodeIgniter\Config\BaseConfig;
use Doctrine\Common\ClassLoader;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\Logging\EchoSQLLogger;

/**
 * Class General
 *
 */
class Doctrine
{
    public $em = null;

    public function __construct( BaseConfig $configuration = null )
    {
        if( empty( $configuration ) )
        {
            $configuration = config( 'Doctrine' );
        }

        $db = \Config\Database::connect();

        $entitiesClassLoader = new ClassLoader( 'App/Models', rtrim( APPPATH, "Models" ) );
        $entitiesClassLoader->register();

        $proxiesClassLoader = new ClassLoader( 'App/Models/Proxies', APPPATH . 'Models/Proxies' );
        $proxiesClassLoader->register();

        $dev_mode = ( ENVIRONMENT == "development" ) ? true : false;

        $cacheConf = config( 'Cache' );

        if( $cacheConf->handler == 'redis' )
        {
            /*$redis = new \Daycry\Doctrine\Libraries\Redis( $cacheConf );
            $cache = new \Doctrine\Common\Cache\RedisCache();
            $cache->setRedis( $redis->getClass() );
            $cache->setNamespace( $cacheConf->prefix );*/

            $redis = new \Redis();
            $redis->connect( $cacheConf->redis[ 'host' ], $cacheConf->redis[ 'port' ] );
            $redis->select( $cacheConf->redis[ 'database' ] );
            $cache = new \Doctrine\Common\Cache\RedisCache();
            $cache->setRedis( $redis );
            $cache->setNamespace( $cacheConf->prefix );
            //$cache->save( 'cache_id', 'my_data' );

        }else if( $cacheConf->handler == 'memcached' )
        {
            /*$memcached = new \Daycry\Doctrine\Libraries\Memcached( $cacheConf );
            $cache = new \Doctrine\Common\Cache\MemcachedCache();
            $cache->setMemcached( $memcached->getClass() );*/

            $memcached = new \Memcached();
            $memcached->addServer( $cacheConf->memcached[ 'host' ], $cacheConf->memcached[ 'port' ], $cacheConf->memcached[ 'weight' ] );
            $cache = new \Doctrine\Common\Cache\MemcachedCache();
            $cache->setMemcached( $memcached );
            //$cache->save( 'cache_id', 'my_data' );

        } else if ($cacheConf->handler == 'file') {
            $cache = new \Doctrine\Common\Cache\PhpFileCache($cacheConf->storePath . 'doctrine');
        }else{
            $cache = new \Doctrine\Common\Cache\ArrayCache();
        }

        $reader = new AnnotationReader();
        $driver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver( $reader, array( APPPATH . 'Models/Entity' ) );

        $config = Setup::createAnnotationMetadataConfiguration( array( APPPATH . 'Models/Entity' ), $dev_mode, APPPATH . 'Models/Proxies', $cache, true );
        $config->setMetadataCacheImpl( $cache );
        $config->setQueryCacheImpl( $cache );
        $config->setMetadataDriverImpl( $driver );


        //Force generate proxy classes
        // comand: vendor/bin/doctrine orm:generate-proxies app/Models/Proxies
        $config->setAutoGenerateProxyClasses( $configuration->setAutoGenerateProxyClasses );

        // Set up logger
        if( $configuration->debug )
        {
            $logger = new EchoSQLLogger;
            $config->setSQLLogger( $logger );
        }

        // Database connection information
        $connectionOptions = $this->convertDbConfig( $db );

        // Create EntityManager
        $this->em = EntityManager::create( $connectionOptions, $config );

        $this->em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('set', 'string');
        $this->em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }

    /**
     * Convert CodeIgniter database config array to Doctrine's
     *
     * See http://www.codeigniter.com/user_guide/database/configuration.html
     * See http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html
     *
     * @param array $db
     * @return array
     * @throws Exception
     */
    public function convertDbConfig( $db )
    {
        $connectionOptions = [];

        if ( $db->DBDriver === 'pdo' )
        {
            return $this->convertDbConfigPdo( $db );
        } elseif( $db->DBDriver === 'MySQLi' )
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
        } else {
            throw new \Exception('Your Database Configuration is not confirmed by CodeIgniter Doctrine');
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
