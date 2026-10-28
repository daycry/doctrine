<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4\Debug\Toolbar\Collectors;

use Daycry\Doctrine\Logging\QueryMiddleware;

class DoctrineQueryMiddleware extends QueryMiddleware
{
    public function __construct(protected DoctrineCollector $collector)
    {
    }

    protected function onQuery(string $sql, array $params, float $start, float $duration): void
    {
        $this->collector->addQuery([
            'sql'      => $sql,
            'params'   => $params,
            'types'    => [],
            'start'    => $start,
            'end'      => $start + $duration,
            'duration' => $duration * 1000.0,
        ]);
    }
}
