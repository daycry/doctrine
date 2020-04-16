<?php 

namespace Daycry\Doctrine\Config;

use CodeIgniter\Config\BaseConfig;

class Doctrine extends BaseConfig
{
    public $debug = false;

    public $setAutoGenerateProxyClasses = true;

    /*
     * Methods "Redis, Memcached" or "null"
     */
    public $cache = null;

    /*
     * Ports
     * for Redis : 6379
     * for Memcached : 11211
     */
    public $portCache = null;

    public $hostCache = null;

    /*
     * Index of redis database
     */
    public $databaseRedis = 0;

    /*
     * Namespace of Redis database or name of Memcached server
     */
    public $namespaceCache = "name";

}
