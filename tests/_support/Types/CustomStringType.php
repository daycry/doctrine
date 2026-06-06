<?php

declare(strict_types=1);

namespace Tests\Support\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Minimal custom DBAL Type used to exercise Config\Doctrine::$customTypes
 * registration. DBAL 4 removed Type::getName(), so only getSQLDeclaration()
 * is required.
 */
final class CustomStringType extends Type
{
    public const NAME = 'custom_string';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }
}
