<?php

namespace Daycry\Doctrine\Collectors;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;

class DoctrineQueryMiddleware implements Middleware
{
    protected $collector;

    public function __construct(DoctrineCollector $collector)
    {
        $this->collector = $collector;
    }

    public function wrap(Driver $driver): Driver
    {
        $collector = $this->collector;

        return new class ($driver, $collector) implements Driver {
            private $driver;
            private $collector;

            public function __construct(Driver $driver, DoctrineCollector $collector)
            {
                $this->driver    = $driver;
                $this->collector = $collector;
            }

            public function connect(array $params): DriverConnection
            {
                $conn      = $this->driver->connect($params);
                $collector = $this->collector;

                return new class ($conn, $collector) implements DriverConnection {
                    private $conn;
                    private $collector;

                    public function __construct(DriverConnection $conn, DoctrineCollector $collector)
                    {
                        $this->conn      = $conn;
                        $this->collector = $collector;
                    }

                    public function prepare(string $sql): Driver\Statement
                    {
                        $start = microtime(true);
                        $stmt  = $this->conn->prepare($sql);
                        $this->collector->addQuery([
                            'sql'      => $sql,
                            'params'   => [],
                            'types'    => [],
                            'start'    => $start,
                            'end'      => null,
                            'duration' => null,
                        ]);

                        return $stmt;
                    }

                    public function query(string $sql): Driver\Result
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
                            'duration' => $end - $start,
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

                    public function quote(string $value): string
                    {
                        return $this->conn->quote($value);
                    }

                    public function exec(string $sql): int
                    {
                        return $this->conn->exec($sql);
                    }

                    public function getNativeConnection(): object
                    {
                        return $this->conn->getNativeConnection();
                    }

                    public function getServerVersion(): string
                    {
                        return method_exists($this->conn, 'getServerVersion') ? $this->conn->getServerVersion() : 'unknown';
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
