# Configuration

## Key Concepts

- Config file: `app/Config/Doctrine.php` holds Doctrine integration settings.
- Publish step: copies the config and CLI bootstrap into your app namespace.
- Cache wiring: query / results / metadata caches share the framework cache backend (`Config\Cache`).
- Second-Level Cache (SLC): toggled via a single boolean; reuses backend and TTL.
- Multi-database: every `Config\Database` group maps to its own cached Doctrine instance — see [`usage.md`](usage.md#multi-database-groups).

## Publishing the configuration

```bash
php spark doctrine:publish
```

This copies `Config/Doctrine.php` into your app namespace and `cli-config.php`
into the project root. Re-running the command prompts before overwriting
existing files; choose `n` to abort safely.

## Options reference

### Entity mapping and lazy objects

PHP 8.5 native lazy objects are always enabled. Generated proxies and their
configuration (`proxyFactory`, `proxies`, `proxiesNamespace`, and
`setAutoGenerateProxyClasses`) have been removed. No proxy directory is needed.
Configure `entities` with the directories containing PHP attributes or XML mappings.

### Caches

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `queryCache` / `resultsCache` / `metadataCache` | `bool` | `true` | Toggle each Doctrine cache backed by `Config\Cache`. |
| `queryCacheNamespace` / `resultsCacheNamespace` / `metadataCacheNamespace` | `string` | `doctrine_queries` / `doctrine_results` / `doctrine_metadata` | Namespace prefix for each cache pool. |

!!! info "Per-group isolation"
    Cache namespaces are automatically suffixed with the database group name for
    every **non-default** group (e.g. `doctrine_results_7265706f7274696e67`), so multiple
    groups sharing one cache backend never collide on the same keys. The default
    group keeps the unsuffixed namespaces shown above.

### Metadata driver

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `metadataConfigurationMethod` | `'attribute'\|'xml'` | `'attribute'` | Which Doctrine metadata driver to use. |
| `isXsdValidationEnabled` | `bool` | `false` | Enable XSD validation when `metadataConfigurationMethod = 'xml'`. |

### Second-Level Cache (SLC)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `secondLevelCache` | `bool` | `false` | Enable Doctrine SLC. Reuses `Config\Cache` backend and TTL. |
| `secondLevelCacheStatistics` | `bool` | `false` | Collect hits/misses/puts via `StatisticsCacheLogger`. Surface them in the toolbar (and reset per request via the filter — see [`debug_toolbar.md`](debug_toolbar.md)). |
| `secondLevelCacheTtl` | `?int` | `null` | Per-cache TTL override in seconds. `null` = inherit `Config\Cache::$ttl`. `0` = no expiration (entries persist until Doctrine invalidates them). `> 0` = custom TTL just for SLC. |

### Custom DQL functions

| Option | Type | Description |
|--------|------|-------------|
| `customStringFunctions`, `customNumericFunctions`, `customDatetimeFunctions`, `customJsonFunctions` | `array<string, class-string>` | DQL extensions registered with Doctrine. Pre-filled with `beberlei/doctrineextensions` and `scienta/doctrine-json-functions`. Override or remove entries to disable specific functions. |

### Type mappings

| Option | Type | Description |
|--------|------|-------------|
| `customTypeMappings` | `array<string, string>` | Native DBAL type → Doctrine type mappings registered on the database platform. Default: `['enum' => 'string', 'set' => 'string']`. Re-applied automatically on `Doctrine::reOpen()`. |

### Extension points

The following extension points default to off (empty / `null` / `false`).

| Option | Type | Description |
|--------|------|-------------|
| `customTypes` | `array<string, class-string<Doctrine\DBAL\Types\Type>>` | Custom DBAL Types registered with `Type::addType()` (idempotent). Use for UUID/ULID keys, value objects, etc. Applied on construction and `reOpen()`. |
| `sqlFilters` | `array<string, class-string<Doctrine\ORM\Query\Filter\SQLFilter>>` | Named SQL filters to register (soft-delete, multi-tenant scoping). |
| `enabledFilters` | `list<string>` | Names from `sqlFilters` to enable automatically on every `EntityManager` (and after `reOpen()`). Filter parameters must still be set at runtime via `$em->getFilters()->getFilter($name)->setParameter(...)`. |
| `eventListeners` | `array<string, list<class-string\|object>>` | Doctrine event listeners keyed by event name (e.g. `'onFlush'`). Class-strings are instantiated with no arguments. |
| `eventSubscribers` | `list<class-string<Doctrine\Common\EventSubscriber>\|object>` | Doctrine event subscribers. |
| `dbalMiddlewares` | `list<class-string<Doctrine\DBAL\Driver\Middleware>\|object>` | User DBAL middlewares (retry/logging/metrics). They wrap the driver first; the toolbar capture middleware is applied last. |
| `defaultRepositoryClass` | `?class-string<Doctrine\ORM\EntityRepository>` | Default repository class for entities that don't declare their own. `null` keeps Doctrine's built-in repository. |

### Production query logging

Unlike the Debug Toolbar collector (which only renders under `CI_DEBUG`), query
logging runs in any environment, giving production a slow-query log via a PSR-3
logger (CodeIgniter's `logger` service by default).

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `queryLogging` | `bool` | `false` | Enable query logging via `Daycry\Doctrine\Logging\QueryLoggerMiddleware`. |
| `slowQueryThreshold` | `float` | `0.0` | Minimum query duration (seconds) before logging. `0.0` logs every query (noisy); set e.g. `0.5` for slow-query logging only. |
| `queryLogLevel` | `string` | `'info'` | PSR-3 level used for the log records. |

## Quick example

```php
<?php

namespace App\Config;

use Daycry\Doctrine\Config\Doctrine as DoctrineBase;

class Doctrine extends DoctrineBase
{
    public array  $entities                    = [APPPATH . 'Models/Entity'];

    public bool   $queryCache              = true;
    public bool   $resultsCache             = true;
    public bool   $metadataCache            = true;

    public string $metadataConfigurationMethod = 'attribute';
    public bool   $isXsdValidationEnabled      = false;

    // SLC: enable + collect stats (separate booleans)
    public bool $secondLevelCache           = true;
    public bool $secondLevelCacheStatistics = true;  // optional

    // Add a custom enum→Doctrine mapping if your DB uses enum columns
    public array $customTypeMappings = [
        'enum' => 'string',
        'set'  => 'string',
    ];
}
```

## Notes

- No PSR-6 adapter has to be configured for SLC — the library wires the same
  backend used by `Config\Cache` automatically.
- If a Redis or Memcached extension is missing, the Doctrine service throws
  `CacheException` with a descriptive message at construction.
- For multi-database setups (`Services::doctrine(true, 'reporting')`), the
  same `Config\Doctrine` is used for every group; each group resolves its own
  Doctrine instance from `Config\Database->{$dbGroup}`.

## Cache lifetime and storage policy

Query and metadata pools inherit `Config\Cache::$ttl`. Result-cache pools have a
zero default lifetime, so `getFromCacheOrQuery(..., ttl: 0, ...)` does not expire just
because the framework cache has a nonzero default. Always pass the intended lifetime
to the helper or to Doctrine's result-cache API for data that must expire.

The file backend uses PhpFilesAdapter for query/metadata caches and FilesystemAdapter
for results and SLC. Dynamic data therefore does not consume OPcache entries.
In-memory pools for queries, results and metadata are independent.

`doctrine:publish` now generates a minimal Config\Doctrine subclass. Only declare
application-specific overrides; inherited defaults continue to receive package fixes.
Remove the deleted generated-proxy options from application configuration when migrating to this major version.

`secondLevelCacheLockPath` configures the FileLockRegion directory for READ_WRITE
regions. By default it is `WRITEPATH/cache/doctrine/locks` with a database-group
suffix when needed. Applications sharing an SLC backend across hosts must use a
shared lock directory or provide a suitable ConcurrentRegion implementation.

Non-default cache namespaces append the hex-encoded database group name. This
preserves exact group identity even though framework service keys are lowercased.

See [the major-version migration guide](migration-major.md) for requirements and removed APIs.
