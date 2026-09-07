<?php
declare(strict_types=1);

namespace App\Application\Payment\Command\CreateCheckout;

use Symfony\Component\Uid\Uuid;

final readonly class CreateCheckoutCommand
{
    public function __construct(
        public Uuid $userId,
        public string $type,          // subscription | featured
        public ?string $planSlug,     // для type=subscription
        public ?int $featuredDays,    // для type=featured
        public ?string $lawyerSlug = null, // для type=consultation
        public ?int $hours = null,         // для type=consultation
    ) {}
}
