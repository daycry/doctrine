<?php

namespace Daycry\Doctrine\Config;

use CodeIgniter\Config\BaseConfig;

class Doctrine extends BaseConfig
{
    public bool $setAutoGenerateProxyClasses = ENVIRONMENT === 'development' ? true : false;

    public array $entities = [APPPATH . 'Models/Entity'];

    public string $proxies = APPPATH . 'Models/Proxies';

    public bool $queryCache = true;
    public string $queryCacheNamespace = 'doctrine_queries';

    public bool $resultsCache = true;
    public string $resultsCacheNamespace = 'doctrine_results';

    /**
     * Ex: attribute, yaml, xml, annotation
     */
    public string $metadataConfigurationMethod = 'annotation';

    public array $metadataConfigMap = [
        'annotation' => 'createAnnotationMetadataConfiguration',
        'attribute' => 'createAttributeMetadataConfiguration',
        'yaml' => 'createYAMLMetadataConfiguration',
        'xml' => 'createXMLMetadataConfiguration'
    ];
}
