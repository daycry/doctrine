<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Debug\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class DoctrineSlcReset implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): RequestInterface|ResponseInterface|string|null
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        // no-op
        return null;
    }
}
