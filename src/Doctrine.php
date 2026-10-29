<?php

declare(strict_types=1);

namespace Daycry\Doctrine;

use Daycry\Doctrine\Cache\CachePools;
use Daycry\Doctrine\Configuration as DoctrineConfig;
use Daycry\Doctrine\Logging\QueryLoggerMiddleware;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Cache\CacheConfiguration as ORMCacheConfiguration;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\Logging\StatisticsCacheLogger;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Framework-independent Doctrine ORM integration.
 * Handles EntityManager, DBAL connection, and cache configuration.
 */
class Doctrine
{
    /**
     * The Doctrine EntityManager instance.
     */
    public ?EntityManager $em = null;

    private readonly DoctrineConfig $doctrineConfig;

    public function __construct(
        DoctrineConfig $doctrineConfig,
        ?CachePools $cachePools = null,
        ?LoggerInterface $logger = null,
    ) {
        if ($doctrineConfig->connection === []) {
            throw new InvalidArgumentException('DBAL connection parameters are required.');
        }
        $this->doctrineConfig = clone $doctrineConfig;
        $cachePools ??= new CachePools();

        foreach ($doctrineConfig->entities as $entityPath) {
            if (! is_dir($entityPath)) {
                throw new InvalidArgumentException('Doctrine entity path does not exist: ' . $entityPath);
            }
        }

        $config = new Configuration();

        $config->enableNativeLazyObjects(true);

        if ($doctrineConfig->queryCache) {
            $config->setQueryCache($cachePools->query);
        }

        if ($doctrineConfig->resultsCache) {
            $config->setResultCache($cachePools->result);
        }

        if ($doctrineConfig->metadataCache) {
            $config->setMetadataCache($cachePools->metadata);
        }

        // Second-Level Cache uses the explicitly supplied PSR-6 pool.
        if ($doctrineConfig->secondLevelCache) {
            $slcTtl = $doctrineConfig->secondLevelCacheTtl;
            $slcTtl ??= 0;

            // Symfony Cache adapters interpret 0 as no-expiration
            // Doctrine RegionsConfiguration expects lifetime seconds for regions
            $regionsConfig = new RegionsConfiguration(
                $slcTtl,
                60,
            );

            $psr6Pool = $cachePools->secondLevel;

            $slcConfig = new ORMCacheConfiguration();
            $slcConfig->setRegionsConfiguration($regionsConfig);
            $cacheFactory = new DefaultCacheFactory($regionsConfig, $psr6Pool);
            if ($doctrineConfig->secondLevelCacheLockPath !== null) {
                $cacheFactory->setFileLockRegionDirectory($doctrineConfig->secondLevelCacheLockPath);
            }
            $slcConfig->setCacheFactory($cacheFactory);

            // Optional SLC statistics logger (hits/misses/puts)
            if ($doctrineConfig->secondLevelCacheStatistics) {
                $slcConfig->setCacheLogger(new StatisticsCacheLogger());
            }

            $config->setSecondLevelCacheEnabled(true);
            $config->setSecondLevelCacheConfiguration($slcConfig);
        }

        match ($doctrineConfig->metadataConfigurationMethod) {
            'xml'   => $config->setMetadataDriverImpl(new XmlDriver($doctrineConfig->entities, XmlDriver::DEFAULT_FILE_EXTENSION, $doctrineConfig->isXsdValidationEnabled)),
            default => $config->setMetadataDriverImpl(new AttributeDriver($doctrineConfig->entities)),
        };

        // Register custom DQL functions (beberlei/doctrineextensions + user-defined)
        foreach ($doctrineConfig->customStringFunctions as $name => $class) {
            $config->addCustomStringFunction($name, $class);
        }

        foreach ($doctrineConfig->customNumericFunctions as $name => $class) {
            $config->addCustomNumericFunction($name, $class);
        }

        foreach ($doctrineConfig->customDatetimeFunctions as $name => $class) {
            $config->addCustomDatetimeFunction($name, $class);
        }

        // Register JSON DQL functions (scienta/doctrine-json-functions)
        foreach ($doctrineConfig->customJsonFunctions as $name => $class) {
            $config->addCustomStringFunction($name, $class);
        }

        // Register SQL Filters (soft-delete, multi-tenant, …) on the configuration.
        foreach ($doctrineConfig->sqlFilters as $name => $class) {
            $config->addFilter($name, $class);
        }

        // Default repository class for entities that don't declare their own.
        if ($doctrineConfig->defaultRepositoryClass !== null) {
            $config->setDefaultRepositoryClassName($doctrineConfig->defaultRepositoryClass);
        }

        // Compose explicitly configured DBAL middlewares.
        $dbalConfig  = new \Doctrine\DBAL\Configuration();
        $middlewares = [];

        foreach ($doctrineConfig->dbalMiddlewares as $userMiddleware) {
            $middlewares[] = is_string($userMiddleware) ? new $userMiddleware() : $userMiddleware;
        }

        // Optional query logging through the caller's PSR-3 logger.
        if ($doctrineConfig->queryLogging) {
            $middlewares[] = new QueryLoggerMiddleware(
                $logger ?? throw new InvalidArgumentException('Query logging requires a PSR-3 logger.'),
                $doctrineConfig->slowQueryThreshold,
                $doctrineConfig->queryLogLevel,
            );
        }

        if ($middlewares !== []) {
            $dbalConfig->setMiddlewares($middlewares);
        }

        $connection = DriverManager::getConnection($doctrineConfig->connection, $dbalConfig);
        // Create EntityManager con la conexión original (middleware ya captura queries)
        $this->em = new EntityManager($connection, $config, $this->buildEventManager($doctrineConfig));

        $this->registerTypeMappings($doctrineConfig);
        $this->enableConfiguredFilters($doctrineConfig);
    }

    /**
     * Get the EntityManager. Throws if it has not been initialized.
     */
    public function getEm(): EntityManager
    {
        if ($this->em === null) {
            throw new RuntimeException('EntityManager has not been initialized.');
        }

        return $this->em;
    }

    /**
     * Reopen the EntityManager, recovering from a stale or dropped database
     * connection (e.g. "MySQL server has gone away" after an idle timeout in a
     * long-running CLI worker or queue consumer).
     *
     * The existing DBAL connection is closed first so DBAL re-establishes the
     * underlying socket lazily on the next query; a fresh EntityManager is then
     * built over that connection with the same configuration, and custom type
     * mappings are re-applied.
     *
     * Note: closing the connection discards an in-memory SQLite database. This
     * method is intended for server-backed connections (MySQL/Postgres/…), which
     * is the documented worker-recovery use case.
     */
    public function reOpen(): void
    {
        $em         = $this->getEm();
        $connection = $em->getConnection();

        if ($connection->isConnected()) {
            $connection->close();
        }

        $doctrineConfig = $this->doctrineConfig;
        $this->em       = new EntityManager($connection, $em->getConfiguration(), $this->buildEventManager($doctrineConfig));
        $this->registerTypeMappings($doctrineConfig);
        $this->enableConfiguredFilters($doctrineConfig);
    }

    /**
     * Build an EventManager from the configured listeners/subscribers, or null
     * when none are configured (so the EntityManager keeps its default).
     * Class-strings are instantiated with no constructor arguments; ready-made
     * instances are used as-is.
     */
    protected function buildEventManager(DoctrineConfig $doctrineConfig): ?EventManager
    {
        if ($doctrineConfig->eventListeners === [] && $doctrineConfig->eventSubscribers === []) {
            return null;
        }

        $eventManager = new EventManager();

        foreach ($doctrineConfig->eventListeners as $event => $listeners) {
            foreach ($listeners as $listener) {
                $eventManager->addEventListener($event, is_string($listener) ? new $listener() : $listener);
            }
        }

        foreach ($doctrineConfig->eventSubscribers as $subscriber) {
            $eventManager->addEventSubscriber(is_string($subscriber) ? new $subscriber() : $subscriber);
        }

        return $eventManager;
    }

    /**
     * Enable the SQL filters listed in Config\Doctrine::$enabledFilters on the
     * current EntityManager. FilterCollection is per-EntityManager, so this must
     * run on construction and again after reOpen() rebuilds the EM.
     */
    protected function enableConfiguredFilters(DoctrineConfig $doctrineConfig): void
    {
        if ($doctrineConfig->enabledFilters === []) {
            return;
        }

        $filters = $this->getEm()->getFilters();

        foreach ($doctrineConfig->enabledFilters as $name) {
            // Only enable filters that were actually registered, to avoid a
            // misconfigured name throwing during construction.
            if (array_key_exists($name, $doctrineConfig->sqlFilters)) {
                $filters->enable($name);
            }
        }
    }

    /**
     * Register custom DBAL type mappings on the current connection's platform.
     */
    protected function registerTypeMappings(DoctrineConfig $doctrineConfig): void
    {
        // Register custom DBAL Types first (process-global, idempotent).
        foreach ($doctrineConfig->customTypes as $name => $class) {
            if (! Type::hasType($name)) {
                Type::addType($name, $class);
            }
        }

        $platform = $this->getEm()->getConnection()->getDatabasePlatform();

        foreach ($doctrineConfig->customTypeMappings as $dbType => $doctrineType) {
            $platform->registerDoctrineTypeMapping($dbType, $doctrineType);
        }
    }

    /**
     * Return Second-Level Cache logger if enabled.
     * Consumers can inspect the logger for stats.
     */
    public function getSecondLevelCacheLogger(): ?StatisticsCacheLogger
    {
        $cfg = $this->em?->getConfiguration()?->getSecondLevelCacheConfiguration();
        if ($cfg === null) {
            return null;
        }
        $logger = $cfg->getCacheLogger();

        return $logger instanceof StatisticsCacheLogger ? $logger : null;
    }

    /**
     * Reset Second-Level Cache statistics counters if available.
     */
    public function resetSecondLevelCacheStatistics(): void
    {
        $logger = $this->getSecondLevelCacheLogger();
        if ($logger === null) {
            return;
        }

        $logger->clearStats();
    }
}
