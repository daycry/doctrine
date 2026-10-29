<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4\Commands;

use CodeIgniter\CLI\CLI;
use Daycry\Doctrine\CodeIgniter4\Config\DatabaseGroup;
use Daycry\Doctrine\CodeIgniter4\Config\Services;
use Daycry\Doctrine\Tools\Diagnostics;
use Override;
use Throwable;

class DoctrineDoctor extends DoctrineCommand
{
    #[Override]
    protected $name = 'doctrine:doctor';

    #[Override]
    protected $description = 'Check Doctrine connectivity, mappings and cache pools.';

    #[Override]
    protected $usage = 'doctrine:doctor [--group <db_group>] [--json]';

    #[Override]
    protected $options = [
        '--group' => 'Database group to diagnose (default: effective default group).',
        '--json'  => 'Print a machine-readable readiness report.',
    ];

    public function run(array $params): int
    {
        $group = $this->dbGroup($params);

        try {
            $group  = DatabaseGroup::resolve($group);
            $report = new Diagnostics()->inspect(Services::doctrine(true, $group));
        } catch (Throwable $error) {
            $report = ['healthy' => false, 'checks' => ['initialization' => ['status' => 'error', 'detail' => 'Doctrine initialization failed (' . $error::class . '). Check group, credentials and mapping paths.']]];
        }
        $report = ['group' => $group] + $report;
        if (array_key_exists('json', $params) || CLI::getOption('json') !== null) {
            CLI::write(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            CLI::write('Doctrine diagnostics: ' . ($group ?? 'default'));

            foreach ($report['checks'] as $name => $check) {
                CLI::write('[' . strtoupper($check['status']) . '] ' . $name . ': ' . $check['detail']);
            }
        }

        return $report['healthy'] ? 0 : 1;
    }
}
