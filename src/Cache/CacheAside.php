<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Cache;

use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CacheAside
{
    public function __construct(private ?CacheItemPoolInterface $pool)
    {
    }

    public function get(string $key, int $ttl, callable $query): mixed
    {
        if ($ttl < 0) {
            throw new InvalidArgumentException('Cache TTL must be non-negative.');
        }
        if ($this->pool === null) {
            return $query();
        }
        if (preg_match('~[{}()/\\\\@:]~', $key) === 1) {
            $key = (preg_replace('~[{}()/\\\\@:]~', '_', $key) ?? $key) . '_' . substr(sha1($key), 0, 12);
        }
        $item = $this->pool->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }
        $value = $query();
        $item->set($value)->expiresAfter($ttl === 0 ? null : $ttl);
        $this->pool->save($item);

        return $value;
    }
}
