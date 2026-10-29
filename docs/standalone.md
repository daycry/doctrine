# Standalone core and CodeIgniter adapter

Version 6.0 provides two Composer packages maintained in one source repository:

| Package | Responsibility |
|---|---|
| `daycry/doctrine` | Standalone ORM setup, DataTables, PSR-6 cache-aside, PSR-3 logging, named managers, diagnostics and Symfony Console. |
| `daycry/doctrine-codeigniter4` | CI4 configuration and services, database/cache translation, toolbar, filters, helpers and Spark commands. |

The core has no CodeIgniter runtime dependency, framework globals or service lookup.
Its development requirements include CI4 so this repository can test the adapter.
Consumers do not inherit those development requirements.

## Installation

The [installation guide](installation.md) covers tagged releases, VCS installation,
package selection and deployment.
For local use, configure Composer path repositories for the checkout and adapter:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../doctrine",
            "options": {"versions": {"daycry/doctrine": "6.0.0"}}
        },
        {
            "type": "path",
            "url": "../doctrine/packages/codeigniter4",
            "options": {"versions": {"daycry/doctrine-codeigniter4": "6.0.0"}}
        }
    ]
}
```

The version overrides are local testing aliases, not published releases. Install
`daycry/doctrine:^6.0` for standalone use or `daycry/doctrine-codeigniter4:^6.0` for
CI4. The adapter pulls in the core and framework itself.

## Explicit configuration

```php
use Daycry\Doctrine\Configuration;
use Daycry\Doctrine\Doctrine;

$password = getenv('DB_PASSWORD');
if ($password === false) {
    throw new RuntimeException('Set DB_PASSWORD in the application environment.');
}
$config = new Configuration();
$config->connection = ['driver' => 'pdo_mysql', 'host' => '127.0.0.1',
    'dbname' => 'app', 'user' => 'app', 'password' => $password];
$config->entities = [__DIR__ . '/src/Entity'];
$doctrine = new Doctrine($config);
$em = $doctrine->getEm();
```

Connection values use DBAL's parameter names directly. Attributes and XML, custom
types, listeners, subscribers, enabled filters, DQL functions and native lazy
objects remain configurable. The default DQL function registrations target MySQL;
override those arrays when using database-specific functions elsewhere.

## Caches and logging

`CachePools` accepts PSR-6 pools for `query`, `result`, `metadata` and `secondLevel`.
Defaults are four independent, process-local ArrayAdapters. Inject Symfony file,
Redis or Memcached adapters, or other PSR-6 implementations, for persistent storage.
Give persistent pools distinct namespaces, including a manager-specific suffix when
sharing a backend across managers.

```php
use Daycry\Doctrine\Cache\CacheAside;
use Daycry\Doctrine\Cache\CachePools;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

$pools = new CachePools(
    query: new PhpFilesAdapter('app_queries', 3600, __DIR__ . '/var/cache'),
    result: new FilesystemAdapter('app_results', 0, __DIR__ . '/var/cache'),
    metadata: new PhpFilesAdapter('app_metadata', 3600, __DIR__ . '/var/cache'),
);
$doctrine = new Doctrine($config, $pools);
$value = new CacheAside($pools->result)->get('dashboard:total', 60, fn () => $repository->count([]));
```

For `CacheAside`, a positive TTL expires the entry explicitly. Zero uses the pool's
default lifetime; configure that default as zero for storage without expiry.
Negative TTLs raise InvalidArgumentException. Passing a null pool runs the callback
without caching. The adapter's namespaced helper delegates to this same class and
uses its configured result pool.
READ_WRITE SLC requires an explicit `secondLevelCacheLockPath` shared by processes
that share regions.

To enable query logging, set `queryLogging = true` and pass a PSR-3 logger as the
`logger` constructor argument. The core does not look up a framework logger.

### Redis and Memcached result pools

Both backends can be supplied directly without CI4. Load the corresponding PHP
extension and configure the client for the application's server:

```php
use Daycry\Doctrine\Cache\CachePools;
use Symfony\Component\Cache\Adapter\RedisAdapter;

$client = new \Redis();
$client->connect('127.0.0.1', 6379);
$pools = new CachePools(result: new RedisAdapter($client, 'app_results', 0));
```

Or, with Memcached:

```php
use Daycry\Doctrine\Cache\CachePools;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

$client = new \Memcached();
$client->addServer('127.0.0.1', 11211);
$pools = new CachePools(result: new MemcachedAdapter($client, 'app_results', 0));
```

Pass `$pools` to the Doctrine constructor. These examples replace only the result
pool; query/metadata/SLC pools keep their ArrayAdapter defaults. Reuse a client when
constructing additional pools, assigning a distinct namespace to each. Configure
authentication and connection options in the application before constructing pools.

## Named managers and DataTables

`ManagerRegistry` accepts named lazy factories and an explicit default name:

```php
use Daycry\Doctrine\ManagerRegistry;

$registry = new ManagerRegistry([
    'default' => fn () => new Doctrine($primaryConfig),
    'reporting' => fn () => new Doctrine($reportingConfig),
]);
$reporting = $registry->get('reporting');
$registry->reset('reporting'); // closes that manager/connection; next get recreates it
```

Names are case-sensitive. Each factory should return a fresh instance. DataTables
continues to accept an ORM QueryBuilder and a request array; no HTTP framework
object is required. See [DataTables](datatables.md).

## Standalone console

Create `doctrine-bootstrap.php` returning a Doctrine instance or ManagerRegistry:

```bash
php vendor/bin/daycry-doctrine doctrine-bootstrap.php orm:info
php vendor/bin/daycry-doctrine doctrine-bootstrap.php doctrine:doctor --json
php vendor/bin/daycry-doctrine doctrine-bootstrap.php doctrine:cache:warmup --em reporting
```

The console includes Doctrine's native ORM commands. Doctor and warmup do not
modify the schema. The corresponding programmatic APIs are `Tools\Diagnostics`
and `Tools\CacheWarmer`.

## CI4 integration and migration

Install the adapter, publish with `php spark doctrine:publish`, and keep using
`service('doctrine')`, framework cache backends and Spark. Application config now
extends `Daycry\Doctrine\CodeIgniter4\Config\Doctrine`. Package-specific CI4 classes
and helpers moved under `Daycry\Doctrine\CodeIgniter4`. See the
[migration guide](migration-major.md); no aliases for previous namespaces are provided.

## Development and release

Run the existing integration suite with `composer test` and the independent core
suite with `php vendor/bin/phpunit -c phpunit.standalone.xml.dist`.
`scripts/prepare-isolation.php core` prepares a consumer that installs no CI4;
the standalone CI workflow runs its tests with current and minimum dependencies.
The adapter consumer verifies installation, framework boot, SQLite and Spark discovery.

The root Composer archive excludes `packages/`, `build/` and `tests/`. The adapter has
its own composer.json, README and license. Its VCS distribution is
[daycry/doctrine-codeigniter4](https://github.com/daycry/doctrine-codeigniter4),
produced from `packages/codeigniter4` using `git subtree split`. See the
[release procedure](releasing.md) for coordinated tags and Packagist registration.
