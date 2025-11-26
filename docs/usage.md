# Usage

## Key Concepts
- Service: `\Config\Services::doctrine()` returns the `Daycry\Doctrine\Doctrine` instance.
- Helper: `doctrine_instance()` convenience accessor when added to `BaseController` helpers.
- EntityManager: available at `$doctrine->em` for repositories, queries, and SchemaTool.

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

## Manual Result Caching with Doctrine

You can manually cache query results using the provided helper. This works for Doctrine 3.x/4.x and integrates with configured cache adapters (file, Redis, Memcached, array).

```php
use function Daycry\Doctrine\Helpers\getFromCacheOrQuery;

$cacheKey = 'webprojects:list:v1';
$ttl      = 300; // seconds

$result = getFromCacheOrQuery(
	cacheKey: $cacheKey,
	ttl: $ttl,
	queryFn: function () use ($doctrine) {
		return $doctrine->em->createQueryBuilder()
			->select('p')
			->from(App\Models\Entity\WebProjects::class, 'p')
			->andWhere('p.deletedAt IS NULL')
			->getQuery()
			->getArrayResult();
	}
);

// $result is cached on first call and reused until TTL expires
```

### Notes
- If a cache adapter is misconfigured or missing, the Doctrine service throws a descriptive error.
- See `src/Doctrine.php` for runtime checks, cache wiring, and configuration details.
