<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Daycry\Doctrine\Config\Doctrine;
use Nexus\PHPUnit\Extension\Expeditable;

abstract class TestCase extends CIUnitTestCase
{
    use Expeditable;

    protected Doctrine $config;
    /**
     * Sets up the ArrayHandler for faster & easier tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var DoctrineConfig $config */
        $this->config = config('Doctrine');
        $this->config->entities = [SUPPORTPATH . 'Models/Entities'];
        $this->config->proxies = SUPPORTPATH . 'Models/Proxies';
    }

    private function _getDatabaseConfig(): Database
    {
        /** @var Database $config*/
        $config = config('Database');
        $config->tests['database'] = 'doctrine_tests';
        $config->tests['username'] = 'root';
        $config->tests['password'] = '';
        $config->tests['DBPrefix'] = '';

        return $config;
    }

    protected function getSQLite3Config(bool $memory = true): Database
    {
        /** @var Database $config*/
        $config = $this->_getDatabaseConfig();
        $config->tests['DBDriver'] = 'SQLite3';
        if($memory)
        {
            $config->tests['database'] = ':memory:';
        }else{
            $config->tests['database'] = SUPPORTPATH . 'db.sqlite';
        }
        return $config;
    }

    protected function getSQLite3DSNConfig(bool $memory = true): Database
    {
        /** @var Database $config*/
        $config = $this->_getDatabaseConfig();
        if($memory)
        {
            $config->tests['DSN'] = 'SQLite3:///:memory:';
        }else{
            $config->tests['DSN'] = 'SQLite3:' . SUPPORTPATH . 'db.sqlite';
        }

        return $config;
    }


    protected function getMysqlConfig(): Database
    {
        /** @var Database $config*/
        $config = $this->_getDatabaseConfig();
        $config->tests['DBDriver'] = 'MySQLi';

        return $config;
    }
    
    protected function getMysqlDSNConfig(): Database
    {
        /** @var Database $config*/
        $config = $this->_getDatabaseConfig();
        $config->tests['DSN'] = 'MySQLi://root:@127.0.0.1:3306/doctrine_tests';

        return $config;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->resetServices();
    }
}