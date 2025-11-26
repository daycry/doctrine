# Installation

## Key Concepts
- Package: `daycry/doctrine` provides Doctrine integration for CodeIgniter 4.
- Autoload: When installing manually, register the `Daycry\Doctrine` namespace in `app/Config/Autoload.php`.

## Via Composer

Use Composer to install the package:

```
composer require daycry/doctrine
```

## Manual Installation

Download this repository and enable it by editing `app/Config/Autoload.php` and adding the `Daycry\Doctrine` namespace to the `$psr4` array. For example, if you copied it into `app/ThirdParty`:

```php
$psr4 = [
    'Config'      => APPPATH . 'Config',
    APP_NAMESPACE => APPPATH,
    'App'         => APPPATH,
    'Daycry\Doctrine' => APPPATH .'ThirdParty/doctrine/src',
];
```

## Notes
- Prefer Composer installation to ensure dependencies and autoloading are managed correctly.
- After installation, run `php spark doctrine:publish` to publish configuration files.

## Debug Toolbar Integration

To view Doctrine queries in the CodeIgniter Debug Toolbar:

1. Register the collector in `app/Config/Toolbar.php`:
   ```php
   public $collectors = [
       // ... other collectors ...
       \Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector::class,
   ];
   ```
2. Instantiate Doctrine (service, helper, or direct). Middleware auto-registers.
3. Optional (development): reset Second-Level Cache counters per request by adding the filter in `app/Config/Filters.php`:
   ```php
   public array $globals = [
       'before' => [
           // ... other filters ...
           \Daycry\Doctrine\Debug\Filters\DoctrineSlcReset::class,
       ],
       'after' => [],
   ];
   ```

If you enable `Config\Doctrine::$secondLevelCacheStatistics = true`, the Doctrine panel shows a badge `SLC:hits/misses/puts (ratio%)` and a small statistics table above the queries.
