# Installation

## Key Concepts

- Package: `daycry/doctrine` provides Doctrine ORM 3 integration for CodeIgniter 4.
- Requirements: PHP ≥ 8.2, CodeIgniter ^4, Doctrine ORM ^3, Symfony Cache ^7.
- Autoload: when installing manually, register the `Daycry\Doctrine` namespace in `app/Config/Autoload.php`.

## Via Composer

```bash
composer require daycry/doctrine
```

After installing, publish the configuration files:

```bash
php spark doctrine:publish
```

This generates `app/Config/Doctrine.php` and `cli-config.php` in the project
root. See [`configuration.md`](configuration.md) for the available options.

## Manual Installation

Download this repository and register the namespace in
`app/Config/Autoload.php`. For example, when copied into `app/ThirdParty`:

```php
$psr4 = [
    'Config'          => APPPATH . 'Config',
    APP_NAMESPACE     => APPPATH,
    'App'             => APPPATH,
    'Daycry\Doctrine' => APPPATH . 'ThirdParty/doctrine/src',
];
```

Composer installation is preferred — it keeps dependencies and the
`autoload.files` entry for the helper function in sync (see below).

## Helper Function Autoload

`Daycry\Doctrine\Helpers\getFromCacheOrQuery()` is autoloaded as a global
function via the package's `composer.json` `autoload.files` entry. No
namespace registration is needed; just import it where you use it:

```php
use function Daycry\Doctrine\Helpers\getFromCacheOrQuery;
```

See [`usage.md`](usage.md) for the cache-aside example.

## Notes

- For Redis/Memcached cache backends, the corresponding PHP extensions
  (`ext-redis`, `ext-memcached`) must be installed; the Doctrine service
  raises a descriptive `CacheException` on missing extensions.
- Ensure every path listed in `Config\Doctrine::$entities` exists and is
  readable; misconfigured paths fail fast at construction time.

## Debug Toolbar

The package ships a Debug Toolbar collector and an optional per-request reset
filter for SLC statistics. See [`debug_toolbar.md`](debug_toolbar.md) for the
collector + filter setup steps.
