<?php
declare(strict_types=1);

namespace App\UI\Api;

use App\Application\Consultation\Command\CreateQuestion\CreateQuestionCommand;
use App\Application\Consultation\Command\CreateQuestion\CreateQuestionHandler;
use App\Domain\Consultation\Entity\Answer;
use App\Domain\Consultation\Entity\Question;
use App\Domain\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/** Q&A: публичные вопросы и ответы юристов (ТЗ §6.3, §11.2). */
#[Route('/api/v1/questions')]
final class QuestionController extends ApiController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(50, max(1, $request->query->getInt('per_page', 20)));

        $qb = $this->em->createQueryBuilder()
            ->select('q', 'c')
            ->from(Question::class, 'q')
            ->join('q.category', 'c')
            ->where('q.type = :public')->setParameter('public', Question::TYPE_PUBLIC)
            ->andWhere('q.status != :mod')->setParameter('mod', Question::STATUS_MODERATION)
            ->orderBy('q.createdAt', 'DESC');

        if ($cat = $request->query->get('category')) {
            $qb->andWhere('c.slug = :cat')->setParameter('cat', $cat);
        }
        if ($status = $request->query->get('status')) {
            $qb->andWhere('q.status = :st')->setParameter('st', $status);
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

    #[Route('', methods: ['POST'])]
    public function create(Request $request, CreateQuestionHandler $handler): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $this->jsonBody($request);

        try {
            $question = $handler(new CreateQuestionCommand(
                authorId: $user->getId(),
                title: (string) ($data['title'] ?? ''),
                body: (string) ($data['body'] ?? ''),
                categoryId: Uuid::fromString((string) ($data['category_id'] ?? '')),
                type: (string) ($data['type'] ?? 'public'),
                isAnonymous: (bool) ($data['is_anonymous'] ?? false),
            ));
        } catch (\DomainException | \InvalidArgumentException $e) {
            return $this->problem($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->toSummary($question), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $question = $this->find($id);
        if (!$question) {
            return $this->problem('Вопрос не найден.', Response::HTTP_NOT_FOUND);
        }

        $question->registerView();
        $this->em->flush();

        return $this->json($this->toSummary($question) + [
            'body' => $question->getBody(),
            'answers' => array_map(fn (Answer $a) => [
                'id' => $a->getId()->toRfc4122(),
                'body' => $a->getBody(),
                'is_accepted' => $a->isAccepted(),
                'helpful_votes' => $a->getHelpfulVotes(),
                'created_at' => $a->getCreatedAt()->format(DATE_ATOM),
                'author' => [
                    'full_name' => $a->getAuthor()->getFullName(),
                    'avatar_url' => $a->getAuthor()->getAvatarUrl(),
                ],
            ], $question->getAnswers()->toArray()),
        ]);
    }

    #[Route('/{id}/answers', methods: ['POST'])]
    public function answer(string $id, Request $request): JsonResponse
    {
        $question = $this->find($id);
        if (!$question) {
            return $this->problem('Вопрос не найден.', Response::HTTP_NOT_FOUND);
        }
        /** @var User $user */
        $user = $this->getUser();
        $body = (string) ($this->jsonBody($request)['body'] ?? '');
        if (mb_strlen($body) < 20) {
            return $this->problem('Ответ слишком короткий (мин. 20 символов).', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $answer = new Answer($question, $user, $body);
        } catch (\DomainException $e) {
            return $this->problem($e->getMessage(), Response::HTTP_FORBIDDEN);
        }
        $this->em->persist($answer);
        $this->em->flush();

        return $this->json(['id' => $answer->getId()->toRfc4122()], Response::HTTP_CREATED);
    }

    #[Route('/{id}/answers/{answerId}/accept', methods: ['POST'])]
    public function accept(string $id, string $answerId): JsonResponse
    {
        $question = $this->find($id);
        $answer = $this->em->find(Answer::class, Uuid::fromString($answerId));
        if (!$question || !$answer) {
            return $this->problem('Не найдено.', Response::HTTP_NOT_FOUND);
        }
        /** @var User $user */
        $user = $this->getUser();
        try {
            $question->acceptAnswer($answer, $user);
        } catch (\DomainException $e) {
            return $this->problem($e->getMessage(), Response::HTTP_FORBIDDEN);
        }
        $this->em->flush();

        return $this->json(['status' => $question->getStatus(), 'accepted_answer_id' => $answerId]);
    }

    private function find(string $id): ?Question
    {
        try {
            return $this->em->find(Question::class, Uuid::fromString($id));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function toSummary(Question $q): array
    {
        return [
            'id' => $q->getId()->toRfc4122(),
            'title' => $q->getTitle(),
            'category' => ['name' => $q->getCategory()->getName(), 'slug' => $q->getCategory()->getSlug()],
            'type' => $q->getType(),
            'status' => $q->getStatus(),
            'author_name' => $q->isAnonymous() ? 'Анонимно' : $q->getAuthor()->getFullName(),
            'views_count' => $q->getViewsCount(),
            'answers_count' => $q->getAnswersCount(),
            'created_at' => $q->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
