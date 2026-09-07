<?php
declare(strict_types=1);

namespace App\UI\Console;

use App\Domain\Consultation\Entity\Question;
use App\Domain\Content\Entity\Article;
use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Infrastructure\Search\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Полная переиндексация OpenSearch (ТЗ SRCH-01). */
#[AsCommand(name: 'app:search-reindex', description: 'Создать индексы OpenSearch и проиндексировать все сущности')]
final class SearchReindexCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SearchIndexer $indexer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->indexer->createIndices();
        } catch (\Throwable $e) {
            $output->writeln('<error>OpenSearch недоступен: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $n = 0;
        foreach ($this->em->getRepository(LawyerProfile::class)->findAll() as $l) {
            $this->indexer->indexLawyer($l); $n++;
        }
        foreach ($this->em->getRepository(Question::class)->findAll() as $q) {
            $this->indexer->indexQuestion($q); $n++;
        }
        foreach ($this->em->getRepository(Article::class)->findAll() as $a) {
            if ($a->getStatus() === Article::STATUS_PUBLISHED) {
                $this->indexer->indexArticle($a); $n++;
            }
        }

        $output->writeln(sprintf('<info>✔ Проиндексировано документов: %d</info>', $n));
        return Command::SUCCESS;
    }
}
