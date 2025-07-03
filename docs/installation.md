# Installation

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
