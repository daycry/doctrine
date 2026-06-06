<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Commands;

use Doctrine\ORM\Tools\Console\Command\InfoCommand;

/**
 * Spark wrapper for `orm:info`: lists the mapped entities and reports mapping
 * errors.
 */
class DoctrineInfo extends DoctrineCommand
{
    protected $name        = 'doctrine:info';
    protected $description = 'Show mapped entities for a database group.';
    protected $usage       = 'doctrine:info [--group <db_group>]';
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
            new InfoCommand($this->provider($this->dbGroup($params))),
            'orm:info',
        );
    }
}
