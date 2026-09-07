<?php
declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Consultation\Event\QuestionPosted;
use App\Domain\Consultation\Entity\Question;
use App\Infrastructure\Search\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Реакция на QuestionPosted: индексация в OpenSearch (ТЗ §9.2). */
#[AsMessageHandler]
final readonly class QuestionPostedHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private SearchIndexer $indexer,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(QuestionPosted $event): void
    {
        $question = $this->em->find(Question::class, $event->questionId);
        if (!$question) {
            return;
        }
        try {
            $this->indexer->indexQuestion($question);
        } catch (\Throwable $e) {
            $this->logger->error('Ошибка индексации вопроса: ' . $e->getMessage());
            throw $e; // retry через messenger
        }
    }
}
