<?php
declare(strict_types=1);

namespace App\Domain\Identity\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Согласие пользователя (GDPR / передача прав).
 * personal_data — обработка и распространение персональных данных (обязательно для юристов);
 * copyright_transfer — передача исключительных прав на статью порталу (при каждом сабмите).
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_consents')]
#[ORM\Index(name: 'idx_consents_user_type', columns: ['user_id', 'type'])]
class UserConsent
{
    public const TYPE_PERSONAL_DATA = 'personal_data';
    public const TYPE_COPYRIGHT_TRANSFER = 'copyright_transfer';

    public const CURRENT_VERSION_PERSONAL_DATA = '1.0';
    public const CURRENT_VERSION_COPYRIGHT = '1.0';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 40)]
    private string $type;

    #[ORM\Column(length: 20)]
    private string $version;

    #[ORM\Column(name: 'accepted_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $acceptedAt;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    public function __construct(User $user, string $type, string $version, ?string $ip = null)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->type = $type;
        $this->version = $version;
        $this->ip = $ip;
        $this->acceptedAt = new \DateTimeImmutable();
    }

    public function getUser(): User { return $this->user; }
    public function getType(): string { return $this->type; }
    public function getVersion(): string { return $this->version; }
    public function getAcceptedAt(): \DateTimeImmutable { return $this->acceptedAt; }
}
