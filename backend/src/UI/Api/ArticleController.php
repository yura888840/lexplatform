<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Domain\Content\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Публичный контент: статьи, новости, блог (ТЗ §6.6). */
#[Route('/api/v1/articles')]
final class ArticleController extends ApiController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(50, max(1, $request->query->getInt('per_page', 12)));

        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Article::class, 'a')
            ->where('a.status = :pub')->setParameter('pub', Article::STATUS_PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC');

        if ($type = $request->query->get('type')) {
            $qb->andWhere('a.type = :type')->setParameter('type', $type);
        }

        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $paginator = new Paginator($qb->getQuery());

        return $this->paginated(
            array_map($this->toSummary(...), iterator_to_array($paginator)),
            count($paginator),
            $page,
            $perPage
        );
    }

    #[Route('/{slug}', methods: ['GET'])]
    public function show(string $slug): JsonResponse
    {
        $article = $this->em->getRepository(Article::class)
            ->findOneBy(['slug' => $slug, 'status' => Article::STATUS_PUBLISHED]);
        if (!$article) {
            return $this->problem('Публикация не найдена.', Response::HTTP_NOT_FOUND);
        }

        $article->registerView();
        $this->em->flush();

        return $this->json($this->toSummary($article) + [
            'body' => $article->getBody(),
            'seo_title' => $article->getSeoTitle(),
            'seo_description' => $article->getSeoDescription(),
        ]);
    }

    private function toSummary(Article $a): array
    {
        return [
            'id' => $a->getId()->toRfc4122(),
            'type' => $a->getType(),
            'title' => $a->getTitle(),
            'slug' => $a->getSlug(),
            'excerpt' => $a->getExcerpt(),
            'cover_image' => $a->getCoverImage(),
            'author_name' => $a->getAuthor()->getFullName(),
            'category' => $a->getCategory()?->getName(),
            'views_count' => $a->getViewsCount(),
            'published_at' => $a->getPublishedAt()?->format(DATE_ATOM),
        ];
    }
}
