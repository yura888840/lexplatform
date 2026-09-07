<?php
declare(strict_types=1);

namespace App\UI\Console;

use App\Domain\LegalBase\Entity\LawDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Импорт НПА из JSON-файла (ТЗ LEG-01, риск "нестача контенту" §14.3).
 * Формат: [{"type","number","slug","title","body","issued_by","issued_at","pro_only"?}, ...]
 * Идемпотентно по slug: существующие документы пропускаются.
 * Батчи по 50 — импорт десятков тысяч документов без переполнения памяти.
 */
#[AsCommand(name: 'app:import-laws', description: 'Импорт НПА из JSON-файла')]
final class ImportLawsCommand extends Command
{
    private const BATCH = 50;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Путь к JSON-файлу с документами');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string) $input->getArgument('file');
        if (!is_readable($file)) {
            $output->writeln('<error>Файл не найден или недоступен: ' . $file . '</error>');
            return Command::FAILURE;
        }

        try {
            $items = json_decode((string) file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $output->writeln('<error>Некорректный JSON: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $repo = $this->em->getRepository(LawDocument::class);
        $imported = $skipped = 0;

        foreach ((array) $items as $i => $item) {
            $slug = (string) ($item['slug'] ?? '');
            if ($slug === '' || $repo->findOneBy(['slug' => $slug])) {
                $skipped++;
                continue;
            }

            try {
                $doc = new LawDocument(
                    (string) ($item['type'] ?? LawDocument::TYPE_LAW),
                    (string) ($item['number'] ?? ''),
                    $slug,
                    (string) ($item['title'] ?? ''),
                    (string) ($item['body'] ?? ''),
                    (string) ($item['issued_by'] ?? ''),
                    new \DateTimeImmutable((string) ($item['issued_at'] ?? 'now')),
                );
                $doc->setProOnly((bool) ($item['pro_only'] ?? false));
                $this->em->persist($doc);
                $imported++;
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<comment>Пропущен элемент #%d: %s</comment>', $i, $e->getMessage()));
                $skipped++;
                continue;
            }

            if ($imported % self::BATCH === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
        $output->writeln(sprintf('<info>✔ Импортировано: %d, пропущено: %d</info>', $imported, $skipped));
        return Command::SUCCESS;
    }
}
