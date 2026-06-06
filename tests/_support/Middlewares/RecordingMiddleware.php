<?php

declare(strict_types=1);

namespace Tests\Support\Middlewares;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * DBAL middleware fixture that records when its wrap() is invoked, used to
 * verify Config\Doctrine::$dbalMiddlewares composition.
 */
final class RecordingMiddleware implements Middleware
{
    public static bool $wrapped = false;

    public function wrap(Driver $driver): Driver
    {
        self::$wrapped = true;

        return $driver;
    }
}
