<?php
declare(strict_types=1);

namespace App\Application\Consultation\Command\CreateQuestion;

use App\Application\Consultation\Event\QuestionPosted;
use App\Domain\Consultation\Entity\Question;
use App\Domain\Content\Entity\Category;
use App\Domain\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateQuestionHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {}

    public function __invoke(CreateQuestionCommand $command): Question
    {
        $author = $this->em->find(User::class, $command->authorId)
            ?? throw new \DomainException('Автор не найден.');
        $category = $this->em->find(Category::class, $command->categoryId)
            ?? throw new \DomainException('Категория не найдена.');

        if (mb_strlen($command->title) < 10) {
            throw new \DomainException('Заголовок вопроса слишком короткий (мин. 10 символов).');
        }
        if (mb_strlen($command->body) < 30) {
            throw new \DomainException('Опишите ситуацию подробнее (мин. 30 символов).');
        }

        $question = new Question($author, $command->title, $command->body, $category, $command->type, $command->isAnonymous);
        $this->em->persist($question);
        $this->em->flush();

        // Событие → индексация в OpenSearch + нотификация юристов (ТЗ §9.2)
        $this->bus->dispatch(new QuestionPosted($question->getId()));

        return $question;
    }
}
