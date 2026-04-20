<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Debug\Toolbar\Collectors;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;

class DoctrineQueryMiddleware implements Middleware
{
    protected DoctrineCollector $collector;

    public function __construct(DoctrineCollector $collector)
    {
        $this->collector = $collector;
    }

    public function wrap(Driver $driver): Driver
    {
        $collector = $this->collector;

        return new class ($driver, $collector) implements Driver {
            private Driver $driver;
            private DoctrineCollector $collector;

            public function __construct(Driver $driver, DoctrineCollector $collector)
            {
                $this->driver    = $driver;
                $this->collector = $collector;
            }

            public function connect(array $params): Connection
            {
                $conn      = $this->driver->connect($params);
                $collector = $this->collector;

                return new class ($conn, $collector) implements Connection {
                    private Connection $conn;
                    private DoctrineCollector $collector;

                    public function __construct(Connection $conn, DoctrineCollector $collector)
                    {
                        $this->conn      = $conn;
                        $this->collector = $collector;
                    }

                    public function prepare(string $sql): Statement
                    {
                        $innerStmt = $this->conn->prepare($sql);
                        $collector = $this->collector;

                        return new class ($innerStmt, $sql, $collector) implements Statement {
                            private Statement $innerStmt;
                            private string $sql;
                            private DoctrineCollector $collector;

                            /**
                             * @var array<int|string, mixed>
                             */
                            private array $params = [];

                            public function __construct(Statement $innerStmt, string $sql, DoctrineCollector $collector)
                            {
                                $this->innerStmt = $innerStmt;
                                $this->sql       = $sql;
                                $this->collector = $collector;
                            }

                            public function bindValue(int|string $param, mixed $value, ParameterType $type): void
                            {
                                $this->params[$param] = $value;
                                $this->innerStmt->bindValue($param, $value, $type);
                            }

                            public function execute(): Result
                            {
                                $start  = microtime(true);
                                $result = $this->innerStmt->execute();
                                $end    = microtime(true);
                                $this->collector->addQuery([
                                    'sql'      => $this->sql,
                                    'params'   => $this->params,
                                    'types'    => [],
                                    'start'    => $start,
                                    'end'      => $end,
                                    'duration' => ($end - $start) * 1000,
                                ]);

                                return $result;
                            }
                        };
                    }

                    public function query(string $sql): Result
                    {
                        $start  = microtime(true);
                        $result = $this->conn->query($sql);
                        $end    = microtime(true);
                        $this->collector->addQuery([
                            'sql'      => $sql,
                            'params'   => [],
                            'types'    => [],
                            'start'    => $start,
                            'end'      => $end,
                            'duration' => ($end - $start) * 1000, // Convert to milliseconds
                        ]);

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

                    public function exec(string $sql): int|string
                    {
                        $start  = microtime(true);
                        $result = $this->conn->exec($sql);
                        $end    = microtime(true);
                        $this->collector->addQuery([
                            'sql'      => $sql,
                            'params'   => [],
                            'types'    => [],
                            'start'    => $start,
                            'end'      => $end,
                            'duration' => ($end - $start) * 1000,
                        ]);

                        return $result;
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
