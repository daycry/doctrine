<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Libraries;

use CodeIgniter\Cache\Exceptions\CacheException;
use CodeIgniter\Cache\Handlers\MemcachedHandler;
use Config\Cache;
use Throwable;

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
            throw new CacheException('Memcached extension not loaded; install php-memcached to enable Memcached cache backend.');
        }
        parent::__construct($config);

        try {
            $this->initialize();
        } catch (Throwable $e) {
            throw new CacheException('Failed to connect to Memcached: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the native Memcached client instance.
     */
    public function getInstance(): mixed
    {
        return $this->memcached;
    }
}
