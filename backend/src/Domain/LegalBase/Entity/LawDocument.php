<?php
declare(strict_types=1);

namespace App\Domain\LegalBase\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Нормативно-правовой акт (ТЗ §4.2 LawDocument).
 * Версионирование: новая редакция = новая запись с parent_id на предыдущую,
 * у старой status → amended. search_vector поддерживается триггером Postgres (см. миграцию).
 */
#[ORM\Entity]
#[ORM\Table(name: 'law_documents')]
#[ORM\Index(name: 'idx_law_type_status', columns: ['type', 'status'])]
#[ORM\HasLifecycleCallbacks]
class LawDocument
{
    public const TYPE_LAW = 'law';
    public const TYPE_CODE = 'code';
    public const TYPE_RESOLUTION = 'resolution';
    public const TYPE_ORDER = 'order';
    public const TYPE_DECREE = 'decree';
    public const TYPE_INTERNATIONAL = 'international';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_AMENDED = 'amended';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\Column(length: 100)]
    private string $number;

    #[ORM\Column(length: 300, unique: true)]
    private string $slug;

    #[ORM\Column(length: 500)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(name: 'issued_by', length: 300)]
    private string $issuedBy;

    #[ORM\Column(name: 'issued_at', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(name: 'effective_from', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveFrom = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    private int $version = 1;

    /** Предыдущая редакция (LEG-02). */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    #[ORM\Column(name: 'views_count')]
    private int $viewsCount = 0;

    /** true → полный текст только для PRO-подписчиков (LEG-09). */
    #[ORM\Column(name: 'is_pro_only')]
    private bool $isProOnly = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $type,
        string $number,
        string $slug,
        string $title,
        string $body,
        string $issuedBy,
        \DateTimeImmutable $issuedAt,
    ) {
        $this->id = Uuid::v7();
        $this->type = $type;
        $this->number = $number;
        $this->slug = $slug;
        $this->title = $title;
        $this->body = $body;
        $this->issuedBy = $issuedBy;
        $this->issuedAt = $issuedAt;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getId(): Uuid { return $this->id; }
    public function getType(): string { return $this->type; }
    public function getNumber(): string { return $this->number; }
    public function getSlug(): string { return $this->slug; }
    public function getTitle(): string { return $this->title; }
    public function getBody(): string { return $this->body; }
    public function getIssuedBy(): string { return $this->issuedBy; }
    public function getIssuedAt(): \DateTimeImmutable { return $this->issuedAt; }
    public function getEffectiveFrom(): ?\DateTimeImmutable { return $this->effectiveFrom; }
    public function setEffectiveFrom(?\DateTimeImmutable $d): void { $this->effectiveFrom = $d; }
    public function getStatus(): string { return $this->status; }
    public function getVersion(): int { return $this->version; }
    public function getParent(): ?self { return $this->parent; }
    public function getViewsCount(): int { return $this->viewsCount; }
    public function registerView(): void { $this->viewsCount++; }
    public function isProOnly(): bool { return $this->isProOnly; }
    public function setProOnly(bool $pro): void { $this->isProOnly = $pro; }

    /**
     * Новая редакция документа (LEG-02): текущая помечается amended,
     * возвращается новая active-версия с инкрементом version.
     */
    public function createNewRevision(string $newBody, string $newSlug, \DateTimeImmutable $effectiveFrom): self
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            throw new \DomainException('Новую редакцию можно создать только от действующей версии.');
        }

        $revision = new self($this->type, $this->number, $newSlug, $this->title, $newBody, $this->issuedBy, $this->issuedAt);
        $revision->version = $this->version + 1;
        $revision->parent = $this;
        $revision->effectiveFrom = $effectiveFrom;
        $revision->isProOnly = $this->isProOnly;

        $this->status = self::STATUS_AMENDED;

        return $revision;
    }

    public function cancel(): void { $this->status = self::STATUS_CANCELLED; }
}
