<?php

declare(strict_types=1);

namespace Tests\Support\Repositories;

use Doctrine\ORM\EntityRepository;

/**
 * Custom default repository fixture used to verify
 * Config\Doctrine::$defaultRepositoryClass wiring.
 *
 * @template T of object
 *
 * @extends EntityRepository<T>
 */
class CustomRepository extends EntityRepository
{
}
