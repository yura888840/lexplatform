<?php
declare(strict_types=1);

namespace App\UI\Console;

use App\Domain\LegalBase\Entity\Court;
use App\Domain\LegalBase\Entity\CourtDecision;
use App\Domain\LegalBase\Entity\LawDocument;
use App\Domain\Payment\Entity\SubscriptionPlan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Сидинг V2: тарифные планы + демо-НПА и судебные решения (ТЗ §14.3: сидинг базы). */
#[AsCommand(name: 'app:seed-legal-base', description: 'Тарифные планы + демо-документы правовой базы')]
final class SeedLegalBaseCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ── Тарифы ──
        if ($this->em->getRepository(SubscriptionPlan::class)->count([]) === 0) {
            foreach ([
                ['PRO Місяць', 'pro-month', '299.00', 'month', ['Повний доступ до бази НПА', 'Судова практика без обмежень', 'Без реклами']],
                ['PRO Рік', 'pro-year', '2490.00', 'year', ['Все з PRO Місяць', 'Знижка 30%', 'Пріоритетна підтримка']],
            ] as [$name, $slug, $price, $interval, $features]) {
                $this->em->persist(new SubscriptionPlan($name, $slug, $price, $interval, $features));
            }
            $output->writeln('<info>✔ Тарифные планы созданы</info>');
        }

        // ── Демо-НПА ──
        if ($this->em->getRepository(LawDocument::class)->count([]) === 0) {
            $demoBody = "Стаття 1. Загальні положення.\n\nЦей документ є демонстраційним наповненням бази законодавства LexPlatform для середовища розробки. "
                . "У продакшн-версії тексти НПА імпортуються командою app:import-laws з офіційних відкритих джерел.\n\n"
                . "Стаття 2. Порядок застосування.\n\nДемонстраційний текст використовується для перевірки повнотекстового пошуку, "
                . "версіонування документів та PRO-доступу до розширеної бази.";

            $docs = [
                [LawDocument::TYPE_CODE, '435-IV', 'tsyvilnyi-kodeks-demo', 'Цивільний кодекс України (демо)', 'Верховна Рада України', '2003-01-16', false],
                [LawDocument::TYPE_CODE, '2341-III', 'kryminalnyi-kodeks-demo', 'Кримінальний кодекс України (демо)', 'Верховна Рада України', '2001-04-05', false],
                [LawDocument::TYPE_LAW, '2694-XII', 'zakon-pro-pratsyu-demo', 'Закон про охорону праці (демо)', 'Верховна Рада України', '1992-10-14', true],
                [LawDocument::TYPE_RESOLUTION, '1236', 'postanova-kmu-demo', 'Постанова КМУ про карантинні заходи (демо)', 'Кабінет Міністрів України', '2020-12-09', true],
            ];
            foreach ($docs as [$type, $number, $slug, $title, $issuedBy, $date, $proOnly]) {
                $doc = new LawDocument($type, $number, $slug, $title, $demoBody, $issuedBy, new \DateTimeImmutable($date));
                $doc->setProOnly($proOnly);
                $this->em->persist($doc);
            }

            // Демонстрация версионирования (LEG-02)
            $this->em->flush();
            $base = $this->em->getRepository(LawDocument::class)->findOneBy(['slug' => 'zakon-pro-pratsyu-demo']);
            if ($base) {
                $rev = $base->createNewRevision(
                    $demoBody . "\n\nСтаття 3. Зміни редакції.\n\nЦя редакція демонструє механізм версіонування документів.",
                    'zakon-pro-pratsyu-demo-v2',
                    new \DateTimeImmutable('2024-01-01'),
                );
                $this->em->persist($rev);
            }
            $output->writeln('<info>✔ Демо-НПА созданы (включая цепочку версий)</info>');
        }

        // ── Реальні НПА з data/laws/*.json (ідемпотентно за slug) ──
        $lawsDir = \dirname(__DIR__, 3) . '/data/laws';
        if (is_dir($lawsDir)) {
            $repo = $this->em->getRepository(LawDocument::class);
            $imported = 0;
            foreach (glob($lawsDir . '/*.json') ?: [] as $file) {
                try {
                    $items = json_decode((string) file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $output->writeln('<comment>Пропущено (некоректний JSON): ' . basename($file) . '</comment>');
                    continue;
                }
                foreach ((array) $items as $item) {
                    $slug = (string) ($item['slug'] ?? '');
                    if ($slug === '' || $repo->findOneBy(['slug' => $slug])) {
                        continue;
                    }
                    $doc = new LawDocument(
                        (string) ($item['type'] ?? LawDocument::TYPE_LAW),
                        (string) ($item['number'] ?? ''),
                        $slug,
                        (string) ($item['title'] ?? ''),
                        (string) ($item['body'] ?? ''),
                        (string) ($item['issued_by'] ?? 'Верховна Рада України'),
                        new \DateTimeImmutable((string) ($item['issued_at'] ?? 'now')),
                    );
                    $doc->setProOnly((bool) ($item['pro_only'] ?? false));
                    $this->em->persist($doc);
                    $imported++;
                }
            }
            if ($imported > 0) {
                $this->em->flush();
                $output->writeln(sprintf('<info>✔ Імпортовано реальних НПА: %d</info>', $imported));
            }
        }

        // ── Демо-решения ──
        if ($this->em->getRepository(CourtDecision::class)->count([]) === 0) {
            $supreme = new Court('Верховний Суд', 'supreme', 'Київ');
            $appellate = new Court('Київський апеляційний суд', 'appellate', 'Київ');
            $this->em->persist($supreme);
            $this->em->persist($appellate);

            $decisionBody = "УХВАЛИВ:\n\nЦе демонстраційне судове рішення для середовища розробки LexPlatform. "
                . "Текст призначений для перевірки фільтрів за судом, роком та категорією, а також повнотекстового пошуку по базі рішень.";

            foreach ([
                [$supreme, '757/12345/24', 'Постанова щодо спору про право власності на нерухоме майно (демо)', '2024-06-12', ['civil', 'real-estate']],
                [$supreme, '761/9876/23', 'Постанова щодо трудового спору про поновлення на роботі (демо)', '2023-11-03', ['labor']],
                [$appellate, '824/5544/24', 'Ухвала у справі про поділ спільного майна подружжя (демо)', '2024-02-20', ['family']],
            ] as [$court, $case, $title, $date, $cats]) {
                $this->em->persist(new CourtDecision($court, $case, $title, $decisionBody, new \DateTimeImmutable($date), $cats));
            }
            $output->writeln('<info>✔ Демо-решения созданы</info>');
        }

        $this->em->flush();
        return Command::SUCCESS;
    }
}
