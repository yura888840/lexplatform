<?php
declare(strict_types=1);

namespace App\Domain\Payment\Entity;

use App\Domain\Lawyer\Entity\LawyerProfile;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Выплата юристу с платежа клиента.
 * Бизнес-правило: комиссия площадки 30% ("Коли до адвоката приходять клієнти - забираємо 30%").
 * Уникальность по payment_id — один платёж порождает ровно одну выплату (идемпотентность webhook).
 */
#[ORM\Entity]
#[ORM\Table(name: 'payouts')]
#[ORM\Index(name: 'idx_payouts_lawyer_status', columns: ['lawyer_id', 'status', 'created_at'])]
class Payout
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: LawyerProfile::class)]
    #[ORM\JoinColumn(name: 'lawyer_id', nullable: false, onDelete: 'CASCADE')]
    private LawyerProfile $lawyer;

    #[ORM\OneToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(name: 'payment_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private Payment $payment;

    #[ORM\Column(name: 'gross_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $grossAmount;

    #[ORM\Column(name: 'commission_rate', type: Types::DECIMAL, precision: 4, scale: 3)]
    private string $commissionRate;

    #[ORM\Column(name: 'commission_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $commissionAmount;

    #[ORM\Column(name: 'net_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $netAmount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct(LawyerProfile $lawyer, Payment $payment, float $commissionRate)
    {
        if ($commissionRate < 0 || $commissionRate >= 1) {
            throw new \DomainException('Ставка комиссии должна быть в диапазоне [0, 1).');
        }
        $gross = (float) $payment->getAmount();
        $commission = round($gross * $commissionRate, 2);

        $this->id = Uuid::v7();
        $this->lawyer = $lawyer;
        $this->payment = $payment;
        $this->grossAmount = number_format($gross, 2, '.', '');
        $this->commissionRate = number_format($commissionRate, 3, '.', '');
        $this->commissionAmount = number_format($commission, 2, '.', '');
        $this->netAmount = number_format($gross - $commission, 2, '.', '');
        $this->currency = $payment->getCurrency();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getLawyer(): LawyerProfile { return $this->lawyer; }
    public function getPayment(): Payment { return $this->payment; }
    public function getGrossAmount(): string { return $this->grossAmount; }
    public function getCommissionRate(): string { return $this->commissionRate; }
    public function getCommissionAmount(): string { return $this->commissionAmount; }
    public function getNetAmount(): string { return $this->netAmount; }
    public function getCurrency(): string { return $this->currency; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function markPaid(): void
    {
        $this->status = self::STATUS_PAID;
        $this->paidAt = new \DateTimeImmutable();
    }
}
