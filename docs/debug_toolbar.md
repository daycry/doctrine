# Doctrine Queries in the Debug Toolbar

This page documents the CodeIgniter Debug Toolbar collector that ships with
the package, the in-memory query log limit, the SLC stats badge, and the
per-request reset filter.

## Key Concepts

- **Collector:** `Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector`
  renders the Doctrine tab in the toolbar.
- **Middleware:** `Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineQueryMiddleware`
  is wired into Doctrine's DBAL stack automatically when you instantiate
  `\Daycry\Doctrine\Doctrine`. No manual setup required.
- **SLC stats badge:** appears when
  `Config\Doctrine::$secondLevelCacheStatistics = true`. The badge format is
  `SLC:hits/misses/puts (ratio%)` and a small stats table is rendered above
  the queries.

## Setup

1. Register the collector in `app/Config/Toolbar.php`:

   ```php
   public $collectors = [
       // …other collectors…
       \Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector::class,
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

`reset()` also invalidates the memoised SLC stats payload.

## Resetting Stats per Request

`DoctrineSlcReset` is a CodeIgniter filter that calls
`Services::doctrine()->resetSecondLevelCacheStatistics()` at the start of
every request, so the toolbar shows hit/miss/put counts scoped to the
current request.

Register it in `app/Config/Filters.php`:

```php
public array $globals = [
    'before' => [
        // …other before-filters…
        \Daycry\Doctrine\Debug\Filters\DoctrineSlcReset::class,
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
