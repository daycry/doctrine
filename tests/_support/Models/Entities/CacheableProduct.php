<?php

namespace Tests\Support\Models\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Cache(usage: 'READ_ONLY', region: 'default_region')]
#[ORM\Entity]
#[ORM\Table(name: 'cacheable_products')]
class CacheableProduct
{
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $name,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
