<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Application\Content\Command\SubmitLawyerArticle\SubmitLawyerArticleCommand;
use App\Application\Content\Command\SubmitLawyerArticle\SubmitLawyerArticleHandler;
use App\Domain\Content\Entity\Article;
use App\Domain\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/** Статьи юристов: сабмит в review и личный список. */
#[Route('/api/v1/articles')]
final class LawyerArticleController extends ApiController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('', methods: ['POST'])]
    public function submit(Request $request, SubmitLawyerArticleHandler $handler): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $this->jsonBody($request);

        try {
            $article = $handler(new SubmitLawyerArticleCommand(
                authorId: $user->getId(),
                title: (string) ($data['title'] ?? ''),
                body: (string) ($data['body'] ?? ''),
                specializationId: Uuid::fromString((string) ($data['specialization_id'] ?? '')),
                acceptsCopyrightTransfer: (bool) ($data['accepts_copyright_transfer'] ?? false),
                ip: $request->getClientIp(),
            ));
        } catch (\DomainException | \InvalidArgumentException $e) {
            return $this->problem($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'id' => $article->getId()->toRfc4122(),
            'slug' => $article->getSlug(),
            'status' => $article->getStatus(),
            'chars_count' => $article->getCharsCount(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/mine', methods: ['GET'])]
    public function mine(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $articles = $this->em->getRepository(Article::class)
            ->findBy(['author' => $user], ['createdAt' => 'DESC'], 100);

        return $this->json(['data' => array_map(static fn (Article $a) => [
            'id' => $a->getId()->toRfc4122(),
            'title' => $a->getTitle(),
            'slug' => $a->getSlug(),
            'status' => $a->getStatus(),
            'specialization' => $a->getSpecialization()?->getName(),
            'chars_count' => $a->getCharsCount(),
            'published_at' => $a->getPublishedAt()?->format(DATE_ATOM),
        ], $articles)]);
    }
}
