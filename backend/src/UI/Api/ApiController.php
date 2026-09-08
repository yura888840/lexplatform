<?php
declare(strict_types=1);

namespace App\UI\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class ApiController extends AbstractController
{
    /** @return array<string, mixed> */
    protected function jsonBody(Request $request): array
    {
        try {
            return (array) json_decode($request->getContent(), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }

    /** RFC 7807 Problem Details (ТЗ §11.1). */
    protected function problem(string $detail, int $status, string $title = 'Ошибка запроса'): JsonResponse
    {
        return new JsonResponse(
            ['type' => 'about:blank', 'title' => $title, 'status' => $status, 'detail' => $detail],
            $status,
            ['Content-Type' => 'application/problem+json']
        );
    }

    /** @param array<array-key, mixed> $items */
    protected function paginated(array $items, int $total, int $page, int $perPage): JsonResponse
    {
        return $this->json([
            'data' => $items,
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int) ceil($total / max(1, $perPage))],
        ]);
    }
}
