# Changelog

All notable changes to `daycry/doctrine` are documented in this file.

The format is based on [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Custom DBAL Types** — `Config\Doctrine::$customTypes` registers
  `Doctrine\DBAL\Types\Type` subclasses via `Type::addType()` (idempotent),
  applied on construction and `reOpen()`.
- **SQL Filters** — `Config\Doctrine::$sqlFilters` / `$enabledFilters` register
  and auto-enable Doctrine SQL filters (soft-delete, multi-tenant). Re-enabled
  after `reOpen()`.
- **Event listeners/subscribers** — `Config\Doctrine::$eventListeners` /
  `$eventSubscribers` build an `EventManager` wired into the `EntityManager`
  (and threaded through `reOpen()`).
- **Composable DBAL middlewares** — `Config\Doctrine::$dbalMiddlewares` compose
  user middlewares with the toolbar middleware (applied last/innermost).
- **Default repository class** — `Config\Doctrine::$defaultRepositoryClass`.
- **Production query logging** — `Config\Doctrine::$queryLogging`,
  `$slowQueryThreshold`, `$queryLogLevel` log queries (optionally only slow
  ones) to a PSR-3 logger via `Daycry\Doctrine\Logging\QueryLoggerMiddleware`,
  in any environment (unlike the debug-only toolbar collector).
- `Daycry\Doctrine\DataTables\Builder::withMaxPageLength(int)` caps the page
  size, clamping unbounded `length=-1` ("All") / oversized requests.
- **Spark commands** (group `Doctrine`): `doctrine:cache:clear`,
  `doctrine:validate`, `doctrine:info`, `doctrine:schema:update` — each accepts
  `--group` to target a database group.
- `cli-config.php` exposes a lazy per-group `EntityManagerProvider`, so
  `php cli-config.php orm:* --em=<group>` can target any database group.
- `Daycry\Doctrine\DataTables\Builder::withFetchJoinCollection(bool)` — opt out
  of `fetchJoinCollection` to collapse the page fetch into a single query for
  scalar/single-entity SELECTs.
- `Daycry\Doctrine\DataTables\Builder::withRecordsTotal(int|Closure)` — inject or
  cache the unfiltered total so the COUNT query is skipped across draws.
- `Daycry\Doctrine\Helpers\getFromCacheOrQuery()` cache-aside helper, autoloaded
  as a global function via `composer.json` `autoload.files`. Backed by the
  configured Doctrine `resultsCache` PSR-6 pool. Accepts an optional `$dbGroup`
  for multi-database setups.
- `Daycry\Doctrine\Doctrine::getEm()` returns the underlying `EntityManager`
  and throws `RuntimeException` if it has not been initialised.
- `Daycry\Doctrine\DataTables\Builder::withMaxFilterValues(int)` to cap
  `[IN]` and `[OR]` value lists. Default `500`; throws on `< 1`. Use
  `PHP_INT_MAX` to disable.
- `Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector::setMaxQueries(int)`
  with FIFO discard. Default `1000`; `0` disables the cap.
- `Daycry\Doctrine\Libraries\InitializesNativeClient` trait shared between
  the `Memcached` and `Redis` cache wrappers.
- `tests/ServicesTest.php` — covers `Services::doctrine()` shared/unshared and
  `Services::resetDoctrine()`.
- `tests/GetFromCacheOrQueryTest.php` — covers cache hit, cache miss, TTL = 0,
  and "result cache disabled" code paths.
- `CHANGELOG.md` (this file).
- Documentation revamp: `Requirements` and `Features` sections, multi-DB usage,
  `getEm()` / `reOpen()` examples, SLC stats reset filter, canonical operator
  reference in `docs/search_modes.md`.

### Changed
- Doctrine query/result/metadata/SLC cache namespaces are now suffixed with the
  (non-default) database group, preventing cross-group key collisions when
  multiple groups share one cache backend. The default group is unchanged.
- `Daycry\Doctrine\Doctrine::reOpen()` now closes the existing DBAL connection
  so it is re-established on the next query, actually recovering from a stale /
  dropped connection ("server has gone away") in long-running workers.
- The Debug Toolbar query middleware/collector is only wired when query
  instrumentation is enabled (`CI_DEBUG`), removing per-statement overhead in
  production. Overridable via the protected `shouldInstrumentQueries()` seam.
- `composer.json` — pin `doctrine/dbal ^4` explicitly (the code uses DBAL-4-only
  APIs), remove the unused `jms/serializer-bundle`, and add `suggest` entries for
  `ext-redis`, `ext-memcached` and `doctrine/migrations`.
- `cli-config.php` requires `vendor/autoload.php` via `__DIR__` so it is
  invokable from any working directory.
- `Daycry\Doctrine\DataTables\Builder::parseFilterOperator()` regex now matches
  only documented operators (`!=, ><, >, <, =, %%, %, IN, OR, LIKE`); accidental
  `•` (U+2022) matches are no longer accepted. Unknown bracket prefixes
  (e.g. `[XYZ]term`) still fall back to `LIKE '%term%'` — the prefix is
  stripped before the LIKE is applied, preserving the legacy typo-tolerant
  behaviour.
- `Daycry\Doctrine\DataTables\Builder` — operator `[><]` now throws
  `InvalidArgumentException` if the value count is not exactly 2 (it previously
  failed silently).
- `Daycry\Doctrine\DataTables\Builder` — DataTables `search.regex: true` and
  per-column `regex: true` raise `InvalidArgumentException` **only when the
  matching search value is non-empty**. The flag is tolerated alongside an
  empty value so existing payloads that include `regex` for every column
  keep working. Use bracket-prefix operators for any actual filtering.
- `Daycry\Doctrine\Debug\Filters\DoctrineSlcReset::before()` now actually
  resets SLC statistics by calling
  `Services::doctrine()->resetSecondLevelCacheStatistics()`. Previously it
  was a no-op despite being documented as functional.
- `Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector` memoises both
  the SLC logger lookup and the `getData()` payload, removing duplicate
  service look-ups during a single toolbar render.
- `Daycry\Doctrine\Commands\DoctrinePublish` now exits with status `1` on
  error paths (read failure, missing autoload, prompted cancellation).
- `Daycry\Doctrine\Libraries\Memcached::getInstance()` and
  `Daycry\Doctrine\Libraries\Redis::getInstance()` now return precise types
  (`?\Memcached`, `?\Redis`) instead of `mixed`.
- `rector.php` now targets PHP 8.2 (was 7.4); applies `match` expressions,
  constructor promotion and other 8.x refactors via `LevelSetList::UP_TO_PHP_82`.
- `phpunit.xml.dist` no longer excludes `src/Config/`, `src/Commands/`,
  `src/Cache/` from coverage; only `src/cli-config.php` remains excluded.
- `.php-cs-fixer.dist.php` now also formats `tests/`.
- `tests/_support/Database/Seeds/TestSeeder.php` is idempotent
  (`truncate()` → `insertBatch()`).

### Removed
- Documented operator `[*%]` (it was never implemented in the code; with the
  current regex it silently fell back to `[%]`).
- PHP 8.5 from the `phpunit.yml` matrix until the language reaches GA.
- `Daycry\Doctrine\Doctrine::convertDbConfigPdo()` — no callers, dead code.
- `Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector::debugToolbarDisplayPublic()`
  — test-only escape hatch; tests now exercise the highlighter via
  `display()`.
- Broken doc links to `docs/DATATABLES_FIX.md` and `docs/TEST_COVERAGE.md`.

### Fixed
- `Daycry\Doctrine\Doctrine::convertDbConfig()` now lower-cases only the DSN
  scheme, preserving a case-sensitive SQLite file path (whole-DSN lowercasing
  opened/created the wrong DB file on case-sensitive filesystems).
- `getFromCacheOrQuery()` normalises PSR-6 reserved characters (`{}()/\@:`) in
  the cache key instead of throwing at runtime.
- `Daycry\Doctrine\DataTables\Builder` — the `[OR]` operator now honors
  `withCaseInsensitive(true)`, wrapping the column and placeholders in `lower()`
  like the other operator branches and the global search.
- `Daycry\Doctrine\DataTables\Builder::applyOrdering()` now bounds-checks the
  client-supplied `order[].column` index instead of emitting an "Undefined array
  key" warning on out-of-range values.
- `README.md` / `docs/cli_commands.md` — replaced the `orm:convert-mapping` and
  `orm:generate-entities` examples (removed in Doctrine ORM 3) with the schema
  tooling that ORM 3 actually ships.
- The documented helper `getFromCacheOrQuery()` now actually exists.
- The documented `DoctrineSlcReset` filter now performs its job.
- Doc-code mismatch around the `[*%]` operator in `README.md`.

### Documentation
- Canonical operator reference now lives in
  [`docs/search_modes.md`](docs/search_modes.md). `README.md` and
  `docs/datatables.md` link to it instead of duplicating the table.
- New "Multi-Database Groups" section in [`docs/usage.md`](docs/usage.md).
- New "Memory Management" and "Resetting Stats per Request" sections in
  [`docs/debug_toolbar.md`](docs/debug_toolbar.md).
- `customTypeMappings`, `proxyFactory` and `secondLevelCacheTtl` documented
  in detail in [`docs/configuration.md`](docs/configuration.md).
