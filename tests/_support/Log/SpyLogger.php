<?php

declare(strict_types=1);

namespace Tests\Support\Log;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * PSR-3 logger spy that records every log call, used to verify query logging.
 */
final class SpyLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string, context: array<array-key, mixed>}>
     */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
