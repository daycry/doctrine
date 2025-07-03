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
