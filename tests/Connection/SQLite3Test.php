<?php

namespace Tests\Connection;

use Tests\Support\TestCase;
use Config\Database;
use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\EntityManager;

class SQLite3Test extends TestCase
{
    public function testDSNSQLite3()
    {
        /** @var Database $config */
        $config = $this->getSQLite3DSNConfig();

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testSQLite3()
    {
        /** @var Database $config */
        $config = $this->getSQLite3Config();

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testSQLite3Path()
    {
        /** @var Database $config */
        $config = $this->getSQLite3Config(false);

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

    public function testDSNSQLite3Path()
    {
        /** @var Database $config */
        $config = $this->getSQLite3DSNConfig(false);

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
    }

}
