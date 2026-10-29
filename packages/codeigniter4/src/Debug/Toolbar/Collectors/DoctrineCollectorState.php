<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4\Debug\Toolbar\Collectors;

use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;

/**
 * Request data shared by the middleware and the collectors created by CI4.
 *
 * @internal
 */
final class DoctrineCollectorState
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $queries = [];

    public int $maxQueries                   = 1000;
    public ?StatisticsCacheLogger $slcLogger = null;
}
