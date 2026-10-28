<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Logging;

use Psr\Log\LoggerInterface;

/**
 * DBAL middleware that logs executed queries to a PSR-3 logger, optionally only
 * those slower than a configured threshold. Unlike the Debug Toolbar collector
 * this runs in any environment, giving production a slow-query log.
 */
class QueryLoggerMiddleware extends QueryMiddleware
{
    public function __construct(
        protected LoggerInterface $logger,
        protected float $slowThreshold = 0.0,
        protected string $level = 'info',
    ) {
    }

    /**
     * Emit a log record when the query is at least as slow as the threshold.
     *
     * @param array<int|string, mixed> $params
     */
    public static function record(LoggerInterface $logger, string $level, float $threshold, string $sql, array $params, float $durationSeconds): void
    {
        if ($durationSeconds < $threshold) {
            return;
        }

        $logger->log($level, 'Doctrine query', [
            'sql'         => $sql,
            'params'      => $params,
            'duration_ms' => round($durationSeconds * 1000.0, 3),
        ]);
    }

    protected function onQuery(string $sql, array $params, float $start, float $duration): void
    {
        self::record($this->logger, $this->level, $this->slowThreshold, $sql, $params, $duration);
    }
}
