# Usage

## Key Concepts

- **Service:** `\Config\Services::doctrine($getShared = true, ?string $dbGroup = null)` returns a shared `Daycry\Doctrine\Doctrine` keyed by the (lower-cased) DB group name.
- **Helper:** `doctrine_instance(?string $dbGroup = null)` is a convenience wrapper around the service for use with the `doctrine_helper`.
- **EntityManager access:** `$doctrine->em` (direct property) or `$doctrine->getEm()` (throws if not initialised).
- **Multi-DB:** every `Config\Database` group resolves to its own cached Doctrine instance — see [Multi-Database Groups](#multi-database-groups).

## Loading the Library

```php
$doctrine = new \Daycry\Doctrine\Doctrine();
$user     = $doctrine->em->getRepository(\App\Models\Entity\User::class)->find(1);
```

## As a Service

```php
$doctrine = \Config\Services::doctrine();
$user     = $doctrine->em->getRepository(\App\Models\Entity\User::class)->find(1);
```

## As a Helper

In your `BaseController` `$helpers`:

```php
protected $helpers = ['doctrine_helper'];
```

Then in any method:

```php
$doctrine = doctrine_instance();              // default DB group
$reporting = doctrine_instance('reporting');  // alternate DB group
```

## Accessing the EntityManager

The Doctrine wrapper exposes the underlying `EntityManagerInterface` two ways:

```php
$em = $doctrine->em;       // public property — nullable until the constructor finishes
$em = $doctrine->getEm();  // throws RuntimeException if the EM is not initialised
```

Prefer `getEm()` in code paths where a `null` EntityManager would be a bug
(eg. controllers / jobs); use the property for lightweight access in places
where the constructor has clearly already run.

## Reopening the Connection

Long-running CLI workers and queue consumers occasionally end up with a
closed `EntityManager` after a swallowed exception. Use:

```php
$doctrine->reOpen();
```

`reOpen()` rebuilds the `EntityManager` from the existing DBAL connection and
re-applies every entry in `customTypeMappings`. It throws if `getEm()` is
`null`.

## Multi-Database Groups

`Config\Database` may declare several database groups (`default`, `reporting`,
`legacy`, …). Each group resolves to its own cached Doctrine instance:

```php
$default   = \Config\Services::doctrine();                 // 'default'
$reporting = \Config\Services::doctrine(true, 'reporting');

// Helper form:
$reporting = doctrine_instance('reporting');
```

Group names are case-insensitive and stored in the singleton cache as
`doctrine_<lowercase_group>`. Pass `false` for `$getShared` to force a fresh
instance:

```php
$fresh = \Config\Services::doctrine(false, 'reporting'); // new every time
```

To drop a cached instance (eg. between tests):

```php
\Config\Services::resetDoctrine();             // drops 'doctrine_default'
\Config\Services::resetDoctrine('reporting');  // drops 'doctrine_reporting'
```

## Manual Result Caching

The library autoloads a global cache-aside helper backed by Doctrine's
`resultsCache` PSR-6 pool:

```php
use function Daycry\Doctrine\Helpers\getFromCacheOrQuery;

$rows = getFromCacheOrQuery(
    cacheKey: 'reports_daily',
    ttl: 600,
    queryFn: fn () => $em->createQuery(/* DQL */)->getArrayResult(),
);
```

Signature:

```php
function getFromCacheOrQuery(
    string $cacheKey,
    int $ttl,
    callable $queryFn,
    ?string $dbGroup = null,
): mixed
```

- Looks up `$cacheKey` in `Config\Doctrine` `resultsCache` pool (`Config\Cache`
  backend by default).
- Cache miss → runs the closure, stores the value with `$ttl` seconds expiry
  (`0` = no expiration), returns it.
- Cache disabled (`Config\Doctrine::$resultsCache = false`) → always runs the
  closure, returns its value, stores nothing.
- `$dbGroup` selects the underlying Doctrine instance. Useful for
  multi-tenant or read-replica setups:

  ```php
  $rows = getFromCacheOrQuery(
      cacheKey: 'reports_daily',
      ttl: 600,
      queryFn: fn () => $em->createQuery(/* DQL */)->getArrayResult(),
      dbGroup: 'reporting',
  );
  ```

PSR-6 reserves `{}()/\@:` in cache keys — avoid them.

## Second-Level Cache Statistics

When `Config\Doctrine::$secondLevelCacheStatistics = true`, the wrapper
exposes Doctrine's `StatisticsCacheLogger`:

```php
$logger = \Config\Services::doctrine()->getSecondLevelCacheLogger();
// ?Doctrine\ORM\Cache\Logging\StatisticsCacheLogger

if ($logger !== null) {
    $hits   = $logger->getHitCount();
    $misses = $logger->getMissCount();
    $puts   = $logger->getPutCount();
}
```

Reset the counters at the start of a request (eg. to read per-request hit
ratios in the toolbar):

```php
\Config\Services::doctrine()->resetSecondLevelCacheStatistics();
```

The package also ships a filter that performs this reset automatically — see
[`debug_toolbar.md`](debug_toolbar.md#resetting-stats-per-request) and
[`second_level_cache_stats.md`](second_level_cache_stats.md).

## Notes

- If a cache adapter is misconfigured (missing extension, unreachable Redis
  server, etc.) the Doctrine service raises `CacheException` at construction
  with a descriptive message and the underlying error chained as `previous`.
- See `src/Doctrine.php` for runtime checks, cache wiring, and SLC setup.
