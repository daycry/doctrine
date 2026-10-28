# Doctrine Second-Level Cache

SLC caches entity/association state across EntityManagers. The core constructs
Doctrine's DefaultCacheFactory using a supplied PSR-6 pool. The CI4 adapter
constructs that pool from the framework cache configuration.

## Standalone setup

Configure SLC before constructing Doctrine and inject a persistent pool:

```php
use Daycry\Doctrine\Cache\CachePools;
use Daycry\Doctrine\Doctrine;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$config->secondLevelCache = true;
$config->secondLevelCacheStatistics = true;
$config->secondLevelCacheTtl = 3600;
$config->secondLevelCacheLockPath = __DIR__ . '/var/locks/doctrine';
$pools = new CachePools(
    secondLevel: new FilesystemAdapter('primary_slc', 0, __DIR__ . '/var/cache'),
);
$doctrine = new Doctrine($config, $pools);
```

Here `$config` is the application's core Configuration, with connection and entity
paths already set. Query/result/metadata pools still use their ArrayAdapter defaults
unless also supplied. Without an injected persistent SLC pool, SLC is process-local.

## CodeIgniter setup

In the published `app/Config/Doctrine.php`:

```php
public bool $secondLevelCache = true;
public bool $secondLevelCacheStatistics = true;
public ?int $secondLevelCacheTtl = null;
```

The adapter reuses the `Config\Cache` backend (file, Redis, Memcached or array).
Its namespace is the framework prefix plus `doctrine_slc` and, for a non-default
group, the hex-encoded group suffix. Each group has its own manager and pools.

## Lifetimes

| `secondLevelCacheTtl` | Standalone | CI4 adapter |
|---|---|---|
| `null` | Region lifetime 0 | Inherit `Config\Cache::$ttl` |
| `0` | No expiry until invalidation | No expiry until invalidation |
| Positive integer | Region lifetime in seconds | Override the framework TTL for SLC |

Configure the injected standalone pool's own lifetime consistently with the desired
region lifetime. A backend may evict entries earlier according to its storage policy.

## Mark entities as cacheable

```php
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Cache(usage: 'READ_ONLY')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;
}
```

| Usage | Suitable data |
|---|---|
| `READ_ONLY` | Immutable reference data |
| `NONSTRICT_READ_WRITE` | Mutable data where eventual cache consistency is acceptable |
| `READ_WRITE` | Mutable data requiring concurrent region locking |

Associations also need suitable cache mappings. Changing mappings requires clearing
stale metadata and invalidating the corresponding data regions.

## READ_WRITE locking

The core requires an explicit `secondLevelCacheLockPath` for READ_WRITE regions.
The CI4 adapter defaults to a group-specific directory under
`WRITEPATH . 'cache/doctrine/locks'`.

Processes sharing regions must share this lock directory. Give it write access
for the application process. File locks on independent hosts do not coordinate
unless the directory is shared; account for this when choosing deployment storage.

## Invalidation and statistics

ORM writes invalidate affected cache state according to the configured strategy.
Writes performed outside ORM need an application invalidation strategy.
Manual invalidation is also needed when changing mappings or deploying incompatible
cached entity layouts. Spark `doctrine:cache:clear` clears only query/result/metadata
pools and does not evict SLC regions.

```php
$cache = $doctrine->getEm()->getCache();
$cache?->evictEntityRegion(Product::class);
```

Statistics are available through `$doctrine->getSecondLevelCacheLogger()` when
enabled. See [SLC statistics](second_level_cache_stats.md). The
[CI4 toolbar](debug_toolbar.md) can display them; the core does not depend on a toolbar.

## Troubleshooting

- Use a persistent pool when testing cache reuse across processes.
- Ensure entity mappings contain `#[ORM\Cache(...)]`.
- Ensure file cache and lock directories are writable.
- For native Redis/Memcached pools, load the PHP extension and verify the service
  connection; having the service running alone does not load the extension.
- Doctor checks general cache access and SLC configuration, not SLC region contents.
