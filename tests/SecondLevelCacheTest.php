<?php

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;
use Daycry\Doctrine\Doctrine;
use Tests\Support\Models\Entities\CacheableProduct;
use Doctrine\ORM\Tools\SchemaTool;

final class SecondLevelCacheTest extends CIUnitTestCase
{
    public function testSecondLevelCacheConfigurationIsApplied()
    {
        $config = new DoctrineConfig();
        $config->entities = [__DIR__ . '/_support/Models/Entities'];
        $config->secondLevelCache = true;

        $doctrine = new Doctrine($config, config('Cache'));
        $em = $doctrine->em;

        $this->assertTrue($em->getConfiguration()->isSecondLevelCacheEnabled());
        $this->assertNotNull($em->getConfiguration()->getSecondLevelCacheConfiguration());
    }

    public function testPersistAndFetchCacheableEntityWithoutErrors()
    {
        $config = new DoctrineConfig();
        $config->entities = [__DIR__ . '/_support/Models/Entities'];
        $config->secondLevelCache = true;

        $doctrine = new Doctrine($config, config('Cache'));
        $em = $doctrine->em;

        // Skip on SQLite memory DB if schema not available
        // Minimal check: ensure EntityManager works and operations do not error out
        $product = new CacheableProduct('Sample');
        // Create schema for the entity
        $metadata = $em->getClassMetadata(CacheableProduct::class);
        $tool = new SchemaTool($em);
        try {
            $tool->createSchema([$metadata]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Unable to create schema for CacheableProduct: ' . $e->getMessage());
        }

        // Persist and fetch twice
        $em->persist($product);
        $em->flush();

        $id = $product->getId();
        $p1 = $em->find(CacheableProduct::class, $id);
        $p2 = $em->find(CacheableProduct::class, $id);

        $this->assertNotNull($p1);
        $this->assertNotNull($p2);
        $this->assertSame($p1->getId(), $p2->getId());

        // Cleanup schema
        try {
            $tool->dropSchema([$metadata]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
