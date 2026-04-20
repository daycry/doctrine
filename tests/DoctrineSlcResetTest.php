<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use Tests\Support\TestCase;
use Daycry\Doctrine\Debug\Filters\DoctrineSlcReset;

/**
 * Covers DoctrineSlcReset::before() and DoctrineSlcReset::after()
 */
final class DoctrineSlcResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testBeforeReturnsNull(): void
    {
        $filter  = new DoctrineSlcReset();
        $request = $this->createStub(IncomingRequest::class);

        $result = $filter->before($request);

        $this->assertNull($result);
    }

    public function testAfterReturnsNull(): void
    {
        $filter   = new DoctrineSlcReset();
        $request  = $this->createStub(IncomingRequest::class);
        $response = $this->createStub(Response::class);

        $result = $filter->after($request, $response);

        $this->assertNotInstanceOf(ResponseInterface::class, $result);
    }
}
