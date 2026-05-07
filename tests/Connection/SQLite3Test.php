<?php

declare(strict_types=1);

namespace Tests\Connection;

use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\EntityManager;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class SQLite3Test extends TestCase
{
    public function testDSNSQLite3()
    {
        $this->getSQLite3DSNConfig();

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testSQLite3()
    {
        $this->getSQLite3Config();

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testSQLite3Path()
    {
        $this->getSQLite3Config(false);

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testDSNSQLite3Path()
    {
        $this->getSQLite3DSNConfig(false);

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }
}
