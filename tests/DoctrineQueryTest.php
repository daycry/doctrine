<?php

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\TestSeeder;
use Daycry\Doctrine\Doctrine;
use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;
use Tests\Support\TestCase;

class DoctrineQueryTest extends TestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seedOnce = false;
    protected $seed = TestSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->getMysqlDSNConfig();
    }

    public function testQueryAttribute()
    {
        $this->config->metadataConfigurationMethod = 'attribute';
        $doctrine = new Doctrine($this->config);

        $data = $doctrine->em->getRepository("Tests\Support\Models\Entities\TestAttribute")->findOneBy(array( 'id' => 1 ));

        $this->assertSame(1, $data->getId());
        $this->assertSame('name1', $data->getName());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
