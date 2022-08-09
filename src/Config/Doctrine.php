<?php

namespace Daycry\Doctrine\Config;

use CodeIgniter\Config\BaseConfig;

class Doctrine extends BaseConfig
{
    public $debug = false;

    // see doc https://www.doctrine-project.org/projects/doctrine-orm/en/2.8/reference/advanced-configuration.html#auto-generating-proxy-classes-optional
    public $setAutoGenerateProxyClasses = ENVIRONMENT === 'development' ? true : false;

    /*
     * Namespace and folder of models
     */
    public $namespaceModel = 'App/Models';
    public $folderModel = APPPATH . 'Models';

    /*
     * Namespace and folder of proxies
     */
    public $namespaceProxy = 'App/Models/Proxies';
    public $folderProxy = APPPATH . 'Models/Proxies';

    /*
     * Folder for entities
     */
    public $folderEntity = APPPATH . 'Models/Entity';
}
