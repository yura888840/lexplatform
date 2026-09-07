<?php
declare(strict_types=1);

namespace App\Domain\Payment\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Тарифный план (ТЗ §10.1: subscription_plans). */
#[ORM\Entity]
#[ORM\Table(name: 'subscription_plans')]
class SubscriptionPlan
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 100, unique: true)]
    private string $slug;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $price;

    #[ORM\Column(length: 3)]
    private string $currency = 'UAH';

    /** month | year */
    #[ORM\Column(length: 10)]
    private string $interval = 'month';

    #[ORM\Column(type: Types::JSON)]
    private array $features = [];

    #[ORM\Column(name: 'is_active')]
    private bool $isActive = true;

    public function __construct(string $name, string $slug, string $price, string $interval = 'month', array $features = [])
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->slug = $slug;
        $this->price = $price;
        $this->interval = $interval;
        $this->features = $features;
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
    public function getPrice(): string { return $this->price; }
    public function getCurrency(): string { return $this->currency; }
    public function getInterval(): string { return $this->interval; }
    public function getFeatures(): array { return $this->features; }
    public function isActive(): bool { return $this->isActive; }
    public function periodDays(): int { return $this->interval === 'year' ? 365 : 30; }
}
