<?php
declare(strict_types=1);

namespace App\Domain\Payment\Entity;

use App\Domain\Identity\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Подписка PRO (ТЗ §4.2 Subscription, LEG-09). */
#[ORM\Entity]
#[ORM\Table(name: 'subscriptions')]
#[ORM\Index(name: 'idx_subscriptions_user_status', columns: ['user_id', 'status'])]
class Subscription
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: SubscriptionPlan::class)]
    #[ORM\JoinColumn(name: 'plan_id', nullable: false)]
    private SubscriptionPlan $plan;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'auto_renew')]
    private bool $autoRenew = false;

    public function __construct(User $user, SubscriptionPlan $plan)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->plan = $plan;
        $this->startedAt = new \DateTimeImmutable();
        $this->expiresAt = $this->startedAt->modify('+' . $plan->periodDays() . ' days');
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getPlan(): SubscriptionPlan { return $this->plan; }
    public function getStatus(): string { return $this->status; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->expiresAt > new \DateTimeImmutable();
    }

    /** Продление при повторной оплате того же плана. */
    public function extend(): void
    {
        $base = max($this->expiresAt, new \DateTimeImmutable());
        $this->expiresAt = \DateTimeImmutable::createFromInterface($base)->modify('+' . $this->plan->periodDays() . ' days');
        $this->status = self::STATUS_ACTIVE;
    }

    public function cancel(): void { $this->status = self::STATUS_CANCELLED; }
}
