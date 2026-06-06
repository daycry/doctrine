<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Daycry\Doctrine\Config\Services;
use Daycry\Doctrine\DataTables\Builder;
use Daycry\Doctrine\Doctrine;
use Tests\Support\Database\Seeds\TestSeeder;
use Tests\Support\Models\Entities\TestAttribute;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class DataTableTest extends TestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $seed = TestSeeder::class;

    public function testDataTableDefault()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id'   => 't.id',
                    'name' => 't.name',
                ],
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('data')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams(
                [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'search'  => ['value' => 'am', 'regex' => false],
                    'columns' => [
                        [
                            'data'       => 'id',
                            'name'       => 'id',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                        [
                            'data'       => 'name',
                            'name'       => 'name',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                    ],
                    'order' => [['column' => 0, 'dir' => 'asc']],
                ],
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(2, $response['data']);
    }

    public function testDataTableSearchColumn()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id'   => 't.id',
                    'name' => 't.name',
                ],
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams(
                [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'search'  => ['value' => '', 'regex' => true],
                    'columns' => [
                        [
                            'data'       => 'id',
                            'name'       => 'id',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                        [
                            'data'       => 'name',
                            'name'       => 'name',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => 'name1', 'regex' => false],
                        ],
                    ],
                    'order' => [['column' => 0, 'dir' => 'asc']],
                ],
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testWithRecordsTotalBypassesTheCountQuery()
    {
        $this->getMysqlDSNConfig();

        $doctrine  = new Doctrine($this->config);
        $collector = Services::doctrineCollector();

        $builder = (new Builder())
            ->withColumnAliases(['id' => 't.id', 'name' => 't.name'])
            ->withRecordsTotal(999)
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()->select('t')->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'columns' => [
                    ['data' => 'id', 'name' => 'id', 'searchable' => true, 'orderable' => true, 'search' => ['value' => '', 'regex' => false]],
                    ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true, 'search' => ['value' => '', 'regex' => false]],
                ],
            ]);

        $collector->reset();
        $total   = $builder->getRecordsTotal();
        $queries = count($collector->getQueries());

        $this->assertSame(999, $total);
        $this->assertSame(0, $queries, 'an injected total must not issue a count query');
    }

    public function testWithRecordsTotalAcceptsAClosure()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $builder = (new Builder())
            ->withColumnAliases(['id' => 't.id', 'name' => 't.name'])
            ->withRecordsTotal(static fn (): int => 1234)
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()->select('t')->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'columns' => [
                    ['data' => 'id', 'name' => 'id', 'searchable' => true, 'orderable' => true, 'search' => ['value' => '', 'regex' => false]],
                    ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true, 'search' => ['value' => '', 'regex' => false]],
                ],
            ]);

        $this->assertSame(1234, $builder->getResponse()['recordsTotal']);
    }

    public function testFetchJoinCollectionOptOutReducesDataQueries()
    {
        $this->getMysqlDSNConfig();

        $doctrine  = new Doctrine($this->config);
        $collector = Services::doctrineCollector();

        $build = static fn (): Builder => (new Builder())
            ->withColumnAliases(['id' => 't.id', 'name' => 't.name'])
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()->select('t')->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'columns' => [
                    ['data' => 'id', 'name' => 'id', 'searchable' => true, 'orderable' => true, 'search' => ['value' => '', 'regex' => false]],
                    ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true, 'search' => ['value' => '', 'regex' => false]],
                ],
            ]);

        // Default fetchJoinCollection=true issues an id sub-query + WHERE-IN fetch.
        $collector->reset();
        $build()->getData();
        $withJoin = count($collector->getQueries());

        // Opting out collapses the page fetch into a single query.
        $collector->reset();
        $build()->withFetchJoinCollection(false)->getData();
        $withoutJoin = count($collector->getQueries());

        $this->assertLessThan($withJoin, $withoutJoin);
    }

    public function testOrderingWithOutOfRangeColumnIndexIsIgnored()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(['id' => 't.id', 'name' => 't.name'])
            ->setUseOutputWalkers(false)
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'columns' => [
                    ['data' => 'id', 'name' => 'id', 'searchable' => true, 'orderable' => true, 'search' => ['value' => '', 'regex' => false]],
                    ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true, 'search' => ['value' => '', 'regex' => false]],
                ],
                // Attacker/garbage column index out of range — must be ignored, not warn.
                'order' => [['column' => 5, 'dir' => 'asc']],
            ]);

        $response = $datatables->getResponse();
        $this->assertCount(2, $response['data']);
    }

    public function testOrOperatorHonorsCaseInsensitive()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $query = (new Builder())
            ->withColumnAliases(['name' => 't.name'])
            ->withCaseInsensitive(true)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'columns' => [
                    ['name' => 'name', 'searchable' => true, 'search' => ['value' => '[OR]name1,name2', 'regex' => false]],
                ],
            ])
            ->getFilteredQuery();

        // Case-insensitive [OR] must wrap BOTH the column and each placeholder in lower(),
        // exactly like the global search and the default LIKE branch already do.
        $dql = $query->getDQL();
        $this->assertStringContainsString('lower(t.name) LIKE lower(:filter_0_0)', $dql);
        $this->assertStringContainsString('lower(:filter_0_1)', $dql);
    }

    public function testMaxPageLengthCapsUnboundedAllRequest()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(['id' => 't.id', 'name' => 't.name'])
            ->setUseOutputWalkers(false)
            ->withMaxPageLength(1)
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => -1, // DataTables "All" — must not bypass the cap
                'columns' => [
                    ['data' => 'id', 'name' => 'id', 'searchable' => true, 'orderable' => true, 'search' => ['value' => '', 'regex' => false]],
                    ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true, 'search' => ['value' => '', 'regex' => false]],
                ],
            ]);

        // Two rows are seeded; the cap must limit the unbounded "All" request to 1.
        $this->assertCount(1, $datatables->getData());
    }

    public function testDataTableSearchColumnWithPercent()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id'   => 't.id',
                    'name' => 't.name',
                ],
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams(
                [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'search'  => ['value' => '', 'regex' => true],
                    'columns' => [
                        [
                            'data'       => 'id',
                            'name'       => 'id',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                        [
                            'data'       => 'name',
                            'name'       => 'name',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '[%%]am', 'regex' => false],
                        ],
                    ],
                    'order' => [['column' => 0, 'dir' => 'asc']],
                ],
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(2, $response['data']);
    }

    public function testDataTableSearchColumnWithInvalidOperatorFallback()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id'   => 't.id',
                    'name' => 't.name',
                ],
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams(
                [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'search'  => ['value' => '', 'regex' => true],
                    'columns' => [
                        [
                            'data'       => 'id',
                            'name'       => 'id',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                        [
                            'data'       => 'name',
                            'name'       => 'name',
                            'searchable' => true,
                            'orderable'  => true,
                            // Unsupported operator should fallback to LIKE
                            'search' => ['value' => '[XYZ]am', 'regex' => false],
                        ],
                    ],
                    'order' => [['column' => 0, 'dir' => 'asc']],
                ],
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        // Should behave like LIKE '%am%' and return 2 rows
        $this->assertCount(2, $response['data']);
    }

    public function testDataTableSearchColumnWithLikeSynonyms()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        // [LIKE] synonym
        $datatablesLike = (new Builder())
            ->withColumnAliases([
                'id'   => 't.id',
                'name' => 't.name',
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => '', 'regex' => true],
                'columns' => [
                    [
                        'data'       => 'id',
                        'name'       => 'id',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '[LIKE]am', 'regex' => false],
                    ],
                ],
                'order' => [['column' => 0, 'dir' => 'asc']],
            ]);

        $respLike = $datatablesLike->getResponse();

        // [%%] synonym
        $datatablesDoublePct = (new Builder())
            ->withColumnAliases([
                'id'   => 't.id',
                'name' => 't.name',
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => '', 'regex' => true],
                'columns' => [
                    [
                        'data'       => 'id',
                        'name'       => 'id',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '[%%]am', 'regex' => false],
                    ],
                ],
                'order' => [['column' => 0, 'dir' => 'asc']],
            ]);

        $respDoublePct = $datatablesDoublePct->getResponse();

        $this->assertArrayHasKey('data', $respLike);
        $this->assertArrayHasKey('data', $respDoublePct);
        $this->assertCount(2, $respLike['data']);
        $this->assertCount(2, $respDoublePct['data']);
    }

    public function testDataTableGlobalSearchSkipsNumericColumnIdentifier()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        // Global search 'am' should apply only to valid fields; numeric 'data' should be ignored
        $datatables = (new Builder())
            ->withColumnAliases([
                'id'   => 't.id',
                'name' => 't.name',
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('data')
            ->withSearchableColumns(['t.name'])
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => 'am', 'regex' => false],
                'columns' => [
                    [
                        // intentionally numeric to simulate bad client config
                        'data'       => '0',
                        'name'       => 'id',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                ],
                'order' => [['column' => 0, 'dir' => 'asc']],
            ]);

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        // Should still return 2 rows based on valid 'name' field search
        $this->assertCount(2, $response['data']);
    }

    public function testDataTableCaseInsensitiveWithOperators()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        // Dataset has names like 'name1', 'name2', 'name3'. We'll test lowercase/uppercase mixing.
        // OR operator with case-insensitive LIKE
        $builderOr = (new Builder())
            ->withColumnAliases([
                'id'   => 't.id',
                'name' => 't.name',
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => '', 'regex' => true],
                'columns' => [
                    [
                        'data'       => 'id',
                        'name'       => 'id',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        // OR no aplica lower() en Builder, usar valores existentes en minúscula
                        'search' => ['value' => '[OR]name1,name2', 'regex' => false],
                    ],
                ],
                'order' => [['column' => 0, 'dir' => 'asc']],
            ]);

        $respOr = $builderOr->getResponse();
        $this->assertArrayHasKey('data', $respOr);
        $this->assertCount(2, $respOr['data']);

        // IN operator should be case-insensitive irrelevant (exact match on ids)
        $builderIn = (new Builder())
            ->withColumnAliases([
                'id'   => 't.id',
                'name' => 't.name',
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('data')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => '', 'regex' => true],
                'columns' => [
                    [
                        'data'       => 'id',
                        'name'       => 'id',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '[IN]1,2', 'regex' => false],
                    ],
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                ],
                'order' => [['column' => 0, 'dir' => 'asc']],
            ]);

        $respIn = $builderIn->getResponse();
        $this->assertArrayHasKey('data', $respIn);
        $this->assertCount(2, $respIn['data']);

        // BETWEEN (><) on ids
        $builderBetween = (new Builder())
            ->withColumnAliases([
                'id'   => 't.id',
                'name' => 't.name',
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('data')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => '', 'regex' => true],
                'columns' => [
                    [
                        'data'       => 'id',
                        'name'       => 'id',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '[><]1,2', 'regex' => false],
                    ],
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                ],
                'order' => [['column' => 0, 'dir' => 'asc']],
            ]);

        $respBetween = $builderBetween->getResponse();
        $this->assertArrayHasKey('data', $respBetween);
        $this->assertCount(2, $respBetween['data']);
    }

    public function testDataTableGlobalAndColumnFiltersCombined()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        // Combina búsqueda global '%am%' (sobre 'name') y filtro por columna 'id' con IN
        $datatables = (new Builder())
            ->withColumnAliases([
                'id'   => 't.id',
                'name' => 't.name',
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('data')
            ->withSearchableColumns(['t.name'])
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => 'am', 'regex' => false],
                'columns' => [
                    [
                        'data'       => 'id',
                        'name'       => 'id',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '[IN]1,2', 'regex' => false],
                    ],
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                ],
                'order' => [['column' => 0, 'dir' => 'asc']],
            ]);

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        // Con ids 1 y 2 y búsqueda '%am%' sobre 'name', deben seguir siendo 2 registros
        $this->assertCount(2, $response['data']);
    }

    public function testDataTableSearchColumnWithDifferent()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id'   => 't.id',
                    'name' => 't.name',
                ],
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams(
                [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'search'  => ['value' => '', 'regex' => true],
                    'columns' => [
                        [
                            'data'       => 'id',
                            'name'       => 'id',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                        [
                            'data'       => 'name',
                            'name'       => 'name',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '[!=]name1', 'regex' => false],
                        ],
                    ],
                    'order' => [['column' => 0, 'dir' => 'asc']],
                ],
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithLessThan()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id'   => 't.id',
                    'name' => 't.name',
                ],
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams(
                [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'search'  => ['value' => '', 'regex' => true],
                    'columns' => [
                        [
                            'data'       => 'id',
                            'name'       => 'id',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '[<]2', 'regex' => false],
                        ],
                        [
                            'data'       => 'name',
                            'name'       => 'name',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                    ],
                    'order' => [['column' => 0, 'dir' => 'asc']],
                ],
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithMoreThan()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id'   => 't.id',
                    'name' => 't.name',
                ],
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams(
                [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'search'  => ['value' => '', 'regex' => true],
                    'columns' => [
                        [
                            'data'       => 'id',
                            'name'       => 'id',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '[>]1', 'regex' => false],
                        ],
                        [
                            'data'       => 'name',
                            'name'       => 'name',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                    ],
                    'order' => [['column' => 0, 'dir' => 'asc']],
                ],
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithIn()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id'   => 't.id',
                    'name' => 't.name',
                ],
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams(
                [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'search'  => ['value' => '', 'regex' => true],
                    'columns' => [
                        [
                            'data'       => 'id',
                            'name'       => 'id',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '[IN]2,3', 'regex' => false],
                        ],
                        [
                            'data'       => 'name',
                            'name'       => 'name',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                    ],
                    'order' => [['column' => 0, 'dir' => 'asc']],
                ],
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithOr()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id'   => 't.id',
                    'name' => 't.name',
                ],
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams(
                [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'search'  => ['value' => '', 'regex' => true],
                    'columns' => [
                        [
                            'data'       => 'id',
                            'name'       => 'id',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '[OR]1,3', 'regex' => false],
                        ],
                        [
                            'data'       => 'name',
                            'name'       => 'name',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                    ],
                    'order' => [['column' => 0, 'dir' => 'asc']],
                ],
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithBetween()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id'   => 't.id',
                    'name' => 't.name',
                ],
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams(
                [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'search'  => ['value' => '', 'regex' => true],
                    'columns' => [
                        [
                            'data'       => 'id',
                            'name'       => 'id',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '[><]2,3', 'regex' => false],
                        ],
                        [
                            'data'       => 'name',
                            'name'       => 'name',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                    ],
                    'order' => [['column' => 0, 'dir' => 'asc']],
                ],
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithEquals()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id'   => 't.id',
                    'name' => 't.name',
                ],
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't'),
            )
            ->withRequestParams(
                [
                    'draw'    => 1,
                    'start'   => 0,
                    'length'  => 10,
                    'search'  => ['value' => '', 'regex' => true],
                    'columns' => [
                        [
                            'data'       => 'id',
                            'name'       => 'id',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '[=]2', 'regex' => false],
                        ],
                        [
                            'data'       => 'name',
                            'name'       => 'name',
                            'searchable' => true,
                            'orderable'  => true,
                            'search'     => ['value' => '', 'regex' => false],
                        ],
                    ],
                    'order' => [['column' => 0, 'dir' => 'asc']],
                ],
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }
}
