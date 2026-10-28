<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Console;

use Daycry\Doctrine\Doctrine;
use Daycry\Doctrine\ManagerRegistry;
use Daycry\Doctrine\Tools\CacheWarmer;
use Daycry\Doctrine\Tools\Diagnostics;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ApplicationFactory
{
    public static function create(Doctrine|ManagerRegistry $managers): Application
    {
        $registry = $managers instanceof Doctrine
            ? new ManagerRegistry(['default' => static fn (): Doctrine => $managers])
            : $managers;
        $application = ConsoleRunner::createApplication($registry);
        $application->addCommand(new class ($registry) extends Command {
            public function __construct(private readonly ManagerRegistry $registry)
            {
                parent::__construct('doctrine:doctor');
            }

            protected function configure(): void
            {
                $this->setDescription('Check connection, mapping and cache access.')
                    ->addOption('em', null, InputOption::VALUE_REQUIRED, 'Named manager')
                    ->addOption('json', null, InputOption::VALUE_NONE, 'Machine-readable report');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                try {
                    $report = new Diagnostics()->inspect($this->registry->get($input->getOption('em')));
                } catch (Throwable $error) {
                    $report = ['healthy' => false, 'checks' => ['initialization' => [
                        'status' => 'error', 'detail' => 'Initialization failed (' . $error::class . ').',
                    ]]];
                }
                if ($input->getOption('json') === true) {
                    $output->writeln(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
                } else {
                    foreach ($report['checks'] as $name => $check) {
                        $output->writeln($name . ': ' . $check['status'] . ' - ' . $check['detail']);
                    }
                }

                return $report['healthy'] ? self::SUCCESS : self::FAILURE;
            }
        });
        $application->addCommand(new class ($registry) extends Command {
            public function __construct(private readonly ManagerRegistry $registry)
            {
                parent::__construct('doctrine:cache:warmup');
            }

            protected function configure(): void
            {
                $this->setDescription('Validate mappings and load metadata into cache.')
                    ->addOption('em', null, InputOption::VALUE_REQUIRED, 'Named manager');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                try {
                    $doctrine = $this->registry->get($input->getOption('em'));
                    $report   = new CacheWarmer()->warm($doctrine);
                    $output->writeln('Loaded ' . $report['metadata'] . ' metadata classes.');
                    $pool = $doctrine->getEm()->getConfiguration()->getMetadataCache();
                    if ($pool === null || $pool instanceof ArrayAdapter) {
                        $output->writeln('Metadata cache is disabled or process-local; use a persistent pool for deployment warmup.');
                    }

                    return self::SUCCESS;
                } catch (Throwable $error) {
                    $output->writeln('Warmup failed (' . $error::class . '). Check configuration and cache access.');

                    return self::FAILURE;
                }
            }
        });

        return $application;
    }
}
