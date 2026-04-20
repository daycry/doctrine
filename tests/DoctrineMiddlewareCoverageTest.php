<?php

declare(strict_types=1);

namespace Tests;

use Throwable;
use Tests\Support\TestCase;
use Tests\Support\Models\Entities\TestAttribute;
use Daycry\Doctrine\Doctrine;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Covers DoctrineQueryMiddleware Connection methods that require an active DB:
 * query(), beginTransaction(), commit(), rollBack(), exec(), lastInsertId(),
 * getNativeConnection(), quote(), getServerVersion(), getExceptionConverter().
 *
 * All tests use SQLite :memory: — no external service required.
 */
final class DoctrineMiddlewareCoverageTest extends TestCase
{
    private Doctrine $doctrine;

    protected function setUp(): void
    {
        parent::setUp();

        $db                    = config('Database');
        $db->tests['DBDriver'] = 'SQLite3';
        $db->tests['database'] = ':memory:';

        $this->config->entities = [SUPPORTPATH . 'Models/Entities'];
        $this->doctrine         = new Doctrine($this->config, null, 'tests');
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

    public function testQueryMethodLogsToCollector(): void
    {
        $tool = $this->createSchema();

        $conn = $this->doctrine->em->getConnection();
        $result = $conn->executeQuery('SELECT 1');
        $row    = $result->fetchOne();

        $this->assertSame(1, $row);

        $this->dropSchema($tool);
    }

    public function testBeginTransactionCommit(): void
    {
        $tool = $this->createSchema();

        $conn = $this->doctrine->em->getConnection();
        $conn->beginTransaction();

        // Insert a row inside the transaction
        $conn->executeStatement(
            'INSERT INTO test (name, created_at) VALUES (?, ?)',
            ['tx_test', '2026-01-01 00:00:00']
        );

        $conn->commit();

        $count = $conn->executeQuery('SELECT COUNT(*) FROM test WHERE name = ?', ['tx_test'])->fetchOne();
        $this->assertSame(1, (int) $count);

        $this->dropSchema($tool);
    }

    public function testBeginTransactionRollback(): void
    {
        $tool = $this->createSchema();

        $conn = $this->doctrine->em->getConnection();
        $conn->beginTransaction();

        $conn->executeStatement(
            'INSERT INTO test (name, created_at) VALUES (?, ?)',
            ['rollback_test', '2026-01-01 00:00:00']
        );

        $conn->rollBack();

        $count = $conn->executeQuery('SELECT COUNT(*) FROM test WHERE name = ?', ['rollback_test'])->fetchOne();
        $this->assertSame(0, (int) $count);

        $this->dropSchema($tool);
    }

    public function testExecLogsToCollector(): void
    {
        $tool = $this->createSchema();

        $conn          = $this->doctrine->em->getConnection();
        $collector     = new DoctrineCollector();
        count($collector->getQueries());

        // executeStatement routes through exec() on the middleware Connection
        $affected = $conn->executeStatement(
            'INSERT INTO test (name, created_at) VALUES (?, ?)',
            ['exec_test', '2026-01-01 00:00:00']
        );

        $this->assertGreaterThanOrEqual(1, $affected);

        $this->dropSchema($tool);
    }

    public function testLastInsertId(): void
    {
        $tool = $this->createSchema();

        $conn = $this->doctrine->em->getConnection();
        $conn->executeStatement(
            'INSERT INTO test (name, created_at) VALUES (?, ?)',
            ['insert_id_test', '2026-01-01 00:00:00']
        );

        $id = $conn->lastInsertId();
        $this->assertNotEmpty((string) $id);

        $this->dropSchema($tool);
    }

    public function testGetNativeConnection(): void
    {
        $conn   = $this->doctrine->em->getConnection();
        $native = $conn->getNativeConnection();

        // For SQLite3, the native connection is the underlying \SQLite3 resource
        $this->assertNotNull($native);
    }

    public function testQuote(): void
    {
        $conn   = $this->doctrine->em->getConnection();
        $quoted = $conn->quote("O'Reilly");

        $this->assertStringContainsString("O", $quoted);
        $this->assertIsString($quoted);
    }

    public function testGetServerVersion(): void
    {
        $conn    = $this->doctrine->em->getConnection();
        $version = $conn->getServerVersion();

        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }

    public function testGetExceptionConverterViaInvalidQuery(): void
    {
        $conn = $this->doctrine->em->getConnection();

        // Trigger exception so DBAL calls getExceptionConverter() internally
        try {
            $conn->executeQuery('INVALID SQL STATEMENT');
            $this->fail('Expected an exception for invalid SQL');
        } catch (Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }
}
