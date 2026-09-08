<?php
declare(strict_types=1);

namespace App\Domain\Payment\Entity;

use App\Domain\Identity\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Платёж через шлюз (ТЗ §10.1: payments). MVP-шлюз — LiqPay (ТЗ §14.3). */
#[ORM\Entity]
#[ORM\Table(name: 'payments')]
#[ORM\Index(name: 'idx_payments_user_status', columns: ['user_id', 'status', 'created_at'])]
class Payment
{
    public const TYPE_SUBSCRIPTION = 'subscription';
    public const TYPE_FEATURED = 'featured';       // платное продвижение юриста (CAT-07)
    public const TYPE_CONSULTATION = 'consultation';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** Идентификатор заказа для шлюза (order_id в LiqPay). */
    #[ORM\Column(name: 'order_id', length: 64, unique: true)]
    private string $orderId;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3)]
    private string $currency = 'UAH';

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 30)]
    private string $gateway = 'liqpay';

    #[ORM\Column(name: 'gateway_tx_id', length: 100, nullable: true)]
    private ?string $gatewayTxId = null;

    /** Полезная нагрузка: plan_id, lawyer_id и т.п.
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /** @param array<string, mixed> $metadata */
    public function __construct(User $user, string $amount, string $type, array $metadata = [], string $currency = 'UAH')
    {
        if ((float) $amount <= 0) {
            throw new \DomainException('Сумма платежа должна быть положительной.');
        }
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->orderId = 'lex-' . $this->id->toBase58();
        $this->amount = $amount;
        $this->currency = $currency;
        $this->type = $type;
        $this->metadata = $metadata;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getOrderId(): string { return $this->orderId; }
    public function getAmount(): string { return $this->amount; }
    public function getCurrency(): string { return $this->currency; }
    public function getType(): string { return $this->type; }
    public function getStatus(): string { return $this->status; }
    /** @return array<string, mixed> */
    public function getMetadata(): array { return $this->metadata; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }

    /** Идемпотентно: повторный webhook LiqPay не меняет состояние. */
    public function markSucceeded(string $gatewayTxId): bool
    {
        if ($this->status === self::STATUS_SUCCEEDED) {
            return false;
        }
        if ($this->status !== self::STATUS_PENDING) {
            throw new \DomainException(sprintf('Платёж в статусе "%s" нельзя завершить.', $this->status));
        }
        $this->status = self::STATUS_SUCCEEDED;
        $this->gatewayTxId = $gatewayTxId;
        $this->paidAt = new \DateTimeImmutable();
        return true;
    }

    public function markFailed(?string $gatewayTxId = null): void
    {
        if ($this->status === self::STATUS_PENDING) {
            $this->status = self::STATUS_FAILED;
            $this->gatewayTxId = $gatewayTxId;
        }
    }
}
