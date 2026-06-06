<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Doctrine\Config\Services;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Base class for the Spark wrappers around the Doctrine ORM console commands.
 * Resolves the EntityManager for the requested database group (via the
 * `--group` option) and runs an ORM console command through a throwaway Symfony
 * Console Application, forwarding its output to the CodeIgniter CLI.
 */
abstract class DoctrineCommand extends BaseCommand
{
    protected $group = 'Doctrine';

    /**
     * Resolve the database group from the `--group` option (null = default).
     *
     * @param array<int|string, string|null> $params
     */
    protected function dbGroup(array $params): ?string
    {
        $group = $params['group'] ?? CLI::getOption('group');

        return is_string($group) && $group !== '' ? $group : null;
    }

    /**
     * Build a single-manager provider for the given database group.
     */
    protected function provider(?string $dbGroup): SingleManagerProvider
    {
        return new SingleManagerProvider(Services::doctrine(true, $dbGroup)->getEm());
    }

    /**
     * Run an ORM console command and stream its output to the CLI.
     *
     * @param array<string, bool|string> $args extra Console input (e.g. ['--force' => true])
     */
    protected function runOrmCommand(Command $command, string $name, array $args = []): int
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand($command);

        $output = new BufferedOutput();
        $exit   = $application->run(new ArrayInput(['command' => $name] + $args), $output);

        CLI::write(rtrim($output->fetch()));

        return $exit;
    }
}
