# Using DataTables

You can use Doctrine with DataTables for advanced querying and data presentation.

## Basic Usage

```php
$datatables = (new \Daycry\Doctrine\DataTables\Builder())
    ->withColumnAliases([
        'id' => 'qlu.id'
    ])
    ->withIndexColumn('qlu.id')
    ->withQueryBuilder(
        $this->doctrine->em->createQueryBuilder()
            ->select('qlu.param, q.param, q.param, qs.id as param, qlu.param, qlu.param')
            ->from(\App\Models\Entity\Class::class, 'qlu')
            ->innerJoin(\App\Models\Entity\Class::class, 'qs', \Doctrine\ORM\Query\Expr\Join::WITH, 'qs.id = qlu.*')
            ->innerJoin(\App\Models\Entity\Class::class, 'ql', \Doctrine\ORM\Query\Expr\Join::WITH, 'ql.id = qlu.*')
            ->innerJoin(\App\Models\Entity\Class::class, 'q', \Doctrine\ORM\Query\Expr\Join::WITH, 'q.id = ql.*')
    )
    ->withRequestParams($this->request->getGet(null));

$response = $datatables->getResponse();
echo json_encode($response);
```

If you receive the error: `Not all identifier properties can be found in the ResultSetMapping`, you can use:

```php
->setUseOutputWalkers(false)
```

## Example

```php
$datatables = (new \Daycry\Doctrine\DataTables\Builder())
    ->withColumnAliases([
        'id' => 'qlu.id'
    ])
    ->withIndexColumn('qlu.id')
    ->setUseOutputWalkers(false)
    ->withQueryBuilder(
        $this->doctrine->em->createQueryBuilder()
            ->select('qlu.param, q.param, q.param, qs.id as param, qlu.param, qlu.param')
            ->from(\App\Models\Entity\Class::class, 'qlu')
            ->innerJoin(\App\Models\Entity\Class::class, 'qs', \Doctrine\ORM\Query\Expr\Join::WITH, 'qs.id = qlu.*')
            ->innerJoin(\App\Models\Entity\Class::class, 'ql', \Doctrine\ORM\Query\Expr\Join::WITH, 'ql.id = qlu.*')
            ->innerJoin(\App\Models\Entity\Class::class, 'q', \Doctrine\ORM\Query\Expr\Join::WITH, 'q.id = ql.*')
    )
    ->withRequestParams($this->request->getGet(null));

$response = $datatables->getResponse();
echo json_encode($response);
```

## Search Modes

There are nine different search modes for DataTables. See the main README for a detailed table of patterns and descriptions.
