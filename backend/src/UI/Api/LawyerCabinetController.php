<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Domain\Identity\Entity\User;
use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Domain\Lawyer\Entity\Specialization;
use App\Domain\Lawyer\Entity\VerificationDocument;
use App\Domain\Lawyer\Service\ComplianceChecker;
use App\Domain\Payment\Entity\Subscription;
use App\Infrastructure\Storage\S3Storage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/** Кабинет юриста: режим участия, специализации, документы верификации, compliance-статус. */
#[Route('/api/v1/lawyers/me')]
final class LawyerCabinetController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        private readonly ComplianceChecker $checker,
        private readonly S3Storage $storage,
    ) {}

    #[Route('', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $lawyer = $this->currentLawyer();
        if (!$lawyer) {
            return $this->problem('Профиль юриста не найден.', Response::HTTP_NOT_FOUND);
        }

        $docs = $this->em->getRepository(VerificationDocument::class)
            ->findBy(['lawyer' => $lawyer], ['uploadedAt' => 'DESC']);

        return $this->json([
            'slug' => $lawyer->getSlug(),
            'contribution_mode' => $lawyer->getContributionMode(),
            'compliance' => $this->complianceReport($lawyer),
            'articles_published_count' => $lawyer->getArticlesPublishedCount(),
            'last_article_at' => $lawyer->getLastArticleAt()?->format(DATE_ATOM),
            'content_score' => $lawyer->getContentScore(),
            'effective_rating' => $lawyer->getEffectiveRating(),
            'is_verified' => $lawyer->isVerified(),
            'specializations' => $lawyer->getSpecializations()->map(
                fn (Specialization $s) => ['id' => $s->getId()->toRfc4122(), 'name' => $s->getName(), 'slug' => $s->getSlug()]
            )->toArray(),
            'documents' => array_map(static fn (VerificationDocument $d) => [
                'id' => $d->getId()->toRfc4122(),
                'type' => $d->getType(),
                'file_name' => $d->getFileName(),
                'status' => $d->getStatus(),
                'uploaded_at' => $d->getUploadedAt()->format(DATE_ATOM),
            ], $docs),
        ]);
    }

    /** Смена режима участия и набора отраслей права. */
    #[Route('', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $lawyer = $this->currentLawyer();
        if (!$lawyer) {
            return $this->problem('Профиль юриста не найден.', Response::HTTP_NOT_FOUND);
        }
        $data = $this->jsonBody($request);

        try {
            if (isset($data['contribution_mode'])) {
                $lawyer->switchContributionMode((string) $data['contribution_mode']);
            }
            if (isset($data['specialization_ids']) && is_array($data['specialization_ids'])) {
                if (count($data['specialization_ids']) < 1) {
                    throw new \DomainException('Выберите минимум одну отрасль права.');
                }
                $lawyer->getSpecializations()->clear();
                foreach ($data['specialization_ids'] as $id) {
                    $spec = $this->em->find(Specialization::class, Uuid::fromString((string) $id))
                        ?? throw new \DomainException('Отрасль не найдена: ' . $id);
                    $lawyer->addSpecialization($spec);
                }
            }
            if (isset($data['bio'])) { $lawyer->setBio((string) $data['bio']); }
            if (isset($data['city'])) { $lawyer->setCity((string) $data['city']); }
            if (isset($data['hourly_rate'])) { $lawyer->setHourlyRate((string) $data['hourly_rate']); }
        } catch (\DomainException | \InvalidArgumentException $e) {
            return $this->problem($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->recomputeCompliance($lawyer);
        $this->em->flush();

        return $this->json(['ok' => true, 'compliance' => $this->complianceReport($lawyer)]);
    }

    /**
     * Загрузка документа верификации (multipart/form-data: file + type).
     * Хранится приватно в S3/MinIO; админ смотрит через presigned URL.
     */
    #[Route('/documents', methods: ['POST'])]
    public function uploadDocument(Request $request): JsonResponse
    {
        $lawyer = $this->currentLawyer();
        if (!$lawyer) {
            return $this->problem('Профиль юриста не найден.', Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('file');
        $type = (string) $request->request->get('type', '');

        if (!$file || !$file->isValid()) {
            return $this->problem('Файл не передан или повреждён.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!in_array($type, VerificationDocument::ALLOWED_TYPES, true)) {
            return $this->problem('Тип документа: advocate_certificate или education_certificate.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $mime = (string) $file->getMimeType();
        if (!in_array($mime, VerificationDocument::ALLOWED_MIME, true)) {
            return $this->problem('Допустимые форматы: PDF, JPEG, PNG.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($file->getSize() > VerificationDocument::MAX_SIZE_BYTES) {
            return $this->problem('Максимальный размер файла — 10 МБ.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $ext = $file->guessExtension() ?: 'bin';
        $objectKey = sprintf('verification/%s/%s-%s.%s', $lawyer->getId()->toRfc4122(), $type, bin2hex(random_bytes(6)), $ext);

        try {
            $url = $this->storage->put($objectKey, (string) file_get_contents($file->getPathname()), $mime, private: true);
        } catch (\Throwable $e) {
            return $this->problem('Хранилище файлов временно недоступно.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $doc = new VerificationDocument($lawyer, $type, $url, (string) $file->getClientOriginalName(), $mime);
        $this->em->persist($doc);
        $this->em->flush();

        return $this->json(['id' => $doc->getId()->toRfc4122(), 'status' => $doc->getStatus()], Response::HTTP_CREATED);
    }

    #[Route('/compliance', methods: ['GET'])]
    public function compliance(): JsonResponse
    {
        $lawyer = $this->currentLawyer();
        if (!$lawyer) {
            return $this->problem('Профиль юриста не найден.', Response::HTTP_NOT_FOUND);
        }
        $this->recomputeCompliance($lawyer);
        $this->em->flush();

        return $this->json($this->complianceReport($lawyer));
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function currentLawyer(): ?LawyerProfile
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getRole() !== User::ROLE_LAWYER) {
            return null;
        }
        return $this->em->getRepository(LawyerProfile::class)->findOneBy(['user' => $user]);
    }

    private function recomputeCompliance(LawyerProfile $lawyer): void
    {
        $status = $this->checker->evaluate(
            $lawyer,
            $this->hasActiveLawyerSubscription($lawyer),
            $this->specializationsCovered($lawyer),
        );
        $lawyer->setComplianceStatus($status);
        if ($status === LawyerProfile::COMPLIANCE_NON_COMPLIANT) {
            $lawyer->setFeatured(false); // санкция: снятие продвижения
        }
    }

    private function hasActiveLawyerSubscription(LawyerProfile $lawyer): bool
    {
        $sub = $this->em->getRepository(Subscription::class)->findOneBy(
            ['user' => $lawyer->getUser(), 'status' => Subscription::STATUS_ACTIVE], ['expiresAt' => 'DESC']
        );
        return $sub !== null && $sub->isActive() && str_starts_with($sub->getPlan()->getSlug(), 'lawyer');
    }

    private function specializationsCovered(LawyerProfile $lawyer): int
    {
        return (int) $this->db->fetchOne(
            "SELECT COUNT(DISTINCT a.specialization_id) FROM articles a
             WHERE a.author_id = :uid AND a.status = 'published' AND a.specialization_id IS NOT NULL",
            ['uid' => $lawyer->getUser()->getId()->toRfc4122()]
        );
    }

    private function complianceReport(LawyerProfile $lawyer): array
    {
        $covered = $this->specializationsCovered($lawyer);
        $total = $lawyer->getSpecializations()->count();

        return [
            'status' => $lawyer->getComplianceStatus(),
            'mode' => $lawyer->getContributionMode(),
            'specializations_total' => $total,
            'specializations_covered' => $covered,
            'articles_published' => $lawyer->getArticlesPublishedCount(),
            'last_article_at' => $lawyer->getLastArticleAt()?->format(DATE_ATOM),
            'cadence_target_days' => ComplianceChecker::CADENCE_OK_DAYS,
            'hint' => match ($lawyer->getComplianceStatus()) {
                LawyerProfile::COMPLIANCE_ONBOARDING => 'Напишіть першу статтю у кожну обрану галузь протягом 14 днів — або перейдіть на платний тариф.',
                LawyerProfile::COMPLIANCE_WARNING => 'Опублікуйте нову статтю найближчим часом, щоб зберегти позиції в каталозі.',
                LawyerProfile::COMPLIANCE_NON_COMPLIANT => 'Профіль понижено в каталозі. Опублікуйте статтю або оформіть тариф «Юрист».',
                default => 'Все гаразд — так тримати!',
            },
        ];
    }
}
