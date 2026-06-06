<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Commands;

use CodeIgniter\CLI\CLI;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;

/**
 * Spark wrapper for `orm:schema-tool:update`. Without --force it only dumps the
 * SQL that would bring the database schema in sync with the mappings.
 */
class DoctrineSchemaUpdate extends DoctrineCommand
{
    protected $name        = 'doctrine:schema:update';
    protected $description = 'Update the database schema to match the entity mappings.';
    protected $usage       = 'doctrine:schema:update [--force] [--dump-sql] [--group <db_group>]';
    protected $arguments   = [];
    protected $options     = [
        '--force'    => 'Apply the schema changes to the database.',
        '--dump-sql' => 'Print the SQL that would be executed (default when --force is absent).',
        '--group'    => 'Database group (default: default group).',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $force   = array_key_exists('force', $params)    || CLI::getOption('force') !== null;
        $dumpSql = array_key_exists('dump-sql', $params) || CLI::getOption('dump-sql') !== null;

        $args = [];
        if ($force) {
            $args['--force'] = true;
        }
        // orm:schema-tool:update requires one of --force/--dump-sql; default to a
        // safe, non-destructive dry run when neither is given.
        if ($dumpSql || ! $force) {
            $args['--dump-sql'] = true;
        }

        return $this->runOrmCommand(
            new UpdateCommand($this->provider($this->dbGroup($params))),
            'orm:schema-tool:update',
            $args,
        );
    }
}
