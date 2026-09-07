<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Domain\Lawyer\Entity\LawyerProfile;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Каталог юристов: фильтры, сортировка, featured (ТЗ §6.2, §11.2). */
#[Route('/api/v1/lawyers')]
final class LawyerController extends ApiController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(50, max(1, $request->query->getInt('per_page', 20))); // CAT-01: 20/стр

        $qb = $this->em->createQueryBuilder()
            ->select('l', 'u')
            ->from(LawyerProfile::class, 'l')
            ->join('l.user', 'u')
            ->where('u.status = :active')->setParameter('active', 'active');

        if ($spec = $request->query->get('specialization')) {
            $qb->join('l.specializations', 's')->andWhere('s.slug = :spec')->setParameter('spec', $spec);
        }
        if ($city = $request->query->get('city')) {
            $qb->andWhere('LOWER(l.city) = LOWER(:city)')->setParameter('city', $city);
        }
        if ($request->query->has('rating_min')) {
            $qb->andWhere('l.rating >= :rmin')->setParameter('rmin', $request->query->get('rating_min'));
        }
        if ($request->query->has('price_max')) {
            $qb->andWhere('l.hourlyRate <= :pmax')->setParameter('pmax', $request->query->get('price_max'));
        }
        if ($request->query->getBoolean('online')) {
            $qb->andWhere('l.isOnline = true');
        }
        if ($request->query->getBoolean('verified')) {
            $qb->andWhere('l.isVerified = true');
        }

        // Featured сверху (CAT-07); non_compliant — вниз (санкция «статьи или деньги»);
        // рейтинг = отзывы + контент-бонус за статьи (бизнес: рейтинг растёт от количества статей)
        $qb->addSelect('(l.rating + l.contentScore) AS HIDDEN effective_rating')
           ->addSelect("CASE WHEN l.complianceStatus = 'non_compliant' THEN 1 ELSE 0 END AS HIDDEN compliance_penalty")
           ->orderBy('compliance_penalty', 'ASC')
           ->addOrderBy('l.isFeatured', 'DESC');
        match ($request->query->get('sort', 'rating')) {
            'reviews' => $qb->addOrderBy('l.reviewsCount', 'DESC'),
            'experience' => $qb->addOrderBy('l.experienceYears', 'DESC'),
            'price_asc' => $qb->addOrderBy('l.hourlyRate', 'ASC'),
            'price_desc' => $qb->addOrderBy('l.hourlyRate', 'DESC'),
            'newest' => $qb->addOrderBy('l.createdAt', 'DESC'),
            'articles' => $qb->addOrderBy('l.articlesPublishedCount', 'DESC'),
            default => $qb->addOrderBy('effective_rating', 'DESC'),
        };

        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $paginator = new Paginator($qb->getQuery());

        return $this->paginated(
            array_map($this->toCard(...), iterator_to_array($paginator)),
            count($paginator),
            $page,
            $perPage
        );
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function show(string $slug): JsonResponse
    {
        $lawyer = $this->em->getRepository(LawyerProfile::class)->findOneBy(['slug' => $slug]);
        if (!$lawyer) {
            return $this->problem('Юрист не найден.', Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->toCard($lawyer) + [
            'bio' => $lawyer->getBio(),
            'bar_number' => $lawyer->getBarNumber(),
            'region' => $lawyer->getRegion(),
            'response_time_avg' => 0,
            'video_consultation_enabled' => $lawyer->isVideoConsultationEnabled(),
            'member_since' => $lawyer->getUser()->getCreatedAt()->format('Y-m-d'),
        ]);
    }

    private function toCard(LawyerProfile $l): array
    {
        return [
            'id' => $l->getId()->toRfc4122(),
            'slug' => $l->getSlug(),
            'full_name' => $l->getUser()->getFullName(),
            'avatar_url' => $l->getUser()->getAvatarUrl(),
            'city' => $l->getCity(),
            'specializations' => $l->getSpecializations()->map(
                fn ($s) => ['name' => $s->getName(), 'slug' => $s->getSlug()]
            )->toArray(),
            'rating' => $l->getEffectiveRating(),
            'articles_published_count' => $l->getArticlesPublishedCount(),
            'reviews_count' => $l->getReviewsCount(),
            'consultations_count' => $l->getConsultationsCount(),
            'experience_years' => $l->getExperienceYears(),
            'hourly_rate' => $l->getHourlyRate(),
            'is_online' => $l->isOnline(),
            'is_verified' => $l->isVerified(),
            'is_featured' => $l->isFeatured(),
        ];
    }
}
