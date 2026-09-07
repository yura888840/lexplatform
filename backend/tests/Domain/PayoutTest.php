<?php
declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Identity\Entity\User;
use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Entity\Payout;
use PHPUnit\Framework\TestCase;

final class PayoutTest extends TestCase
{
    public function testCommission30Percent(): void
    {
        $client = new User('c@test.local', 'Клиент');
        $lawyer = new LawyerProfile(new User('l@test.local', 'Юрист', User::ROLE_LAWYER), 'x');
        $payment = new Payment($client, '1500.00', Payment::TYPE_CONSULTATION, ['lawyer_id' => 'x']);

        $payout = new Payout($lawyer, $payment, 0.30);

        self::assertSame('1500.00', $payout->getGrossAmount());
        self::assertSame('450.00', $payout->getCommissionAmount());  // 30% площадке
        self::assertSame('1050.00', $payout->getNetAmount());        // 70% юристу
        self::assertSame('0.300', $payout->getCommissionRate());
    }

    public function testRoundingOnOddAmount(): void
    {
        $payment = new Payment(new User('c@test.local', 'К'), '999.99', Payment::TYPE_CONSULTATION);
        $payout = new Payout(new LawyerProfile(new User('l@test.local', 'Ю', User::ROLE_LAWYER), 'y'), $payment, 0.30);

        // 999.99 * 0.3 = 299.997 → 300.00; net = 699.99
        self::assertSame('300.00', $payout->getCommissionAmount());
        self::assertSame('699.99', $payout->getNetAmount());
        self::assertEqualsWithDelta(
            (float) $payout->getGrossAmount(),
            (float) $payout->getCommissionAmount() + (float) $payout->getNetAmount(),
            0.001
        );
    }

    public function testInvalidRateRejected(): void
    {
        $payment = new Payment(new User('c@test.local', 'К'), '100.00', Payment::TYPE_CONSULTATION);
        $this->expectException(\DomainException::class);
        new Payout(new LawyerProfile(new User('l@test.local', 'Ю', User::ROLE_LAWYER), 'z'), $payment, 1.5);
    }
}
