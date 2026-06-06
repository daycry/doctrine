<?php

declare(strict_types=1);

namespace Tests;

use Daycry\Doctrine\Config\Doctrine as DoctrineConfig;
use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Log\LoggerInterface;
use Tests\Support\Log\SpyLogger;
use Tests\Support\Models\Entities\TestAttribute;
use Tests\Support\TestCase;
use Throwable;

/**
 * Exercises QueryLoggerMiddleware's wrapped Connection methods (query, exec,
 * prepare/execute, transactions, quote, native connection, server version,
 * exception converter) through real SQLite operations with query logging on.
 *
 * @internal
 */
final class DoctrineQueryLoggerCoverageTest extends TestCase
{
    private Doctrine $doctrine;
    private SpyLogger $spy;

    protected function setUp(): void
    {
        parent::setUp();

        $db                    = config('Database');
        $db->tests['DBDriver'] = 'SQLite3';
        $db->tests['database'] = ':memory:';

        $this->config->entities           = [SUPPORTPATH . 'Models/Entities'];
        $this->config->queryLogging       = true;
        $this->config->slowQueryThreshold = 0.0;

        $this->spy      = new SpyLogger();
        $spy            = $this->spy;
        $this->doctrine = new class ($this->config, $spy) extends Doctrine {
            public function __construct(DoctrineConfig $config, private readonly LoggerInterface $spyLogger)
            {
                parent::__construct($config, null, 'tests');
            }

            protected function logger(): LoggerInterface
            {
                return $this->spyLogger;
            }
        };
    }

    private function createSchema(): SchemaTool
    {
        $tool     = new SchemaTool($this->doctrine->em);
        $metadata = $this->doctrine->em->getClassMetadata(TestAttribute::class);
        $tool->createSchema([$metadata]);

        return $tool;
    }

    private function dropSchema(SchemaTool $tool): void
    {
        try {
            $metadata = $this->doctrine->em->getClassMetadata(TestAttribute::class);
            $tool->dropSchema([$metadata]);
        } catch (Throwable) {
            // ignore cleanup errors
        }
    }

    public function testLoggerCoversQueryExecPrepareAndTransactions(): void
    {
        $tool = $this->createSchema();
        $conn = $this->doctrine->em->getConnection();

        // query() — parameterless SELECT
        $this->assertSame(1, $conn->executeQuery('SELECT 1')->fetchOne());

        // prepare()/execute() + transaction commit
        $conn->beginTransaction();
        $conn->executeStatement('INSERT INTO test (name, created_at) VALUES (?, ?)', ['log_tx', '2026-01-01 00:00:00']);
        $conn->commit();

        // transaction rollback
        $conn->beginTransaction();
        $conn->executeStatement('INSERT INTO test (name, created_at) VALUES (?, ?)', ['log_rb', '2026-01-01 00:00:00']);
        $conn->rollBack();

        // exec() — parameterless statement
        $conn->executeStatement('DELETE FROM test WHERE name = \'never\'');

        $id      = $conn->lastInsertId();
        $native  = $conn->getNativeConnection();
        $quoted  = $conn->quote("O'Reilly");
        $version = $conn->getServerVersion();

        $this->assertNotEmpty($this->spy->records, 'query logging should have recorded queries');
        $this->assertNotEmpty((string) $id);
        $this->assertNotNull($native);
        $this->assertIsString($quoted);
        $this->assertNotEmpty($version);

        $this->dropSchema($tool);
    }

    public function testLoggerGetExceptionConverterOnInvalidSql(): void
    {
        $conn = $this->doctrine->em->getConnection();

        try {
            $conn->executeQuery('INVALID SQL STATEMENT');
            $this->fail('Expected an exception for invalid SQL');
        } catch (Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testLoggerSkipsQueriesBelowThreshold(): void
    {
        // A very high threshold means nothing is logged.
        $this->config->slowQueryThreshold = 9999.0;

        $spy      = new SpyLogger();
        $doctrine = new class ($this->config, $spy) extends Doctrine {
            public function __construct(DoctrineConfig $config, private readonly LoggerInterface $spyLogger)
            {
                parent::__construct($config, null, 'tests');
            }

            protected function logger(): LoggerInterface
            {
                return $this->spyLogger;
            }
        };

        $doctrine->em->getConnection()->executeQuery('SELECT 1')->fetchOne();

        $this->assertSame([], $spy->records, 'no query is slow enough to be logged');
    }
}
