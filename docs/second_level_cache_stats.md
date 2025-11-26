# Second Level Cache Statistics

## Key Concepts
- **Backend reuse:** Uses your framework cache backend (files/redis/memcached/array) as PSR-6 pool.
- **Toggle:** Enable via `Config\Doctrine::$secondLevelCache = true`.
- **TTL:** Controlled by `Config\Cache::$ttl` (default 3600s) and regions configuration.
- **Statistics (optional):** Enable counters with `Config\Doctrine::$secondLevelCacheStatistics = true`.

## Enabling
1. Configure your preferred cache handler in `Config\Cache`.
2. In `Config\Doctrine`, set:

```php
public bool $secondLevelCache = true;
public bool $secondLevelCacheStatistics = true; // optional
```

3. Mark entities/associations cacheable where needed using Doctrine ORM 3 annotations/attributes (e.g., `#[ORM\Cache]`).

## Viewing Statistics
When `secondLevelCacheStatistics` is enabled:
- The Debug Toolbar `Doctrine` panel shows a compact badge `SLC:hits/misses/puts (ratio%)`.
- Inside the panel, a small table lists `Hits`, `Misses`, and `Puts`.

You can also access the logger programmatically:

```php
$logger = \Config\Services::doctrine()->getSecondLevelCacheLogger();
// Inspect properties if available
$hits = $logger?->cacheHits ?? 0;
$misses = $logger?->cacheMisses ?? 0;
$puts = $logger?->cachePuts ?? 0;
```

## Notes
- Clear metadata/proxies after changing cacheable mappings to avoid stale state.
- Associations require target entities to be cacheable for join caching to work.
- If stats do not appear, ensure the toolbar is enabled and Doctrine service is initialized.
