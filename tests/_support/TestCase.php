<?php

namespace Tests\Support;

use CodeIgniter\Settings\Settings;
use CodeIgniter\Settings\Config\Settings as SettingsConfig;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use Nexus\PHPUnit\Extension\Expeditable;

abstract class TestCase extends CIUnitTestCase
{
    use Expeditable;

    /**
     * @var Settings
     */
    protected $settings;

    /**
     * Sets up the ArrayHandler for faster & easier tests.
     */
    protected function setUp(): void
    {
        parent::setUp();
        /** @var SettingsConfig $config */
        $config           = config('Settings');
        $config->handlers = ['array'];
        $this->settings   = new Settings($config);

        Services::injectMock('settings', $this->settings);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->resetServices();
    }
}