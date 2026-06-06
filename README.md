[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# Doctrine

Doctrine ORM 3 integration for CodeIgniter 4.

[![PHPUnit](https://github.com/daycry/doctrine/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/daycry/doctrine/actions/workflows/phpunit.yml)
[![PHPStan](https://github.com/daycry/doctrine/actions/workflows/phpstan.yml/badge.svg?branch=master)](https://github.com/daycry/doctrine/actions/workflows/phpstan.yml)
[![Psalm](https://github.com/daycry/doctrine/actions/workflows/psalm.yml/badge.svg?branch=master)](https://github.com/daycry/doctrine/actions/workflows/psalm.yml)
[![Rector](https://github.com/daycry/doctrine/actions/workflows/rector.yml/badge.svg?branch=master)](https://github.com/daycry/doctrine/actions/workflows/rector.yml)
[![Code Style](https://github.com/daycry/doctrine/actions/workflows/cs.yml/badge.svg?branch=master)](https://github.com/daycry/doctrine/actions/workflows/cs.yml)
[![Coverage Status](https://coveralls.io/repos/github/daycry/doctrine/badge.svg?branch=master)](https://coveralls.io/github/daycry/doctrine?branch=master)
[![Downloads](https://poser.pugx.org/daycry/doctrine/downloads)](https://packagist.org/packages/daycry/doctrine)
[![Monthly Downloads](https://img.shields.io/packagist/dm/daycry/doctrine)](https://packagist.org/packages/daycry/doctrine)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/doctrine)](https://packagist.org/packages/daycry/doctrine)
[![GitHub stars](https://img.shields.io/github/stars/daycry/doctrine)](https://packagist.org/packages/daycry/doctrine)
[![GitHub license](https://img.shields.io/github/license/daycry/doctrine)](https://github.com/daycry/doctrine/blob/master/LICENSE)

## Features

- ORM integration via `\Daycry\Doctrine\Doctrine` and `\Config\Services::doctrine()`.
- Server-side **DataTables Builder** with safe operator parsing, whitelisted columns, and `[><]` / `[IN]` / `[OR]` validation.
- **CodeIgniter Debug Toolbar** collector with optional Second-Level Cache (SLC) statistics badge.
- **Doctrine Second-Level Cache** wired to the framework cache backend (file, Redis, Memcached, array).
- `getFromCacheOrQuery()` cache-aside helper backed by the configured PSR-6 result cache.
- **Multi-database group support** — get a separate Doctrine instance per `Config\Database` group.
- **Extensible** via config: custom DBAL **Types**, **SQL Filters** (soft-delete / multi-tenant), **event listeners/subscribers**, composable **DBAL middlewares** and a **default repository class**.
- **Production query logging** (PSR-3) with an optional slow-query threshold — independent of the debug toolbar.
- **Spark commands** (`doctrine:cache:clear`, `doctrine:validate`, `doctrine:info`, `doctrine:schema:update`) and a multi-group ORM CLI (`--em=<group>`).

## Requirements

- PHP **≥ 8.2**
- CodeIgniter **^4**
- Doctrine ORM **^3**, DBAL **^4**
- Symfony Cache **^7**

See [`composer.json`](composer.json) for the complete dependency graph.

## Documentation Index

📖 **Full documentation site:** <https://daycry.github.io/doctrine/>

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Usage](docs/usage.md) — service, helper, multi-DB, `getFromCacheOrQuery`, advanced API
- [CLI Commands](docs/cli_commands.md)
- [DataTables Builder](docs/datatables.md)
- [DataTables Search Modes](docs/search_modes.md) — canonical operator reference
- [Debug Toolbar](docs/debug_toolbar.md) — query log + SLC stats + per-request reset filter
- [Second-Level Cache (SLC)](docs/second_level_cache.md)
- [SLC Statistics](docs/second_level_cache_stats.md)
- [Changelog](CHANGELOG.md)

## Installation

```bash
composer require daycry/doctrine
```

Then publish the configuration:

```bash
php spark doctrine:publish
```

This copies `Config/Doctrine.php` into your app namespace and `cli-config.php`
into the project root for use with the Doctrine ORM CLI.

## Quick Start

### As a service

```php
$doctrine = \Config\Services::doctrine();
$user     = $doctrine->em->getRepository(\App\Models\Entity\User::class)->find(1);
```

### As a helper

Add `doctrine_helper` to your `BaseController::$helpers`:

```php
protected $helpers = ['doctrine_helper'];
```

```php
$doctrine  = doctrine_instance();             // default DB group
$reporting = doctrine_instance('reporting');  // alternate DB group
```

### Constructing manually

```php
$doctrine = new \Daycry\Doctrine\Doctrine();
$user     = $doctrine->em->getRepository(\App\Models\Entity\User::class)->find(1);
```

## Manual Result Caching

`getFromCacheOrQuery()` is autoloaded as a global function (no `use` is needed
beyond the function import). It looks up `$cacheKey` in the configured result
cache pool and falls back to the closure on miss.

```php
use function Daycry\Doctrine\Helpers\getFromCacheOrQuery;

$rows = getFromCacheOrQuery(
    cacheKey: 'projects_list_v1',
    ttl: 300,
    queryFn: fn () => $doctrine->em
        ->createQueryBuilder()
        ->select('p')
        ->from(\App\Models\Entity\Project::class, 'p')
        ->getQuery()
        ->getArrayResult(),
);
```

When the result cache is disabled (`Config\Doctrine::$resultsCache = false`)
the closure runs every time. PSR-6 reserved characters (`{}()/\@:`) in the key
are normalised automatically, so any key string is accepted.

See [docs/usage.md](docs/usage.md) for advanced API: `getEm()`, `reOpen()`,
multi-database groups, `Services::resetDoctrine()`, and more.

## Doctrine ORM CLI

Use the generated `cli-config.php` from the project root:

```bash
php cli-config.php orm:validate-schema          # check mappings vs. database
php cli-config.php orm:schema-tool:update --dump-sql   # preview schema changes
php cli-config.php orm:schema-tool:update --force      # apply them
php cli-config.php orm:generate-proxies app/Models/Proxies
php cli-config.php orm:info                      # list mapped entities
php cli-config.php orm:run-dql "SELECT u FROM App\Models\Entity\User u"
```

> Doctrine ORM 3 removed `orm:convert-mapping` and `orm:generate-entities`.
> Reverse-engineering an existing schema into entities is no longer part of the
> ORM toolchain; map your entities with PHP attributes (or XML) directly.

The same commands are also available through Spark — see
[docs/cli_commands.md](docs/cli_commands.md).

## DataTables

```php
$datatables = (new \Daycry\Doctrine\DataTables\Builder())
    ->withColumnAliases([
        'id'   => 'p.id',
        'name' => 'p.name',
    ])
    ->withSearchableColumns(['p.name'])
    ->withCaseInsensitive(true)
    ->withMaxFilterValues(500) // cap [IN] / [OR] value lists; default 500
    ->withMaxPageLength(200)   // cap page size; clamps length=-1 ("All") — default 0 (no cap)
    ->withQueryBuilder(
        $this->doctrine->em->createQueryBuilder()
            ->select('p.id, p.name')
            ->from(\App\Models\Entity\Project::class, 'p'),
    )
    ->withRequestParams($this->request->getGet());

return $this->response->setJSON($datatables->getResponse());
```

If pagination throws *"Not all identifier properties can be found in the
ResultSetMapping"*, set `->setUseOutputWalkers(false)` on the Builder.

### Search modes

The Builder supports bracket-prefixed operators per column:

```
[%]   (LIKE, default)   [=]   [!=]   [>]   [<]   [IN]   [OR]   [><]
```

Synonyms `[LIKE]` and `[%%]` map to `[%]`. Unknown prefixes silently fall
back to `[%]`. The DataTables `regex: true` flag is **not supported** —
sending it raises `InvalidArgumentException`.

See **[`docs/search_modes.md`](docs/search_modes.md)** for the full operator
matrix, validation rules, case-insensitivity behaviour and examples.

## Debug Toolbar

A `DoctrineCollector` automatically captures every DBAL query so you can
inspect them in the CodeIgniter Debug Toolbar.

1. Register the collector in `app/Config/Toolbar.php`:

   ```php
   public $collectors = [
       // ...
       \Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector::class,
   ];
   ```

2. Use Doctrine as usual — the middleware self-registers when you instantiate
   the service.

For long-running CLI workers you can cap the in-memory query log:

```php
\Config\Services::doctrineCollector()->setMaxQueries(500); // FIFO; 0 disables the cap
```

See [docs/debug_toolbar.md](docs/debug_toolbar.md) for the full collector API,
the SLC stats badge, and the per-request reset filter.

## Second-Level Cache (SLC)

Doctrine's Second-Level Cache reuses the framework cache backend
(file / Redis / Memcached / array) and its `ttl`. Enable in
`app/Config/Doctrine.php`:

```php
public bool $secondLevelCache           = true;
public bool $secondLevelCacheStatistics = true;  // optional: hits/misses/puts badge
public ?int $secondLevelCacheTtl        = null;   // null = inherit Config\Cache::$ttl; 0 = no expiry
```

To reset SLC statistics at the start of every request (useful in development
to read per-request hit ratios in the toolbar), register the filter:

```php
// app/Config/Filters.php
public array $globals = [
    'before' => [
        \Daycry\Doctrine\Debug\Filters\DoctrineSlcReset::class,
    ],
];
```

The filter is a no-op unless `secondLevelCacheStatistics` is enabled.

See [docs/second_level_cache.md](docs/second_level_cache.md) and
[docs/second_level_cache_stats.md](docs/second_level_cache_stats.md) for full
details.

## Extending the EntityManager

`Config\Doctrine` exposes additive, backward-compatible hooks (all default to
off) for the common Doctrine extension points:

```php
// app/Config/Doctrine.php
public array  $customTypes            = ['uuid' => \Ramsey\Uuid\Doctrine\UuidType::class];
public array  $sqlFilters             = ['soft_delete' => \App\Doctrine\SoftDeleteFilter::class];
public array  $enabledFilters         = ['soft_delete'];
public array  $eventListeners         = ['onFlush' => [\App\Doctrine\AuditListener::class]];
public array  $eventSubscribers       = [\App\Doctrine\TimestampSubscriber::class];
public array  $dbalMiddlewares        = [\App\Doctrine\RetryMiddleware::class];
public ?string $defaultRepositoryClass = \App\Repositories\BaseRepository::class;

// Production query logging (PSR-3) — independent of the debug toolbar
public bool   $queryLogging        = true;
public float  $slowQueryThreshold  = 0.5;  // log queries slower than 500 ms
public string $queryLogLevel       = 'warning';
```

All are re-applied across `Doctrine::reOpen()`. See
[docs/configuration.md](docs/configuration.md#extension-points) for the full
reference.

## Development

Available Composer scripts for contributors:

```bash
composer test          # PHPUnit test suite
composer phpstan       # PHPStan (level 6)
composer psalm         # Psalm static analysis
composer rector        # Rector dry-run
composer analyze       # phpstan + psalm + rector
composer cs            # PHP-CS-Fixer dry-run
composer cs-fix        # PHP-CS-Fixer apply
```

## License

[MIT](LICENSE.md). Issues and PRs welcome at
<https://github.com/daycry/doctrine>.
