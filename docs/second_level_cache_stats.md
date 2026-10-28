# Second-Level Cache statistics

Statistics are part of the shared core API. The CI4 adapter can also display
them in its debug toolbar.

## Enable counters

In standalone code, set these options before constructing Doctrine:

```php
$config->secondLevelCache = true;
$config->secondLevelCacheStatistics = true;
```

In a CI4 application, declare the corresponding overrides in `Config\Doctrine`:

```php
public bool $secondLevelCache = true;
public bool $secondLevelCacheStatistics = true;
```

Entities need cache mappings, and cross-process reuse needs a persistent pool.
See [SLC configuration](second_level_cache.md).

## Read and reset

Given either a core Doctrine instance or an adapter instance:

```php
$logger = $doctrine->getSecondLevelCacheLogger();
if ($logger !== null) {
    $hits = $logger->getHitCount();
    $misses = $logger->getMissCount();
    $puts = $logger->getPutCount();
    $regionHits = $logger->getRegionHitCount('your_region_name');
}

$doctrine->resetSecondLevelCacheStatistics();
```

The getter returns null when no StatisticsCacheLogger is configured. Resetting
counters does not evict entity or collection cache data. Counters belong to the
logger instance; they are not a distributed aggregation of all application workers.
Choose an application or worker lifecycle point at which to reset them.

## CodeIgniter toolbar

Register the [collector](debug_toolbar.md) to display the
`SLC:hits/misses/puts (ratio%)` badge and the counters table.

The optional `DoctrineSlcReset` filter uses `Services::peekDoctrine()` and resets
the effective default group's existing manager. It does not initialize a manager
just to reset its counters. For additional named groups, reset explicitly in your
application's lifecycle:

```php
use Daycry\Doctrine\CodeIgniter4\Config\Services;

Services::peekDoctrine('reporting')?->resetSecondLevelCacheStatistics();
```

Enabling statistics does not automatically register the collector or reset filter.
Use [toolbar setup](debug_toolbar.md#setup) for registration.
