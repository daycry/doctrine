# Doctrine for CodeIgniter 4

CodeIgniter 4 adapter for the standalone `daycry/doctrine` core.

Requires PHP 8.5, CI4 4.7+ and daycry/doctrine 6.x.

Install from [Packagist](https://packagist.org/packages/daycry/doctrine-codeigniter4):

```bash
composer require daycry/doctrine-codeigniter4:^6.0
php spark doctrine:publish
```

For local development, use the
[installation guide](https://daycry.github.io/doctrine/installation/).

The adapter provides framework configuration/services, group-specific database and
cache factories, Redis/Memcached integration, toolbar collectors, SLC reset filters,
helpers and the Spark commands. The core provides ORM setup, pagination, logging
and operational tools.

Application configuration extends `Daycry\Doctrine\CodeIgniter4\Config\Doctrine`.
The publisher generates a minimal subclass in `app/Config/Doctrine.php`:

```php
<?php

namespace Config;

class Doctrine extends \Daycry\Doctrine\CodeIgniter4\Config\Doctrine
{
    public array $entities = [APPPATH . 'Models/Entity'];
}
```

Ensure the mapping directory exists. Database connections come from `Config\Database`
and cache pools from `Config\Cache`.

The explicit service class is `Daycry\Doctrine\CodeIgniter4\Config\Services`.

```php
use Daycry\Doctrine\CodeIgniter4\Config\Services;

$doctrine = service('doctrine');
$em = $doctrine->getEm();
$reporting = Services::doctrine(true, 'reporting');
```

Group names are case-sensitive. Implicit calls use `tests` under CI4's testing
environment and the framework default database group elsewhere.

The cache-aside helper is
`Daycry\Doctrine\CodeIgniter4\Helpers\getFromCacheOrQuery`, autoloaded by Composer
and imported with `use function`. The global `doctrine_instance()` helper is
separate and requires loading `doctrine_helper` in CI4.

## Operational commands

```bash
php spark doctrine:doctor --group reporting --json
php spark doctrine:cache:warmup --group reporting
php spark doctrine:cache:clear --group reporting
```

Doctor and metadata warmup do not modify the schema. Cache clearing affects
query/result/metadata pools, not SLC regions. Native lazy objects require no
generated proxy files.

## Upgrading

Update application config inheritance, package class imports, toolbar collectors,
filters and cache-helper imports to the `Daycry\Doctrine\CodeIgniter4` namespace.
Republish `cli-config.php` after updating its application customizations.
The former namespaces and constructor signatures have no compatibility aliases.

See [documentation](https://daycry.github.io/doctrine/) and the
[major-version migration guide](https://daycry.github.io/doctrine/migration-major/).
