<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Tools;

use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\Tools\SchemaValidator;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Throwable;

/**
 * Readiness probes that never expose connection parameters or exception messages.
 */
final class Diagnostics
{
    /**
     * @return array{healthy: bool, checks: array<string, array{status: string, detail: string}>}
     */
    public function inspect(Doctrine $doctrine): array
    {
        $em                       = $doctrine->getEm();
        $config                   = $em->getConfiguration();
        $checks                   = [];
        $checks['entity_manager'] = $em->isOpen()
            ? ['status' => 'ok', 'detail' => 'EntityManager is open.']
            : ['status' => 'error', 'detail' => 'EntityManager is closed; reopen it before use.'];
        $checks['database'] = $this->probe(static function () use ($em): string {
            if ((string) $em->getConnection()->executeQuery($em->getConnection()->getDatabasePlatform()->getDummySelectSQL())->fetchOne() !== '1') {
                throw new RuntimeException('Unexpected database probe result.');
            }

            return 'Connection accepts queries.';
        });
        $checks['mapping'] = $this->probe(static function () use ($em): string {
            if (new SchemaValidator($em)->validateMapping() !== []) {
                throw new RuntimeException('Invalid mapping.');
            }

            return count($em->getMetadataFactory()->getAllMetadata()) . ' mapped entities; use doctrine:validate for schema validation.';
        });

        foreach (['query' => $config->getQueryCache(), 'result' => $config->getResultCache(), 'metadata' => $config->getMetadataCache()] as $name => $pool) {
            if ($pool === null) {
                $checks['cache.' . $name] = ['status' => 'disabled', 'detail' => 'No pool configured.'];

                continue;
            }
            $result = $this->probe(static fn (): string => CacheProbe::verify($pool));
            if ($result['status'] === 'ok' && $pool instanceof ArrayAdapter) {
                $result = ['status' => 'warning', 'detail' => 'ArrayAdapter passed; entries exist only in this process.'];
            }
            $checks['cache.' . $name] = $result;
        }
        $checks['lazy_objects'] = ['status' => 'ok', 'detail' => 'Native lazy objects enabled.'];
        $checks['slc']          = ['status' => $config->isSecondLevelCacheEnabled() ? 'ok' : 'disabled', 'detail' => $config->isSecondLevelCacheEnabled() ? 'Second-level cache configured; region contents are not modified by this probe.' : 'Second-level cache disabled.'];

        return ['healthy' => ! in_array('error', array_column($checks, 'status'), true), 'checks' => $checks];
    }

    /**
     * @param callable(): string $operation
     *
     * @return array{status: string, detail: string}
     */
    private function probe(callable $operation): array
    {
        try {
            return ['status' => 'ok', 'detail' => $operation()];
        } catch (Throwable $error) {
            return ['status' => 'error', 'detail' => 'Probe failed (' . $error::class . '). Check configuration and connectivity.'];
        }
    }
}
