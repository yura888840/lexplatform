<?php
declare(strict_types=1);

namespace App\UI\Console;

use App\Domain\Consultation\Entity\Answer;
use App\Domain\Consultation\Entity\Question;
use App\Domain\Content\Entity\Article;
use App\Domain\Content\Entity\Category;
use App\Domain\Identity\Entity\User;
use App\Domain\Lawyer\Entity\LawyerProfile;
use App\Domain\Lawyer\Entity\Review;
use App\Domain\Lawyer\Entity\Specialization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Демо-данные: категории, специализации, юристы, вопросы, статьи (ТЗ §14.3: сидинг). */
#[AsCommand(name: 'app:seed-demo', description: 'Наполнить БД демо-данными для разработки')]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->em->getRepository(Category::class)->count([]) > 0) {
            $output->writeln('<comment>БД уже содержит данные — сидинг пропущен.</comment>');
            return Command::SUCCESS;
        }

        // Категории права (CONS-04)
        $catData = [
            'Цивільне право' => 'civil', 'Кримінальне право' => 'criminal',
            'Сімейне право' => 'family', 'Трудове право' => 'labor',
            'Податкове право' => 'tax', 'Корпоративне право' => 'corporate',
            'Нерухомість' => 'real-estate', 'Військове право' => 'military',
            'Спадкове право' => 'inheritance', 'Адміністративне право' => 'administrative',
            'Міграційне право' => 'migration', 'Захист прав споживачів' => 'consumer',
        ];
        $categories = [];
        $i = 0;
        foreach ($catData as $name => $slug) {
            $c = new Category($name, $slug, 'legal');
            $this->em->persist($c);
            $categories[$slug] = $c;
            $i++;
        }

        // Специализации (совпадают с категориями для простоты)
        $specs = [];
        foreach ($catData as $name => $slug) {
            $s = new Specialization($name, $slug);
            $this->em->persist($s);
            $specs[$slug] = $s;
        }

        // Админ
        $admin = new User('admin@lexplatform.local', 'Администратор', User::ROLE_ADMIN);
        $admin->setPasswordHash($this->hasher->hashPassword($admin, 'admin12345'));
        $admin->markEmailVerified();
        $this->em->persist($admin);

        // Клиент
        $client = new User('client@lexplatform.local', 'Олена Коваленко', User::ROLE_CLIENT);
        $client->setPasswordHash($this->hasher->hashPassword($client, 'client12345'));
        $client->markEmailVerified();
        $this->em->persist($client);

        // Юристы
        $lawyerData = [
            ['Ігор Шевченко', 'ihor-shevchenko', 'Київ', 'civil', 12, '1500.00', true, true],
            ['Марія Бондаренко', 'maria-bondarenko', 'Львів', 'family', 8, '1200.00', true, false],
            ['Андрій Мельник', 'andrii-melnyk', 'Київ', 'criminal', 15, '2500.00', true, true],
            ['Наталія Ткаченко', 'natalia-tkachenko', 'Одеса', 'tax', 10, '1800.00', false, false],
            ['Сергій Кравченко', 'serhii-kravchenko', 'Дніпро', 'military', 6, '900.00', true, false],
            ['Оксана Лисенко', 'oksana-lysenko', 'Харків', 'labor', 9, '1100.00', true, false],
        ];
        $lawyers = [];
        foreach ($lawyerData as [$name, $slug, $city, $spec, $exp, $rate, $verified, $featured]) {
            $u = new User(str_replace('-', '.', $slug) . '@lexplatform.local', $name, User::ROLE_LAWYER);
            $u->setPasswordHash($this->hasher->hashPassword($u, 'lawyer12345'));
            $u->markEmailVerified();
            $this->em->persist($u);

            $p = new LawyerProfile($u, $slug);
            $p->setCity($city);
            $p->setRegion($city . 'ська область');
            $p->setBio(sprintf('Практикуючий юрист, %d років досвіду. Спеціалізація: %s. Допомагаю клієнтам вирішувати складні правові питання.', $exp, $specs[$spec]->getName()));
            $p->setExperienceYears($exp);
            $p->setHourlyRate($rate);
            $p->addSpecialization($specs[$spec]);
            if ($verified) { $p->verify(); }
            $p->setFeatured($featured);
            $p->setOnline((bool) random_int(0, 1));
            $this->em->persist($p);
            $lawyers[] = ['profile' => $p, 'user' => $u];
        }

        // Отзывы (одобренные — пересчитывают рейтинг)
        foreach ($lawyers as $idx => $l) {
            foreach ([5, 4, 5] as $stars) {
                $r = new Review($l['profile'], $client, $stars, 'Дуже допоміг із моїм питанням, рекомендую.');
                $r->approve();
                $this->em->persist($r);
            }
            // Один відгук на модерації — щоб адмін-панель мала що модерувати
            if ($idx < 2) {
                $pending = new Review($l['profile'], $client, 4, 'Непоганий фахівець, але хотілося б швидшої відповіді.');
                $this->em->persist($pending); // статус pending за замовчуванням
            }
        }

        // Вопросы + ответы
        $qData = [
            ['Як оформити спадщину без заповіту?', 'inheritance', 'Помер батько, заповіту немає. Я єдина дитина, мати померла раніше. Які документи потрібні та в які строки треба звернутися до нотаріуса?'],
            ['Незаконне звільнення з роботи — що робити?', 'labor', 'Мене звільнили без пояснення причин та без відпрацювання. Трудовий договір був офіційний. Чи можу я оскаржити звільнення та отримати компенсацію?'],
            ['Розлучення з розподілом майна', 'family', 'Розлучаємось із чоловіком. Є спільна квартира, придбана у шлюбі, та автомобіль, оформлений на нього. Як ділиться майно і чи впливає те, на кого воно оформлене?'],
            ['Сусіди затопили квартиру — як стягнути збитки?', 'civil', 'Сусіди зверху затопили квартиру, є акт від ЖЕКу. Ремонт коштує близько 80 тис. грн. Вони відмовляються платити добровільно. Який порядок дій?'],
        ];
        foreach ($qData as $idx => [$title, $cat, $body]) {
            $q = new Question($client, $title, $body, $categories[$cat]);
            $this->em->persist($q);
            $a = new Answer($q, $lawyers[$idx % count($lawyers)]['user'],
                'Доброго дня! У вашій ситуації рекомендую наступний порядок дій: зібрати всі підтверджуючі документи, звернутися з письмовою претензією, а у разі відмови — до суду. Готовий детально розібрати вашу справу на консультації.');
            $this->em->persist($a);
        }

        // Статьи
        $artData = [
            ['news', 'Нові зміни до Податкового кодексу 2026', 'podatkovi-zminy-2026', 'Огляд ключових змін податкового законодавства, що набули чинності цього року.'],
            ['article', 'Як правильно скласти договір оренди житла', 'dogovir-orendy-zhytla', 'Покрокова інструкція та типові помилки при укладанні договору оренди.'],
            ['blog', 'Мобілізація та бронювання: права працівника', 'mobilizatsiya-bronyuvannya', 'Розбираємо актуальні питання бронювання працівників критичних підприємств.'],
        ];
        foreach ($artData as [$type, $title, $slug, $excerpt]) {
            $a = new Article($admin, $type, $title, $slug,
                '<p>' . $excerpt . '</p><p>Повний текст статті. Це демонстраційний контент для розробки платформи. У продакшн-версії тут буде повноцінний матеріал від редакції.</p>');
            $a->setExcerpt($excerpt);
            $a->setCategory($categories['civil']);
            $a->publish();
            $this->em->persist($a);
        }

        $this->em->flush();
        $output->writeln('<info>✔ Демо-данные созданы:</info>');
        $output->writeln('  admin@lexplatform.local / admin12345');
        $output->writeln('  client@lexplatform.local / client12345');
        $output->writeln('  ihor.shevchenko@lexplatform.local / lawyer12345 (и другие юристы)');

        return Command::SUCCESS;
    }
}
