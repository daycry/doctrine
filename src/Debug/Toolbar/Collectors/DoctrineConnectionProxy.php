<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Debug\Toolbar\Collectors;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;

/**
 * Proxy for Doctrine DBAL Connection to log queries for the Debug Toolbar.
 */
class DoctrineConnectionProxy
{
    private DoctrineCollector $collector;
    private Connection $conn;

    public function __construct(Connection $conn, DoctrineCollector $collector)
    {
        $this->conn      = $conn;
        $this->collector = $collector;
    }

    /**
     * Executes an SQL query and logs it.
     *
     * @param mixed      $sql
     * @param mixed      $types
     * @param mixed|null $queryCacheProfile
     */
    public function executeQuery($sql, array $params = [], $types = [], $queryCacheProfile = null): Result
    {
        $start  = microtime(true);
        $result = $this->conn->executeQuery($sql, $params, $types, $queryCacheProfile);
        $end    = microtime(true);
        $this->collector->addQuery([
            'sql'         => $sql,
            'params'      => $params,
            'types'       => $types,
            'start'       => $start,
            'end'         => $end,
            'duration'    => $end - $start,
            'executionMS' => ($end - $start) * 1000,
        ]);

        return $result;
    }

    /**
     * Executes an SQL statement and logs it.
     *
     * @param mixed $sql
     */
    public function executeStatement($sql, array $params = [], array $types = []): int
    {
        $start  = microtime(true);
        $result = $this->conn->executeStatement($sql, $params, $types);
        $end    = microtime(true);
        $this->collector->addQuery([
            'sql'         => $sql,
            'params'      => $params,
            'types'       => $types,
            'start'       => $start,
            'end'         => $end,
            'duration'    => $end - $start,
            'executionMS' => ($end - $start) * 1000,
        ]);

        return $result;
    }

    /**
     * Forwards all other method calls to the underlying connection.
     *
     * @param string $name
     * @param array  $arguments
     */
    public function __call($name, $arguments): mixed
    {
        return $this->conn->{$name}(...$arguments);
    }
}
