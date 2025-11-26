# Doctrine Second-Level Cache (SLC)

This library optionally integrates Doctrine's Second-Level Cache (SLC). When enabled, entities can be cached across requests to reduce database load and improve performance for read-mostly data.

## Key Concepts
- Enable: single boolean in `app/Config/Doctrine.php` (`public bool $secondLevelCache = true;`).
- Backend: reuses the framework cache backend from `Config\Cache` (file, redis, memcached, array) and its `ttl`.
- Factory: `DefaultCacheFactory` is set up automatically in `src/Doctrine.php`; no app code changes required.
- Regions: Doctrine manages regions and invalidation internally when entities change.

## Enable and Configure

Publish config if you haven't:

```bash
php spark doctrine:publish
```

Then edit `app/Config/Doctrine.php` and set (see also [Configuration](configuration.md)):

```php
public bool $secondLevelCache = true;
```

No application code changes are required to wire the factory; `src/Doctrine.php` sets up `DefaultCacheFactory` using the same cache backend configured in `Config\Cache` (file/redis/memcached/array) and its `ttl`.

## Notes
- Ensure cache adapters are available (extensions for Redis/Memcached). The service throws descriptive errors if missing.
- Lifetimes follow the framework `ttl`. Use your cache configuration to tune overall behavior.

## Cache Key Namespace

SLC entries use the framework cache prefix plus `doctrine_slc` as namespace root:

```
<cache prefix>doctrine_slc
```

Within this namespace Doctrine creates internal region keys. Flushing or updating entities invalidates affected region entries automatically—no manual deletion is required.

## Mark Entities as Cacheable

Use Doctrine attributes to mark cacheable entities and optionally define a region:

```php
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Cache(usage: 'READ_ONLY', region: 'default_region')]
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

Common `usage` values:
- `READ_ONLY`: fastest; use for immutable data.
- `NONSTRICT_READ_WRITE`: allows concurrent reads with eventual consistency.
- `READ_WRITE`: strict invalidation; slightly slower.

## Best Practices

- Prefer `READ_ONLY` for reference/lookup tables.
- Avoid caching highly volatile entities unless using `READ_WRITE` and proper invalidation.
- Use distinct regions to separate concerns; lifetimes follow the framework `ttl`.
- Verify cache adapter availability (extensions for Redis/Memcached) in your environment.

## Troubleshooting

- If the SLC does not seem effective, ensure entities are annotated with `#[ORM\Cache(...)]` and queries aren’t bypassing the cache (e.g., custom hydrators).
- With `file` adapter, ensure the framework's cache directory is writable.
- For Redis/Memcached, confirm the PHP extensions are loaded and credentials in `Config\Cache` are correct.
- To inspect raw keys: list entries filtered by the prefix plus `doctrine_slc`. Avoid manual deletion unless performing a full cache reset.
