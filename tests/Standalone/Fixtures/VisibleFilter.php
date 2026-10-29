<?php

declare(strict_types=1);

namespace Tests\Standalone\Fixtures;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

final class VisibleFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        return $targetEntity->getName() === Product::class ? $targetTableAlias . ".name <> 'hidden'" : '';
    }
}
