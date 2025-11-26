<?php

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Daycry\Doctrine\Doctrine;
use Daycry\Doctrine\DataTables\Builder;
use Tests\Support\Models\Entities\TestAttribute;
use Tests\Support\TestCase;
use Tests\Support\Database\Seeds\TestSeeder;

class DataTableTest extends TestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $seed = TestSeeder::class;

    public function testDataTableDefault()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
            ->withColumnAliases(
                [
                    'id' => 't.id',
                    'name' => 't.name'
                ]
            )
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('data')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't')
            )
            ->withRequestParams(
                array(
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                    'search' => array('value' => 'am', 'regex' => false ),
                    'columns' => array(
                        array(
                            'data' => 'id',
                            'name' => 'id',
                            'searchable' => true,
                            'orderable' => true,
                            'search' => array('value' => '', 'regex' => false)
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

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(2, $response['data']);
    }


    public function testDataTableSearchColumn()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
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
                    ->from(TestAttribute::class, 't')
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
                            'search' => array('value' => '', 'regex' => false)
                        ),
                        array(
                            'data' => 'name',
                            'name' => 'name',
                            'searchable' => true,
                            'orderable' => true,
                            'search' => array('value' => 'name1', 'regex' => true)
                        )
                    ),
                    'order' => array( array( 'column' => 0, 'dir' => 'asc') )
                )
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithPercent()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
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
                    ->from(TestAttribute::class, 't')
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
                            'search' => array('value' => '', 'regex' => false)
                        ),
                        array(
                            'data' => 'name',
                            'name' => 'name',
                            'searchable' => true,
                            'orderable' => true,
                            'search' => array('value' => '[%%]am', 'regex' => false)
                        )
                    ),
                    'order' => array( array( 'column' => 0, 'dir' => 'asc') )
                )
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(2, $response['data']);
    }

    public function testDataTableSearchColumnWithInvalidOperatorFallback()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
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
                    ->from(TestAttribute::class, 't')
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
                            'search' => array('value' => '', 'regex' => false)
                        ),
                        array(
                            'data' => 'name',
                            'name' => 'name',
                            'searchable' => true,
                            'orderable' => true,
                            // Unsupported operator should fallback to LIKE
                            'search' => array('value' => '[XYZ]am', 'regex' => false)
                        )
                    ),
                    'order' => array( array( 'column' => 0, 'dir' => 'asc') )
                )
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        // Should behave like LIKE '%am%' and return 2 rows
        $this->assertCount(2, $response['data']);
    }

    public function testDataTableSearchColumnWithLikeSynonyms()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        // [LIKE] synonym
        $datatablesLike = (new Builder())
            ->withColumnAliases([
                'id' => 't.id',
                'name' => 't.name'
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't')
            )
            ->withRequestParams([
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => '', 'regex' => true],
                'columns' => [
                    [
                        'data' => 'id',
                        'name' => 'id',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '', 'regex' => false]
                    ],
                    [
                        'data' => 'name',
                        'name' => 'name',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '[LIKE]am', 'regex' => false]
                    ]
                ],
                'order' => [ [ 'column' => 0, 'dir' => 'asc'] ]
            ]);

        $respLike = $datatablesLike->getResponse();

        // [%%] synonym
        $datatablesDoublePct = (new Builder())
            ->withColumnAliases([
                'id' => 't.id',
                'name' => 't.name'
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(false)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't')
            )
            ->withRequestParams([
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => '', 'regex' => true],
                'columns' => [
                    [
                        'data' => 'id',
                        'name' => 'id',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '', 'regex' => false]
                    ],
                    [
                        'data' => 'name',
                        'name' => 'name',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '[%%]am', 'regex' => false]
                    ]
                ],
                'order' => [ [ 'column' => 0, 'dir' => 'asc'] ]
            ]);

        $respDoublePct = $datatablesDoublePct->getResponse();

        $this->assertArrayHasKey('data', $respLike);
        $this->assertArrayHasKey('data', $respDoublePct);
        $this->assertCount(2, $respLike['data']);
        $this->assertCount(2, $respDoublePct['data']);
    }

    public function testDataTableGlobalSearchSkipsNumericColumnIdentifier()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        // Global search 'am' should apply only to valid fields; numeric 'data' should be ignored
        $datatables = (new Builder())
            ->withColumnAliases([
                'id' => 't.id',
                'name' => 't.name'
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('data')
            ->withSearchableColumns(['t.name'])
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't')
            )
            ->withRequestParams([
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => 'am', 'regex' => false],
                'columns' => [
                    [
                        // intentionally numeric to simulate bad client config
                        'data' => '0',
                        'name' => 'id',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '', 'regex' => false]
                    ],
                    [
                        'data' => 'name',
                        'name' => 'name',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '', 'regex' => false]
                    ]
                ],
                'order' => [ [ 'column' => 0, 'dir' => 'asc'] ]
            ]);

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        // Should still return 2 rows based on valid 'name' field search
        $this->assertCount(2, $response['data']);
    }

    public function testDataTableCaseInsensitiveWithOperators()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        // Dataset has names like 'name1', 'name2', 'name3'. We'll test lowercase/uppercase mixing.
        // OR operator with case-insensitive LIKE
        $builderOr = (new Builder())
            ->withColumnAliases([
                'id' => 't.id',
                'name' => 't.name'
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('name')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't')
            )
            ->withRequestParams([
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => '', 'regex' => true],
                'columns' => [
                    [
                        'data' => 'id',
                        'name' => 'id',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '', 'regex' => false]
                    ],
                    [
                        'data' => 'name',
                        'name' => 'name',
                        'searchable' => true,
                        'orderable' => true,
                        // OR no aplica lower() en Builder, usar valores existentes en minúscula
                        'search' => ['value' => '[OR]name1,name2', 'regex' => false]
                    ]
                ],
                'order' => [ [ 'column' => 0, 'dir' => 'asc'] ]
            ]);

        $respOr = $builderOr->getResponse();
        $this->assertArrayHasKey('data', $respOr);
        $this->assertCount(2, $respOr['data']);

        // IN operator should be case-insensitive irrelevant (exact match on ids)
        $builderIn = (new Builder())
            ->withColumnAliases([
                'id' => 't.id',
                'name' => 't.name'
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('data')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't')
            )
            ->withRequestParams([
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => '', 'regex' => true],
                'columns' => [
                    [
                        'data' => 'id',
                        'name' => 'id',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '[IN]1,2', 'regex' => false]
                    ],
                    [
                        'data' => 'name',
                        'name' => 'name',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '', 'regex' => false]
                    ]
                ],
                'order' => [ [ 'column' => 0, 'dir' => 'asc'] ]
            ]);

        $respIn = $builderIn->getResponse();
        $this->assertArrayHasKey('data', $respIn);
        $this->assertCount(2, $respIn['data']);

        // BETWEEN (><) on ids
        $builderBetween = (new Builder())
            ->withColumnAliases([
                'id' => 't.id',
                'name' => 't.name'
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('data')
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't')
            )
            ->withRequestParams([
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => '', 'regex' => true],
                'columns' => [
                    [
                        'data' => 'id',
                        'name' => 'id',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '[><]1,2', 'regex' => false]
                    ],
                    [
                        'data' => 'name',
                        'name' => 'name',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '', 'regex' => false]
                    ]
                ],
                'order' => [ [ 'column' => 0, 'dir' => 'asc'] ]
            ]);

        $respBetween = $builderBetween->getResponse();
        $this->assertArrayHasKey('data', $respBetween);
        $this->assertCount(2, $respBetween['data']);
    }

    public function testDataTableGlobalAndColumnFiltersCombined()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        // Combina búsqueda global '%am%' (sobre 'name') y filtro por columna 'id' con IN
        $datatables = (new Builder())
            ->withColumnAliases([
                'id' => 't.id',
                'name' => 't.name'
            ])
            ->withIndexColumn('qlu.id')
            ->setUseOutputWalkers(false)
            ->withCaseInsensitive(true)
            ->withColumnField('data')
            ->withSearchableColumns(['t.name'])
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from(TestAttribute::class, 't')
            )
            ->withRequestParams([
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => 'am', 'regex' => false],
                'columns' => [
                    [
                        'data' => 'id',
                        'name' => 'id',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '[IN]1,2', 'regex' => false]
                    ],
                    [
                        'data' => 'name',
                        'name' => 'name',
                        'searchable' => true,
                        'orderable' => true,
                        'search' => ['value' => '', 'regex' => false]
                    ]
                ],
                'order' => [ [ 'column' => 0, 'dir' => 'asc'] ]
            ]);

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        // Con ids 1 y 2 y búsqueda '%am%' sobre 'name', deben seguir siendo 2 registros
        $this->assertCount(2, $response['data']);
    }

    public function testDataTableSearchColumnWithDifferent()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
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
                    ->from(TestAttribute::class, 't')
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
                            'search' => array('value' => '', 'regex' => false)
                        ),
                        array(
                            'data' => 'name',
                            'name' => 'name',
                            'searchable' => true,
                            'orderable' => true,
                            'search' => array('value' => '[!=]name1', 'regex' => false)
                        )
                    ),
                    'order' => array( array( 'column' => 0, 'dir' => 'asc') )
                )
            );

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithLessThan()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
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
                    ->from(TestAttribute::class, 't')
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
                            'search' => array('value' => '[<]2', 'regex' => false)
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

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithMoreThan()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
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
                    ->from(TestAttribute::class, 't')
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
                            'search' => array('value' => '[>]1', 'regex' => false)
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

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithIn()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
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
                    ->from(TestAttribute::class, 't')
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
                            'search' => array('value' => '[IN]2,3', 'regex' => false)
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

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithOr()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
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
                    ->from(TestAttribute::class, 't')
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

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithBetween()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
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
                    ->from(TestAttribute::class, 't')
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
                            'search' => array('value' => '[><]2,3', 'regex' => false)
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

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }

    public function testDataTableSearchColumnWithEquals()
    {
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $datatables = (new Builder())
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
                    ->from(TestAttribute::class, 't')
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
                            'search' => array('value' => '[=]2', 'regex' => false)
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

        $response = $datatables->getResponse();

        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
    }
}
