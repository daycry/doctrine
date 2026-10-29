<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4\Helpers;

use Daycry\Doctrine\Cache\CacheAside;
use Daycry\Doctrine\CodeIgniter4\Config\Services;

/**
 * Cache-aside helper backed by Doctrine's configured result cache.
 *
 * Looks up `$cacheKey` in the PSR-6 result cache pool of the Doctrine
 * configuration. On a cache miss, executes `$queryFn`, stores the value
 * with `$ttl` seconds expiry, and returns it.
 *
 * If no result cache is configured (`Config\Doctrine::$resultsCache = false`),
 * the query is always executed and nothing is cached.
 *
 * @param string      $cacheKey Cache key. PSR-6 reserved characters ({}()/\@:) are
 *                              normalised automatically (sanitised + short hash of the
 *                              original to avoid collisions), so any string is accepted.
 * @param int         $ttl      Lifetime in seconds; 0 means no expiration
 * @param callable    $queryFn  Closure that returns the value to cache
 * @param string|null $dbGroup  Optional CodeIgniter database group; null = default
 *
 * @return mixed The cached or freshly computed value
 */
function getFromCacheOrQuery(string $cacheKey, int $ttl, callable $queryFn, ?string $dbGroup = null): mixed
{
    $doctrine = Services::doctrine(true, $dbGroup);
    $pool     = $doctrine->em?->getConfiguration()->getResultCache();

    return new CacheAside($pool)->get($cacheKey, $ttl, $queryFn);
}
