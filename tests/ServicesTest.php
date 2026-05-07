<?php

declare(strict_types=1);

namespace Tests;

use Daycry\Doctrine\Config\Services;
use Daycry\Doctrine\Doctrine;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class ServicesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Avoid touching MySQL: use SQLite in-memory.
        $db                    = config('Database');
        $db->tests['DBDriver'] = 'SQLite3';
        $db->tests['database'] = ':memory:';
    }

    public function testDoctrineServiceReturnsSharedInstance(): void
    {
        $a = Services::doctrine(true, 'tests');
        $b = Services::doctrine(true, 'tests');

        $this->assertInstanceOf(Doctrine::class, $a);
        $this->assertSame($a, $b, 'Shared mode should return the same instance for the same group.');
    }

    public function testDoctrineServiceUnsharedReturnsFreshInstance(): void
    {
        $a = Services::doctrine(false, 'tests');
        $b = Services::doctrine(false, 'tests');

        $this->assertInstanceOf(Doctrine::class, $a);
        $this->assertNotSame($a, $b, 'Unshared mode should always return a fresh instance.');
    }

    public function testResetDoctrineDropsCachedInstance(): void
    {
        $a = Services::doctrine(true, 'tests');
        Services::resetDoctrine('tests');
        $b = Services::doctrine(true, 'tests');

        $this->assertNotSame($a, $b, 'After reset, a new shared instance must be created.');
    }

    public function testDoctrineCollectorReturnsSharedInstance(): void
    {
        $a = Services::doctrineCollector();
        $b = Services::doctrineCollector();

        $this->assertSame($a, $b);
    }

    public function testDoctrineCollectorUnsharedReturnsFresh(): void
    {
        $a = Services::doctrineCollector(false);
        $b = Services::doctrineCollector(false);

        $this->assertNotSame($a, $b);
    }
}
