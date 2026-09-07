<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Domain\Consultation\Entity\Answer;
use App\Domain\Consultation\Entity\Question;
use App\Domain\Identity\Entity\User;
use App\Domain\Payment\Entity\Payment;
use App\Infrastructure\Storage\S3Storage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Особистий кабінет користувача (клієнта): профіль, питання, платежі.
 * Доступний будь-якому автентифікованому користувачу (клієнт, юрист — усі мають базовий профіль).
 */
#[Route('/api/v1/account')]
final class AccountController extends ApiController
{
    private const AVATAR_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const AVATAR_MAX = 5 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly S3Storage $storage,
    ) {}

    /** Дані профілю поточного користувача. */
    #[Route('', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $questionsCount = $this->em->getRepository(Question::class)->count(['author' => $user]);
        $paymentsCount = $this->em->getRepository(Payment::class)->count(['user' => $user]);

        return $this->json([
            'id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'full_name' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'avatar_url' => $user->getAvatarUrl(),
            'role' => $user->getRole(),
            'status' => $user->getStatus(),
            'locale' => $user->getLocale(),
            'member_since' => $user->getCreatedAt()->format('Y-m-d'),
            'stats' => [
                'questions' => $questionsCount,
                'payments' => $paymentsCount,
            ],
        ]);
    }

    /** Редагування профілю: ім'я, телефон, мова. Email не змінюється тут (потребує верифікації). */
    #[Route('', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $this->jsonBody($request);

        if (isset($data['full_name'])) {
            $name = trim((string) $data['full_name']);
            if (mb_strlen($name) < 2) {
                return $this->problem("Ім'я має містити щонайменше 2 символи.", Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user->setFullName($name);
        }
        if (array_key_exists('phone', $data)) {
            $phone = $data['phone'] === null ? null : trim((string) $data['phone']);
            if ($phone !== null && !preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone)) {
                return $this->problem('Некоректний формат телефону.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $user->setPhone($phone);
        }
        if (isset($data['locale']) && in_array($data['locale'], ['uk', 'en'], true)) {
            $user->setLocale((string) $data['locale']);
        }

        $this->em->flush();

        return $this->json(['ok' => true, 'full_name' => $user->getFullName(), 'phone' => $user->getPhone()]);
    }

    /** Завантаження аватара (multipart: file). */
    #[Route('/avatar', methods: ['POST'])]
    public function uploadAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $file = $request->files->get('file');

        if (!$file || !$file->isValid()) {
            return $this->problem('Файл не передано або пошкоджено.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $mime = (string) $file->getMimeType();
        if (!in_array($mime, self::AVATAR_MIME, true)) {
            return $this->problem('Допустимі формати: JPEG, PNG, WebP.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($file->getSize() > self::AVATAR_MAX) {
            return $this->problem('Максимальний розмір — 5 МБ.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $ext = $file->guessExtension() ?: 'jpg';
        $key = sprintf('avatars/%s/%s.%s', $user->getId()->toRfc4122(), bin2hex(random_bytes(6)), $ext);

        try {
            $url = $this->storage->put($key, (string) file_get_contents($file->getPathname()), $mime, private: false);
        } catch (\Throwable) {
            return $this->problem('Сховище файлів тимчасово недоступне.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $user->setAvatarUrl($url);
        $this->em->flush();

        return $this->json(['avatar_url' => $url]);
    }

    /** Мої питання зі статусами та кількістю відповідей. */
    #[Route('/questions', methods: ['GET'])]
    public function questions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;

        $questions = $this->em->getRepository(Question::class)->findBy(
            ['author' => $user], ['createdAt' => 'DESC'], $perPage, ($page - 1) * $perPage
        );
        $total = $this->em->getRepository(Question::class)->count(['author' => $user]);

        return $this->paginated(array_map(fn (Question $q) => [
            'id' => $q->getId()->toRfc4122(),
            'title' => $q->getTitle(),
            'category' => $q->getCategory()->getName(),
            'status' => $q->getStatus(),
            'answers_count' => $q->getAnswersCount(),
            'views_count' => $q->getViewsCount(),
            'has_accepted' => $q->getAcceptedAnswerId() !== null,
            'created_at' => $q->getCreatedAt()->format(DATE_ATOM),
        ], $questions), $total, $page, $perPage);
    }

    /** Історія платежів і консультацій клієнта. */
    #[Route('/payments', methods: ['GET'])]
    public function payments(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 20;

        $payments = $this->em->getRepository(Payment::class)->findBy(
            ['user' => $user], ['createdAt' => 'DESC'], $perPage, ($page - 1) * $perPage
        );
        $total = $this->em->getRepository(Payment::class)->count(['user' => $user]);

        $typeLabels = [
            Payment::TYPE_SUBSCRIPTION => 'Підписка',
            Payment::TYPE_CONSULTATION => 'Консультація',
            Payment::TYPE_FEATURED => 'Просування',
        ];

        return $this->paginated(array_map(fn (Payment $p) => [
            'id' => $p->getId()->toRfc4122(),
            'order_id' => $p->getOrderId(),
            'type' => $p->getType(),
            'type_label' => $typeLabels[$p->getType()] ?? $p->getType(),
            'amount' => $p->getAmount(),
            'currency' => $p->getCurrency(),
            'status' => $p->getStatus(),
            'created_at' => $p->getCreatedAt()->format(DATE_ATOM),
            'paid_at' => $p->getPaidAt()?->format(DATE_ATOM),
        ], $payments), $total, $page, $perPage);
    }
}
