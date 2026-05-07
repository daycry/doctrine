<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Helpers;

use Daycry\Doctrine\Config\Services;

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
 * @param string      $cacheKey PSR-6 compatible cache key (no reserved characters: {}()/\@:)
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

    if ($pool === null) {
        return $queryFn();
    }

    $item = $pool->getItem($cacheKey);
    if ($item->isHit()) {
        return $item->get();
    }

    $value = $queryFn();
    $item->set($value);
    if ($ttl > 0) {
        $item->expiresAfter($ttl);
    }
    $pool->save($item);

    return $value;
}
