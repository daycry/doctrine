# Daycry Doctrine for CodeIgniter 4

Modern integration of Doctrine ORM/DBAL into CodeIgniter 4. Highlights:

- ORM bootstrap via `\Daycry\Doctrine\Doctrine` and `Services::doctrine()`
- Server-side **DataTables Builder** with safe operators and `[><]` / `[IN]` / `[OR]` validation
- **Debug Toolbar** collector with optional SLC stats and a per-request reset filter
- **Second-Level Cache** wired to the framework cache backend
- `getFromCacheOrQuery()` cache-aside helper
- Multi-database group support (`Services::doctrine(true, 'reporting')`)
- Comprehensive PHPUnit, PHPStan, Psalm and Rector coverage

## Documentation Index

- [installation.md](installation.md) — Install, autoload helper, optional Debug Toolbar setup
- [configuration.md](configuration.md) — Publish and configure `Config\Doctrine`
- [usage.md](usage.md) — Service / helper / direct, multi-DB, `getFromCacheOrQuery`, `reOpen()`, SLC reset
- [cli_commands.md](cli_commands.md) — `doctrine:publish` and Doctrine ORM CLI
- [datatables.md](datatables.md) — DataTables Builder API, examples, troubleshooting
- [search_modes.md](search_modes.md) — Canonical reference of per-column operators
- [debug_toolbar.md](debug_toolbar.md) — Query log + SLC badge + memory limits + reset filter
- [second_level_cache.md](second_level_cache.md) — Doctrine SLC configuration
- [second_level_cache_stats.md](second_level_cache_stats.md) — `StatisticsCacheLogger` API

For breaking changes and a chronological list of additions see
[`CHANGELOG.md`](../CHANGELOG.md) at the repository root.

## Quick Start

```php
// As a service
$doctrine = \Config\Services::doctrine();

// As a helper (after adding 'doctrine_helper' to BaseController::$helpers)
$doctrine = doctrine_instance();

$repo = $doctrine->em->getRepository(\App\Models\Entity\WebProjects::class);
$item = $repo->findOneBy(['uuid' => $uuid]);
```

For advanced usage (multi-DB, manual caching, EntityManager re-opening,
Debug Toolbar memory limits) see the documents linked above.
