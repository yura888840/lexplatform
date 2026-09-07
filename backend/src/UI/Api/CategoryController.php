<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Domain\Content\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/categories')]
final class CategoryController extends ApiController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = $this->em->getRepository(Category::class)
            ->findBy(['type' => 'legal'], ['sortOrder' => 'ASC', 'name' => 'ASC']);

        return $this->json(['data' => array_map(fn (Category $c) => [
            'id' => $c->getId()->toRfc4122(),
            'name' => $c->getName(),
            'slug' => $c->getSlug(),
        ], $categories)]);
    }
}
