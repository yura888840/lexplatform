<?php
declare(strict_types=1);

namespace App\Domain\Lawyer\Entity;

use App\Domain\Identity\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'reviews')]
#[ORM\Index(name: 'idx_reviews_lawyer_status', columns: ['lawyer_id', 'status'])]
class Review
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: LawyerProfile::class)]
    #[ORM\JoinColumn(name: 'lawyer_id', nullable: false, onDelete: 'CASCADE')]
    private LawyerProfile $lawyer;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $rating;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $body = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(LawyerProfile $lawyer, User $author, int $rating, ?string $body)
    {
        if ($rating < 1 || $rating > 5) {
            throw new \DomainException('Рейтинг должен быть от 1 до 5.');
        }
        $this->id = Uuid::v7();
        $this->lawyer = $lawyer;
        $this->author = $author;
        $this->rating = $rating;
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getLawyer(): LawyerProfile { return $this->lawyer; }
    public function getAuthor(): User { return $this->author; }
    public function getRating(): int { return $this->rating; }
    public function getBody(): ?string { return $this->body; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function approve(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \DomainException('Отзыв уже промодерирован.');
        }
        $this->status = self::STATUS_APPROVED;
        $this->lawyer->applyReview($this->rating);
    }

    public function reject(): void { $this->status = self::STATUS_REJECTED; }
}
