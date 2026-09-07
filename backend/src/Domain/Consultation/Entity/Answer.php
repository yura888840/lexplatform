<?php
declare(strict_types=1);

namespace App\Domain\Consultation\Entity;

use App\Domain\Identity\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'answers')]
#[ORM\Index(name: 'idx_answers_question', columns: ['question_id', 'created_at'])]
class Answer
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'answers')]
    #[ORM\JoinColumn(name: 'question_id', nullable: false, onDelete: 'CASCADE')]
    private Question $question;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(name: 'is_accepted')]
    private bool $isAccepted = false;

    #[ORM\Column(name: 'helpful_votes')]
    private int $helpfulVotes = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Question $question, User $author, string $body)
    {
        if ($author->getRole() !== User::ROLE_LAWYER && $author->getRole() !== User::ROLE_FIRM) {
            throw new \DomainException('Отвечать на вопросы могут только юристы.');
        }
        $this->id = Uuid::v7();
        $this->question = $question;
        $this->author = $author;
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable();
        $question->registerAnswer();
    }

    public function getId(): Uuid { return $this->id; }
    public function getQuestion(): Question { return $this->question; }
    public function getAuthor(): User { return $this->author; }
    public function getBody(): string { return $this->body; }
    public function isAccepted(): bool { return $this->isAccepted; }
    public function markAccepted(): void { $this->isAccepted = true; }
    public function getHelpfulVotes(): int { return $this->helpfulVotes; }
    public function voteHelpful(): void { $this->helpfulVotes++; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
