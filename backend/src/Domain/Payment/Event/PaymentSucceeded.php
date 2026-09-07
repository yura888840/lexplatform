<?php
declare(strict_types=1);

namespace App\Domain\Payment\Event;

use App\Application\Shared\AsyncMessageInterface;
use Symfony\Component\Uid\Uuid;

/** Домен-событие (ТЗ §9.2): активация подписки/featured + инвойс. */
final readonly class PaymentSucceeded implements AsyncMessageInterface
{
    public function __construct(public Uuid $paymentId) {}
}
