<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\TestSeeder;
use Doctrine\ORM\EntityManager;
use Daycry\Doctrine\Doctrine;

class DoctrineQueryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seedOnce = false;
    protected $seed = TestSeeder::class;

    protected $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config('Doctrine');
        $this->config->entities = [SUPPORTPATH . 'Models/Entities'];
        $this->config->proxies = SUPPORTPATH . 'Models/Proxies';
    }

    public function testQueryAnnotation()
    {
        $this->config->metadataConfigurationMethod = 'annotation';
        $doctrine = new Doctrine($this->config);

        $data = $doctrine->em->getRepository("Tests\Support\Models\Entities\Test")->findOneBy( array( 'id' => 1 ) );

        $this->assertSame(1, $data->getId());
        $this->assertSame('name1', $data->getName());
    }

    public function testQueryAttribute()
    {
        $this->config->metadataConfigurationMethod = 'attribute';
        $doctrine = new Doctrine($this->config);

        $data = $doctrine->em->getRepository("Tests\Support\Models\Entities\TestAttribute")->findOneBy( array( 'id' => 1 ) );

        $this->assertSame(1, $data->getId());
        $this->assertSame('name1', $data->getName());
    }

    public function testQueryYaml()
    {
        $this->config->metadataConfigurationMethod = 'yaml';
        $doctrine = new Doctrine($this->config);

        $data = $doctrine->em->getRepository("TestYaml")->findOneBy( array( 'id' => 1 ) );

        $this->assertSame(1, $data->getId());
        $this->assertSame('name1', $data->getName());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
