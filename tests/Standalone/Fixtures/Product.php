<?php

declare(strict_types=1);

namespace Tests\Standalone\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Cache(usage: 'READ_ONLY')]
#[ORM\Entity]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Column]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 100)]
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
