<?php
declare(strict_types=1);

namespace App\Domain\Identity\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\Index(name: 'idx_users_role_status', columns: ['role', 'status'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_CLIENT = 'client';
    public const ROLE_LAWYER = 'lawyer';
    public const ROLE_FIRM = 'firm';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPERADMIN = 'superadmin';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BANNED = 'banned';
    public const STATUS_PENDING = 'pending_verification';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(name: 'password_hash')]
    private string $passwordHash = '';

    #[ORM\Column(name: 'full_name', length: 255)]
    private string $fullName;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'avatar_url', length: 500, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column(length: 20)]
    private string $role = self::ROLE_CLIENT;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'email_verified_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(length: 5)]
    private string $locale = 'uk';

    #[ORM\Column(name: 'last_login_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null; // soft-delete, GDPR (AUTH-10)

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $email, string $fullName, string $role = self::ROLE_CLIENT)
    {
        $this->id = Uuid::v7();
        $this->email = mb_strtolower(trim($email));
        $this->fullName = $fullName;
        $this->role = $role;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $name): void { $this->fullName = $name; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): void { $this->phone = $phone; }
    public function getAvatarUrl(): ?string { return $this->avatarUrl; }
    public function setAvatarUrl(?string $url): void { $this->avatarUrl = $url; }
    public function getRole(): string { return $this->role; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }

    public function setRole(string $role): void
    {
        $allowed = [self::ROLE_CLIENT, self::ROLE_LAWYER, self::ROLE_FIRM, self::ROLE_MODERATOR, self::ROLE_EDITOR, self::ROLE_ADMIN, self::ROLE_SUPERADMIN];
        if (!in_array($role, $allowed, true)) {
            throw new \DomainException('Недопустима роль користувача.');
        }
        $this->role = $role;
    }

    public function ban(): void { $this->status = self::STATUS_BANNED; }
    public function unban(): void { $this->status = self::STATUS_ACTIVE; }
    public function isBanned(): bool { return $this->status === self::STATUS_BANNED; }
    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale): void { $this->locale = $locale; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function markEmailVerified(): void
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
        if ($this->status === self::STATUS_PENDING) {
            $this->status = self::STATUS_ACTIVE;
        }
    }

    public function recordLogin(): void { $this->lastLoginAt = new \DateTimeImmutable(); }

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
        $this->status = self::STATUS_INACTIVE;
    }

    public function isDeleted(): bool { return $this->deletedAt !== null; }

    // ── Symfony Security ────────────────────────────────────────────────
    public function getPassword(): string { return $this->passwordHash; }
    public function setPasswordHash(string $hash): void { $this->passwordHash = $hash; }
    public function getUserIdentifier(): string { return $this->email; }
    public function eraseCredentials(): void {}

    /** @return string[] */
    public function getRoles(): array
    {
        return array_unique(['ROLE_USER', 'ROLE_' . strtoupper($this->role)]);
    }
}
