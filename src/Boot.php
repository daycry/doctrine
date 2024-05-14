<?php

namespace Daycry\Doctrine;

use CodeIgniter\Boot as CodeIgniterBoot;
use Config\Paths;

class Boot extends CodeIgniterBoot
{
    public static function bootDoctrine(Paths $paths): bool
    {
        static::definePathConstants($paths);
        if (! defined('APP_NAMESPACE')) {
            static::loadConstants();
        }
        static::checkMissingExtensions();

        static::loadDotEnv($paths);
        static::defineEnvironment();
        static::loadEnvironmentBootstrap($paths);

        static::loadCommonFunctions();
        static::loadAutoloader();
        static::setExceptionHandler();
        static::initializeKint();
        static::autoloadHelpers();

        static::initializeCodeIgniter();

        return true;
    }
}
