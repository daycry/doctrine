# Daycry Doctrine documentation

This documentation describes version 6.0: a standalone PHP 8.5
toolkit and an optional CodeIgniter 4 adapter.

The [documentation site](https://daycry.github.io/doctrine/) is built from this
folder and published from `master`.

## Choose your entry point

| Goal | Guide |
|---|---|
| Install the core or the CI4 adapter | [Installation](installation.md) |
| Use Doctrine without a framework | [Standalone core](standalone.md) |
| Configure mappings, caches, filters and logging | [Configuration](configuration.md) |
| Use services, helpers and database groups in CI4 | [CodeIgniter usage](usage.md) |
| Upgrade an existing application | [6.x migration](migration-major.md) |

## Shared functionality

- [DataTables Builder](datatables.md): framework-independent pagination and filtering.
- [Search modes](search_modes.md): supported operators and validation.
- [Second-Level Cache](second_level_cache.md): explicit pools or CI4 backend wiring.
- [SLC statistics](second_level_cache_stats.md): read and reset cache counters.
- [Console commands](cli_commands.md): standalone console, Spark and ORM tools.

## CodeIgniter integration

The adapter supplies configuration discovery, shared services, database/cache
translation, helpers, Spark commands and the [debug toolbar](debug_toolbar.md).
Install it when the application uses CI4; standalone consumers need only the core.

See [maintenance and validation](maintenance.md) for implementation evidence.
See the [release procedure](releasing.md) for package distribution and validation.
