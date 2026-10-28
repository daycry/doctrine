<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4\Commands;

use CodeIgniter\CLI\CLI;
use Daycry\Doctrine\CodeIgniter4\Config\Services;
use Daycry\Doctrine\Tools\CacheWarmer;
use Override;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Throwable;

class DoctrineCacheWarmup extends DoctrineCommand
{
    #[Override]
    protected $name = 'doctrine:cache:warmup';

    #[Override]
    protected $description = 'Warm Doctrine metadata without changing the database schema.';

    #[Override]
    protected $usage = 'doctrine:cache:warmup [--group <db_group>]';

    #[Override]
    protected $options = ['--group' => 'Database group to warm (default: effective default group).'];

    public function run(array $params): int
    {
        try {
            $doctrine = Services::doctrine(true, $this->dbGroup($params));
            $report   = new CacheWarmer()->warm($doctrine);
            CLI::write(sprintf('Loaded %d metadata classes; native lazy objects need no proxy files.', $report['metadata']));
            $pool = $doctrine->getEm()->getConfiguration()->getMetadataCache();
            if ($pool === null || $pool instanceof ArrayAdapter) {
                CLI::write('Metadata cache is disabled or process-local; use file, Redis or Memcached for persistent warmup.', 'yellow');
            }

            return 0;
        } catch (Throwable $error) {
            CLI::error('Doctrine cache warmup failed (' . $error::class . '). Check configuration or run doctrine:doctor.');

            return 1;
        }
    }
}
