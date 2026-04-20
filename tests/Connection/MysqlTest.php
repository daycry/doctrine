<?php

declare(strict_types=1);

namespace Tests\Connection;

use Tests\Support\TestCase;
use Config\Database;
use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\EntityManager;

final class MysqlTest extends TestCase
{
    public function testDSNMysql()
    {
        $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
        $this->assertSame('doctrine_tests', $doctrine->em->getConnection()->getDatabase());
    }

    public function testMysql()
    {
        $this->getMysqlConfig();

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
        $this->assertSame('doctrine_tests', $doctrine->em->getConnection()->getDatabase());
    }

}
