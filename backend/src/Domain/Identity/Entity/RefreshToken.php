<?php
declare(strict_types=1);

namespace App\Domain\Identity\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Refresh-токен 30 дней (ТЗ AUTH-05), хранится хешем. */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Index(name: 'idx_refresh_user', columns: ['user_id'])]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'token_hash', length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $plainToken, int $ttlDays = 30)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->tokenHash = hash('sha256', $plainToken);
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify("+{$ttlDays} days");
    }

    public function getUser(): User { return $this->user; }
    public function isExpired(): bool { return $this->expiresAt < new \DateTimeImmutable(); }
    public static function hash(string $plain): string { return hash('sha256', $plain); }
}
