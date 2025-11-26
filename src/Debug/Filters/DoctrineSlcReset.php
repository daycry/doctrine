<?php

namespace Daycry\Doctrine\Debug\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class DoctrineSlcReset implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Only reset in development environment
        if (defined('ENVIRONMENT') && ENVIRONMENT !== 'development') {
            return;
        }
        if (class_exists('Config\\Services') && method_exists(\Config\Services::class, 'doctrine')) {
            $doctrine = \Config\Services::doctrine();
            if (method_exists($doctrine, 'resetSecondLevelCacheStatistics')) {
                $doctrine->resetSecondLevelCacheStatistics();
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
