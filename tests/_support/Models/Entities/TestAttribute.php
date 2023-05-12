<?php

namespace Tests\Support\Models\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'test')]
#[ORM\UniqueConstraint(name: "name", columns: ["name"])]
#[ORM\Index(name: "deleted_at", columns: ["deleted_at"])]
class TestAttribute
{
    #[ORM\Id, ORM\Column(name: "id", type: "integer", nullable: "false"), ORM\GeneratedValue(strategy: "IDENTITY")]
    private $id;

    #[ORM\Column(name: "name", type: "string", nullable: "false")]
    private $name;

    #[ORM\Column(name: "created_at", type: "datetime", nullable: "false", options: ["default" => "CURRENT_TIMESTAMP"])]
    private $createdAt = 'CURRENT_TIMESTAMP';

    #[ORM\Column(name: "updated_at", type: "datetime", nullable: "true")]
    private $updatedAt;

    #[ORM\Column(name: "deleted_at", type: "datetime", nullable: "true")]
    private $deletedAt;

    #[ORM\PrePersist]
    public function setPrePersist()
    {
        $time = new \DateTime('now');
        $this->setCreatedAt($time);
    }

    #[ORM\PreUpdate]
    public function setPreUpdate()
    {
        $time = new \DateTime('now');
        $this->setUpdatedAt($time);
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        // @codeCoverageIgnoreStart
        return $this->id;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Test
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return Test
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt.
     *
     * @param \DateTime|null $deletedAt
     *
     * @return Test
     */
    public function setUpdatedAt($updatedAt = null)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt.
     *
     * @return \DateTime|null
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set deletedAt.
     *
     * @param \DateTime|null $deletedAt
     *
     * @return Secret
     */
    public function setDeletedAt($deletedAt = null)
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * Get deletedAt.
     *
     * @return \DateTime|null
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }
}
