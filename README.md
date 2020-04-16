# Doctrine

Doctrine for Codeigniter 4

## Installation via composer

Use the package with composer install

	> composer require daycry/doctrine

## Manual installation

Download this repo and then enable it by editing **app/Config/Autoload.php** and adding the **Daycry\Doctrine**
namespace to the **$psr4** array. For example, if you copied it into **app/ThirdParty**:

```php
$psr4 = [
    'Config'      => APPPATH . 'Config',
    APP_NAMESPACE => APPPATH,
    'App'         => APPPATH,
    'Daycry\Doctrine' => APPPATH .'ThirdParty/doctrine/src',
];
```

## Configuration

Run command:

	> php spark doctrine:publish

This command will copy a config file to your app namespace and "cli-config.php" file for doctrine cli.
Then you can adjust it to your needs. By default file will be present in `app/Config/Doctrine.php`.


## Usage Loading Library

```php
$doctrine = new \Daycry\Doctrine\Doctrine();
$data = $doctrine->em->getRepository( 'App\Models\Entity\Class' )->findOneBy( array( 'id' => 1 ) );
var_dump( $data );

```

## Usage as a Service

```php
$doctrine = \Config\Services::doctrine();
$data = $doctrine->em->getRepository( 'App\Models\Entity\Class' )->findOneBy( array( 'id' => 1 ) );
var_dump( $data );

```

## Usage as a Helper

In your BaseController - $helpers array, add an element with your helper filename.

```php
protected $helpers = [ 'doctrine_helper' ];

```

And then, you can use the helper

```php

$doctrine = doctrine_instance();
$data = $doctrine->em->getRepository( 'App\Models\Entity\Class' )->findOneBy( array( 'id' => 1 ) );
var_dump( $data );

```

## Cli Commands

```php

//Mapping de database to entities classes
vendor/bin/doctrine orm:convert-mapping --namespace="App\Models\Entity\" --force --from-database annotation .

//Generate getters & setters
vendor/bin/doctrine orm:generate-entities .

//Generate proxy classes
vendor/bin/doctrine orm:generate-proxies app/Models/Proxies

```