<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class DataTableTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seedOnce = false;
    protected $seed = \Tests\Support\Database\Seeds\TestSeeder::class;

    protected $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config('Doctrine');
        $this->config->namespaceModel = 'Tests/Support/Models';
        $this->config->folderModel = SUPPORTPATH . 'Models';
        $this->config->namespaceProxy = 'Tests/Support/Models/Proxies';
        $this->config->folderProxy = SUPPORTPATH . 'Models/Proxies';
        $this->config->folderEntity = SUPPORTPATH . 'Models/Entities';
    }
    
    public function testDataTable()
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
            ->withIndexColumn( 'qlu.id' )
            ->setUseOutputWalkers( false )
            ->withCaseInsensitive( true )
            ->withColumnField('name')
            ->withReturnCollection( false )
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select( 't.id, t.name' )
                    ->from( \Test\Support\Models\Entities\Test::class, 't' )
            )
            ->withRequestParams( 
                array(
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                    'search' => array('value' => 'name', 'regex' => true ),
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
        $this->assertCount( 2, $response['data'] );
    }

    public function testDataTableWithReturnCollection()
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
            ->withIndexColumn( 'qlu.id' )
            ->setUseOutputWalkers( false )
            ->withCaseInsensitive( true )
            ->withColumnField('name')
            ->withReturnCollection( true )
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select( 't.id, t.name' )
                    ->from( \Test\Support\Models\Entities\Test::class, 't' )
            )
            ->withRequestParams( 
                array(
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                    'search' => array('value' => 'name', 'regex' => true ),
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
        $this->assertCount( 2, $response['data'] );
    }

    public function testDataTableSearchColumn()
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
            ->withIndexColumn( 'qlu.id' )
            ->setUseOutputWalkers( false )
            ->withCaseInsensitive( true )
            ->withColumnField('name')
            ->withReturnCollection( false )
            ->withQueryBuilder(
                $doctrine->em->createQueryBuilder()
                    ->select( 't.id, t.name' )
                    ->from( \Test\Support\Models\Entities\Test::class, 't' )
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
        $this->assertCount( 1, $response['data'] );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}