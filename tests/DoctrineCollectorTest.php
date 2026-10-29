<?php

declare(strict_types=1);

namespace Tests;

use Daycry\Doctrine\CodeIgniter4\Debug\Toolbar\Collectors\DoctrineCollector;
use Daycry\Doctrine\CodeIgniter4\Debug\Toolbar\Collectors\DoctrineCollectorState;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class DoctrineCollectorTest extends TestCase
{
    public function testAddQueryAndGetQueries()
    {
        $collector = new DoctrineCollector(new DoctrineCollectorState());
        $query     = [
            'sql'      => 'SELECT * FROM users WHERE id = ?',
            'params'   => [1],
            'duration' => 0.1234,
        ];
        $collector->addQuery($query);
        $queries = $collector->getQueries();
        $this->assertNotEmpty($queries);
        $this->assertSame($query['sql'], $queries[0]['sql']);
        $this->assertSame($query['params'], $queries[0]['params']);
        $this->assertSame($query['duration'], $queries[0]['duration']);
    }

    public function testDisplayReturnsHtml()
    {
        $collector = new DoctrineCollector(new DoctrineCollectorState());
        $collector->addQuery([
            'sql'      => 'SELECT * FROM users',
            'params'   => [],
            'duration' => 0.1,
        ]);
        $html = $collector->display();
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('SELECT', $html);
    }

    public function testIsEmpty()
    {
        $collector = new DoctrineCollector(new DoctrineCollectorState());
        $this->assertTrue($collector->isEmpty());
        $collector->addQuery([
            'sql'      => 'SELECT 1',
            'params'   => [],
            'duration' => 0.01,
        ]);
        $this->assertFalse($collector->isEmpty());
    }

    public function testGetTitleAndDetails()
    {
        $collector = new DoctrineCollector(new DoctrineCollectorState());
        $this->assertSame('Doctrine', $collector->getTitle());
        $this->assertSame('', $collector->getTitleDetails());
        $collector->addQuery([
            'sql'      => 'SELECT 1',
            'params'   => [],
            'duration' => 0.01,
        ]);
        $this->assertStringContainsString('query', $collector->getTitleDetails());
    }

    public function testDebugToolbarDisplayHighlightsKeywords()
    {
        $collector = new DoctrineCollector(new DoctrineCollectorState());
        $sql       = 'SELECT * FROM users WHERE id = 1';

        // The highlighter is a protected method; verify via display() output.
        $collector->addQuery([
            'sql'      => $sql,
            'params'   => [],
            'duration' => 0.01,
        ]);
        $html = $collector->display();
        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringContainsString('SELECT', $html);
    }

    public function testGetBadgeValue()
    {
        $collector = new DoctrineCollector(new DoctrineCollectorState());
        $this->assertSame(0, $collector->getBadgeValue());
        $collector->addQuery([
            'sql'      => 'SELECT 1',
            'params'   => [],
            'duration' => 0.01,
        ]);
        $this->assertSame(1, $collector->getBadgeValue());
    }

    public function testGetData()
    {
        $collector = new DoctrineCollector(new DoctrineCollectorState());
        $collector->addQuery([
            'sql'      => 'SELECT 1',
            'params'   => [],
            'duration' => 0.01,
        ]);
        $data = $collector->getData();
        $this->assertArrayHasKey('queries', $data);
        $this->assertCount(1, $data['queries']);
    }

    public function testDisplayWithNoQueries()
    {
        $collector = new DoctrineCollector(new DoctrineCollectorState());
        $html      = $collector->display();
        $this->assertStringContainsString('No Doctrine queries executed', $html);
    }
}
