<?php

namespace Daycry\Doctrine\Libraries;

use CodeIgniter\Cache\Handlers\MemcachedHandler;
use Config\Cache;

/**
 * Memcached cache handler extension for Doctrine integration.
 */
class Memcached extends MemcachedHandler
{
    /**
     * Memcached constructor.
     *
     * @param Cache $config Cache configuration
     */
    public function __construct(Cache $config)
    {
        parent::__construct($config);
        $this->initialize();
    }

    /**
     * Get the native Memcached instance.
     *
     * @return mixed
     */
    public function getInstance()
    {
        return $this->memcached;
    }
}
