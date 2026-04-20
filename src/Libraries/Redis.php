<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Libraries;

use CodeIgniter\Cache\Exceptions\CacheException;
use CodeIgniter\Cache\Handlers\RedisHandler;
use Config\Cache;
use Throwable;

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
            throw new CacheException('Redis extension not loaded; install php-redis to enable Redis cache backend.');
        }
        parent::__construct($config);

        try {
            $this->initialize();
        } catch (Throwable $e) {
            throw new CacheException('Failed to connect to Redis: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the native Redis client instance.
     */
    public function getInstance(): mixed
    {
        return $this->redis;
    }
}
