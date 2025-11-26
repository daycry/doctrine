<?php

namespace Daycry\Doctrine\Libraries;

use CodeIgniter\Cache\Handlers\RedisHandler;
use Config\Cache;

/**
 * Redis cache handler extension for Doctrine integration.
 */
class Redis extends RedisHandler
{
    /**
     * Redis constructor.
     *
     * @param Cache $config Cache configuration
     */
    public function __construct(Cache $config)
    {
        if (! extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension not loaded; install php-redis to enable Redis cache backend.');
        }
        parent::__construct($config);
        $this->initialize();
    }

    /**
     * Get the native Redis instance.
     *
     * @return mixed
     */
    public function getInstance()
    {
        return $this->redis;
    }
}
