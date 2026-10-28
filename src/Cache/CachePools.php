<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Independent PSR-6 pools; inject persistent adapters for cross-process caching.
 */
final readonly class CachePools
{
    public function __construct(
        public CacheItemPoolInterface $query = new ArrayAdapter(),
        public CacheItemPoolInterface $result = new ArrayAdapter(),
        public CacheItemPoolInterface $metadata = new ArrayAdapter(),
        public CacheItemPoolInterface $secondLevel = new ArrayAdapter(),
    ) {
    }
}
