<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Autoload;
use Override;
use Throwable;

class DoctrinePublish extends BaseCommand
{
    #[Override]
    protected $group = 'Doctrine';

    #[Override]
    protected $name = 'doctrine:publish';

    #[Override]
    protected $description = 'Doctrine config file publisher.';

    /**
     * The path to Daycry\Doctrine\src directory.
     *
     * @var string
     */
    protected $sourcePath;

    // --------------------------------------------------------------------
    /**
     * Copy config file
     */
    public function run(array $params): void
    {
        $this->determineSourcePath();
        $this->publishCliConfig();
        $this->publishConfig();
        CLI::write('Config file was successfully generated.', 'green');
    }

    // --------------------------------------------------------------------
    /**
     * Determines the current source path from which all other files are located.
     */
    protected function determineSourcePath(): void
    {
        $this->sourcePath = realpath(__DIR__ . '/../');
        if ($this->sourcePath === '/' || empty($this->sourcePath)) {
            CLI::error('Unable to determine the correct source directory. Bailing.');

            exit(1);
        }
    }

    // --------------------------------------------------------------------
    /**
     * Publish cli config file.
     */
    protected function publishCliConfig(): void
    {
        $path    = "{$this->sourcePath}/cli-config.php";
        $content = file_get_contents($path);
        if ($content === false) {
            CLI::error("Unable to read source file: {$path}");

            exit(1);
        }
        $this->writeFile('../cli-config.php', $content);
    }

    // --------------------------------------------------------------------
    /**
     * Publish config file.
     */
    protected function publishConfig(): void
    {
        $content = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Config;

            class Doctrine extends \Daycry\Doctrine\CodeIgniter4\Config\Doctrine
            {
                // Override only the settings that differ from the package defaults.
            }
            PHP;
        $content .= "\n";
        $this->writeFile('Config/Doctrine.php', $content);
    }

    // --------------------------------------------------------------------
    /**
     * Write a file, catching any exceptions and showing a nicely formatted error.
     */
    protected function writeFile(string $path, string $content): void
    {
        $config = new Autoload();

        $appPath = $config->psr4[APP_NAMESPACE] ?? null;
        if (is_array($appPath)) {
            $appPath = $appPath[0] ?? null;
        }
        if (! is_string($appPath) || $appPath === '') {
            CLI::error('APP_NAMESPACE "' . APP_NAMESPACE . '" not found in autoload psr4 configuration.');

            exit(1);
        }
        $appPath   = rtrim($appPath, '/\\') . DIRECTORY_SEPARATOR;
        $directory = dirname($appPath . $path);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        if (file_exists($appPath . $path) && CLI::prompt('Config file already exists, do you want to replace it?', ['y', 'n']) === 'n') {
            CLI::error('Cancelled');

            exit(1);
        }

        try {
            /** @psalm-suppress UndefinedFunction CI4 filesystem helper */
            write_file($appPath . $path, $content);
        } catch (Throwable $e) {
            $this->showError($e);

            exit(1);
        }
        $path = str_replace($appPath, '', $path);
        CLI::write(CLI::color('Created: ', 'yellow') . $path);
    }
    // --------------------------------------------------------------------
}
