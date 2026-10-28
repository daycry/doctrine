<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\HTTP\IncomingRequest;
use Daycry\Doctrine\CodeIgniter4\Config\DatabaseGroup;
use Daycry\Doctrine\CodeIgniter4\Config\Doctrine as DoctrineConfig;
use Daycry\Doctrine\CodeIgniter4\Config\Services;
use Daycry\Doctrine\CodeIgniter4\Debug\Filters\DoctrineSlcReset;
use Daycry\Doctrine\CodeIgniter4\Doctrine;
use Daycry\Doctrine\DataTables\Builder;
use Doctrine\ORM\Cache\CacheFactory;
use Doctrine\ORM\Cache\ConcurrentRegion;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionMethod;
use Tests\Support\Filters\SoftDeleteFilter;
use Tests\Support\Listeners\RecordingSubscriber;
use Tests\Support\Models\Entities\CacheableProduct;
use Tests\Support\Models\Entities\TestAttribute;
use Tests\Support\TestCase;
use TypeError;

use function Daycry\Doctrine\CodeIgniter4\Helpers\getFromCacheOrQuery;

/**
 * @internal
 */
final class RegressionTest extends TestCase
{
    private string $originalHandler;
    private int $originalTtl;

    public function testDeclarativeEntityPathsSurviveAdapterConfigurationConstruction(): void
    {
        $config = new class () extends DoctrineConfig {
            public array $entities = [__DIR__ . '/_support/Models/Entities'];
        };

        $this->assertSame([__DIR__ . '/_support/Models/Entities'], $config->entities);
        $this->assertSame($config->entities, $config->toCoreConfig(['driver' => 'pdo_sqlite', 'memory' => true])->entities);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $db                      = config('Database');
        $db->tests['DSN']        = '';
        $db->tests['DBDriver']   = 'SQLite3';
        $db->tests['database']   = ':memory:';
        $db->default             = $db->tests;
        $this->originalHandler   = config('Cache')->handler;
        $this->originalTtl       = config('Cache')->ttl;
        config('Cache')->handler = 'dummy';
    }

    protected function tearDown(): void
    {
        config('Cache')->handler = $this->originalHandler;
        config('Cache')->ttl     = $this->originalTtl;
        parent::tearDown();
    }

    public function testGlobalSearchPreservesBaseParameters(): void
    {
        $query = new Doctrine($this->config)->getEm()->createQueryBuilder()
            ->select('p')->from(TestAttribute::class, 'p')
            ->where('p.name = :search')->setParameter('search', 'scope');
        $filtered = Builder::create()->withQueryBuilder($query)->withRequestParams([
            'columns' => [['data' => 'p.name', 'searchable' => true]],
            'search'  => ['value' => 'client'],
        ])->getFilteredQuery();

        $this->assertSame('scope', $filtered->getParameter('search')->getValue());
        $this->assertCount(2, $filtered->getParameters());
        $this->assertSame('scope', $query->getParameter('search')->getValue());
    }

    public function testColumnFiltersPreserveBaseParameters(): void
    {
        $query = new Doctrine($this->config)->getEm()->createQueryBuilder()
            ->select('p')->from(TestAttribute::class, 'p')
            ->where('p.name = :filter_0_0')->setParameter('filter_0_0', 'scope');
        $filtered = Builder::create()->withQueryBuilder($query)->withRequestParams([
            'columns' => [['data' => 'p.name', 'searchable' => true, 'search' => ['value' => '[OR]a,b']]],
        ])->getFilteredQuery();

        $this->assertSame('scope', $filtered->getParameter('filter_0_0')->getValue());
        $this->assertCount(3, $filtered->getParameters());
        $this->assertCount(1, $query->getParameters());
    }

    public function testReopenKeepsExplicitConfiguration(): void
    {
        $custom                   = clone $this->config;
        $custom->sqlFilters       = ['soft_delete' => SoftDeleteFilter::class];
        $custom->enabledFilters   = ['soft_delete'];
        $custom->eventSubscribers = [RecordingSubscriber::class];
        $doctrine                 = new Doctrine($custom);
        $doctrine->reOpen();

        $this->assertTrue($doctrine->getEm()->getFilters()->isEnabled('soft_delete'));
        $this->assertTrue($doctrine->getEm()->getEventManager()->hasListeners('postLoad'));
    }

    public function testImplicitGroupSharesTheEffectiveGroupInstance(): void
    {
        $implicit = Services::doctrine();

        $this->assertSame($implicit, Services::doctrine(true, 'tests'));
        $this->assertNotSame($implicit, Services::doctrine(true, 'default'));
        Services::resetDoctrine();
        $this->assertNotSame($implicit, Services::doctrine(true, 'tests'));
    }

    public function testGroupKeysPreserveCaseAndCannotAliasConfiguredNames(): void
    {
        $legacyCollision = 'reporting_' . substr(hash('sha256', 'Reporting'), 0, 16);
        $this->assertNotSame(
            DatabaseGroup::key('Reporting'),
            DatabaseGroup::key($legacyCollision),
        );
        $this->assertNotSame(
            DatabaseGroup::key('Reporting'),
            DatabaseGroup::key('reporting'),
        );
    }

    public function testDbalQueryBuilderIsRejectedWithActionableMessage(): void
    {
        $query = new Doctrine($this->config)->getEm()->getConnection()->createQueryBuilder();
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Doctrine\\ORM\\QueryBuilder');

        new ReflectionMethod(Builder::class, 'withQueryBuilder')->invoke(Builder::create(), $query);
    }

    public function testZeroTtlOutlivesFrameworkDefault(): void
    {
        config('Cache')->ttl = 1;
        $calls               = 0;
        $compute             = static function () use (&$calls): int {
            return ++$calls;
        };
        getFromCacheOrQuery('ttl_zero_regression', 0, $compute);
        sleep(2);

        $this->assertSame(1, getFromCacheOrQuery('ttl_zero_regression', 0, $compute));
        $this->assertSame(1, $calls);
    }

    public function testReadWriteRegionCanBeCreated(): void
    {
        $this->config->secondLevelCache = true;
        $doctrine                       = new Doctrine($this->config);
        $factory                        = $doctrine->getEm()->getConfiguration()->getSecondLevelCacheConfiguration()->getCacheFactory();
        $this->assertInstanceOf(CacheFactory::class, $factory);
        $region = $factory->getRegion(['region' => 'regression_read_write', 'usage' => ClassMetadata::CACHE_USAGE_READ_WRITE]);

        $this->assertInstanceOf(ConcurrentRegion::class, $region);
        $em       = $doctrine->getEm();
        $metadata = $em->getClassMetadata(CacheableProduct::class);
        $metadata->enableCache(['region' => 'regression_read_write', 'usage' => ClassMetadata::CACHE_USAGE_READ_WRITE]);
        new SchemaTool($em)->createSchema([$metadata]);
        $product = new CacheableProduct('READ_WRITE product');
        $em->persist($product);
        $em->flush();
        $id = $product->getId();
        $em->clear();
        $cached = $em->find(CacheableProduct::class, $id);
        $this->assertInstanceOf(CacheableProduct::class, $cached);
        $this->assertSame('READ_WRITE product', $cached->getName());
    }

    public function testArrayCachePoolsAreIsolated(): void
    {
        $configuration = new Doctrine($this->config)->getEm()->getConfiguration();
        $result        = $configuration->getResultCache();
        $query         = $configuration->getQueryCache();
        $metadata      = $configuration->getMetadataCache();
        $this->assertInstanceOf(CacheItemPoolInterface::class, $result);
        $this->assertInstanceOf(CacheItemPoolInterface::class, $query);
        $this->assertInstanceOf(CacheItemPoolInterface::class, $metadata);
        $result->save($result->getItem('same_key')->set('result'));

        $this->assertFalse($query->hasItem('same_key'));
        $this->assertFalse($metadata->hasItem('same_key'));
        $query->clear();
        $this->assertSame('result', $result->getItem('same_key')->get());
    }

    public function testUnfilteredResponseUsesOneCountQuery(): void
    {
        $doctrine = new Doctrine($this->config);
        $em       = $doctrine->getEm();
        new SchemaTool($em)->createSchema([$em->getClassMetadata(TestAttribute::class)]);
        $collector = Services::doctrineCollector();
        $collector->reset();
        $response = Builder::create()->withFetchJoinCollection(false)
            ->withQueryBuilder($em->createQueryBuilder()->select('p')->from(TestAttribute::class, 'p'))
            ->withRequestParams(['columns' => [['data' => 'p.name', 'searchable' => true]], 'length' => 10])
            ->getResponse();

        $this->assertSame(0, $response['recordsTotal']);
        $this->assertSame(0, $response['recordsFiltered']);
        $this->assertCount(2, $collector->getQueries(), 'One data query and one count query are sufficient without filters.');
    }

    public function testSlcResetDoesNotInitializeDoctrine(): void
    {
        Services::resetDoctrine();
        new DoctrineSlcReset()
            ->before($this->createStub(IncomingRequest::class));

        $this->assertNotInstanceOf(Doctrine::class, Services::peekDoctrine());
    }

    public function testSlcInheritsZeroFrameworkLifetime(): void
    {
        config('Cache')->ttl            = 0;
        $this->config->secondLevelCache = true;
        $doctrine                       = new Doctrine($this->config);
        $regions                        = $doctrine->getEm()->getConfiguration()->getSecondLevelCacheConfiguration()->getRegionsConfiguration();

        $this->assertSame(0, $regions->getDefaultLifetime());
    }
}
