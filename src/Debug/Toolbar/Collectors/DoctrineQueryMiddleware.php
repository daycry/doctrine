<?php

namespace Daycry\Doctrine\Debug\Toolbar\Collectors;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
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

            public function connect(array $params): Connection
            {
                $conn      = $this->driver->connect($params);
                $collector = $this->collector;

                return new class ($conn, $collector) implements Connection {
                    private $conn;
                    private $collector;

                    public function __construct($conn, DoctrineCollector $collector)
                    {
                        $this->conn      = $conn;
                        $this->collector = $collector;
                    }

                    public function prepare(string $sql): Statement
                    {
                        return $this->conn->prepare($sql);
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

                    public function lastInsertId($name = null): string
                    {
                        return $this->conn->lastInsertId($name);
                    }

                    public function getNativeConnection()
                    {
                        return $this->conn->getNativeConnection();
                    }

                    public function exec(string $sql): int|string
                    {
                        return $this->conn->exec($sql);
                    }

                    public function quote(string $value): string
                    {
                        return $this->conn->quote($value);
                    }

                    public function getServerVersion(): string
                    {
                        return $this->conn->getServerVersion();
                    }

                    public function __call($name, $arguments)
                    {
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
