# Configuration

The core and CI4 adapter share ORM options, but obtain connections and cache
pools differently.

| Concern | Standalone core | CodeIgniter adapter |
|---|---|---|
| Configuration object | `Daycry\Doctrine\Configuration` | `Config\Doctrine` extending the adapter config |
| Connection | Explicit `connection` DBAL parameters | `Config\Database` group |
| Mapping directories | `entities` defaults to `[]` | Defaults to `APPPATH . 'Models/Entity'` |
| Caches | Inject `CachePools`; defaults are independent ArrayAdapters | Creates pools from `Config\Cache` |
| Query logger | Inject a PSR-3 logger | Resolves CI4's logger service |
| Named managers | Explicit `ManagerRegistry` factories | Framework services keyed by database group |

## Configure the standalone core

```php
use Daycry\Doctrine\Configuration;
use Daycry\Doctrine\Doctrine;

$config = new Configuration();
$config->connection = ['driver' => 'pdo_sqlite', 'memory' => true];
$config->entities = [__DIR__ . '/src/Entity']; // directory must exist
$doctrine = new Doctrine($config);
```

Connection keys use DBAL's names: for example `driver`, `host`, `dbname`, `user`
and `password`. The core does not read `config()`, `service()`, CI4 constants or
environment variables. Resolve application settings before constructing it.

## Configure the CI4 adapter

Run `php spark doctrine:publish` once, then edit `app/Config/Doctrine.php`.
Republishing prompts before replacing existing files.

```php
<?php

namespace Config;

class Doctrine extends \Daycry\Doctrine\CodeIgniter4\Config\Doctrine
{
    public array $entities = [APPPATH . 'Models/Entity'];
    public bool $secondLevelCache = true;
    public bool $secondLevelCacheStatistics = true;
}
```

Declare only application overrides. Property initializers in subclasses are
respected, and the inherited BaseConfig constructor handles CI4 configuration.
See [CI4 usage](usage.md#multi-database-groups) for group selection.

## Entity mapping and lazy objects

| Option | Default | Meaning |
|---|---|---|
| `entities` | Core: `[]`; adapter: application entity directory | Existing directories containing entity mappings |
| `metadataConfigurationMethod` | `'attribute'` | Use `'attribute'` or `'xml'` mappings |
| `isXsdValidationEnabled` | `false` | Validate XML mappings against the schema |

PHP 8.5 native lazy objects are always enabled. Generated-proxy options
`proxyFactory`, `proxies`, `proxiesNamespace` and `setAutoGenerateProxyClasses`
have been removed. No proxy files are generated during deployment.

## Query, result and metadata caches

| Option | Default | Scope |
|---|---|---|
| `queryCache` / `resultsCache` / `metadataCache` | `true` | Enable the corresponding pool on the ORM configuration |
| `queryCacheNamespace` / `resultsCacheNamespace` / `metadataCacheNamespace` | `doctrine_queries` / `doctrine_results` / `doctrine_metadata` | Used by the CI4 adapter when constructing pools |

The core uses the namespaces and lifetimes of the injected PSR-6 pools.
Set those on the adapters themselves; changing a namespace option does not
rename an injected pool. Defaults are independent, process-local ArrayAdapters.
See [standalone caching](standalone.md#caches-and-logging).

The CI4 adapter creates pools using `Config\Cache`:

- Query and metadata pools inherit the framework TTL.
- Result pools have a zero default lifetime.
- The file backend uses PhpFilesAdapter for query/metadata and FilesystemAdapter
  for results/SLC.
- Non-default groups append their exact hex-encoded group name to namespaces.
  The default group keeps the base namespace.

In the core, give different managers distinct persistent pool namespaces.
`ManagerRegistry` does not add namespace suffixes.

## Second-Level Cache

| Option | Default | Meaning |
|---|---|---|
| `secondLevelCache` | `false` | Enable SLC using the injected or adapter-created pool |
| `secondLevelCacheStatistics` | `false` | Collect region hits, misses and puts |
| `secondLevelCacheTtl` | `null` | Core: zero lifetime; adapter: inherit framework TTL. Set `0` for no expiration or a positive number of seconds |
| `secondLevelCacheLockPath` | `null` | Core: explicit path required for READ_WRITE regions. Adapter: supplies a group-specific directory under WRITEPATH |

The core accepts the SLC pool as `CachePools::$secondLevel`. Processes sharing
READ_WRITE regions must share the lock directory. See
[SLC configuration](second_level_cache.md) for mappings, lifetimes and invalidation.

## Custom DQL functions and types

| Option | Meaning |
|---|---|
| `customStringFunctions` / `customNumericFunctions` / `customDatetimeFunctions` / `customJsonFunctions` | Named DQL function classes |
| `customTypeMappings` | Native database type to Doctrine type mappings; defaults to enum/set mapped to string |
| `customTypes` | Custom DBAL type names mapped to Type subclasses |

DQL functions are prefilled from DoctrineExtensions and
scienta/doctrine-json-functions using MySQL implementations. Override the arrays
for database-specific functions on other platforms. Basic ORM use does not require
calling these functions.

Custom types are registered idempotently. Platform type mappings are applied
at construction and after `reOpen()`.

## Extension points

These options default to empty arrays or `null`:

| Option | Meaning |
|---|---|
| `sqlFilters` | Filter names mapped to SQLFilter subclasses |
| `enabledFilters` | Registered filters enabled on each new EntityManager |
| `eventListeners` | Event names mapped to lists of listener objects or class names |
| `eventSubscribers` | EventSubscriber objects or class names |
| `dbalMiddlewares` | DBAL middleware objects or class names |
| `defaultRepositoryClass` | Repository class for entities without an explicit repository |

Classes supplied without instances must support construction without arguments.
The CI4 adapter also adds toolbar instrumentation when `CI_DEBUG` is enabled.
Filter parameters must be supplied by the application after enabling a filter.

## Query logging

| Option | Default | Meaning |
|---|---|---|
| `queryLogging` | `false` | Enable the PSR-3 query logger middleware |
| `slowQueryThreshold` | `0.0` | Minimum duration in seconds; zero logs every successful query |
| `queryLogLevel` | `'info'` | PSR-3 record level |

In standalone code, pass a PSR-3 logger explicitly:

```php
$config->queryLogging = true;
$config->slowQueryThreshold = 0.5;
$config->queryLogLevel = 'warning';
$doctrine = new \Daycry\Doctrine\Doctrine($config, logger: $logger);
```

`$logger` is supplied by the application. Enabling logging without one raises
InvalidArgumentException. The CI4 adapter uses its logger service automatically.
Records contain SQL, bound parameters and `duration_ms`. Logging is independent
of the debug toolbar.

## Reopening

`reOpen()` creates a replacement EntityManager over the existing DBAL connection
and ORM configuration. It rebuilds configured events, registers types/mappings and
enables configured filters using the original constructor settings.
The connection's middleware chain and cache pools remain in place.

Reapply runtime filter parameters and load entities through the replacement manager.
Closing an in-memory SQLite connection discards its database; use `reOpen()` for
the documented server-connection recovery case. See [usage](usage.md#reopening-the-connection).
