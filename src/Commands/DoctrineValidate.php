<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Commands;

use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand;

/**
 * Spark wrapper for `orm:validate-schema`: validates the mapping and that the
 * database schema is in sync with it.
 */
class DoctrineValidate extends DoctrineCommand
{
    protected $name        = 'doctrine:validate';
    protected $description = 'Validate the Doctrine mapping files and database schema.';
    protected $usage       = 'doctrine:validate [--group <db_group>]';
    protected $arguments   = [];
    protected $options     = [
        '--group' => 'Database group (default: default group).',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        return $this->runOrmCommand(
            new ValidateSchemaCommand($this->provider($this->dbGroup($params))),
            'orm:validate-schema',
        );
    }
}
