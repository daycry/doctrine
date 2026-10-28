<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4\Config;

use CodeIgniter\Exceptions\ConfigException;
use Config\Database;

/**
 * @internal
 */
final class DatabaseGroup
{
    public static function resolve(?string $group = null): string
    {
        $database = config('Database');
        if (! $database instanceof Database) {
            throw new ConfigException('Database config not found.');
        }
        $group ??= ENVIRONMENT === 'testing' ? 'tests' : $database->defaultGroup;
        $groups = get_object_vars($database);
        if (! isset($groups[$group]) || ! is_array($groups[$group])) {
            throw new ConfigException('Unknown Doctrine database group: ' . $group);
        }

        return $group;
    }

    public static function key(string $group): string
    {
        // BaseService lowercases service keys. Hex encoding preserves exact
        // group identity without collisions with another configured name.
        return bin2hex($group);
    }
}
