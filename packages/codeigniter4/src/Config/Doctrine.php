<?php

declare(strict_types=1);

namespace Daycry\Doctrine\CodeIgniter4\Config;

use CodeIgniter\Config\BaseConfig;
use Daycry\Doctrine\Configuration as CoreConfig;
use Daycry\Doctrine\Configuration\OrmOptions;
use ReflectionClass;
use ReflectionProperty;

class Doctrine extends BaseConfig
{
    use OrmOptions;

    /**
     * @var list<string>
     */
    public array $entities = [APPPATH . 'Models/Entity'];

    /**
     * @param array<string, mixed> $connection
     */
    public function toCoreConfig(array $connection): CoreConfig
    {
        $options = new CoreConfig();

        foreach (new ReflectionClass(CoreConfig::class)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getName() !== 'connection') {
                $property->setValue($options, new ReflectionProperty($this, $property->getName())->getValue($this));
            }
        }
        $options->connection = $connection;

        return $options;
    }
}
