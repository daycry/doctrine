<?php

declare(strict_types=1);

namespace Daycry\Doctrine;

use Daycry\Doctrine\Configuration\OrmOptions;

/**
 * Explicit, framework-independent ORM configuration.
 */
class Configuration
{
    use OrmOptions;

    /**
     * @var list<string>
     */
    public array $entities = [];

    /**
     * @var array<string, mixed> DBAL connection parameters.
     */
    public array $connection = [];
}
