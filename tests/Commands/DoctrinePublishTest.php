<?php

declare(strict_types=1);

namespace Tests\Commands;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Autoload;

/**
 * Happy-path coverage for `php spark doctrine:publish`.
 *
 * The command shells out to CLI::write/CLI::error and uses `exit()` on the
 * error branches, which makes the failure paths intractable to assert in
 * PHPUnit (they would terminate the test runner). This test therefore
 * focuses on the normal path: read `cli-config.php` and `Config/Doctrine.php`
 * from the package and write transformed copies into the host application.
 *
 * @internal
 */
final class DoctrinePublishTest extends CIUnitTestCase
{
    private string $cliConfigDest;
    private string $configDest;
    private ?string $cliConfigBackup  = null;
    private ?string $configBackup     = null;
    private bool $cliConfigPreExisted = false;
    private bool $configPreExisted    = false;

    protected function setUp(): void
    {
        parent::setUp();

        $autoload = new Autoload();
        $appPath  = $autoload->psr4[APP_NAMESPACE] ?? null;
        $this->assertNotNull(
            $appPath,
            'APP_NAMESPACE must be present in Config\\Autoload::$psr4 for this test',
        );

        // Mirror the paths the command writes to.
        $this->configDest    = rtrim($appPath, '/\\') . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Doctrine.php';
        $this->cliConfigDest = dirname(rtrim($appPath, '/\\')) . DIRECTORY_SEPARATOR . 'cli-config.php';

        // Snapshot any pre-existing files so we can restore them in tearDown.
        $this->cliConfigPreExisted = file_exists($this->cliConfigDest);
        if ($this->cliConfigPreExisted) {
            $this->cliConfigBackup = (string) file_get_contents($this->cliConfigDest);
            unlink($this->cliConfigDest);
        }

        $this->configPreExisted = file_exists($this->configDest);
        if ($this->configPreExisted) {
            $this->configBackup = (string) file_get_contents($this->configDest);
            unlink($this->configDest);
        }
    }

    protected function tearDown(): void
    {
        // Remove anything the command created.
        if (file_exists($this->cliConfigDest)) {
            unlink($this->cliConfigDest);
        }
        if (file_exists($this->configDest)) {
            unlink($this->configDest);
        }

        // Restore the original snapshots.
        if ($this->cliConfigBackup !== null) {
            file_put_contents($this->cliConfigDest, $this->cliConfigBackup);
        }
        if ($this->configBackup !== null) {
            file_put_contents($this->configDest, $this->configBackup);
        }

        parent::tearDown();
    }

    public function testPublishCreatesCliConfigAndAppConfig(): void
    {
        // CLI::write/error go straight to STDOUT/STDERR, so we cannot capture the
        // success banner with ob_start(); instead we verify the filesystem side
        // effects which are what callers actually depend on.
        command('doctrine:publish');

        // Both target files must exist after publishing.
        $this->assertFileExists($this->cliConfigDest);
        $this->assertFileExists($this->configDest);

        // The CLI config is copied verbatim from the package.
        $this->assertStringContainsString('ConsoleRunner::run', file_get_contents($this->cliConfigDest));

        // The Config\Doctrine class is rewritten with the host namespace and parent.
        $configContent = file_get_contents($this->configDest);
        $this->assertStringContainsString('namespace Config', $configContent);
        $this->assertStringContainsString('extends \\Daycry\\Doctrine\\Config\\Doctrine', $configContent);
        $this->assertStringNotContainsString('namespace Daycry\\Doctrine\\Config', $configContent);
        $this->assertStringNotContainsString('extends BaseConfig', $configContent);
    }
}
