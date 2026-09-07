<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Application\Content\Event\ArticlePublished;
use App\Domain\Content\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/** Очередь модерации статей (ROLE_EDITOR, workflow CMS-06: review → published). */
#[Route('/api/v1/editor/articles')]
final class EditorController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    #[Route('/review-queue', methods: ['GET'])]
    public function queue(): JsonResponse
    {
        $articles = $this->em->getRepository(Article::class)
            ->findBy(['status' => Article::STATUS_REVIEW], ['createdAt' => 'ASC'], 50);

        return $this->json(['data' => array_map(static fn (Article $a) => [
            'id' => $a->getId()->toRfc4122(),
            'title' => $a->getTitle(),
            'author' => $a->getAuthor()->getFullName(),
            'specialization' => $a->getSpecialization()?->getName(),
            'chars_count' => $a->getCharsCount(),
            'copyright_transferred' => $a->isCopyrightTransferred(),
            'submitted_at' => $a->getCreatedAt()->format(DATE_ATOM),
        ], $articles)]);
    }

    #[Route('/{id}/publish', methods: ['POST'])]
    public function publish(string $id): JsonResponse
    {
        $article = $this->find($id);
        if (!$article) {
            return $this->problem('Статья не найдена.', Response::HTTP_NOT_FOUND);
        }
        if ($article->getStatus() !== Article::STATUS_REVIEW) {
            return $this->problem('Публиковать можно только статьи в статусе review.', Response::HTTP_CONFLICT);
        }

        $article->publish();
        $this->em->flush();
        $this->bus->dispatch(new ArticlePublished($article->getId()));

        return $this->json(['id' => $id, 'status' => $article->getStatus()]);
    }

    #[Route('/{id}/reject', methods: ['POST'])]
    public function reject(string $id): JsonResponse
    {
        $article = $this->find($id);
        if (!$article) {
            return $this->problem('Статья не найдена.', Response::HTTP_NOT_FOUND);
        }
        $article->reject(); // назад в draft на доработку
        $this->em->flush();

        return $this->json(['id' => $id, 'status' => $article->getStatus()]);
    }

    private function find(string $id): ?Article
    {
        try {
            return $this->em->find(Article::class, Uuid::fromString($id));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
