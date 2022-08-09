<?php

namespace Daycry\Doctrine\Config;

use CodeIgniter\Config\BaseService;
use Daycry\Doctrine\Doctrine;

class Services extends BaseService
{
    public static function doctrine(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('doctrine');
        }

        $config = config('Doctrine');

        return new Doctrine($config);
    }
}
