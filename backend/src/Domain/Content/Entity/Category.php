<?php
declare(strict_types=1);

namespace App\Domain\Content\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Правовые категории — общие для Q&A и контента (ТЗ CONS-04, CMS-04). */
#[ORM\Entity]
#[ORM\Table(name: 'categories')]
class Category
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 150, unique: true)]
    private string $slug;

    #[ORM\Column(length: 30)]
    private string $type; // legal | content

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder = 0;

    public function __construct(string $name, string $slug, string $type = 'legal', ?self $parent = null)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->slug = $slug;
        $this->type = $type;
        $this->parent = $parent;
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
    public function getType(): string { return $this->type; }
}
