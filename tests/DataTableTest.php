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
        $this->assertCount(2, $response['data']);
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
        $this->assertCount(2, $response['data']);
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
        $this->assertCount(2, $response['data']);
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
        $this->assertCount(2, $response['data']);
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
        $this->assertCount(2, $response['data']);
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
        $this->assertCount(2, $response['data']);
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
        $this->assertCount(2, $response['data']);
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
        $this->assertCount(2, $response['data']);
    }
}
