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

### Proxies

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `setAutoGenerateProxyClasses` | `bool` | `ENVIRONMENT === 'development'` | Auto-regenerates Doctrine proxies in development. |
| `proxies` | `string` | `APPPATH . 'Models/Proxies'` | Filesystem path for generated proxies. |
| `proxiesNamespace` | `string` | `'DoctrineProxies'` | Namespace assigned to generated proxies. |
| `proxyFactory` | `bool` | `true` | On PHP ≥ 8.4 enables the engine's **native lazy objects** as proxy factory (skipping proxy class generation entirely). On PHP < 8.4 the flag has no effect — Doctrine falls back to generated proxies. Set to `false` to keep generated proxies even on PHP 8.4+ when running tools that don't yet support native lazy objects. |

### Caches

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `queryCache` / `resultsCache` / `metadataCache` | `bool` | `true` | Toggle each Doctrine cache backed by `Config\Cache`. |
| `queryCacheNamespace` / `resultsCacheNamespace` / `metadataCacheNamespace` | `string` | `doctrine_queries` / `doctrine_results` / `doctrine_metadata` | Namespace prefix for each cache pool. |

!!! info "Per-group isolation"
    Cache namespaces are automatically suffixed with the database group name for
    every **non-default** group (e.g. `doctrine_results_reporting`), so multiple
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

All of the following are additive and default to "off" (empty / `null` / `false`),
so existing configs are unaffected.

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
    public bool   $setAutoGenerateProxyClasses = ENVIRONMENT === 'development';
    public array  $entities                    = [APPPATH . 'Models/Entity'];
    public string $proxies                     = APPPATH . 'Models/Proxies';
    public string $proxiesNamespace            = 'DoctrineProxies';

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
