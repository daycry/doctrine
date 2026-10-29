<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Tools;

use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

/**
 * @internal
 */
final class CacheProbe
{
    public static function verify(CacheItemPoolInterface $pool): string
    {
        $key = 'doctrine_doctor_' . bin2hex(random_bytes(12));

        try {
            $item = $pool->getItem($key)->set('ready')->expiresAfter(5);
            if (! $pool->save($item)) {
                throw new RuntimeException('Cache write failed.');
            }
            $read = $pool->getItem($key);
            if (! $read->isHit() || $read->get() !== 'ready') {
                throw new RuntimeException('Cache read failed.');
            }
        } finally {
            if (! $pool->deleteItem($key)) {
                throw new RuntimeException('Cache probe cleanup failed.');
            }
        }

        return get_debug_type($pool) . ' read/write/delete passed.';
    }
}
