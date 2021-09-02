<?php

namespace Daycry\Doctrine\Config;

use CodeIgniter\Config\BaseConfig;

class Doctrine extends BaseConfig
{
    public $debug = false;

    // see doc https://www.doctrine-project.org/projects/doctrine-orm/en/2.8/reference/advanced-configuration.html#auto-generating-proxy-classes-optional
    public $setAutoGenerateProxyClasses = ENVIRONMENT === 'development' ? true : false;

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
    
    /*
     * Namespace and folder of models
     */
    public $namespaceModel = 'App/Models';
    public $folderModel = APPPATH . 'Models';

    /*
     * Namespace and folder of proxies
     */
    public $namespaceProxy = 'App/Models/Proxies';
    public $folderProxy = APPPATH . 'Models/Proxies';

        /*
     * Folder for entities
     */
    public $folderEntity = APPPATH . 'Models/Entity';

}
