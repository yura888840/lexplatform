<?php
declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Content\Event\ArticlePublished;
use App\Domain\Content\Entity\Article;
use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Infrastructure\Search\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Реакция на ArticlePublished:
 * счётчик статей юриста + content_score (рейтинг растёт от количества статей) + OpenSearch.
 */
#[AsMessageHandler]
final readonly class ArticlePublishedHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private SearchIndexer $indexer,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ArticlePublished $event): void
    {
        $article = $this->em->find(Article::class, $event->articleId);
        if (!$article || $article->getStatus() !== Article::STATUS_PUBLISHED) {
            return;
        }

        $lawyer = $this->em->getRepository(LawyerProfile::class)
            ->findOneBy(['user' => $article->getAuthor()]);
        if ($lawyer !== null) {
            $lawyer->registerPublishedArticle();
            $this->em->flush();
        }

        try {
            $this->indexer->indexArticle($article);
            if ($lawyer !== null) {
                $this->indexer->indexLawyer($lawyer); // обновлённый рейтинг в поиск
            }
        } catch (\Throwable $e) {
            $this->logger->error('Индексация статьи: ' . $e->getMessage());
            throw $e;
        }
    }
}
