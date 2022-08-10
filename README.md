[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# Doctrine

Doctrine for Codeigniter 4

[![Build Status](https://github.com/daycry/doctrine/workflows/PHP%20Tests/badge.svg)](https://github.com/daycry/doctrine/actions?query=workflow%3A%22PHP+Tests%22)
[![Coverage Status](https://coveralls.io/repos/github/daycry/doctrine/badge.svg?branch=master)](https://coveralls.io/github/daycry/doctrine?branch=master)
[![Downloads](https://poser.pugx.org/daycry/doctrine/downloads)](https://packagist.org/packages/daycry/doctrine)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/doctrine)](https://packagist.org/packages/daycry/doctrine)
[![GitHub stars](https://img.shields.io/github/stars/daycry/doctrine)](https://packagist.org/packages/daycry/doctrine)
[![GitHub license](https://img.shields.io/github/license/daycry/doctrine)](https://github.com/daycry/doctrine/blob/master/LICENSE)

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
If you receive the followrin error:
**[Semantical Error] The annotation "@JMS\Serializer\Annotation\ExclusionPolicy" in class App\Models\Entity\Secret was never imported. Did you maybe forget to add a "use" statement for this annotation?**


You must execute the following command

```php
    composer dump-autoload
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

## Search

To search from datatables there are nine different search modes

Mode | Pattern | Desctiption
-------- | ------------- | -----------
**LIKE '…%'** | [*%]searchTerm | This performs a LIKE '…%' search where the start of the search term must match a value in the given column. This can be archived with only providing the search term (because it's default) or by prefixing the search term with "[*%]" ([*%]searchTerm).
**LIKE '%…%'**| [%%]searchTerm | This performs a LIKE '%…%' search where any part the search term must match a value in the given column. This can be archived by prefixing the search term with "[%%]" ([%%]searchTerm).
**Equality** | [=]searchTerm | This performs a = … search. The search term must exactly match a value in the given column. This can be archived by prefixing the search term with "[=]" ([=]searchTerm).
**!= (No Equality)** | [!=]searchTerm | This performs a != … search. The search term must not exactly match a value in the given column. This can be archived by prefixing the search term with "[!=]" ([!=]searchTerm).
**>** (Greater Than) | [>]searchTerm | This performs a > … search. The search term must be smaller than a value in the given column. This can be archived by prefixing the search term with "[>]" ([>]searchTerm).
**<** (Smaller Than) | [<]searchTerm | This performs a < … search. The search term must be greater than a value in the given column. This can be archived by prefixing the search term with "[<]" ([<]searchTerm).
**< (IN)** | [IN]searchTerm,searchTerm,… | This performs an IN(…) search. One of the provided comma-separated search terms must exactly match a value in the given column. This can be archived by prefixing the search terms with "[IN]" ([IN]searchTerm,searchTerm,…).
**< (OR)** | [OR]searchTerm,searchTerm,… | This performs multiple OR-connected LIKE('%…%') searches. One of the provided comma-separated search terms must match a fragment of a value in the given column. This can be archived by prefixing the search terms with "[OR]" ([OR]searchTerm,searchTerm,…).
**\>< (Between)** | [><]searchTerm,searchTerm | This performs a BETWEEN … AND … search. Both search terms must be separated with a comma. This operation can be archived by prefixing the comma-separated search terms with "[><]" ([><]searchTerm,searchTerm).

Prefixes are case-insenstive (IN, in, OR, or). Provided search terms were trimmed.

## Example

```php

public function testDataTableSearchColumnWithOr()
    {
        $doctrine = new \Daycry\Doctrine\Doctrine($this->config);
        $request = \Config\Services::request();

        $datatables = ( new \Daycry\Doctrine\DataTables\Builder() )
            ->withColumnAliases(
                [
                    'id' => 't.id',
                    'name' => 't.name'
                ]
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(\Tests\Support\Models\Entities\Test::class, 't')
            )
            ->withRequestParams(
                array(
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                    'search' => array('value' => '', 'regex' => true ),
                    'columns' => array(
                        array(
                            'data' => 'id',
                            'name' => 'id',
                            'searchable' => true,
                            'orderable' => true,
                            'search' => array('value' => '[OR]1,3', 'regex' => false)
                        ),
                        array(
                            'data' => 'name',
                            'name' => 'name',
                            'searchable' => true,
                            'orderable' => true,
                            'search' => array('value' => '', 'regex' => false)
                        )
                    ),
                    'order' => array( array( 'column' => 0, 'dir' => 'asc') )
                )
            );

        echo $response = json_encode($datatables->getResponse());

    }
```