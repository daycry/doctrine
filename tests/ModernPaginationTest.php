<?php

declare(strict_types=1);

namespace Tests;

use Daycry\Doctrine\CodeIgniter4\Config\Services;
use Daycry\Doctrine\CodeIgniter4\Doctrine;
use Daycry\Doctrine\DataTables\Builder;
use Daycry\Doctrine\DataTables\Counter;
use Doctrine\ORM\Tools\Pagination\WindowPage;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use Tests\Support\Models\Entities\TestAttribute;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class ModernPaginationTest extends TestCase
{
    private Doctrine $doctrine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getSQLite3Config();
        $cache          = clone config('Cache');
        $cache->handler = 'dummy';
        $this->doctrine = new Doctrine($this->config, $cache, 'tests');
        $em             = $this->doctrine->getEm();
        new SchemaTool($em)->createSchema([$em->getClassMetadata(TestAttribute::class)]);

        foreach (['alpha', 'beta', 'alphabet'] as $name) {
            $entity = new TestAttribute()->setName($name);
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();
    }

    public function testGroupedCountsRespectHavingAndIgnorePagination(): void
    {
        $qb = $this->doctrine->getEm()->createQueryBuilder()->select('p.name, COUNT(p.id) AS total')
            ->from(TestAttribute::class, 'p')->groupBy('p.name')
            ->having('COUNT(p.id) >= :minimum')->setParameter('minimum', 1)
            ->setFirstResult(1)->setMaxResults(1);

        $this->assertSame(3, new Counter()->count($qb));
        $this->assertSame(1, $qb->getFirstResult());
        $this->assertSame(1, $qb->getMaxResults());
        $this->assertSame(1, $qb->getParameter('minimum')->getValue());
    }

    public function testTreeWalkerCountRemovesSelectOnlyParametersFromItsOwnCopy(): void
    {
        $qb = $this->doctrine->getEm()->createQueryBuilder()->select('p.id, CONCAT(:title, p.name) AS heading')
            ->from(TestAttribute::class, 'p')->where('p.name != :excluded')
            ->setParameter('title', 'Heading')->setParameter('excluded', 'beta');

        $this->assertSame(2, new Counter()->count($qb, false));
        $this->assertCount(2, $qb->getParameters());
        $this->assertSame('Heading', $qb->getParameter('title')->getValue());
    }

    public function testFilteredResponseUsesExactlyThreeQueries(): void
    {
        $qb        = $this->doctrine->getEm()->createQueryBuilder()->select('p')->from(TestAttribute::class, 'p');
        $collector = Services::doctrineCollector();
        $collector->reset();
        $response = Builder::create()->withQueryBuilder($qb)->withFetchJoinCollection(false)
            ->withRequestParams([
                'columns' => [['data' => 'p.name', 'searchable' => true]],
                'search'  => ['value' => 'alpha'], 'length' => 10,
            ])->getResponse();

        $this->assertCount(2, $response['data']);
        $this->assertSame(2, $response['recordsFiltered']);
        $this->assertSame(3, $response['recordsTotal']);
        $this->assertCount(3, $collector->getQueries());
        $this->assertNull($qb->getMaxResults());
    }

    public function testPageReturnsItemsAndTotalTogether(): void
    {
        $qb   = $this->doctrine->getEm()->createQueryBuilder()->select('p')->from(TestAttribute::class, 'p');
        $page = Builder::create()->withQueryBuilder($qb)->withFetchJoinCollection(false)
            ->withRequestParams(['columns' => [['data' => 'p.name']], 'start' => 1, 'length' => 1])
            ->getPage();

        $this->assertInstanceOf(WindowPage::class, $page);
        $this->assertCount(1, $page->getItems());
        $this->assertSame(3, $page->getTotalCount());
        $this->assertSame(1, $page->getWindow()->getFirstResult());
    }

    public function testAllRowsSentinelUsesTheDefaultPageCap(): void
    {
        $em = $this->doctrine->getEm();

        for ($i = 0; $i < 205; $i++) {
            $em->getConnection()->insert('test', ['name' => 'extra_' . $i, 'created_at' => '2026-10-28 12:00:00']);
        }
        $page = Builder::create()->withFetchJoinCollection(false)
            ->withQueryBuilder($em->createQueryBuilder()->select('p')->from(TestAttribute::class, 'p'))
            ->withRequestParams(['columns' => [['data' => 'p.name']], 'length' => -1])->getPage();

        $this->assertCount(200, $page->getItems());
        $this->assertSame(208, $page->getTotalCount());
    }

    public function testPageCapCannotBeDisabled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Builder::create()->withMaxPageLength(0);
    }

    public function testConfiguredDateFormatFunctionCompilesWithOrm(): void
    {
        $query = $this->doctrine->getEm()->createQuery("SELECT DATE_FORMAT(p.createdAt, '%Y') FROM " . TestAttribute::class . ' p');

        $this->assertStringContainsString("DATE_FORMAT(t0_.created_at, '%Y')", (string) $query->getSQL());
    }
}
