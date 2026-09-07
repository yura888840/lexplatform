<?php
declare(strict_types=1);

namespace App\UI\Api;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Health-check для балансировщика/K8s liveness probe. */
final class HealthController extends ApiController
{
    #[Route('/api/health', methods: ['GET'])]
    public function health(Connection $db): JsonResponse
    {
        $dbOk = true;
        try {
            $db->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }
        return $this->json(['status' => $dbOk ? 'ok' : 'degraded', 'db' => $dbOk, 'time' => date(DATE_ATOM)], $dbOk ? 200 : 503);
    }
}
