# doctrine
Doctrine for Codeigniter 4

## Installation

Use the package with composer install

```bash
composer require daycry/doctrine

php vendor/daycry/doctrine/install.php

```

## Usage

```php
$doctrine = new \Daycry\Doctrine\Doctrine();
$data = $doctrine->em->getRepository( 'App\Models\Entity\Class' )->findOneBy( array( 'id' => 1 ) );
var_dump( $data );

```


## Custom Config

```php
<?php 

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Doctrine extends BaseConfig
{
    public $debug = false;

    public $setAutoGenerateProxyClasses = true;

}

```

```php
//app/config/Doctrine
$config = new \Config\Doctrine();
$doctrine = new \Daycry\Doctrine\Doctrine( $config );

```

## Cli Commands

```php

//Mapping de database to entities classes
vendor/bin/doctrine orm:convert-mapping --namespace="App\\Models\\Entity\\" --force --from-database annotation .

//Generate getters & setters
vendor/bin/doctrine orm:generate-entities .

//Generate proxy classes
vendor/bin/doctrine orm:generate-proxies app/Models/Proxies

```