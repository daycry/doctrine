# Usage

## Loading the Library

```php
$doctrine = new \Daycry\Doctrine\Doctrine();
$data = $doctrine->em->getRepository('App\Models\Entity\Class')->findOneBy(['id' => 1]);
var_dump($data);
```

## As a Service

```php
$doctrine = \Config\Services::doctrine();
$data = $doctrine->em->getRepository('App\Models\Entity\Class')->findOneBy(['id' => 1]);
var_dump($data);
```

## As a Helper

In your `BaseController` `$helpers` array, add your helper filename:

```php
protected $helpers = ['doctrine_helper'];
```

Then use the helper:

```php
$doctrine = doctrine_instance();
$data = $doctrine->em->getRepository('App\Models\Entity\Class')->findOneBy(['id' => 1]);
var_dump($data);
```
