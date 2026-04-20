<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Daycry\Doctrine\Config\Doctrine;
use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;

abstract class TestCase extends CIUnitTestCase
{
    protected DoctrineConfig $config;
    /**
     * Sets up the ArrayHandler for faster & easier tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = config('Doctrine');
        $this->config->entities = [SUPPORTPATH . 'Models/Entities'];
        $this->config->proxies = SUPPORTPATH . 'Models/Proxies';
    }

    private function _getDatabaseConfig(): Database
    {
        /** @var Database $config*/
        $config = config('Database');
        $config->tests['database'] = 'doctrine_tests';
        $config->tests['hostname'] = '127.0.0.1';
        $config->tests['username'] = 'root';
        $config->tests['password'] = '';
        $config->tests['DBPrefix'] = '';

        return $config;
    }

    protected function getSQLite3Config(bool $memory = true): Database
    {
        $config = $this->_getDatabaseConfig();
        $config->tests['DBDriver'] = 'SQLite3';
        $config->tests['database'] = $memory ? ':memory:' : SUPPORTPATH . 'db.sqlite';
        return $config;
    }

    protected function getSQLite3DSNConfig(bool $memory = true): Database
    {
        $config = $this->_getDatabaseConfig();
        $config->tests['DSN'] = $memory ? 'SQLite3:///:memory:' : 'SQLite3:' . SUPPORTPATH . 'db.sqlite';

        return $config;
    }


    protected function getMysqlConfig(): Database
    {
        $config = $this->_getDatabaseConfig();
        $config->tests['DBDriver'] = 'MySQLi';

        return $config;
    }

    protected function getMysqlDSNConfig(): Database
    {
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
