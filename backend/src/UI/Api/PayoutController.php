<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Domain\Identity\Entity\User;
use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Domain\Payment\Entity\Payout;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Выплаты юристу: 70% от платежей клиентов (комиссия площадки 30%). */
#[Route('/api/v1/payouts')]
final class PayoutController extends ApiController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $lawyer = $this->em->getRepository(LawyerProfile::class)->findOneBy(['user' => $user]);
        if (!$lawyer) {
            return $this->problem('Профиль юриста не найден.', Response::HTTP_NOT_FOUND);
        }

        $payouts = $this->em->getRepository(Payout::class)
            ->findBy(['lawyer' => $lawyer], ['createdAt' => 'DESC'], 100);

        $pendingTotal = '0.00';
        foreach ($payouts as $p) {
            if ($p->getStatus() === Payout::STATUS_PENDING) {
                $pendingTotal = number_format((float) $pendingTotal + (float) $p->getNetAmount(), 2, '.', '');
            }
        }

        return $this->json([
            'pending_total' => $pendingTotal,
            'data' => array_map(static fn (Payout $p) => [
                'id' => $p->getId()->toRfc4122(),
                'gross' => $p->getGrossAmount(),
                'commission_rate' => $p->getCommissionRate(),
                'commission' => $p->getCommissionAmount(),
                'net' => $p->getNetAmount(),
                'currency' => $p->getCurrency(),
                'status' => $p->getStatus(),
                'created_at' => $p->getCreatedAt()->format(DATE_ATOM),
            ], $payouts),
        ]);
    }
}
