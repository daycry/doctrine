<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Libraries;

use CodeIgniter\Cache\Exceptions\CacheException;
use Throwable;

/**
 * Initializes a CodeIgniter cache handler and converts low-level connection
 * failures into CacheException, preserving the original exception as `previous`.
 *
 * Used by Daycry\Doctrine\Libraries\Memcached and Daycry\Doctrine\Libraries\Redis.
 */
trait InitializesNativeClient
{
    /**
     * @param non-empty-string $extension Extension name required for this client (e.g. 'memcached', 'redis').
     * @param non-empty-string $driver    Human-readable driver name used in error messages.
     */
    protected function bootClient(string $extension, string $driver): void
    {
        if (! extension_loaded($extension)) {
            throw new CacheException(sprintf(
                '%s extension not loaded; install php-%s to enable %s cache backend.',
                ucfirst($extension),
                $extension,
                $driver,
            ));
        }

        try {
            $this->initialize();
        } catch (Throwable $e) {
            throw new CacheException(sprintf('Failed to connect to %s: %s', $driver, $e->getMessage()), 0, $e);
        }
    }
}
