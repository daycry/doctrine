# Doctrine for CodeIgniter 4

CodeIgniter 4 adapter for the standalone `daycry/doctrine` core.

Requires PHP 8.5, CI4 4.7+ and daycry/doctrine 6.x. The 6.x packages are not yet
published; see [local installation and migration](https://daycry.github.io/doctrine/standalone/).

After the coordinated release:

```bash
composer require daycry/doctrine-codeigniter4:^6.0
php spark doctrine:publish
```

The adapter provides framework configuration/services, group-specific database and
cache factories, Redis/Memcached integration, toolbar collectors, SLC reset filters,
helpers and the Spark commands. The core provides ORM setup, pagination, logging
and operational tools.

Application configuration extends `Daycry\Doctrine\CodeIgniter4\Config\Doctrine`.
The explicit service class is `Daycry\Doctrine\CodeIgniter4\Config\Services`.
The cache-aside helper is
`Daycry\Doctrine\CodeIgniter4\Helpers\getFromCacheOrQuery`.

See [documentation](https://daycry.github.io/doctrine/) and the
[major-version migration guide](https://daycry.github.io/doctrine/migration-major/).
