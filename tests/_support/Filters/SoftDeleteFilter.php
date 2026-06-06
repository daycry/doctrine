<?php

declare(strict_types=1);

namespace Tests\Support\Filters;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Minimal SQL filter fixture used to exercise Config\Doctrine::$sqlFilters /
 * $enabledFilters wiring. Returns no constraint so it is safe on any entity.
 */
final class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        return '';
    }
}
