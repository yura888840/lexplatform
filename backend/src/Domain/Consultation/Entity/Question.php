<?php
declare(strict_types=1);

namespace App\Domain\Consultation\Entity;

use App\Domain\Content\Entity\Category;
use App\Domain\Identity\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'questions')]
#[ORM\Index(name: 'idx_questions_category_status', columns: ['category_id', 'status', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class Question
{
    public const TYPE_PUBLIC = 'public';
    public const TYPE_PRIVATE = 'private';
    public const TYPE_PAID = 'paid';

    public const STATUS_OPEN = 'open';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_MODERATION = 'moderation';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(length: 300)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: false)]
    private Category $category;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_PUBLIC;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(name: 'is_anonymous')]
    private bool $isAnonymous = false;

    #[ORM\Column(name: 'views_count')]
    private int $viewsCount = 0;

    #[ORM\Column(name: 'answers_count')]
    private int $answersCount = 0;

    #[ORM\Column(name: 'accepted_answer_id', type: 'uuid', nullable: true)]
    private ?Uuid $acceptedAnswerId = null;

    /** @var Collection<int, Answer> */
    #[ORM\OneToMany(targetEntity: Answer::class, mappedBy: 'question')]
    private Collection $answers;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $author, string $title, string $body, Category $category, string $type = self::TYPE_PUBLIC, bool $isAnonymous = false)
    {
        $this->id = Uuid::v7();
        $this->author = $author;
        $this->title = $title;
        $this->body = $body;
        $this->category = $category;
        $this->type = $type;
        $this->isAnonymous = $isAnonymous;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getId(): Uuid { return $this->id; }
    public function getAuthor(): User { return $this->author; }
    public function getTitle(): string { return $this->title; }
    public function getBody(): string { return $this->body; }
    public function getCategory(): Category { return $this->category; }
    public function getType(): string { return $this->type; }
    public function getStatus(): string { return $this->status; }
    public function isAnonymous(): bool { return $this->isAnonymous; }
    public function getViewsCount(): int { return $this->viewsCount; }
    public function registerView(): void { $this->viewsCount++; }
    public function getAnswersCount(): int { return $this->answersCount; }
    public function getAcceptedAnswerId(): ?Uuid { return $this->acceptedAnswerId; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    /** @return Collection<int, Answer> */
    public function getAnswers(): Collection { return $this->answers; }

    public function registerAnswer(): void
    {
        $this->answersCount++;
        if ($this->status === self::STATUS_OPEN) {
            $this->status = self::STATUS_ANSWERED;
        }
    }

    /** Клиент выбирает лучший ответ (ТЗ CONS-06). Только автор вопроса. */
    public function acceptAnswer(Answer $answer, User $actor): void
    {
        if (!$actor->getId()->equals($this->author->getId())) {
            throw new \DomainException('Только автор вопроса может выбрать лучший ответ.');
        }
        if (!$answer->getQuestion()->getId()->equals($this->id)) {
            throw new \DomainException('Ответ не относится к этому вопросу.');
        }
        $this->acceptedAnswerId = $answer->getId();
        $answer->markAccepted();
        $this->status = self::STATUS_CLOSED;
    }
}
