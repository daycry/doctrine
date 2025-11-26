<?php

namespace Tests;

use Tests\Support\TestCase;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector;

class DoctrineCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Limpiar queries estáticas antes de cada test
        $ref = new \ReflectionProperty(DoctrineCollector::class, 'queries');
        $ref->setAccessible(true);
        // For static properties, pass null as the object per modern Reflection API
        $ref->setValue(null, []);
    }

    public function testAddQueryAndGetQueries()
    {
        $collector = new DoctrineCollector();
        $query = [
            'sql' => 'SELECT * FROM users WHERE id = ?',
            'params' => [1],
            'duration' => 0.1234
        ];
        $collector->addQuery($query);
        $queries = $collector->getQueries();
        $this->assertNotEmpty($queries);
        $this->assertEquals($query['sql'], $queries[0]['sql']);
        $this->assertEquals($query['params'], $queries[0]['params']);
        $this->assertEquals($query['duration'], $queries[0]['duration']);
    }

    public function testDisplayReturnsHtml()
    {
        $collector = new DoctrineCollector();
        $collector->addQuery([
            'sql' => 'SELECT * FROM users',
            'params' => [],
            'duration' => 0.1
        ]);
        $html = $collector->display();
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('SELECT', $html);
    }

    public function testIsEmpty()
    {
        $collector = new DoctrineCollector();
        $this->assertTrue($collector->isEmpty());
        $collector->addQuery([
            'sql' => 'SELECT 1',
            'params' => [],
            'duration' => 0.01
        ]);
        $this->assertFalse($collector->isEmpty());
    }

    public function testGetTitleAndDetails()
    {
        $collector = new DoctrineCollector();
        $this->assertEquals('Doctrine', $collector->getTitle());
        $this->assertEquals('', $collector->getTitleDetails());
        $collector->addQuery([
            'sql' => 'SELECT 1',
            'params' => [],
            'duration' => 0.01
        ]);
        $this->assertStringContainsString('query', $collector->getTitleDetails());
    }

    public function testDebugToolbarDisplayHighlightsKeywords()
    {
        $collector = new DoctrineCollector();
        $sql = 'SELECT * FROM users WHERE id = 1';
        $highlighted = $collector->debugToolbarDisplayPublic($sql);
        $this->assertStringContainsString('<strong>', $highlighted);
    }

    public function testGetBadgeValue()
    {
        $collector = new DoctrineCollector();
        $this->assertEquals(0, $collector->getBadgeValue());
        $collector->addQuery([
            'sql' => 'SELECT 1',
            'params' => [],
            'duration' => 0.01
        ]);
        $this->assertEquals(1, $collector->getBadgeValue());
    }

    public function testGetData()
    {
        $collector = new DoctrineCollector();
        $collector->addQuery([
            'sql' => 'SELECT 1',
            'params' => [],
            'duration' => 0.01
        ]);
        $data = $collector->getData();
        $this->assertArrayHasKey('queries', $data);
        $this->assertCount(1, $data['queries']);
    }

    public function testDisplayWithNoQueries()
    {
        $collector = new DoctrineCollector();
        $html = $collector->display();
        $this->assertStringContainsString('No Doctrine queries executed', $html);
    }
}