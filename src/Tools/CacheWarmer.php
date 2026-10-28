<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Tools;

use Daycry\Doctrine\Doctrine;
use Doctrine\ORM\Tools\SchemaValidator;
use RuntimeException;

/**
 * Warms mapped metadata for the native-lazy-object runtime.
 */
final class CacheWarmer
{
    /**
     * @return array{metadata: int, metadata_cache: bool}
     */
    public function warm(Doctrine $doctrine): array
    {
        $em   = $doctrine->getEm();
        $pool = $em->getConfiguration()->getMetadataCache();
        if ($pool !== null) {
            CacheProbe::verify($pool);
        }
        if (new SchemaValidator($em)->validateMapping() !== []) {
            throw new RuntimeException('Invalid Doctrine mapping; run doctrine:validate for details.');
        }
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        return [
            'metadata'       => count($metadata),
            'metadata_cache' => $em->getConfiguration()->getMetadataCache() !== null,
        ];
    }
}
