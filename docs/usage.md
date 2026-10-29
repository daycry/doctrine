# CodeIgniter 4 usage

This page requires `daycry/doctrine-codeigniter4`. For PHP without CI4, use
the [standalone guide](standalone.md) and [shared configuration reference](configuration.md).

## Resolve the adapter

```php
$doctrine = service('doctrine');
$em = $doctrine->getEm();
```

The explicit service class is useful for named groups and resets:

```php
use Daycry\Doctrine\CodeIgniter4\Config\Services;

$doctrine = Services::doctrine();
$reporting = Services::doctrine(true, 'reporting');
```

Both forms return `Daycry\Doctrine\CodeIgniter4\Doctrine`, which extends the core
wrapper. To construct an unshared adapter directly:

```php
$doctrine = new \Daycry\Doctrine\CodeIgniter4\Doctrine();
```

It still obtains ORM/cache/database configuration from CI4. The core constructor
instead requires an explicit `Daycry\Doctrine\Configuration`.

## Framework helper

Add the helper to the controller's existing helpers list:

```php
protected $helpers = ['doctrine_helper'];
```

Then:

```php
$doctrine = doctrine_instance();
$reporting = doctrine_instance('reporting');
```

`doctrine_instance()` is a global CI4 helper. It is separate from the namespaced,
Composer-autoloaded cache-aside function.

## Access mapped entities

Configure existing mapping directories in `app/Config/Doctrine.php` first.
Use your application's mapped entity class:

```php
$em = $doctrine->getEm();
$repository = $em->getRepository(\App\Models\Entity\Product::class);
$product = $repository->find(1);
```

`getEm()` returns the EntityManager and raises RuntimeException if the wrapper
does not currently contain one. The public `em` property is also available.

## Multi-database groups

Declare connection groups in `Config\Database`:

```php
use Daycry\Doctrine\CodeIgniter4\Config\Services;

$default = Services::doctrine();
$reporting = Services::doctrine(true, 'reporting');
$freshReporting = Services::doctrine(false, 'reporting');
```

Names are case-sensitive and must identify a configured group. Implicit calls use
`tests` in CI4's testing environment, otherwise `Config\Database::$defaultGroup`.
Implicit and explicit requests for the same effective group share one instance.
Passing `false` creates a fresh instance.

The adapter uses the same ORM options for each group and constructs separate
cache pools with group-specific namespaces. Unknown groups raise ConfigException.

To remove a shared service entry:

```php
Services::resetDoctrine();
Services::resetDoctrine('reporting');
```

The next request creates a new adapter. Do not confuse this with reopening a
manager or with the standalone registry's `reset()`, which explicitly closes its
managed EntityManager and connection.

## Manual result caching

The adapter autoloads this namespaced helper through Composer:

```php
use Daycry\Doctrine\CodeIgniter4\Config\Services;
use function Daycry\Doctrine\CodeIgniter4\Helpers\getFromCacheOrQuery;

$reporting = Services::doctrine(true, 'reporting');
$repository = $reporting->getEm()->getRepository(\App\Models\Entity\Product::class);
$total = getFromCacheOrQuery(
    cacheKey: 'products:total:v1',
    ttl: 300,
    queryFn: fn () => $repository->count([]),
    dbGroup: 'reporting',
);
```

Use the same database group for the query and cache lookup. The group argument
selects the pool; it does not change the EntityManager captured by the callback.

| Condition | Behaviour |
|---|---|
| Cache hit | Return the stored value |
| Cache miss | Execute the callback, save and return its value |
| Positive TTL | Explicit expiry in seconds |
| TTL zero | Uses the adapter result pool's zero default lifetime |
| Negative TTL | InvalidArgumentException |
| `resultsCache = false` | Execute the callback without caching |

The helper normalizes PSR-6 reserved key characters and appends a short hash when
normalization is needed. Invalidate or version keys according to application data
changes. In standalone code, use `Daycry\Doctrine\Cache\CacheAside` with an explicit
pool; see [standalone caching](standalone.md#caches-and-logging).

## Reopening the connection

For recovery after a closed EntityManager or stale server connection:

```php
$doctrine->reOpen();
$em = $doctrine->getEm();
```

The wrapper closes the DBAL connection and constructs a replacement EntityManager
over that connection with the existing ORM configuration. It reapplies constructor
event listeners/subscribers, types, platform mappings and enabled filters.
The configured cache pools and DBAL middlewares remain in place.

Reapply runtime filter parameters, and reload entities through the replacement.
Objects loaded through the previous manager are not managed by the replacement.
Closing an in-memory SQLite connection discards its database.

## SLC statistics and debugging

Enable `secondLevelCache` and `secondLevelCacheStatistics` in ORM configuration:

```php
$logger = $doctrine->getSecondLevelCacheLogger();
if ($logger !== null) {
    $hits = $logger->getHitCount();
    $misses = $logger->getMissCount();
    $puts = $logger->getPutCount();
}
$doctrine->resetSecondLevelCacheStatistics();
```

The [SLC guide](second_level_cache.md) covers mappings and invalidation.
The [toolbar guide](debug_toolbar.md) explains debug query capture and the
optional per-request statistics filter. Query logging through CI4's PSR-3 logger
is separately configurable and does not require the toolbar.
