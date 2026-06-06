<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Logging;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Psr\Log\LoggerInterface;

/**
 * DBAL middleware that logs executed queries to a PSR-3 logger, optionally only
 * those slower than a configured threshold. Unlike the Debug Toolbar collector
 * this runs in any environment, giving production a slow-query log.
 */
class QueryLoggerMiddleware implements Middleware
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
            'duration_ms' => round($durationSeconds * 1000, 3),
        ]);
    }

    public function wrap(Driver $driver): Driver
    {
        $logger    = $this->logger;
        $threshold = $this->slowThreshold;
        $level     = $this->level;

        return new class ($driver, $logger, $threshold, $level) implements Driver {
            public function __construct(
                private readonly Driver $driver,
                private readonly LoggerInterface $logger,
                private readonly float $threshold,
                private readonly string $level,
            ) {
            }

            public function connect(array $params): Connection
            {
                $conn = $this->driver->connect($params);

                return new class ($conn, $this->logger, $this->threshold, $this->level) implements Connection {
                    public function __construct(
                        private readonly Connection $conn,
                        private readonly LoggerInterface $logger,
                        private readonly float $threshold,
                        private readonly string $level,
                    ) {
                    }

                    public function prepare(string $sql): Statement
                    {
                        return new class ($this->conn->prepare($sql), $sql, $this->logger, $this->threshold, $this->level) implements Statement {
                            /**
                             * @var array<int|string, mixed>
                             */
                            private array $params = [];

                            public function __construct(
                                private readonly Statement $stmt,
                                private readonly string $sql,
                                private readonly LoggerInterface $logger,
                                private readonly float $threshold,
                                private readonly string $level,
                            ) {
                            }

                            public function bindValue(int|string $param, mixed $value, ParameterType $type): void
                            {
                                $this->params[$param] = $value;
                                $this->stmt->bindValue($param, $value, $type);
                            }

                            public function execute(): Result
                            {
                                $start  = microtime(true);
                                $result = $this->stmt->execute();
                                QueryLoggerMiddleware::record($this->logger, $this->level, $this->threshold, $this->sql, $this->params, microtime(true) - $start);

                                return $result;
                            }
                        };
                    }

                    public function query(string $sql): Result
                    {
                        $start  = microtime(true);
                        $result = $this->conn->query($sql);
                        QueryLoggerMiddleware::record($this->logger, $this->level, $this->threshold, $sql, [], microtime(true) - $start);

                        return $result;
                    }

                    public function exec(string $sql): int|string
                    {
                        $start  = microtime(true);
                        $result = $this->conn->exec($sql);
                        QueryLoggerMiddleware::record($this->logger, $this->level, $this->threshold, $sql, [], microtime(true) - $start);

                        return $result;
                    }

                    public function beginTransaction(): void
                    {
                        $this->conn->beginTransaction();
                    }

                    public function commit(): void
                    {
                        $this->conn->commit();
                    }

                    public function rollBack(): void
                    {
                        $this->conn->rollBack();
                    }

                    public function lastInsertId(): int|string
                    {
                        return $this->conn->lastInsertId();
                    }

                    public function getNativeConnection()
                    {
                        return $this->conn->getNativeConnection();
                    }

                    public function quote(string $value): string
                    {
                        return $this->conn->quote($value);
                    }

                    public function getServerVersion(): string
                    {
                        return $this->conn->getServerVersion();
                    }

                    /**
                     * @param array<int|string, mixed> $arguments
                     */
                    public function __call(string $name, array $arguments): mixed
                    {
                        /** @var mixed */
                        return $this->conn->{$name}(...$arguments);
                    }
                };
            }

            public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
            {
                return $this->driver->getDatabasePlatform($versionProvider);
            }

            public function getExceptionConverter(): ExceptionConverter
            {
                return $this->driver->getExceptionConverter();
            }
        };
    }
}
