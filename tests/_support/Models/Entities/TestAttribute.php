<?php

namespace Tests\Support\Models\Entities;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'test')]
#[ORM\UniqueConstraint(name: "name", columns: ["name"])]
#[ORM\Index(name: "deleted_at", columns: ["deleted_at"])]
class TestAttribute
{
    #[ORM\Id, ORM\Column(name: "id", type: "integer", nullable: false), ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    #[ORM\Column(name: "name", type: "string", nullable: false)]
    private string $name = '';

    #[ORM\Column(name: "created_at", type: "datetime", nullable: false, options: ["default" => "CURRENT_TIMESTAMP"])]
    private ?DateTime $createdAt = null;

    #[ORM\Column(name: "updated_at", type: "datetime", nullable: true)]
    private ?DateTime $updatedAt = null;

    #[ORM\Column(name: "deleted_at", type: "datetime", nullable: true)]
    private ?DateTime $deletedAt = null;

    #[ORM\PrePersist]
    public function setPrePersist(): void
    {
        $time = new DateTime('now');
        $this->setCreatedAt($time);
    }

    #[ORM\PreUpdate]
    public function setPreUpdate(): void
    {
        $time = new DateTime('now');
        $this->setUpdatedAt($time);
    }

    public function getId(): ?int
    {
        // @codeCoverageIgnoreStart
        return $this->id;
        // @codeCoverageIgnoreEnd
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setCreatedAt(DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt = null): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setDeletedAt(?DateTime $deletedAt = null): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getDeletedAt(): ?DateTime
    {
        return $this->deletedAt;
    }
}
