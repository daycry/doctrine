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
     *
     * The parent CI4 handler types `$memcached` as `Memcache|Memcached` to
     * support either extension; only `Memcached` is supported by
     * `Symfony\Component\Cache\Adapter\MemcachedAdapter`, so we narrow the
     * return type here. If the underlying client is somehow a `Memcache`
     * instance we return `null` so the caller can fail loudly with a
     * descriptive error instead of triggering a fatal type mismatch downstream.
     *
     * @psalm-suppress UndefinedClass — Psalm CI runner does not load ext-memcached
     */
    public function getInstance(): ?NativeMemcached
    {
        /** @psalm-suppress UndefinedClass */
        return $this->memcached instanceof NativeMemcached ? $this->memcached : null;
    }
}
