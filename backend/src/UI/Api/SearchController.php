<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Infrastructure\Search\SearchIndexer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Глобальный поиск через OpenSearch (ТЗ §6.8). */
#[Route('/api/v1/search')]
final class SearchController extends ApiController
{
    public function __construct(private readonly SearchIndexer $indexer) {}

    #[Route('', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 2) {
            return $this->problem('Минимальная длина запроса — 2 символа.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->indexer->search(
                $q,
                (string) $request->query->get('type', 'all'),
                max(1, $request->query->getInt('page', 1)),
            );
        } catch (\Throwable) {
            return $this->problem('Поиск временно недоступен.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json(['query' => $q, 'total' => $result['total'], 'results' => $result['hits']]);
    }
}
