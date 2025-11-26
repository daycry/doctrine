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
