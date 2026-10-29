# Installation

The 6.x major separates the standalone core from the CodeIgniter integration.
These instructions cover version 6.0. Local path version overrides below are
Composer aliases for testing.

## Choose a package

| Package | Use | Runtime requirements |
|---|---|---|
| `daycry/doctrine` | Plain PHP or integration into another framework | PHP ^8.5, ORM ^3.7, DBAL ^4.5, Symfony Cache/Console ^8 |
| `daycry/doctrine-codeigniter4` | CodeIgniter services, config, cache backends, toolbar and Spark | Core ^6.0 and CI4 ^4.7 |

Composer installs the remaining dependencies, including DoctrineExtensions and
JSON DQL functions. The core does not require or boot CodeIgniter at runtime.
Redis and Memcached extensions are needed only when using their native backends.
Enable the PHP extension for the selected DBAL driver.

## Install tagged releases

For the standalone core:

```bash
composer require daycry/doctrine:^6.0
```

If the adapter has not yet been indexed by Packagist, add its GitHub repository
before requiring it:

```bash
composer config repositories.doctrine-codeigniter4 vcs https://github.com/daycry/doctrine-codeigniter4
composer require daycry/doctrine-codeigniter4:^6.0
```

The adapter's `v6.0.0` tag must exist and the core release must be available.
Once Packagist indexes the adapter, the repository override can be removed.

## Install from this checkout

In the consuming application's `composer.json`, add path repositories pointing
to the checkout. Adjust the paths to your directory layout:

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

For standalone use, only the first repository is needed:

```bash
composer require daycry/doctrine:^6.0
```

For CI4, keep both repositories and install the adapter:

```bash
composer require daycry/doctrine-codeigniter4:^6.0
```

The adapter installs the core and framework itself.
Path installations depend on the checkout remaining available; use released
packages or prepared distributions for deployment.

## Standalone bootstrap

Load Composer and construct the core with explicit configuration. No Spark
command, framework bootstrap or application constants are needed:

```php
require __DIR__ . '/vendor/autoload.php';

$config = new \Daycry\Doctrine\Configuration();
$config->connection = ['driver' => 'pdo_sqlite', 'memory' => true];
$doctrine = new \Daycry\Doctrine\Doctrine($config);

echo $doctrine->getEm()->getConnection()->fetchOne('SELECT 1');
```

For mapped entities, set `$config->entities` to existing mapping directories
before constructing Doctrine. See [standalone usage](standalone.md) for persistent
caches, named managers and a console bootstrap.

## CodeIgniter setup

After installing the adapter:

```bash
php spark doctrine:publish
```

This writes `app/Config/Doctrine.php` and the application-level `cli-config.php`.
The application config extends
`Daycry\Doctrine\CodeIgniter4\Config\Doctrine` and declares only your overrides:

```php
<?php

namespace Config;

class Doctrine extends \Daycry\Doctrine\CodeIgniter4\Config\Doctrine
{
    public array $entities = [APPPATH . 'Models/Entity'];
}
```

Ensure the mapping directory exists. Connections come from `Config\Database` and
caches from `Config\Cache`. See [configuration](configuration.md) and
[CI4 usage](usage.md).

The adapter autoloads the namespaced cache helper through Composer:

```php
use function Daycry\Doctrine\CodeIgniter4\Helpers\getFromCacheOrQuery;
```

The global `doctrine_instance()` helper is separate: load `doctrine_helper` through
CI4's helper mechanism. The core uses `Cache\CacheAside` directly.

## Deployment and upgrades

Commit the consuming application's lockfile and install with PHP 8.5:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

Configure persistent pools before running metadata warmup across processes.
Doctor and warmup do not create or migrate database tables.
Use the [6.x migration guide](migration-major.md) when upgrading existing code.
