# Donation Button

[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

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

## Using DataTables

Usage with [doctrine/orm](https://github.com/doctrine/doctrine2):
-----
```php

$datatables = ( new \Daycry\Doctrine\DataTables\Builder() )
            ->withColumnAliases(
                [
                    'id' => 'qlu.id'
                ]
            )
            ->withIndexColumn( 'qlu.id' )
            ->withQueryBuilder(
                $this->doctrine->em->createQueryBuilder()
                    ->select( 'qlu.param, q.param, q.param, qs.id as param, qlu.param, qlu.param' )
                    ->from( \App\Models\Entity\Class::class, 'qlu' )
                    ->innerJoin( \App\Models\Entity\Class::class, 'qs', \Doctrine\ORM\Query\Expr\Join::WITH, 'qs.id = qlu.*' )
                    ->innerJoin( \App\Models\Entity\Class::class, 'ql', \Doctrine\ORM\Query\Expr\Join::WITH, 'ql.id = qlu.*' )
                    ->innerJoin( \App\Models\Entity\Class::class, 'q', \Doctrine\ORM\Query\Expr\Join::WITH, 'q.id = ql.*' )
            )
            ->withRequestParams( $this->request->getGet( null ) );
        
        $response = $datatables->getResponse();

        echo \json_encode( $response );

```

If you receive an error: **Not all identifier properties can be found in the ResultSetMapping** you can use:

```php

    ->setUseOutputWalkers( false )
```
## Example

```php

$datatables = ( new \Daycry\Doctrine\DataTables\Builder() )
            ->withColumnAliases(
                [
                    'id' => 'qlu.id'
                ]
            )
            ->withIndexColumn( 'qlu.id' )
            ->setUseOutputWalkers( false )
            ->withQueryBuilder(
                $this->doctrine->em->createQueryBuilder()
                    ->select( 'qlu.param, q.param, q.param, qs.id as param, qlu.param, qlu.param' )
                    ->from( \App\Models\Entity\Class::class, 'qlu' )
                    ->innerJoin( \App\Models\Entity\Class::class, 'qs', \Doctrine\ORM\Query\Expr\Join::WITH, 'qs.id = qlu.*' )
                    ->innerJoin( \App\Models\Entity\Class::class, 'ql', \Doctrine\ORM\Query\Expr\Join::WITH, 'ql.id = qlu.*' )
                    ->innerJoin( \App\Models\Entity\Class::class, 'q', \Doctrine\ORM\Query\Expr\Join::WITH, 'q.id = ql.*' )
            )
            ->withRequestParams( $this->request->getGet( null ) );
        
        $response = $datatables->getResponse();

        echo \json_encode( $response );

```
