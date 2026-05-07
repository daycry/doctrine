<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Doctrine\Doctrine;
use Tests\Support\Database\Seeds\TestSeeder;
use Tests\Support\Models\Entities\TestAttribute;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class DoctrineQueryTest extends TestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seedOnce    = false;
    protected $seed        = TestSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getMysqlDSNConfig();
    }

    public function testQueryAttribute()
    {
        $this->config->metadataConfigurationMethod = 'attribute';
        $doctrine                                  = new Doctrine($this->config);

        $data = $doctrine->em->getRepository('Tests\\Support\\Models\\Entities\\TestAttribute')->findOneBy(['id' => 1]);
        $this->assertInstanceOf(TestAttribute::class, $data);

        $this->assertSame(1, $data->getId());
        $this->assertSame('name1', $data->getName());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
