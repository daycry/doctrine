---
hide:
  - navigation
  - toc
---

<div class="hero" markdown>

# Daycry Doctrine

**Standalone Doctrine ORM toolkit for PHP 8.5**, with an optional **CodeIgniter 4**
adapter. Configure ORM, caching and logging explicitly, or integrate with CI4's
services, database groups, toolbar and Spark.

[Install :material-rocket-launch:](installation.md){ .md-button .md-button--primary }
[Standalone :material-language-php:](standalone.md){ .md-button }
[Migrate to 6.x :material-source-branch:](migration-major.md){ .md-button }

</div>

!!! info "Version 6.0: breaking changes"
    Existing application imports and constructors require migration. See
    [Installation](installation.md) for tagged releases, the adapter's VCS repository
    and local development installations.

## Choose a package

| Package | Included |
|---|---|
| `daycry/doctrine` | Framework-independent ORM setup, DataTables, PSR-6 caching, PSR-3 query logging, named managers and console tools |
| `daycry/doctrine-codeigniter4` | Core plus CI4 config/services, database/cache translation, helpers, toolbar and Spark |

The core requires PHP ^8.5, ORM ^3.7, DBAL ^4.5, Symfony Cache/Console ^8 and
DoctrineExtensions ^1.5. The adapter additionally requires CodeIgniter ^4.7.

## Standalone quick start

After [installing the core](installation.md#install-from-this-checkout):

```php
require __DIR__ . '/vendor/autoload.php';

$config = new \Daycry\Doctrine\Configuration();
$config->connection = ['driver' => 'pdo_sqlite', 'memory' => true];
$doctrine = new \Daycry\Doctrine\Doctrine($config);

echo $doctrine->getEm()->getConnection()->fetchOne('SELECT 1');
```

Set existing mapping directories on `$config->entities` before constructing the
manager to use mapped entities. See [standalone usage](standalone.md).

## CodeIgniter quick start

After [installing the adapter](installation.md#codeigniter-setup):

```bash
php spark doctrine:publish
```

```php
$doctrine = service('doctrine');
$em = $doctrine->getEm();
```

Configure connections in `Config\Database`, caches in `Config\Cache` and ORM
overrides in the published `Config\Doctrine`. See [CI4 usage](usage.md).

## Explore the toolkit

<div class="grid cards" markdown>

-   :material-table-search:{ .lg .middle } __DataTables__

    ---

    ORM QueryBuilder input, array request parameters, safe filter operators,
    bounded pages and WindowPage results. Works without an HTTP framework.

    [:octicons-arrow-right-24: Builder API](datatables.md)

-   :material-layers-triple:{ .lg .middle } __Caching__

    ---

    Inject PSR-6 pools into the core or use CI4 backend wiring. Cache-aside and SLC
    support explicit lifetimes, invalidation and optional statistics.

    [:octicons-arrow-right-24: SLC setup](second_level_cache.md)

-   :material-database-multiple:{ .lg .middle } __Named managers__

    ---

    Lazy ManagerRegistry factories in standalone code and case-sensitive database
    group services in the adapter.

    [:octicons-arrow-right-24: Standalone managers](standalone.md#named-managers-and-datatables)

-   :material-puzzle:{ .lg .middle } __Configuration__

    ---

    Attributes/XML, custom types and DQL functions, filters, listeners/subscribers,
    DBAL middlewares and repository classes.

    [:octicons-arrow-right-24: Option reference](configuration.md)

-   :material-bug-check:{ .lg .middle } __Logging and toolbar__

    ---

    PSR-3 query logging with a slow-query threshold in the core. The optional CI4
    toolbar displays debug queries and SLC counters.

    [:octicons-arrow-right-24: CI4 toolbar](debug_toolbar.md)

-   :material-console:{ .lg .middle } __Operational tools__

    ---

    Standalone Symfony Console, CI4 Spark, readiness diagnostics and metadata
    warmup. Schema operations use the native ORM commands.

    [:octicons-arrow-right-24: Console guide](cli_commands.md)

</div>
