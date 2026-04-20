<?php

declare(strict_types=1);

namespace Tests;

use Throwable;
use CodeIgniter\Exceptions\InvalidArgumentException;
use Daycry\Doctrine\Doctrine;
use Daycry\Doctrine\DataTables\Builder;
use Tests\Support\Models\Entities\TestAttribute;
use Tests\Support\TestCase;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Covers previously untested Builder methods:
 * - withMaxFilterValues() — exception branch and fluent-return branch
 * - getData() — with a live SQLite :memory: QueryBuilder
 */
final class BuilderCoverageTest extends TestCase
{
    private Doctrine $doctrine;

    protected function setUp(): void
    {
        parent::setUp();

        $db                    = config('Database');
        $db->tests['DBDriver'] = 'SQLite3';
        $db->tests['database'] = ':memory:';

        $this->config->entities = [SUPPORTPATH . 'Models/Entities'];
        $this->doctrine         = new Doctrine($this->config, null, 'tests');
    }

    // -----------------------------------------------------------------------
    // withMaxFilterValues() — throws when value < 1
    // -----------------------------------------------------------------------

    public function testWithMaxFilterValuesBelowOneThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxFilterValues must be at least 1');

        Builder::create()->withMaxFilterValues(0);
    }

    public function testWithMaxFilterValuesNegativeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Builder::create()->withMaxFilterValues(-5);
    }

    // -----------------------------------------------------------------------
    // withMaxFilterValues() — happy path returns $this (fluent interface)
    // -----------------------------------------------------------------------

    public function testWithMaxFilterValuesReturnsBuilder(): void
    {
        $builder = Builder::create();
        $result  = $builder->withMaxFilterValues(5);

        $this->assertSame($builder, $result);
    }

    public function testWithMaxFilterValuesPhpIntMaxIsAllowed(): void
    {
        $builder = Builder::create()->withMaxFilterValues(PHP_INT_MAX);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testWithMaxFilterValuesOneIsMinimumAllowed(): void
    {
        $builder = Builder::create()->withMaxFilterValues(1);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    // -----------------------------------------------------------------------
    // getData() — with a live SQLite in-memory database
    // -----------------------------------------------------------------------

    public function testGetDataReturnsEmptyArrayOnNoRows(): void
    {
        $em   = $this->doctrine->em;
        $tool = new SchemaTool($em);

        try {
            $tool->createSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Unable to create TestAttribute schema: ' . $e->getMessage());
        }

        $qb = $em->createQueryBuilder()
            ->select('t.id, t.name')
            ->from(TestAttribute::class, 't');

        $result = (new Builder())
            ->setUseOutputWalkers(false)
            ->withColumnAliases(['name' => 't.name'])
            ->withQueryBuilder($qb)
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => '', 'regex' => false],
                'columns' => [
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                ],
                'order'   => [['column' => 0, 'dir' => 'asc']],
            ])
            ->getData();

        $this->assertIsArray($result);
        $this->assertSame([], $result);

        try {
            $tool->dropSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable) {
            // ignore
        }
    }

    public function testGetDataReturnsPaginatedRows(): void
    {
        $em   = $this->doctrine->em;
        $tool = new SchemaTool($em);

        try {
            $tool->createSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Unable to create TestAttribute schema: ' . $e->getMessage());
        }

        // Persist two rows
        for ($i = 1; $i <= 2; $i++) {
            $entity = new TestAttribute();
            $entity->setName('row_' . $i);
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();

        $qb = $em->createQueryBuilder()
            ->select('t.id, t.name')
            ->from(TestAttribute::class, 't');

        $result = (new Builder())
            ->setUseOutputWalkers(false)
            ->withColumnAliases(['name' => 't.name'])
            ->withQueryBuilder($qb)
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => '', 'regex' => false],
                'columns' => [
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                ],
                'order'   => [['column' => 0, 'dir' => 'asc']],
            ])
            ->getData();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        // When selecting specific columns, rows are plain arrays
        $this->assertIsArray($result[0]);
        $this->assertStringStartsWith('row_', $result[0]['name']);

        try {
            $tool->dropSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable) {
            // ignore
        }
    }

    public function testGetDataWithSearchFilter(): void
    {
        $em   = $this->doctrine->em;
        $tool = new SchemaTool($em);

        try {
            $tool->createSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Unable to create TestAttribute schema: ' . $e->getMessage());
        }

        $entity = new TestAttribute();
        $entity->setName('searchable_row');
        $em->persist($entity);

        $other = new TestAttribute();
        $other->setName('other_row');
        $em->persist($other);

        $em->flush();
        $em->clear();

        $qb = $em->createQueryBuilder()
            ->select('t.id, t.name')
            ->from(TestAttribute::class, 't');

        $result = (new Builder())
            ->setUseOutputWalkers(false)
            ->withColumnAliases(['name' => 't.name'])
            ->withSearchableColumns(['t.name'])
            ->withQueryBuilder($qb)
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => 'searchable', 'regex' => false],
                'columns' => [
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                ],
                'order'   => [['column' => 0, 'dir' => 'asc']],
            ])
            ->getData();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('searchable_row', $result[0]['name']);

        try {
            $tool->dropSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable) {
            // ignore
        }
    }

    // -----------------------------------------------------------------------
    // getRecordsFiltered() — live SQLite count
    // -----------------------------------------------------------------------

    public function testGetRecordsFiltered(): void
    {
        $em   = $this->doctrine->em;
        $tool = new SchemaTool($em);

        try {
            $tool->createSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Unable to create schema: ' . $e->getMessage());
        }

        $entity = new TestAttribute();
        $entity->setName('filtered_row');
        $em->persist($entity);
        $em->flush();
        $em->clear();

        $qb = $em->createQueryBuilder()
            ->select('t.id, t.name')
            ->from(TestAttribute::class, 't');

        $count = (new Builder())
            ->setUseOutputWalkers(false)
            ->withColumnAliases(['name' => 't.name'])
            ->withQueryBuilder($qb)
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => '', 'regex' => false],
                'columns' => [
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                ],
                'order'   => [['column' => 0, 'dir' => 'asc']],
            ])
            ->getRecordsFiltered();

        $this->assertSame(1, $count);

        try {
            $tool->dropSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable) {
            // ignore
        }
    }

    // -----------------------------------------------------------------------
    // getRecordsTotal() — live SQLite count (no filters)
    // -----------------------------------------------------------------------

    public function testGetRecordsTotal(): void
    {
        $em   = $this->doctrine->em;
        $tool = new SchemaTool($em);

        try {
            $tool->createSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Unable to create schema: ' . $e->getMessage());
        }

        for ($i = 1; $i <= 3; $i++) {
            $entity = new TestAttribute();
            $entity->setName('total_row_' . $i);
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();

        $qb = $em->createQueryBuilder()
            ->select('t.id, t.name')
            ->from(TestAttribute::class, 't');

        $total = (new Builder())
            ->setUseOutputWalkers(false)
            ->withColumnAliases(['name' => 't.name'])
            ->withQueryBuilder($qb)
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => '', 'regex' => false],
                'columns' => [
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        'search'     => ['value' => '', 'regex' => false],
                    ],
                ],
                'order'   => [['column' => 0, 'dir' => 'asc']],
            ])
            ->getRecordsTotal();

        $this->assertSame(3, $total);

        try {
            $tool->dropSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable) {
            // ignore
        }
    }

    // -----------------------------------------------------------------------
    // IN operator — throws when count > maxFilterValues
    // -----------------------------------------------------------------------

    public function testInOperatorExceedsMaxFilterValuesThrows(): void
    {
        $em   = $this->doctrine->em;
        $tool = new SchemaTool($em);

        try {
            $tool->createSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Unable to create schema: ' . $e->getMessage());
        }

        $qb = $em->createQueryBuilder()
            ->select('t.id, t.name')
            ->from(TestAttribute::class, 't');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IN filter exceeds maximum allowed values');

        (new Builder())
            ->setUseOutputWalkers(false)
            ->withColumnAliases(['name' => 't.name'])
            ->withMaxFilterValues(2)
            ->withQueryBuilder($qb)
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => '', 'regex' => false],
                'columns' => [
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        // IN: with 3 values, exceeds maxFilterValues of 2
                        'search'     => ['value' => '[IN]a,b,c', 'regex' => false],
                    ],
                ],
                'order'   => [['column' => 0, 'dir' => 'asc']],
            ])
            ->getData();

        try {
            $tool->dropSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable) {
            // ignore
        }
    }

    // -----------------------------------------------------------------------
    // OR operator — throws when count > maxFilterValues
    // -----------------------------------------------------------------------

    public function testOrOperatorExceedsMaxFilterValuesThrows(): void
    {
        $em   = $this->doctrine->em;
        $tool = new SchemaTool($em);

        try {
            $tool->createSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable $e) {
            $this->markTestSkipped('Unable to create schema: ' . $e->getMessage());
        }

        $qb = $em->createQueryBuilder()
            ->select('t.id, t.name')
            ->from(TestAttribute::class, 't');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OR filter exceeds maximum allowed values');

        (new Builder())
            ->setUseOutputWalkers(false)
            ->withColumnAliases(['name' => 't.name'])
            ->withMaxFilterValues(2)
            ->withQueryBuilder($qb)
            ->withRequestParams([
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'search'  => ['value' => '', 'regex' => false],
                'columns' => [
                    [
                        'data'       => 'name',
                        'name'       => 'name',
                        'searchable' => true,
                        'orderable'  => true,
                        // OR: with 3 values, exceeds maxFilterValues of 2
                        'search'     => ['value' => '[OR]x,y,z', 'regex' => false],
                    ],
                ],
                'order'   => [['column' => 0, 'dir' => 'asc']],
            ])
            ->getData();

        try {
            $tool->dropSchema([$em->getClassMetadata(TestAttribute::class)]);
        } catch (Throwable) {
            // ignore
        }
    }
}
