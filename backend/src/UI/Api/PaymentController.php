<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Application\Payment\Command\CreateCheckout\CreateCheckoutCommand;
use App\Application\Payment\Command\CreateCheckout\CreateCheckoutHandler;
use App\Domain\Identity\Entity\User;
use App\Domain\Payment\Entity\Payment;
use App\Domain\Payment\Event\PaymentSucceeded;
use App\Infrastructure\Payment\LiqPayClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/** Платежи через LiqPay (ТЗ §11.2 /payments). */
#[Route('/api/v1/payments')]
final class PaymentController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LiqPayClient $liqpay,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * POST /payments/checkout
     * Body: {type: "subscription"|"featured", plan_slug?, days?}
     * → параметры для формы LiqPay (фронт делает auto-submit POST на checkout_url).
     */
    #[Route('/checkout', methods: ['POST'])]
    public function checkout(Request $request, CreateCheckoutHandler $handler): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $this->jsonBody($request);

        try {
            $result = $handler(new CreateCheckoutCommand(
                userId: $user->getId(),
                type: (string) ($data['type'] ?? ''),
                planSlug: isset($data['plan_slug']) ? (string) $data['plan_slug'] : null,
                featuredDays: isset($data['days']) ? (int) $data['days'] : null,
                lawyerSlug: isset($data['lawyer_slug']) ? (string) $data['lawyer_slug'] : null,
                hours: isset($data['hours']) ? (int) $data['hours'] : null,
            ));
        } catch (\DomainException $e) {
            return $this->problem($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($result, Response::HTTP_CREATED);
    }

    /**
     * POST /payments/webhook/liqpay — server_url callback.
     * LiqPay шлёт form-data: data (base64 json) + signature.
     * Всегда отвечаем 200 при валидной подписи, иначе LiqPay ретраит бесконечно.
     */
    #[Route('/webhook/liqpay', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        $data = (string) $request->request->get('data', '');
        $signature = (string) $request->request->get('signature', '');

        if ($data === '' || !$this->liqpay->verifyCallback($data, $signature)) {
            $this->logger->warning('LiqPay webhook: невалидная подпись', ['ip' => $request->getClientIp()]);
            return $this->problem('Invalid signature.', Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $this->liqpay->decodeCallback($data);
        } catch (\Throwable $e) {
            return $this->problem('Malformed payload.', Response::HTTP_BAD_REQUEST);
        }

        $orderId = (string) ($payload['order_id'] ?? '');
        $status = (string) ($payload['status'] ?? '');
        $txId = isset($payload['payment_id']) ? (string) $payload['payment_id'] : null;

        $payment = $this->em->getRepository(Payment::class)->findOneBy(['orderId' => $orderId]);
        if (!$payment) {
            $this->logger->warning('LiqPay webhook: платёж не найден', ['order_id' => $orderId]);
            return new Response('OK'); // 200, чтобы LiqPay не ретраил чужой/старый заказ
        }

        // Сверка суммы и валюты — защита от подмены
        if (isset($payload['amount']) && abs((float) $payload['amount'] - (float) $payment->getAmount()) > 0.001) {
            $this->logger->error('LiqPay webhook: сумма не совпадает', ['order_id' => $orderId]);
            return new Response('OK');
        }

        if ($this->liqpay->isSuccessStatus($status)) {
            if ($payment->markSucceeded($txId ?? 'liqpay')) {
                $this->em->flush();
                $this->bus->dispatch(new PaymentSucceeded($payment->getId()));
            }
        } elseif ($this->liqpay->isFailureStatus($status)) {
            $payment->markFailed($txId);
            $this->em->flush();
        }
        // Промежуточные статусы (processing, wait_accept, 3ds_verify) — игнорируем, ждём финальный

        return new Response('OK');
    }

    #[Route('/history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;

        $payments = $this->em->getRepository(Payment::class)->findBy(
            ['user' => $user], ['createdAt' => 'DESC'], $perPage, ($page - 1) * $perPage
        );
        $total = $this->em->getRepository(Payment::class)->count(['user' => $user]);

        return $this->paginated(array_map(fn (Payment $p) => [
            'id' => $p->getId()->toRfc4122(),
            'order_id' => $p->getOrderId(),
            'amount' => $p->getAmount(),
            'currency' => $p->getCurrency(),
            'type' => $p->getType(),
            'status' => $p->getStatus(),
            'created_at' => $p->getCreatedAt()->format(DATE_ATOM),
            'paid_at' => $p->getPaidAt()?->format(DATE_ATOM),
        ], $payments), $total, $page, $perPage);
    }

    /** Статус платежа для result_url страницы фронта. */
    #[Route('/status/{orderId}', methods: ['GET'])]
    public function status(string $orderId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payment = $this->em->getRepository(Payment::class)->findOneBy(['orderId' => $orderId, 'user' => $user]);
        if (!$payment) {
            return $this->problem('Платёж не найден.', Response::HTTP_NOT_FOUND);
        }
        return $this->json(['order_id' => $orderId, 'status' => $payment->getStatus()]);
    }
}
