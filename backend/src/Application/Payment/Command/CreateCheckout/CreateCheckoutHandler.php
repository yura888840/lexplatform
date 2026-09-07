<?php
declare(strict_types=1);

namespace App\Application\Payment\Command\CreateCheckout;

use App\Domain\Identity\Entity\User;
use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Entity\SubscriptionPlan;
use App\Infrastructure\Payment\LiqPayClient;
use Doctrine\ORM\EntityManagerInterface;

/** Создание платежа + параметров LiqPay checkout (ТЗ §11.2 /payments/checkout). */
final readonly class CreateCheckoutHandler
{
    private const FEATURED_PRICE_PER_DAY = '50.00'; // грн/день продвижения (CAT-07)

    public function __construct(
        private EntityManagerInterface $em,
        private LiqPayClient $liqpay,
    ) {}

    /** @return array{payment_id:string, order_id:string, checkout_url:string, data:string, signature:string} */
    public function __invoke(CreateCheckoutCommand $command): array
    {
        $user = $this->em->find(User::class, $command->userId)
            ?? throw new \DomainException('Пользователь не найден.');

        [$amount, $description, $metadata] = match ($command->type) {
            Payment::TYPE_SUBSCRIPTION => $this->forSubscription($command->planSlug),
            Payment::TYPE_FEATURED => $this->forFeatured($user, $command->featuredDays),
            Payment::TYPE_CONSULTATION => $this->forConsultation($command->lawyerSlug, $command->hours),
            default => throw new \DomainException('Неизвестный тип платежа.'),
        };

        $payment = new Payment($user, $amount, $command->type, $metadata);
        $this->em->persist($payment);
        $this->em->flush();

        $checkout = $this->liqpay->buildCheckout(
            $payment->getOrderId(),
            $payment->getAmount(),
            $payment->getCurrency(),
            $description,
        );

        return ['payment_id' => $payment->getId()->toRfc4122(), 'order_id' => $payment->getOrderId()] + $checkout;
    }

    /** @return array{0:string,1:string,2:array} */
    private function forSubscription(?string $planSlug): array
    {
        $plan = $this->em->getRepository(SubscriptionPlan::class)
            ->findOneBy(['slug' => (string) $planSlug, 'isActive' => true])
            ?? throw new \DomainException('Тарифный план не найден.');

        return [
            $plan->getPrice(),
            sprintf('LexPlatform: подписка «%s» (%s)', $plan->getName(), $plan->getInterval() === 'year' ? 'год' : 'месяц'),
            ['plan_id' => $plan->getId()->toRfc4122()],
        ];
    }

    /**
     * Клиент оплачивает консультацию юриста. Комиссия площадки 30% удерживается
     * при успешной оплате (Payout создаёт PaymentSucceededHandler).
     * @return array{0:string,1:string,2:array}
     */
    private function forConsultation(?string $lawyerSlug, ?int $hours): array
    {
        $lawyer = $this->em->getRepository(LawyerProfile::class)->findOneBy(['slug' => (string) $lawyerSlug])
            ?? throw new \DomainException('Юрист не найден.');
        if ($lawyer->getHourlyRate() === null) {
            throw new \DomainException('Юрист не указал стоимость консультации.');
        }
        $hours = max(1, min(8, (int) ($hours ?? 1)));

        return [
            number_format((float) $lawyer->getHourlyRate() * $hours, 2, '.', ''),
            sprintf('LexPlatform: консультація — %s (%d год.)', $lawyer->getUser()->getFullName(), $hours),
            ['lawyer_id' => $lawyer->getId()->toRfc4122(), 'hours' => $hours],
        ];
    }

    /** @return array{0:string,1:string,2:array} */
    private function forFeatured(User $user, ?int $days): array
    {
        $lawyer = $this->em->getRepository(LawyerProfile::class)->findOneBy(['user' => $user])
            ?? throw new \DomainException('Продвижение доступно только юристам с профилем.');
        $days = max(1, min(90, (int) $days));

        return [
            number_format((float) self::FEATURED_PRICE_PER_DAY * $days, 2, '.', ''),
            sprintf('LexPlatform: продвижение профиля на %d дн.', $days),
            ['lawyer_id' => $lawyer->getId()->toRfc4122(), 'days' => $days],
        ];
    }
}
