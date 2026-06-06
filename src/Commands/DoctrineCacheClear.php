<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Commands;

use CodeIgniter\CLI\CLI;
use Daycry\Doctrine\Config\Services;

/**
 * Clears the Doctrine query, result and metadata caches for a database group.
 */
class DoctrineCacheClear extends DoctrineCommand
{
    protected $name        = 'doctrine:cache:clear';
    protected $description = 'Clear the Doctrine query, result and metadata caches.';
    protected $usage       = 'doctrine:cache:clear [--group <db_group>]';
    protected $arguments   = [];
    protected $options     = [
        '--group' => 'Database group whose caches to clear (default: default group).',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $config = Services::doctrine(true, $this->dbGroup($params))->getEm()->getConfiguration();

        $pools = [
            'query'    => $config->getQueryCache(),
            'result'   => $config->getResultCache(),
            'metadata' => $config->getMetadataCache(),
        ];

        $cleared = [];

        foreach ($pools as $label => $pool) {
            if ($pool !== null) {
                $pool->clear();
                $cleared[] = $label;
            }
        }

        CLI::write('Cleared Doctrine caches: ' . ($cleared === [] ? 'none configured' : implode(', ', $cleared)), 'green');

        return 0;
    }
}
