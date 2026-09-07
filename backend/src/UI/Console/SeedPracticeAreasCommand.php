<?php
declare(strict_types=1);

namespace App\UI\Console;

use App\Domain\Lawyer\Entity\Specialization;
use App\Domain\Payment\Entity\SubscriptionPlan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Отрасли права по бизнес-списку (advok.in.ua) + платный тариф «Юрист». Идемпотентно по slug. */
#[AsCommand(name: 'app:seed-practice-areas', description: 'Сидинг 19 отраслей права и тарифа «Юрист»')]
final class SeedPracticeAreasCommand extends Command
{
    /** slug => название (укр.), slug'и совместимы со структурой URL advok.in.ua */
    private const AREAS = [
        'advokat-po-kryminalnym-spravam' => 'Кримінальні справи',
        'czyvilne-pravo' => 'Цивільне право',
        'real-estate-construction' => 'Нерухомість та будівництво',
        'divorce-and-property-division' => 'Сімейне право',
        'vijskove-pravo' => 'Військове право',
        'kupap' => 'КУпАП (адміністративні правопорушення)',
        'borgy' => 'Борги',
        'avtorske-pravo' => 'Авторське право',
        'gospodarske-pravo' => 'Господарське право',
        'korporatyvni-vzayemovidnosyny' => 'Корпоративні взаємовідносини',
        'medyacziya-ta-peregovory' => 'Медіація та проведення переговорів',
        'pensiyne-pravo' => 'Пенсійне право',
        'spadshhyna' => 'Спадщина',
        'vyznannya-dogovoriv-nediysnymy' => 'Визнання договорів недійсними',
        'poslugy-advokata' => 'Послуги адвоката',
        'poslugy-yurysta' => 'Послуги юриста',
        'yurydychnyy-suprovid' => 'Юридичний супровід',
        'zahyst-borzhnyka' => 'Захист прав боржника',
        'dtp-vidshkoduvannya-shkody' => 'ДТП: відшкодування шкоди',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->em->getRepository(Specialization::class);
        $created = 0;
        foreach (self::AREAS as $slug => $name) {
            if (!$repo->findOneBy(['slug' => $slug])) {
                $this->em->persist(new Specialization($name, $slug));
                $created++;
            }
        }

        // Тариф «Юрист» — альтернатива статьям («або ви платите гроші, або статтями»)
        $plans = $this->em->getRepository(SubscriptionPlan::class);
        if (!$plans->findOneBy(['slug' => 'lawyer-monthly'])) {
            $this->em->persist(new SubscriptionPlan(
                'Тариф «Юрист»', 'lawyer-monthly', '990.00', 'month',
                ['Участь у каталозі без обов\'язкових статей', 'Повний доступ до бази PRO', 'Пріоритетна підтримка'],
            ));
            $output->writeln('<info>✔ Тариф «Юрист» создан (990 грн/мес)</info>');
        }

        $this->em->flush();
        $output->writeln(sprintf('<info>✔ Отраслей права добавлено: %d (всего в списке: %d)</info>', $created, count(self::AREAS)));
        return Command::SUCCESS;
    }
}
