<?php
declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Entity\Subscription;
use App\Domain\Payment\Entity\SubscriptionPlan;
use App\Domain\Payment\Entity\Payout;
use App\Domain\Payment\Event\PaymentSucceeded;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

/**
 * Реакция на PaymentSucceeded (ТЗ §9.2):
 * subscription → активация/продление PRO; featured → включение продвижения; email-квитанция.
 */
#[AsMessageHandler]
final readonly class PaymentSucceededHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%app.commission_rate%')]
        private float $commissionRate,
    ) {}

    public function __invoke(PaymentSucceeded $event): void
    {
        $payment = $this->em->find(Payment::class, $event->paymentId);
        if (!$payment || $payment->getStatus() !== Payment::STATUS_SUCCEEDED) {
            return;
        }

        match ($payment->getType()) {
            Payment::TYPE_SUBSCRIPTION => $this->activateSubscription($payment),
            Payment::TYPE_FEATURED => $this->activateFeatured($payment),
            Payment::TYPE_CONSULTATION => $this->createPayout($payment),
            default => null,
        };

        $this->em->flush();

        $this->mailer->send(
            (new Email())
                ->to($payment->getUser()->getEmail())
                ->subject('LexPlatform: оплата получена')
                ->text(sprintf(
                    "Спасибо за оплату!\n\nЗаказ: %s\nСумма: %s %s\n\n— LexPlatform",
                    $payment->getOrderId(), $payment->getAmount(), $payment->getCurrency()
                ))
        );
    }

    private function activateSubscription(Payment $payment): void
    {
        $planId = $payment->getMetadata()['plan_id'] ?? null;
        $plan = $planId ? $this->em->find(SubscriptionPlan::class, Uuid::fromString($planId)) : null;
        if (!$plan) {
            $this->logger->error('PaymentSucceeded: план не найден', ['payment' => $payment->getOrderId()]);
            return;
        }

        $existing = $this->em->getRepository(Subscription::class)->findOneBy([
            'user' => $payment->getUser(), 'plan' => $plan, 'status' => Subscription::STATUS_ACTIVE,
        ]);

        if ($existing) {
            $existing->extend();
        } else {
            $this->em->persist(new Subscription($payment->getUser(), $plan));
        }
    }

    /** Бизнес-правило: с платежа клиента юристу площадка удерживает 30%. */
    private function createPayout(Payment $payment): void
    {
        // Идемпотентность: payout уникален по payment_id
        if ($this->em->getRepository(Payout::class)->findOneBy(['payment' => $payment])) {
            return;
        }
        $lawyerId = $payment->getMetadata()['lawyer_id'] ?? null;
        $lawyer = $lawyerId ? $this->em->find(LawyerProfile::class, Uuid::fromString($lawyerId)) : null;
        if (!$lawyer) {
            $this->logger->error('PaymentSucceeded: юрист для payout не найден', ['payment' => $payment->getOrderId()]);
            return;
        }
        $this->em->persist(new Payout($lawyer, $payment, $this->commissionRate));
        $lawyer->incrementConsultations();
    }

    private function activateFeatured(Payment $payment): void
    {
        $lawyerId = $payment->getMetadata()['lawyer_id'] ?? null;
        $lawyer = $lawyerId ? $this->em->find(LawyerProfile::class, Uuid::fromString($lawyerId)) : null;
        if ($lawyer) {
            $lawyer->setFeatured(true);
            // Снятие featured по истечении срока — cron-команда app:featured-expire (V2.1)
        }
    }
}
