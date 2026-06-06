<?php

declare(strict_types=1);

namespace Tests\Commands;

use Daycry\Doctrine\Commands\DoctrineInfo;
use Daycry\Doctrine\Commands\DoctrineSchemaUpdate;
use Daycry\Doctrine\Commands\DoctrineValidate;
use Daycry\Doctrine\Config\Services;
use Doctrine\ORM\Tools\SchemaTool;
use Tests\Support\TestCase;

/**
 * Smoke tests for the ORM-console Spark wrappers: verify they resolve the
 * EntityManager for the requested group, bridge to the ORM console command and
 * exit successfully.
 *
 * @internal
 */
final class DoctrineConsoleCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $db                    = config('Database');
        $db->tests['DBDriver'] = 'SQLite3';
        $db->tests['database'] = ':memory:';

        Services::resetDoctrine('tests');
    }

    protected function tearDown(): void
    {
        Services::resetDoctrine('tests');
        parent::tearDown();
    }

    public function testInfoWrapperListsEntitiesAndExitsZero(): void
    {
        $command = new DoctrineInfo(service('logger'), service('commands'));

        $this->assertSame(0, $command->run(['group' => 'tests']));
    }

    public function testSchemaUpdateWrapperDumpsSqlAndExitsZero(): void
    {
        $command = new DoctrineSchemaUpdate(service('logger'), service('commands'));

        // No --force: defaults to a non-destructive --dump-sql dry run.
        $this->assertSame(0, $command->run(['group' => 'tests']));
    }

    public function testSchemaUpdateWrapperWithForceAppliesDdl(): void
    {
        $command = new DoctrineSchemaUpdate(service('logger'), service('commands'));

        $this->assertSame(0, $command->run(['group' => 'tests', 'force' => null]));
    }

    public function testValidateWrapperExitsZeroOnSyncedSchema(): void
    {
        // Create the full schema on the shared 'tests' instance the command reuses,
        // so orm:validate-schema finds the database in sync and exits 0.
        $em   = Services::doctrine(true, 'tests')->getEm();
        $tool = new SchemaTool($em);
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $command = new DoctrineValidate(service('logger'), service('commands'));

        $this->assertSame(0, $command->run(['group' => 'tests']));
    }
}
