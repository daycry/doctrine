# doctrine
Doctrine for Codeigniter 4

## Installation

Use the package with composer install

```bash
composer require daycry/doctrine
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
