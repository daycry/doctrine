[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# Daycry Doctrine

Standalone Doctrine ORM toolkit for PHP 8.5, with an optional CodeIgniter 4 adapter.

[![PHPUnit](https://github.com/daycry/doctrine/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/daycry/doctrine/actions/workflows/phpunit.yml)
[![PHPStan](https://github.com/daycry/doctrine/actions/workflows/phpstan.yml/badge.svg?branch=master)](https://github.com/daycry/doctrine/actions/workflows/phpstan.yml)
[![Psalm](https://github.com/daycry/doctrine/actions/workflows/psalm.yml/badge.svg?branch=master)](https://github.com/daycry/doctrine/actions/workflows/psalm.yml)
[![Rector](https://github.com/daycry/doctrine/actions/workflows/rector.yml/badge.svg?branch=master)](https://github.com/daycry/doctrine/actions/workflows/rector.yml)
[![Code Style](https://github.com/daycry/doctrine/actions/workflows/cs.yml/badge.svg?branch=master)](https://github.com/daycry/doctrine/actions/workflows/cs.yml)
[![Coverage Status](https://coveralls.io/repos/github/daycry/doctrine/badge.svg?branch=master)](https://coveralls.io/github/daycry/doctrine?branch=master)
[![Downloads](https://poser.pugx.org/daycry/doctrine/downloads)](https://packagist.org/packages/daycry/doctrine)
[![Monthly Downloads](https://img.shields.io/packagist/dm/daycry/doctrine)](https://packagist.org/packages/daycry/doctrine)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/doctrine)](https://packagist.org/packages/daycry/doctrine)
[![GitHub stars](https://img.shields.io/github/stars/daycry/doctrine)](https://packagist.org/packages/daycry/doctrine)
[![GitHub license](https://img.shields.io/github/license/daycry/doctrine)](https://github.com/daycry/doctrine/blob/master/LICENSE.md)
[![Documentation](https://img.shields.io/badge/docs-daycry.github.io%2Fdoctrine-blue?logo=materialformkdocs&logoColor=white)](https://daycry.github.io/doctrine/)

📖 **Documentation:** <https://daycry.github.io/doctrine/>

## Packages and release status

This documentation describes **version 6.0**, with breaking API and namespace
changes. See the [migration guide](docs/migration-major.md) before upgrading.
Published package badges can refer to an earlier release.

| Package | Role |
|---|---|
| `daycry/doctrine` | Standalone core: ORM configuration, DataTables, PSR-6 caching, PSR-3 logging, named managers and console tools |
| `daycry/doctrine-codeigniter4` | Optional adapter: CI4 configuration/services, database groups, cache backends, helpers, toolbar and Spark |

The core has no CodeIgniter runtime dependency. Both packages require PHP **^8.5**;
the core uses ORM **^3.7**, DBAL **^4.5**, Symfony Cache/Console **^8** and
DoctrineExtensions **^1.5**. The adapter also requires CI4 **^4.7**.

## Installation

Choose one package from Packagist:

```bash
# Standalone core
composer require daycry/doctrine:^6.0

# CodeIgniter application: installs the core and framework too
composer require daycry/doctrine-codeigniter4:^6.0
```

Local path repositories are also available for development.

## Standalone quick start

```php
require __DIR__ . '/vendor/autoload.php';

use Daycry\Doctrine\Configuration;
use Daycry\Doctrine\Doctrine;

$config = new Configuration();
$config->connection = ['driver' => 'pdo_sqlite', 'memory' => true];
$doctrine = new Doctrine($config);

echo $doctrine->getEm()->getConnection()->fetchOne('SELECT 1');
```

For mapped entities, assign existing directories to `$config->entities` before
constructing Doctrine. Native lazy objects are always enabled; no proxy files are
generated. See [standalone usage](docs/standalone.md) for persistent cache pools,
logging, named managers and lifecycle handling.

## CodeIgniter quick start

```bash
php spark doctrine:publish
```

The publisher writes `app/Config/Doctrine.php` and `cli-config.php`. The config
extends `Daycry\Doctrine\CodeIgniter4\Config\Doctrine` and contains application
overrides. Configure connections through `Config\Database` and caches through
`Config\Cache`.

```php
$doctrine = service('doctrine');
$em = $doctrine->getEm();

// An explicitly named database group:
$reporting = \Daycry\Doctrine\CodeIgniter4\Config\Services::doctrine(true, 'reporting');
```

See [CI4 usage](docs/usage.md) for shared services, helpers and group resets, and
[debug toolbar](docs/debug_toolbar.md) for collector/filter registration.

## DataTables

The Builder works in either package and accepts an ORM QueryBuilder and a request
array. It does not require an HTTP framework:

```php
use Daycry\Doctrine\DataTables\Builder;

$response = Builder::create()
    ->withQueryBuilder($queryBuilder)
    ->withColumnAliases(['id' => 'p.id', 'name' => 'p.name'])
    ->withSearchableColumns(['p.name'])
    ->withRequestParams($requestParams)
    ->getResponse();
```

The query must use the `p` alias in this example. The response contains `draw`,
`recordsTotal`, `recordsFiltered` and `data`. The default page cap is **200**;
`length=-1` is clamped to it, and the cap must remain positive.
Use `getPage()` for a WindowPage with items and filtered total.
See [Builder API](docs/datatables.md) and [search operators](docs/search_modes.md).

## Caching, logging and extension points

- Inject `CachePools` into the core for query/result/metadata/SLC storage; defaults
  are independent process-local ArrayAdapters. The CI4 adapter creates pools from
  its framework backend.
- Use `Cache\CacheAside` directly in standalone code. The adapter also autoloads the
  namespaced `Daycry\Doctrine\CodeIgniter4\Helpers\getFromCacheOrQuery` function.
- Enable SLC, statistics, custom DBAL types, SQL filters, event listeners/subscribers,
  DBAL middlewares and a default repository through [configuration](docs/configuration.md).
- Query logging accepts a PSR-3 logger and a slow-query threshold. The core requires
  an injected logger; the adapter resolves CI4's logger service.

See [SLC configuration](docs/second_level_cache.md) and
[cache statistics](docs/second_level_cache_stats.md).

## Console

The standalone console loads an application bootstrap returning Doctrine or a
ManagerRegistry. Spark commands use the CI4 adapter:

```bash
php vendor/bin/daycry-doctrine doctrine-bootstrap.php orm:info
php vendor/bin/daycry-doctrine doctrine-bootstrap.php doctrine:doctor --json
php vendor/bin/daycry-doctrine doctrine-bootstrap.php doctrine:cache:warmup

php spark doctrine:doctor --group reporting --json
php spark doctrine:cache:warmup --group reporting
```

Doctor and warmup do not change the schema. They report connection/mapping/cache
failures through nonzero exit codes. The [console guide](docs/cli_commands.md)
explains the bootstrap, named managers and native ORM commands.

## Development

```bash
composer install
composer test
php vendor/bin/phpunit -c phpunit.standalone.xml.dist --no-coverage
composer analyze
composer cs
python -m mkdocs build --strict --site-dir build/docs
```

The integration suite needs its configured test database and optional cache
services. The native core suite uses SQLite and a separate bootstrap.
CI also installs isolated consumers to verify the core without CI4 installed and
the adapter's framework discovery. See [validation records](docs/maintenance.md).

## Documentation

- [Installation](docs/installation.md)
- [Standalone core](docs/standalone.md)
- [Configuration](docs/configuration.md)
- [CI4 services and helpers](docs/usage.md)
- [DataTables](docs/datatables.md)
- [Console commands](docs/cli_commands.md)
- [6.x migration](docs/migration-major.md)
- [Changelog](CHANGELOG.md)

## License

[MIT](LICENSE.md).
