<?php
declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Identity\Entity\User;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Entity\Subscription;
use App\Domain\Payment\Entity\SubscriptionPlan;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function testWebhookIdempotency(): void
    {
        $user = new User('u@test.local', 'User');
        $payment = new Payment($user, '299.00', Payment::TYPE_SUBSCRIPTION);

        self::assertTrue($payment->markSucceeded('tx-1'));   // первый webhook применяется
        self::assertFalse($payment->markSucceeded('tx-1'));  // ретрай LiqPay — no-op
        self::assertSame(Payment::STATUS_SUCCEEDED, $payment->getStatus());
    }

    public function testFailedCannotSucceed(): void
    {
        $payment = new Payment(new User('u@test.local', 'User'), '100.00', Payment::TYPE_FEATURED);
        $payment->markFailed();
        $this->expectException(\DomainException::class);
        $payment->markSucceeded('tx-2');
    }

    public function testNegativeAmountRejected(): void
    {
        $this->expectException(\DomainException::class);
        new Payment(new User('u@test.local', 'User'), '-5.00', Payment::TYPE_FEATURED);
    }

    public function testSubscriptionExtend(): void
    {
        $plan = new SubscriptionPlan('PRO', 'pro-month', '299.00', 'month');
        $sub = new Subscription(new User('u@test.local', 'User'), $plan);
        $firstExpiry = $sub->getExpiresAt();
        $sub->extend();

        self::assertTrue($sub->isActive());
        self::assertGreaterThan($firstExpiry, $sub->getExpiresAt());
    }
}
