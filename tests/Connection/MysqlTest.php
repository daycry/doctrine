<?php

namespace Tests\Connection;

use Tests\Support\TestCase;
use Config\Database;
use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\EntityManager;

class MysqlTest extends TestCase
{
    public function testDSNMysql()
    {
        /** @var Database $config */
        $config = $this->getMysqlDSNConfig();

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
        $this->assertSame('doctrine_tests', $doctrine->em->getConnection()->getDatabase());
    }

    public function testMysql()
    {
        /** @var Database $config */
        $config = $this->getMysqlConfig();

        $doctrine = new Doctrine($this->config);

        $this->assertInstanceOf(Doctrine::class, $doctrine);
        $this->assertInstanceOf(EntityManager::class, $doctrine->em);
        $this->assertSame('doctrine_tests', $doctrine->em->getConnection()->getDatabase());
    }

}
