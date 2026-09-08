<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Domain\Identity\Entity\User;
use App\Domain\LegalBase\Entity\CourtDecision;
use App\Domain\LegalBase\Entity\LawDocument;
use App\Domain\Payment\Entity\Subscription;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * База законодательства (ТЗ §6.7):
 * LEG-01 хранение НПА, LEG-02 версии, LEG-03 FTS, LEG-06 судебные решения, LEG-09 PRO-доступ.
 */
#[Route('/api/v1/laws')]
final class LawController extends ApiController
{
    private const EXCERPT_CHARS = 1500; // сколько текста видно без PRO

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(50, max(1, $request->query->getInt('per_page', 20)));
        $q = trim((string) $request->query->get('q', ''));

        $where = ["status != 'cancelled'"];
        $params = [];

        if ($type = $request->query->get('type')) {
            $where[] = 'type = :type';
            $params['type'] = $type;
        }
        if ($status = $request->query->get('status')) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($year = $request->query->getInt('year')) {
            $where[] = 'EXTRACT(YEAR FROM issued_at) = :year';
            $params['year'] = $year;
        }

        // FTS через websearch_to_tsquery: поддерживает "фразы", OR, - (LEG-03)
        $rankSelect = '0 AS rank';
        $order = 'issued_at DESC';
        if ($q !== '') {
            $where[] = "search_vector @@ websearch_to_tsquery('simple', :q)";
            $rankSelect = "ts_rank(search_vector, websearch_to_tsquery('simple', :q)) AS rank";
            $order = 'rank DESC, issued_at DESC';
            $params['q'] = $q;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM law_documents WHERE $whereSql", $params);
        $rows = $this->db->fetchAllAssociative(
            "SELECT id, type, number, slug, title, issued_by, issued_at, status, version, is_pro_only, views_count, $rankSelect
             FROM law_documents WHERE $whereSql ORDER BY $order LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER, 'offset' => \Doctrine\DBAL\ParameterType::INTEGER]
        );

        return $this->paginated(array_map(static fn (array $r) => [
            'id' => $r['id'],
            'type' => $r['type'],
            'number' => $r['number'],
            'slug' => $r['slug'],
            'title' => $r['title'],
            'issued_by' => $r['issued_by'],
            'issued_at' => $r['issued_at'],
            'status' => $r['status'],
            'version' => (int) $r['version'],
            'is_pro_only' => (bool) $r['is_pro_only'],
        ], $rows), $total, $page, $perPage);
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function show(string $slug): JsonResponse
    {
        $doc = $this->em->getRepository(LawDocument::class)->findOneBy(['slug' => $slug]);
        if (!$doc) {
            return $this->problem('Документ не найден.', Response::HTTP_NOT_FOUND);
        }

        $doc->registerView();
        $this->em->flush();

        // PRO-гейт (LEG-09): без активной подписки — только превью
        $hasPro = $this->hasActivePro();
        $fullAccess = !$doc->isProOnly() || $hasPro;
        $body = $fullAccess ? $doc->getBody() : mb_substr($doc->getBody(), 0, self::EXCERPT_CHARS) . '…';

        // Цепочка версий (LEG-02)
        $versions = [];
        $cursor = $doc;
        while ($cursor->getParent() !== null && count($versions) < 20) {
            $cursor = $cursor->getParent();
            $versions[] = ['version' => $cursor->getVersion(), 'slug' => $cursor->getSlug(), 'status' => $cursor->getStatus()];
        }

        return $this->json([
            'id' => $doc->getId()->toRfc4122(),
            'type' => $doc->getType(),
            'number' => $doc->getNumber(),
            'slug' => $doc->getSlug(),
            'title' => $doc->getTitle(),
            'body' => $body,
            'full_access' => $fullAccess,
            'is_pro_only' => $doc->isProOnly(),
            'issued_by' => $doc->getIssuedBy(),
            'issued_at' => $doc->getIssuedAt()->format('Y-m-d'),
            'effective_from' => $doc->getEffectiveFrom()?->format('Y-m-d'),
            'status' => $doc->getStatus(),
            'version' => $doc->getVersion(),
            'previous_versions' => $versions,
            'views_count' => $doc->getViewsCount(),
        ]);
    }

    private function hasActivePro(): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $sub = $this->em->getRepository(Subscription::class)->findOneBy(
            ['user' => $user, 'status' => Subscription::STATUS_ACTIVE], ['expiresAt' => 'DESC']
        );
        return $sub !== null && $sub->isActive();
    }
}
