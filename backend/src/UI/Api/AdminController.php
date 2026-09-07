<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Domain\Consultation\Entity\Question;
use App\Domain\Content\Entity\Article;
use App\Domain\Identity\Entity\User;
use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Domain\Lawyer\Entity\Review;
use App\Domain\Lawyer\Entity\VerificationDocument;
use App\Domain\Payment\Entity\Payment;
use App\Infrastructure\Storage\S3Storage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Адмін-панель (ROLE_ADMIN, захищено в security.yaml: ^/api/v1/admin).
 * Дашборд, користувачі, модерація відгуків, верифікація документів, керування юристами.
 */
#[Route('/api/v1/admin')]
final class AdminController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        private readonly S3Storage $storage,
    ) {}

    /** Зведення метрик платформи. */
    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $one = fn (string $sql, array $p = []) => (int) $this->db->fetchOne($sql, $p);
        $sum = fn (string $sql, array $p = []) => (float) ($this->db->fetchOne($sql, $p) ?? 0);

        return $this->json([
            'users' => [
                'total' => $one('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
                'clients' => $one("SELECT COUNT(*) FROM users WHERE role = 'client' AND deleted_at IS NULL"),
                'lawyers' => $one("SELECT COUNT(*) FROM users WHERE role = 'lawyer' AND deleted_at IS NULL"),
                'banned' => $one("SELECT COUNT(*) FROM users WHERE status = 'banned'"),
                'new_7d' => $one("SELECT COUNT(*) FROM users WHERE created_at > NOW() - INTERVAL '7 days'"),
            ],
            'moderation' => [
                'reviews_pending' => $one("SELECT COUNT(*) FROM reviews WHERE status = 'pending'"),
                'documents_pending' => $one("SELECT COUNT(*) FROM verification_documents WHERE status = 'pending'"),
                'articles_review' => $one("SELECT COUNT(*) FROM articles WHERE status = 'review'"),
            ],
            'content' => [
                'questions' => $one('SELECT COUNT(*) FROM questions'),
                'articles_published' => $one("SELECT COUNT(*) FROM articles WHERE status = 'published'"),
                'lawyers_verified' => $one('SELECT COUNT(*) FROM lawyer_profiles WHERE is_verified = true'),
            ],
            'revenue' => [
                'payments_succeeded' => $one("SELECT COUNT(*) FROM payments WHERE status = 'succeeded'"),
                'gross_total' => number_format($sum("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'succeeded'"), 2, '.', ''),
                'commission_total' => number_format($sum("SELECT COALESCE(SUM(commission_amount),0) FROM payouts"), 2, '.', ''),
            ],
        ]);
    }

    // ── Користувачі ──────────────────────────────────────────────────────

    #[Route('/users', methods: ['GET'])]
    public function users(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 25;

        $qb = $this->em->createQueryBuilder()->select('u')->from(User::class, 'u')->orderBy('u.createdAt', 'DESC');
        if ($role = $request->query->get('role')) {
            $qb->andWhere('u.role = :role')->setParameter('role', $role);
        }
        if ($status = $request->query->get('status')) {
            $qb->andWhere('u.status = :status')->setParameter('status', $status);
        }
        if ($q = trim((string) $request->query->get('q', ''))) {
            $qb->andWhere('LOWER(u.fullName) LIKE :q OR LOWER(u.email) LIKE :q')->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        $total = (clone $qb)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        $users = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return $this->paginated(array_map(fn (User $u) => [
            'id' => $u->getId()->toRfc4122(),
            'full_name' => $u->getFullName(),
            'email' => $u->getEmail(),
            'role' => $u->getRole(),
            'status' => $u->getStatus(),
            'created_at' => $u->getCreatedAt()->format(DATE_ATOM),
        ], $users), (int) $total, $page, $perPage);
    }

    #[Route('/users/{id}/ban', methods: ['POST'])]
    public function banUser(string $id): JsonResponse
    {
        $user = $this->findUser($id);
        if (!$user) {
            return $this->problem('Користувача не знайдено.', Response::HTTP_NOT_FOUND);
        }
        $this->guardNotSelf($user);
        if (in_array(User::ROLE_ADMIN, [$user->getRole()], true) || $user->getRole() === User::ROLE_SUPERADMIN) {
            return $this->problem('Не можна заблокувати адміністратора.', Response::HTTP_FORBIDDEN);
        }
        $user->ban();
        $this->em->flush();
        return $this->json(['id' => $id, 'status' => $user->getStatus()]);
    }

    #[Route('/users/{id}/unban', methods: ['POST'])]
    public function unbanUser(string $id): JsonResponse
    {
        $user = $this->findUser($id);
        if (!$user) {
            return $this->problem('Користувача не знайдено.', Response::HTTP_NOT_FOUND);
        }
        $user->unban();
        $this->em->flush();
        return $this->json(['id' => $id, 'status' => $user->getStatus()]);
    }

    /** Зміна ролі (напр. призначити модератора/редактора). Тільки superadmin може призначати admin. */
    #[Route('/users/{id}/role', methods: ['PATCH'])]
    public function changeRole(string $id, Request $request): JsonResponse
    {
        $user = $this->findUser($id);
        if (!$user) {
            return $this->problem('Користувача не знайдено.', Response::HTTP_NOT_FOUND);
        }
        $this->guardNotSelf($user);
        $newRole = (string) ($this->jsonBody($request)['role'] ?? '');

        if (in_array($newRole, [User::ROLE_ADMIN, User::ROLE_SUPERADMIN], true) && !$this->isGranted('ROLE_SUPERADMIN')) {
            return $this->problem('Призначати адміністраторів може лише суперадмін.', Response::HTTP_FORBIDDEN);
        }
        try {
            $user->setRole($newRole);
        } catch (\DomainException $e) {
            return $this->problem($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->em->flush();
        return $this->json(['id' => $id, 'role' => $user->getRole()]);
    }

    // ── Модерація відгуків ───────────────────────────────────────────────

    #[Route('/reviews/pending', methods: ['GET'])]
    public function pendingReviews(): JsonResponse
    {
        $reviews = $this->em->getRepository(Review::class)
            ->findBy(['status' => Review::STATUS_PENDING], ['createdAt' => 'ASC'], 50);

        return $this->json(['data' => array_map(fn (Review $r) => [
            'id' => $r->getId()->toRfc4122(),
            'rating' => $r->getRating(),
            'body' => $r->getBody(),
            'author' => $r->getAuthor()->getFullName(),
            'lawyer' => $r->getLawyer()->getUser()->getFullName(),
            'lawyer_slug' => $r->getLawyer()->getSlug(),
            'created_at' => $r->getCreatedAt()->format(DATE_ATOM),
        ], $reviews)]);
    }

    #[Route('/reviews/{id}/approve', methods: ['POST'])]
    public function approveReview(string $id): JsonResponse
    {
        $review = $this->find(Review::class, $id);
        if (!$review) {
            return $this->problem('Відгук не знайдено.', Response::HTTP_NOT_FOUND);
        }
        try {
            $review->approve(); // домен: перераховує рейтинг юриста
        } catch (\DomainException $e) {
            return $this->problem($e->getMessage(), Response::HTTP_CONFLICT);
        }
        $this->em->flush();
        return $this->json(['id' => $id, 'status' => $review->getStatus()]);
    }

    #[Route('/reviews/{id}/reject', methods: ['POST'])]
    public function rejectReview(string $id): JsonResponse
    {
        $review = $this->find(Review::class, $id);
        if (!$review) {
            return $this->problem('Відгук не знайдено.', Response::HTTP_NOT_FOUND);
        }
        $review->reject();
        $this->em->flush();
        return $this->json(['id' => $id, 'status' => $review->getStatus()]);
    }

    // ── Верифікація документів ───────────────────────────────────────────

    #[Route('/documents/pending', methods: ['GET'])]
    public function pendingDocuments(): JsonResponse
    {
        $docs = $this->em->getRepository(VerificationDocument::class)
            ->findBy(['status' => VerificationDocument::STATUS_PENDING], ['uploadedAt' => 'ASC'], 50);

        return $this->json(['data' => array_map(function (VerificationDocument $d) {
            // Presigned URL для перегляду приватного файлу (15 хв)
            $viewUrl = null;
            try {
                $key = $this->objectKeyFromUrl($d->getFileUrl());
                $viewUrl = $key ? $this->storage->presignedGet($key, 15) : $d->getFileUrl();
            } catch (\Throwable) {
                $viewUrl = null;
            }
            return [
                'id' => $d->getId()->toRfc4122(),
                'type' => $d->getType(),
                'file_name' => $d->getFileName(),
                'lawyer' => $d->getLawyer()->getUser()->getFullName(),
                'lawyer_slug' => $d->getLawyer()->getSlug(),
                'view_url' => $viewUrl,
                'uploaded_at' => $d->getUploadedAt()->format(DATE_ATOM),
            ];
        }, $docs)]);
    }

    #[Route('/documents/{id}/approve', methods: ['POST'])]
    public function approveDocument(string $id, Request $request): JsonResponse
    {
        $doc = $this->find(VerificationDocument::class, $id);
        if (!$doc) {
            return $this->problem('Документ не знайдено.', Response::HTTP_NOT_FOUND);
        }
        $doc->approve((string) ($this->jsonBody($request)['note'] ?? '') ?: null); // домен: верифікує профіль
        $this->em->flush();
        return $this->json(['id' => $id, 'status' => $doc->getStatus()]);
    }

    #[Route('/documents/{id}/reject', methods: ['POST'])]
    public function rejectDocument(string $id, Request $request): JsonResponse
    {
        $doc = $this->find(VerificationDocument::class, $id);
        if (!$doc) {
            return $this->problem('Документ не знайдено.', Response::HTTP_NOT_FOUND);
        }
        $note = trim((string) ($this->jsonBody($request)['note'] ?? ''));
        if ($note === '') {
            return $this->problem('Вкажіть причину відхилення.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $doc->reject($note);
        $this->em->flush();
        return $this->json(['id' => $id, 'status' => $doc->getStatus()]);
    }

    // ── Керування юристами ───────────────────────────────────────────────

    #[Route('/lawyers/{slug}/featured', methods: ['POST'])]
    public function toggleFeatured(string $slug, Request $request): JsonResponse
    {
        $lawyer = $this->em->getRepository(LawyerProfile::class)->findOneBy(['slug' => $slug]);
        if (!$lawyer) {
            return $this->problem('Юриста не знайдено.', Response::HTTP_NOT_FOUND);
        }
        $lawyer->setFeatured((bool) ($this->jsonBody($request)['featured'] ?? false));
        $this->em->flush();
        return $this->json(['slug' => $slug, 'is_featured' => $lawyer->isFeatured()]);
    }

    #[Route('/lawyers/{slug}/verify', methods: ['POST'])]
    public function verifyLawyer(string $slug): JsonResponse
    {
        $lawyer = $this->em->getRepository(LawyerProfile::class)->findOneBy(['slug' => $slug]);
        if (!$lawyer) {
            return $this->problem('Юриста не знайдено.', Response::HTTP_NOT_FOUND);
        }
        $lawyer->verify();
        $this->em->flush();
        return $this->json(['slug' => $slug, 'is_verified' => $lawyer->isVerified()]);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function findUser(string $id): ?User
    {
        try {
            return $this->em->find(User::class, Uuid::fromString($id));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @param class-string $class */
    private function find(string $class, string $id): ?object
    {
        try {
            return $this->em->find($class, Uuid::fromString($id));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function guardNotSelf(User $target): void
    {
        $current = $this->getUser();
        if ($current instanceof User && $current->getId()->equals($target->getId())) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Не можна змінювати власний акаунт.');
        }
    }

    /** Витягує S3-ключ об'єкта з повного URL (endpoint/bucket/key). */
    private function objectKeyFromUrl(string $url): ?string
    {
        if (preg_match('#/[^/]+/(verification/.+)$#', $url, $m)) {
            return $m[1];
        }
        return null;
    }
}
