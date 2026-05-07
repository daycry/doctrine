<?php

declare(strict_types=1);

namespace Tests\Libraries;

use CodeIgniter\Cache\Exceptions\CacheException;
use Daycry\Doctrine\Libraries\InitializesNativeClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Covers the two error paths in `InitializesNativeClient::bootClient()`:
 *
 *  - Required PHP extension is not loaded → CacheException with extension name.
 *  - Underlying initialize() throws → CacheException wrapping the original.
 *
 * @internal
 */
final class InitializesNativeClientTest extends TestCase
{
    public function testBootClientThrowsWhenExtensionMissing(): void
    {
        $fixture = new class () {
            use InitializesNativeClient;

            public function initialize(): void
            {
                // never reached when ext is missing
            }

            public function trigger(string $extension, string $driver): void
            {
                $this->bootClient($extension, $driver);
            }
        };

        $this->expectException(CacheException::class);
        $this->expectExceptionMessageMatches('/extension not loaded/i');

        $fixture->trigger('__definitely_not_a_real_extension__', 'FakeDriver');
    }

    public function testBootClientWrapsInitializationException(): void
    {
        $fixture = new class () {
            use InitializesNativeClient;

            public function initialize(): never
            {
                throw new RuntimeException('boom');
            }

            public function trigger(string $extension, string $driver): void
            {
                $this->bootClient($extension, $driver);
            }
        };

        try {
            // `json` is part of PHP core, so the extension check passes and
            // the test reaches the try/catch branch.
            $fixture->trigger('json', 'FakeDriver');
            $this->fail('Expected CacheException was not thrown.');
        } catch (CacheException $e) {
            $this->assertStringContainsString('Failed to connect to FakeDriver', $e->getMessage());
            $this->assertStringContainsString('boom', $e->getMessage());

            $previous = $e->getPrevious();
            $this->assertInstanceOf(RuntimeException::class, $previous);
            $this->assertSame('boom', $previous->getMessage());
        }
    }
}
