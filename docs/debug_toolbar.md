# Doctrine Queries in the Debug Toolbar

This page requires the `daycry/doctrine-codeigniter4` adapter. It documents the
CodeIgniter Debug Toolbar collector, the in-memory query log limit, the SLC stats badge, and the
per-request reset filter.

## Key Concepts

- **Collector:** `Daycry\Doctrine\CodeIgniter4\Debug\Toolbar\Collectors\DoctrineCollector`
  renders the Doctrine tab in the toolbar.
- **Middleware:** `Daycry\Doctrine\CodeIgniter4\Debug\Toolbar\Collectors\DoctrineQueryMiddleware`
  is wired into Doctrine's DBAL stack when `CI_DEBUG` is enabled and you instantiate
  `\Daycry\Doctrine\CodeIgniter4\Doctrine`. The collector must still be registered.
- **SLC stats badge:** appears when
  `Config\Doctrine::$secondLevelCacheStatistics = true`. The badge format is
  `SLC:hits/misses/puts (ratio%)` and a small stats table is rendered above
  the queries.

## Setup

1. Register the collector in `app/Config/Toolbar.php`:

   ```php
   public $collectors = [
       // …other collectors…
       \Daycry\Doctrine\CodeIgniter4\Debug\Toolbar\Collectors\DoctrineCollector::class,
   ];
   ```

2. Use Doctrine as usual — no extra method calls. The middleware self-registers
   in `Doctrine::__construct()` and pushes every DBAL query into the
   collector.

3. (Optional) reset SLC counters per request — see
   [Resetting Stats per Request](#resetting-stats-per-request).

## Usage

Run any Doctrine query (repository, DBAL, DataTables Builder, cache lookup …)
and a *Doctrine* tab will appear in the toolbar with:

- a count badge
- per-query SQL, bound parameters and duration
- (when SLC stats are on) an SLC summary table and a `SLC:n/m/p (r%)` badge

CI4 constructs the displayed collector itself. That collector and Doctrine's
middleware share request data through the adapter service registry, so queries
remain visible regardless of which is created first. Reading the toolbar does
not initialize a Doctrine manager. SLC statistics are read from the existing
effective default manager.

## Memory Management

The collector keeps every captured query in memory. For long-running CLI
workers or queue consumers that share a process, cap the buffer:

```php
$collector = \Config\Services::doctrineCollector();
$collector->setMaxQueries(500);  // default 1000; 0 disables the cap
```

When the cap is reached the **oldest** entry is dropped (FIFO) before the
new query is appended.

## Manual Reset (tests)

To clear the buffer programmatically — typically between tests:

```php
$collector = \Config\Services::doctrineCollector();
$collector->reset();
```

`reset()` clears the shared query buffer for all collector instances. CI4's
toolbar reset uses the same operation in worker mode. SLC statistics are read
when rendered; reset their counters separately as described below.

## Resetting Stats per Request

`DoctrineSlcReset` is a CodeIgniter filter that calls
`Services::peekDoctrine()?->resetSecondLevelCacheStatistics()` for the effective
default group at the start of a request. It never initializes a manager just to
reset counters. Additional named groups need explicit lifecycle resets; see
[SLC statistics](second_level_cache_stats.md#codeigniter-toolbar).

Register it in `app/Config/Filters.php`:

```php
public array $globals = [
    'before' => [
        // …other before-filters…
        \Daycry\Doctrine\CodeIgniter4\Debug\Filters\DoctrineSlcReset::class,
    ],
];
```

The filter is a no-op when:

- `Config\Doctrine::$secondLevelCacheStatistics` is `false` (logger absent)
- the Doctrine service has not been instantiated yet for this request

It always returns `null` and never throws — the toolbar must never break the
request pipeline.

## Notes

- The collector is fully compatible with advanced DBAL connections (SQLite3,
  SSL, custom options) and Doctrine DBAL 4+.
- If the *Doctrine* tab is missing, double-check that the collector is
  registered in `Toolbar.php` and that the toolbar is enabled in your
  environment.
- SQL display includes parameter bindings and timing. Long statements are
  shortened with a hover tooltip showing the full SQL.
- Queries executed by DataTables and cache lookups (eg. `getFromCacheOrQuery`
  on a miss) are also collected.
- SLC stats rely on the Doctrine service exposing a `StatisticsCacheLogger`;
  if it is not available the badge and table are omitted gracefully.
