<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Libraries;

use CodeIgniter\Cache\Handlers\RedisHandler;
use Config\Cache;
use Redis as NativeRedis;

/**
 * Redis cache handler extension that exposes the native client to Doctrine.
 */
class Redis extends RedisHandler
{
    use InitializesNativeClient;

    public function __construct(Cache $config)
    {
        parent::__construct($config);
        $this->bootClient('redis', 'Redis');
    }

    /**
     * Native Redis client used by Symfony Cache adapters.
     */
    public function getInstance(): ?NativeRedis
    {
        return $this->redis;
    }
}
