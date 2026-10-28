<?php

declare(strict_types=1);

namespace Daycry\Doctrine;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use InvalidArgumentException;

/**
 * Named, lazy managers without a framework service container.
 */
final class ManagerRegistry implements EntityManagerProvider
{
    /**
     * @var array<string, Doctrine>
     */
    private array $instances = [];

    /**
     * @param array<string, Closure(): Doctrine> $factories
     */
    public function __construct(private readonly array $factories, private readonly string $default = 'default')
    {
        if (! isset($factories[$default])) {
            throw new InvalidArgumentException('The default manager must have a factory.');
        }
    }

    public function get(?string $name = null): Doctrine
    {
        $name ??= $this->default;
        $factory = $this->factories[$name] ?? throw new InvalidArgumentException('Unknown Doctrine manager: ' . $name);

        return $this->instances[$name] ??= $factory();
    }

    public function getDefaultManager(): EntityManagerInterface
    {
        return $this->get()->getEm();
    }

    public function getManager(string $name): EntityManagerInterface
    {
        return $this->get($name)->getEm();
    }

    public function reset(?string $name = null): void
    {
        $name ??= $this->default;
        if (isset($this->instances[$name])) {
            $em = $this->instances[$name]->getEm();
            $em->close();
            $em->getConnection()->close();
            unset($this->instances[$name]);
        }
    }
}
