<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Libraries;

use CodeIgniter\Cache\Handlers\MemcachedHandler;
use Config\Cache;
use Memcached as NativeMemcached;

/**
 * Memcached cache handler extension that exposes the native client to Doctrine.
 */
class Memcached extends MemcachedHandler
{
    use InitializesNativeClient;

    public function __construct(Cache $config)
    {
        parent::__construct($config);
        $this->bootClient('memcached', 'Memcached');
    }

    /**
     * Native Memcached client used by Symfony Cache adapters.
     */
    public function getInstance(): ?NativeMemcached
    {
        return $this->memcached;
    }
}
