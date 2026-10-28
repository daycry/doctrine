<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4\Commands;

use Doctrine\ORM\Tools\Console\Command\InfoCommand;
use Override;

/**
 * Spark wrapper for `orm:info`: lists the mapped entities and reports mapping
 * errors.
 */
class DoctrineInfo extends DoctrineCommand
{
    #[Override]
    protected $name = 'doctrine:info';

    #[Override]
    protected $description = 'Show mapped entities for a database group.';

    #[Override]
    protected $usage = 'doctrine:info [--group <db_group>]';

    #[Override]
    protected $arguments = [];

    #[Override]
    protected $options = [
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
