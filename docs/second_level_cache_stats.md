# Second-Level Cache Statistics

When `Config\Doctrine::$secondLevelCacheStatistics` is enabled, Doctrine
collects per-region hit / miss / put counters via its native
`Doctrine\ORM\Cache\Logging\StatisticsCacheLogger`. The library exposes the
logger so you can read or reset the counters.

## Key Concepts

- **Backend reuse:** uses your framework cache backend (file / Redis /
  Memcached / array) as PSR-6 pool.
- **Toggle:** enable via `Config\Doctrine::$secondLevelCache = true` (TTL via
  `Config\Cache::$ttl` by default — see [`configuration.md`](configuration.md)).
- **Statistics:** enable counters with
  `Config\Doctrine::$secondLevelCacheStatistics = true`.

## Enabling

1. Configure your preferred cache handler in `Config\Cache`.
2. In `Config\Doctrine`:

   ```php
   public bool $secondLevelCache           = true;
   public bool $secondLevelCacheStatistics = true; // optional
   ```

3. Mark entities cacheable with `#[ORM\Cache]` attributes — see
   [`second_level_cache.md`](second_level_cache.md#mark-entities-as-cacheable).

## Reading the Counters

```php
$logger = \Config\Services::doctrine()->getSecondLevelCacheLogger();
// ?Doctrine\ORM\Cache\Logging\StatisticsCacheLogger

if ($logger === null) {
    // Statistics are not enabled (Config\Doctrine::$secondLevelCacheStatistics = false)
    // or the configured logger is not a StatisticsCacheLogger.
    return;
}

$hits   = $logger->getHitCount();
$misses = $logger->getMissCount();
$puts   = $logger->getPutCount();

// Per-region:
$regionHits = $logger->getRegionHitCount('your_region_name');
```

`StatisticsCacheLogger` is part of Doctrine ORM; refer to the
[Doctrine ORM Cache documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/second-level-cache.html#cache-logging)
for the full API.

## Resetting the Counters

To reset counters programmatically (useful at the start of a long-running
worker, or in tests):

```php
\Config\Services::doctrine()->resetSecondLevelCacheStatistics();
```

The package also ships a CodeIgniter filter that performs this reset at the
start of every HTTP request (so the toolbar shows per-request stats); see
[`debug_toolbar.md`](debug_toolbar.md#resetting-stats-per-request) for the
registration steps.

## Viewing the Stats in the Toolbar

When `secondLevelCacheStatistics` is enabled:

- the Doctrine panel shows a compact badge `SLC:hits/misses/puts (ratio%)`
- inside the panel a small table lists hits, misses and puts

See [`debug_toolbar.md`](debug_toolbar.md) for the toolbar integration.

## Notes

- Clear metadata / proxies after changing the `#[ORM\Cache]` attributes of
  an entity to avoid stale state.
- Associations require the target entity to be cacheable for join caching
  to work.
- If stats do not appear in the toolbar, ensure the toolbar is enabled and
  the Doctrine service has been instantiated at least once during the
  request.
