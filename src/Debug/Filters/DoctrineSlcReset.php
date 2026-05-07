<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Debug\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Daycry\Doctrine\Config\Services;
use Throwable;

/**
 * Resets Second-Level Cache statistics counters at the start of every request,
 * so the Debug Toolbar shows hits/misses/puts scoped to the current request.
 *
 * Register in `app/Config/Filters.php`:
 *
 *     public array $globals = [
 *         'before' => [
 *             \Daycry\Doctrine\Debug\Filters\DoctrineSlcReset::class,
 *         ],
 *     ];
 *
 * Has no effect when `Config\Doctrine::$secondLevelCacheStatistics` is disabled
 * or the Doctrine service has not yet been instantiated.
 */
class DoctrineSlcReset implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): RequestInterface|ResponseInterface|string|null
    {
        try {
            Services::doctrine()->resetSecondLevelCacheStatistics();
        } catch (Throwable) {
            // Toolbar filter must never break the request pipeline.
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
