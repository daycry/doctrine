# Doctrine Second-Level Cache (SLC)

The library wires Doctrine's Second-Level Cache (SLC) to the framework cache
backend. When enabled, entities can be cached across requests to reduce
database load and improve performance for read-mostly data.

## Key Concepts

- **Enable:** a single boolean in `app/Config/Doctrine.php`
  (`public bool $secondLevelCache = true;`).
- **Backend:** reuses the framework cache backend from `Config\Cache` — file,
  Redis, Memcached or in-memory array — and its `ttl`.
- **Factory:** `DefaultCacheFactory` is set up automatically in
  `src/Doctrine.php`; no application code required.
- **Regions:** Doctrine creates and invalidates regions automatically per
  entity. Manual region naming is optional and only useful for granular
  invalidation strategies.
- **Statistics (optional):** enable counters via
  `public bool $secondLevelCacheStatistics = true;`. See
  [`second_level_cache_stats.md`](second_level_cache_stats.md) for the API
  and [`debug_toolbar.md`](debug_toolbar.md#resetting-stats-per-request) for
  the per-request reset filter.

## Enable and Configure

Publish the config if you haven't already:

```bash
php spark doctrine:publish
```

Then edit `app/Config/Doctrine.php`:

```php
public bool $secondLevelCache           = true;
public bool $secondLevelCacheStatistics = true; // optional
```

## TTL Override

```php
public ?int $secondLevelCacheTtl = null;
```

| Value | Behaviour |
|-------|-----------|
| `null` | Inherit `Config\Cache::$ttl` (default). |
| `0`    | No expiration — entries persist until Doctrine invalidates them. |
| `> 0`  | Custom SLC TTL in seconds (overrides `Config\Cache::$ttl` only for SLC). |

## Cache Key Namespace

SLC entries use the framework cache prefix plus `doctrine_slc` as namespace
root:

```
<cache prefix>doctrine_slc
```

Within this namespace Doctrine creates internal region keys. Flushing or
updating entities invalidates affected region entries automatically — manual
deletion is not required and is generally discouraged.

## Mark Entities as Cacheable

Use Doctrine attributes to mark entities cacheable:

```php
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Cache(
    usage: 'READ_ONLY',         // READ_ONLY | NONSTRICT_READ_WRITE | READ_WRITE
    region: 'default_region',   // optional; omit to use the Doctrine default
)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(length: 120)]
    private string $name;
}
```

`usage` semantics:

- `READ_ONLY` — fastest; use for immutable reference data.
- `NONSTRICT_READ_WRITE` — concurrent reads with eventual consistency.
- `READ_WRITE` — strict invalidation; slightly slower under concurrency.

## Best Practices

- Prefer `READ_ONLY` for reference / lookup tables.
- Avoid caching highly volatile entities unless using `READ_WRITE` and
  knowing the invalidation cost.
- Use distinct regions to separate concerns when needed; lifetimes follow
  the framework `ttl` unless overridden via `secondLevelCacheTtl`.
- Verify cache adapter availability (extensions for Redis / Memcached) in
  every environment.

## Troubleshooting

- If SLC does not seem effective, ensure entities are annotated with
  `#[ORM\Cache(...)]` and that queries are not bypassing the cache (eg.
  custom hydrators, `setHint(Query::HINT_CACHE_ENABLED, false)`).
- With the `file` adapter, ensure the framework's cache directory is
  writable.
- For Redis / Memcached, confirm the PHP extensions are loaded and the
  credentials in `Config\Cache` are correct.

## See Also

- Toolbar integration and the `SLC: hits/misses/puts (ratio%)` badge:
  [`debug_toolbar.md`](debug_toolbar.md)
- Statistics API and counters reset:
  [`second_level_cache_stats.md`](second_level_cache_stats.md)
