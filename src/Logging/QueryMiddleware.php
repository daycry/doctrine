<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Logging;

use Closure;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

/**
 * Shared instrumentation for successful DBAL queries.
 */
abstract class QueryMiddleware implements Middleware
{
    /**
     * @param array<int|string, mixed> $params
     */
    abstract protected function onQuery(string $sql, array $params, float $start, float $duration): void;

    public function wrap(Driver $driver): Driver
    {
        return new class ($driver, $this->onQuery(...)) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly Closure $record)
            {
                parent::__construct($driver);
            }

            public function connect(array $params): Connection
            {
                return new class (parent::connect($params), $this->record) extends AbstractConnectionMiddleware {
                    public function __construct(private readonly Connection $connection, private readonly Closure $record)
                    {
                        parent::__construct($connection);
                    }

                    public function prepare(string $sql): Statement
                    {
                        return new class (parent::prepare($sql), $sql, $this->record) extends AbstractStatementMiddleware {
                            /**
                             * @var array<int|string, mixed>
                             */
                            private array $params = [];

                            public function __construct(Statement $statement, private readonly string $sql, private readonly Closure $record)
                            {
                                parent::__construct($statement);
                            }

                            public function bindValue(int|string $param, mixed $value, ParameterType $type): void
                            {
                                parent::bindValue($param, $value, $type);
                                $this->params[$param] = $value;
                            }

                            public function execute(): Result
                            {
                                $start  = microtime(true);
                                $timer  = hrtime(true);
                                $result = parent::execute();
                                ($this->record)($this->sql, $this->params, $start, (float) (hrtime(true) - $timer) / 1e9);

                                return $result;
                            }
                        };
                    }

                    public function query(string $sql): Result
                    {
                        $start  = microtime(true);
                        $timer  = hrtime(true);
                        $result = parent::query($sql);
                        ($this->record)($sql, [], $start, (float) (hrtime(true) - $timer) / 1e9);

                        return $result;
                    }

                    public function exec(string $sql): int|string
                    {
                        $start  = microtime(true);
                        $timer  = hrtime(true);
                        $result = parent::exec($sql);
                        ($this->record)($sql, [], $start, (float) (hrtime(true) - $timer) / 1e9);

                        return $result;
                    }

                    /**
                     * @param array<int|string, mixed> $arguments
                     */
                    public function __call(string $name, array $arguments): mixed
                    {
                        return $this->connection->{$name}(...$arguments);
                    }
                };
            }
        };
    }
}
