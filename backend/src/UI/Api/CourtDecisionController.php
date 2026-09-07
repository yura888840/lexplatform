<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Domain\LegalBase\Entity\CourtDecision;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/** База судебных решений с фильтрами и FTS (ТЗ LEG-06, §11.2). */
#[Route('/api/v1/court-decisions')]
final class CourtDecisionController extends ApiController
{
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

        $where = ['1=1'];
        $params = [];

        if ($courtType = $request->query->get('court_type')) {
            $where[] = 'c.type = :ctype';
            $params['ctype'] = $courtType;
        }
        if ($year = $request->query->getInt('year')) {
            $where[] = 'EXTRACT(YEAR FROM d.decided_at) = :year';
            $params['year'] = $year;
        }
        if ($category = $request->query->get('category')) {
            $where[] = 'd.categories @> :cat::jsonb';
            $params['cat'] = json_encode([$category]);
        }

        $order = 'd.decided_at DESC';
        if ($q !== '') {
            $where[] = "d.search_vector @@ websearch_to_tsquery('simple', :q)";
            $order = "ts_rank(d.search_vector, websearch_to_tsquery('simple', :q)) DESC, d.decided_at DESC";
            $params['q'] = $q;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM court_decisions d JOIN courts c ON c.id = d.court_id WHERE $whereSql", $params
        );
        $rows = $this->db->fetchAllAssociative(
            "SELECT d.id, d.case_number, d.title, d.decided_at, d.categories, d.views_count,
                    c.name AS court_name, c.type AS court_type
             FROM court_decisions d JOIN courts c ON c.id = d.court_id
             WHERE $whereSql ORDER BY $order LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
            ['limit' => \PDO::PARAM_INT, 'offset' => \PDO::PARAM_INT]
        );

        return $this->paginated(array_map(static fn (array $r) => [
            'id' => $r['id'],
            'case_number' => $r['case_number'],
            'title' => $r['title'],
            'decided_at' => $r['decided_at'],
            'categories' => json_decode((string) $r['categories'], true),
            'court' => ['name' => $r['court_name'], 'type' => $r['court_type']],
        ], $rows), $total, $page, $perPage);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $decision = $this->em->find(CourtDecision::class, Uuid::fromString($id));
        } catch (\InvalidArgumentException) {
            $decision = null;
        }
        if (!$decision) {
            return $this->problem('Решение не найдено.', Response::HTTP_NOT_FOUND);
        }

        $decision->registerView();
        $this->em->flush();

        return $this->json([
            'id' => $decision->getId()->toRfc4122(),
            'case_number' => $decision->getCaseNumber(),
            'title' => $decision->getTitle(),
            'body' => $decision->getBody(),
            'decided_at' => $decision->getDecidedAt()->format('Y-m-d'),
            'categories' => $decision->getCategories(),
            'court' => [
                'name' => $decision->getCourt()->getName(),
                'type' => $decision->getCourt()->getType(),
                'region' => $decision->getCourt()->getRegion(),
            ],
            'views_count' => $decision->getViewsCount(),
        ]);
    }
}
