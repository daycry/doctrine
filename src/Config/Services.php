<?php

namespace Daycry\Doctrine\Config;

use CodeIgniter\Config\BaseService;
use Daycry\Doctrine\Debug\Toolbar\Collectors\DoctrineCollector;
use Daycry\Doctrine\Doctrine;

class Services extends BaseService
{
    public static function doctrine(bool $getShared = true, ?string $dbGroup = null)
    {
        if ($getShared && $dbGroup === null) {
            return static::getSharedInstance('doctrine');
        }

        $config      = config('Doctrine');
        $cacheConfig = config('Cache');

        return new Doctrine($config, $cacheConfig, $dbGroup);
    }

    public static function doctrineCollector(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('doctrineCollector');
        }

        return new DoctrineCollector();
    }
}
