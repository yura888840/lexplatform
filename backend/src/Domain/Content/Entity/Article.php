<?php
declare(strict_types=1);

namespace App\Domain\Content\Entity;

use App\Domain\Identity\Entity\User;
use App\Domain\Lawyer\Entity\Specialization;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'articles')]
#[ORM\Index(name: 'idx_articles_published', columns: ['status', 'published_at'])]
#[ORM\HasLifecycleCallbacks]
class Article
{
    public const TYPE_ARTICLE = 'article';
    public const TYPE_NEWS = 'news';
    public const TYPE_BLOG = 'blog';
    public const TYPE_DIGEST = 'digest';
    public const TYPE_METHODOLOGY = 'methodology';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEW = 'review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_ARTICLE;

    #[ORM\Column(length: 300)]
    private string $title;

    #[ORM\Column(length: 300, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::TEXT)]
    private string $excerpt = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', nullable: false)]
    private User $author;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: true)]
    private ?Category $category = null;

    /** Отрасль права — для статей юристов (подтверждение компетенции). */
    #[ORM\ManyToOne(targetEntity: Specialization::class)]
    #[ORM\JoinColumn(name: 'specialization_id', nullable: true, onDelete: 'SET NULL')]
    private ?Specialization $specialization = null;

    /** Права на статью переданы порталу (обязательное условие для статей юристов). */
    #[ORM\Column(name: 'copyright_transferred')]
    private bool $copyrightTransferred = false;

    #[ORM\Column(name: 'chars_count')]
    private int $charsCount = 0;

    #[ORM\Column(name: 'cover_image', length: 500, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'views_count')]
    private int $viewsCount = 0;

    #[ORM\Column(name: 'seo_title', length: 300, nullable: true)]
    private ?string $seoTitle = null;

    #[ORM\Column(name: 'seo_description', length: 500, nullable: true)]
    private ?string $seoDescription = null;

    #[ORM\Column(name: 'published_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $author, string $type, string $title, string $slug, string $body)
    {
        $this->id = Uuid::v7();
        $this->author = $author;
        $this->type = $type;
        $this->title = $title;
        $this->slug = $slug;
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getId(): Uuid { return $this->id; }
    public function getType(): string { return $this->type; }
    public function getTitle(): string { return $this->title; }
    public function getSlug(): string { return $this->slug; }
    public function getExcerpt(): string { return $this->excerpt; }
    public function setExcerpt(string $e): void { $this->excerpt = $e; }
    public function getBody(): string { return $this->body; }
    public function getAuthor(): User { return $this->author; }
    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $c): void { $this->category = $c; }
    public function getSpecialization(): ?Specialization { return $this->specialization; }
    public function setSpecialization(?Specialization $s): void { $this->specialization = $s; }
    public function isCopyrightTransferred(): bool { return $this->copyrightTransferred; }
    public function transferCopyright(): void { $this->copyrightTransferred = true; }
    public function getCharsCount(): int { return $this->charsCount; }
    public function setCharsCount(int $n): void { $this->charsCount = $n; }
    public function markInReview(): void { $this->status = self::STATUS_REVIEW; }
    public function reject(): void { $this->status = self::STATUS_DRAFT; }
    public function getCoverImage(): ?string { return $this->coverImage; }
    public function getStatus(): string { return $this->status; }
    public function getViewsCount(): int { return $this->viewsCount; }
    public function registerView(): void { $this->viewsCount++; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function getSeoTitle(): ?string { return $this->seoTitle; }
    public function getSeoDescription(): ?string { return $this->seoDescription; }

    /** Workflow: draft → review → published (ТЗ CMS-06). */
    public function publish(): void
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->publishedAt ??= new \DateTimeImmutable();
    }
}
