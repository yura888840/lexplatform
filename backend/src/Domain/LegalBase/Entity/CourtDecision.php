<?php
declare(strict_types=1);

namespace App\Domain\LegalBase\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Судебное решение (ТЗ LEG-06). search_vector — триггером Postgres. */
#[ORM\Entity]
#[ORM\Table(name: 'court_decisions')]
#[ORM\Index(name: 'idx_decisions_court_date', columns: ['court_id', 'decided_at'])]
class CourtDecision
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Court::class)]
    #[ORM\JoinColumn(name: 'court_id', nullable: false)]
    private Court $court;

    #[ORM\Column(name: 'case_number', length: 100)]
    private string $caseNumber;

    #[ORM\Column(length: 500)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(name: 'decided_at', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $decidedAt;

    /** Слаги правовых категорий (JSONB). */
    #[ORM\Column(type: Types::JSON)]
    private array $categories = [];

    #[ORM\Column(name: 'views_count')]
    private int $viewsCount = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Court $court, string $caseNumber, string $title, string $body, \DateTimeImmutable $decidedAt, array $categories = [])
    {
        $this->id = Uuid::v7();
        $this->court = $court;
        $this->caseNumber = $caseNumber;
        $this->title = $title;
        $this->body = $body;
        $this->decidedAt = $decidedAt;
        $this->categories = $categories;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getCourt(): Court { return $this->court; }
    public function getCaseNumber(): string { return $this->caseNumber; }
    public function getTitle(): string { return $this->title; }
    public function getBody(): string { return $this->body; }
    public function getDecidedAt(): \DateTimeImmutable { return $this->decidedAt; }
    public function getCategories(): array { return $this->categories; }
    public function getViewsCount(): int { return $this->viewsCount; }
    public function registerView(): void { $this->viewsCount++; }
}
