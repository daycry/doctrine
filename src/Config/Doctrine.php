<?php

namespace Daycry\Doctrine\Config;

use CodeIgniter\Config\BaseConfig;

class Doctrine extends BaseConfig
{
    public bool $setAutoGenerateProxyClasses = ENVIRONMENT === 'development' ? true : false;
    public array $entities                   = [APPPATH . 'Models/Entity'];
    public string $proxies                   = APPPATH . 'Models/Proxies';
    public string $proxiesNamespace          = 'DoctrineProxies';
    public bool $queryCache                  = true;
    public string $queryCacheNamespace       = 'doctrine_queries';
    public bool $resultsCache                = true;
    public string $resultsCacheNamespace     = 'doctrine_results';
    public bool $metadataCache               = true;
    public string $metadataCacheNamespace    = 'doctrine_metadata';

    /**
     * Ex: attribute,xml
     */
    public string $metadataConfigurationMethod = 'attribute';

    /**
     * If metadataConfigurationMethod is 'xml'
     */
    public bool $isXsdValidationEnabled = false;
}
