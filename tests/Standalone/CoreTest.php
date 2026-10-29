<?php

declare(strict_types=1);

namespace Tests\Standalone;

use ArrayObject;
use Composer\InstalledVersions;
use Daycry\Doctrine\Cache\CacheAside;
use Daycry\Doctrine\Cache\CachePools;
use Daycry\Doctrine\Configuration;
use Daycry\Doctrine\Console\ApplicationFactory;
use Daycry\Doctrine\DataTables\Builder;
use Daycry\Doctrine\Doctrine;
use Daycry\Doctrine\ManagerRegistry;
use Daycry\Doctrine\Tools\CacheWarmer;
use Daycry\Doctrine\Tools\Diagnostics;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use ReflectionClass;
use RuntimeException;
use Stringable;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Tester\ApplicationTester;
use Tests\Standalone\Fixtures\Product;
use Tests\Standalone\Fixtures\VisibleFilter;

/**
 * @internal
 */
final class CoreTest extends TestCase
{
    private function options(): Configuration
    {
        $config             = new Configuration();
        $config->connection = ['driver' => 'pdo_sqlite', 'memory' => true];
        $config->entities   = [__DIR__ . '/Fixtures'];

        return $config;
    }

    private function database(?Configuration $config = null, ?CachePools $pools = null): Doctrine
    {
        $doctrine = new Doctrine($config ?? $this->options(), $pools);
        $em       = $doctrine->getEm();
        new SchemaTool($em)->createSchema([$em->getClassMetadata(Product::class)]);

        foreach (['alpha', 'alphabet', 'beta', 'hidden'] as $name) {
            $em->persist(new Product($name));
        }
        $em->flush();
        $em->clear();

        return $doctrine;
    }

    public function testCoreBootsWithoutFrameworkGlobals(): void
    {
        $this->assertFalse(defined('APPPATH'));
        $this->assertFalse(function_exists('config'));
        if (getenv('DOCTRINE_REQUIRE_FRAMEWORK_FREE')) {
            $this->assertFalse(InstalledVersions::isInstalled('codeigniter4/framework'));
        }
        $this->assertSame(1, (int) new Doctrine($this->options())->getEm()->getConnection()->fetchOne('SELECT 1'));
    }

    public function testNativeLazyReferenceLoadsPersistedData(): void
    {
        $em      = $this->database()->getEm();
        $product = $em->getReference(Product::class, 1);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertTrue(new ReflectionClass(Product::class)->isUninitializedLazyObject($product));
        $this->assertInstanceOf(Product::class, $product);
        $this->assertSame('alpha', $product->getName());
        $this->assertFalse(new ReflectionClass(Product::class)->isUninitializedLazyObject($product));
    }

    public function testDatatablesUsesExplicitRequestAndPreservesBaseQuery(): void
    {
        $qb = $this->database()->getEm()->createQueryBuilder()->select('p')->from(Product::class, 'p')
            ->where('p.name != :excluded')->setParameter('excluded', 'hidden');
        $result = Builder::create()->withQueryBuilder($qb)->withFetchJoinCollection(false)
            ->withRequestParams(['columns' => [['data' => 'p.name', 'searchable' => true]],
                'search'                   => ['value' => 'alpha'], 'length' => 1, 'draw' => '7'])->getResponse();

        $this->assertSame(3, $result['recordsTotal']);
        $this->assertSame(2, $result['recordsFiltered']);
        $this->assertCount(1, $result['data']);
        $this->assertSame(7, $result['draw']);
        $this->assertCount(1, $qb->getParameters());
        $this->assertNull($qb->getMaxResults());
    }

    public function testInjectedPoolsAreUsedByDiagnosticsAndWarmupWithoutDdl(): void
    {
        $pools    = new CachePools();
        $doctrine = new Doctrine($this->options(), $pools);
        $before   = $doctrine->getEm()->getConnection()->createSchemaManager()->introspectTableNames();
        $this->assertSame($pools->metadata, $doctrine->getEm()->getConfiguration()->getMetadataCache());
        $this->assertTrue(new Diagnostics()->inspect($doctrine)['healthy']);
        $this->assertSame(1, new CacheWarmer()->warm($doctrine)['metadata']);
        $this->assertSame($before, $doctrine->getEm()->getConnection()->createSchemaManager()->introspectTableNames());
        $this->assertNotSame($pools->query, $pools->result);
    }

    public function testSecondLevelCacheUsesInjectedPool(): void
    {
        $config                             = $this->options();
        $config->secondLevelCache           = true;
        $config->secondLevelCacheStatistics = true;
        $doctrine                           = $this->database($config);
        $em                                 = $doctrine->getEm();
        $this->assertSame('alpha', $em->find(Product::class, 1)->getName());
        $em->clear();
        $this->assertSame('alpha', $em->find(Product::class, 1)->getName());
        $this->assertGreaterThan(0, $doctrine->getSecondLevelCacheLogger()->getHitCount());
    }

    public function testConfiguredFilterIsApplied(): void
    {
        $options                 = $this->options();
        $options->sqlFilters     = ['visible' => VisibleFilter::class];
        $options->enabledFilters = ['visible'];
        $doctrine                = $this->database($options);

        $this->assertCount(3, $doctrine->getEm()->getRepository(Product::class)->findAll());
    }

    public function testReopenRestoresConstructorOptions(): void
    {
        $options                 = $this->options();
        $options->sqlFilters     = ['visible' => VisibleFilter::class];
        $options->enabledFilters = ['visible'];
        $doctrine                = new Doctrine($options);
        $old                     = $doctrine->getEm();
        $options->enabledFilters = [];
        $old->close();
        $doctrine->reOpen();

        $this->assertNotSame($old, $doctrine->getEm());
        $this->assertTrue($doctrine->getEm()->isOpen());
        $this->assertTrue($doctrine->getEm()->getFilters()->isEnabled('visible'));
    }

    public function testCacheAsideNormalizesKeysAndUsesPoolLifetimeWhenTtlIsZero(): void
    {
        $pool  = new ArrayAdapter();
        $cache = new CacheAside($pool);
        $this->assertSame('cached', $cache->get('key:{one}', 0, static fn (): string => 'cached'));
        sleep(2);
        $this->assertSame('cached', $cache->get('key:{one}', 0, static fn (): string => 'recomputed'));
        $this->assertSame('other', $cache->get('key:{two}', 0, static fn (): string => 'other'));
        $this->assertSame('no-pool', new CacheAside(null)->get('key', 0, static fn (): string => 'no-pool'));
    }

    public function testRegistryCreatesLazilyAndSeparatesExactNames(): void
    {
        $created = new ArrayObject();
        $factory = function () use ($created): Doctrine {
            $instance = new Doctrine($this->options());
            $created->append($instance);

            return $instance;
        };
        $registry = new ManagerRegistry(['default' => $factory, 'Reports' => $factory, 'reports' => $factory]);
        $this->assertCount(0, $created);
        $this->assertSame($registry->get(), $registry->get());
        $this->assertNotSame($registry->get('Reports'), $registry->get('reports'));
        $this->assertCount(3, $created);
        $old = $registry->get();
        $registry->reset();
        $this->assertFalse($old->getEm()->isOpen());
        $this->assertNotSame($old, $registry->get());
        $this->assertCount(4, $created);
    }

    public function testUnknownManagerFailsExplicitly(): void
    {
        $registry = new ManagerRegistry(['default' => fn (): Doctrine => new Doctrine($this->options())]);
        $this->expectException(InvalidArgumentException::class);
        $registry->get('missing');
    }

    public function testRegistryRequiresAFactoryForItsDefaultManager(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The default manager must have a factory.');
        new ManagerRegistry([], 'primary');
    }

    public function testConsoleProviderResolvesTheRequestedManager(): void
    {
        $primary   = new Doctrine($this->options());
        $reporting = new Doctrine($this->options());
        $registry  = new ManagerRegistry([
            'primary' => static fn (): Doctrine => $primary,
            'reports' => static fn (): Doctrine => $reporting,
        ], 'primary');

        $this->assertSame($primary->getEm(), $registry->getDefaultManager());
        $this->assertSame($reporting->getEm(), $registry->getManager('reports'));
    }

    public function testCacheAsideRejectsNegativeTtlBeforeExecutingTheQuery(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CacheAside(new ArrayAdapter())->get('key', -1, static function (): never {
            self::fail('A rejected cache request must not execute its query.');
        });
    }

    public function testTextDoctorReportsAnUnhealthyClosedManager(): void
    {
        $doctrine = new Doctrine($this->options());
        $doctrine->getEm()->close();
        $application = ApplicationFactory::create($doctrine);
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $this->assertSame(1, $tester->run(['command' => 'doctrine:doctor']));
        $this->assertStringContainsString('entity_manager: error', $tester->getDisplay());
        $this->assertStringContainsString('database: ok', $tester->getDisplay());
    }

    public function testWarmupFailureReturnsNonzeroAndRedactsInitializationSecrets(): void
    {
        $registry = new ManagerRegistry([
            'default' => static fn (): Doctrine => throw new RuntimeException('password=private-value'),
        ]);
        $application = ApplicationFactory::create($registry);
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $this->assertSame(1, $tester->run(['command' => 'doctrine:cache:warmup']));
        $this->assertStringContainsString('Warmup failed (RuntimeException)', $tester->getDisplay());
        $this->assertStringNotContainsString('private-value', $tester->getDisplay());
    }

    public function testQueryLoggingUsesInjectedPsrLogger(): void
    {
        $logger = new class () extends AbstractLogger {
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = $context;
            }
        };
        $config               = $this->options();
        $config->queryLogging = true;
        $em                   = new Doctrine($config, logger: $logger)->getEm();
        $em->getConnection()->executeQuery('SELECT ?', [42])->fetchOne();

        $this->assertSame('SELECT ?', $logger->records[0]['sql']);
        $this->assertSame([1 => 42], $logger->records[0]['params']);
        $this->assertGreaterThanOrEqual(0, $logger->records[0]['duration_ms']);
    }

    public function testLoggingRequiresAnExplicitLogger(): void
    {
        $config               = $this->options();
        $config->queryLogging = true;
        $this->expectException(InvalidArgumentException::class);
        new Doctrine($config);
    }

    public function testNativeConsoleSupportsDoctorWarmupAndOrmInfo(): void
    {
        $application = ApplicationFactory::create(new Doctrine($this->options()));
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        $this->assertSame(0, $tester->run(['command' => 'doctrine:doctor', '--json' => true]));
        $this->assertTrue(json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['healthy']);
        $this->assertSame(0, $tester->run(['command' => 'doctrine:cache:warmup']));
        $this->assertSame(0, $tester->run(['command' => 'orm:info']));
        $this->assertStringContainsString(Product::class, $tester->getDisplay());
        $this->assertSame(1, $tester->run(['command' => 'doctrine:doctor', '--em' => 'missing', '--json' => true]));
    }
}
