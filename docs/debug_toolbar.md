## Viewing Doctrine Queries in the Debug Toolbar

This feature shows all SQL queries executed by Doctrine in the CodeIgniter 4 Debug Toolbar.

### Key Concepts
- Collector: `DoctrineCollector` renders queries in the toolbar.
- Middleware: `DoctrineQueryMiddleware` captures DBAL queries automatically.
- Activation: enabled when you instantiate `\Daycry\Doctrine\Doctrine`.
- Optional SLC stats: when `Config\\Doctrine::$secondLevelCacheStatistics = true`, the toolbar shows Second-Level Cache counters.
    - You can reset counters per-request via `\Config\Services::doctrine()->resetSecondLevelCacheStatistics()`.

### Setup
1. Register the collector in `app/Config/Toolbar.php`:
   ```php
   public $collectors = [
       // ... other collectors ...
       \Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector::class,
   ];
   ```
2. Instantiate Doctrine (service, helper, or direct). Middleware auto-registers.
3. Optional: Reset SLC counters per request in development by adding the filter in `app/Config/Filters.php`:

   ```php
   public array $globals = [
       'before' => [
           // ... other filters ...
           \Daycry\Doctrine\Debug\Filters\DoctrineSlcReset::class,
       ],
       'after' => [],
   ];
   ```

### Usage
- Execute any Doctrine queries; a "Doctrine" tab appears showing the captured SQL.
- If SLC statistics are enabled, the tab title includes a badge `SLC:hits/misses/puts (ratio%)` and a small stats table appears above the queries.

### Notes
- No extra method calls are required.
- If the tab is missing, confirm the collector registration and that the toolbar is enabled.
- Works with advanced connections (SQLite3, SSL, custom options) and Doctrine DBAL 4+.
- SQL display includes parameter bindings and timing; hover tooltips provide extra context if enabled.
- Queries executed by DataTables and cache lookups are also collected.
- SLC stats rely on the Doctrine service exposing a statistics logger; if not available, the badge/table are omitted gracefully.
 - Counters can be reset programmatically as shown above to measure hit ratio per request.
 - The SLC stats table uses Debug Toolbar classes for compact layout.
