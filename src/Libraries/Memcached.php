<?php

namespace Daycry\Doctrine\Libraries;

use CodeIgniter\Cache\Handlers\MemcachedHandler;
use Config\Cache;
use RuntimeException;

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
        if (! extension_loaded('memcached')) {
            throw new RuntimeException('Memcached extension not loaded; install php-memcached to enable Memcached cache backend.');
        }
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
