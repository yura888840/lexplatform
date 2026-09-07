<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Domain\Identity\Entity\User;
use App\Domain\Payment\Entity\Subscription;
use App\Domain\Payment\Entity\SubscriptionPlan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/** Подписки PRO (ТЗ §11.2 /subscriptions). */
#[Route('/api/v1/subscriptions')]
final class SubscriptionController extends ApiController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/plans', methods: ['GET'])]
    public function plans(): JsonResponse
    {
        $plans = $this->em->getRepository(SubscriptionPlan::class)->findBy(['isActive' => true], ['price' => 'ASC']);

        return $this->json(['data' => array_map(fn (SubscriptionPlan $p) => [
            'id' => $p->getId()->toRfc4122(),
            'name' => $p->getName(),
            'slug' => $p->getSlug(),
            'price' => $p->getPrice(),
            'currency' => $p->getCurrency(),
            'interval' => $p->getInterval(),
            'features' => $p->getFeatures(),
        ], $plans)]);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $sub = $this->em->getRepository(Subscription::class)->findOneBy(
            ['user' => $user, 'status' => Subscription::STATUS_ACTIVE], ['expiresAt' => 'DESC']
        );

        if (!$sub || !$sub->isActive()) {
            return $this->json(['active' => false]);
        }

        return $this->json([
            'active' => true,
            'id' => $sub->getId()->toRfc4122(),
            'plan' => $sub->getPlan()->getSlug(),
            'started_at' => $sub->getStartedAt()->format(DATE_ATOM),
            'expires_at' => $sub->getExpiresAt()->format(DATE_ATOM),
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function cancel(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        try {
            $sub = $this->em->find(Subscription::class, Uuid::fromString($id));
        } catch (\InvalidArgumentException) {
            $sub = null;
        }
        if (!$sub || !$sub->getUser()->getId()->equals($user->getId())) {
            return $this->problem('Подписка не найдена.', Response::HTTP_NOT_FOUND);
        }
        $sub->cancel();
        $this->em->flush();

        return $this->json(['id' => $id, 'status' => $sub->getStatus()]);
    }
}
