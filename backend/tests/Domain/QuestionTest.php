<?php
declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Consultation\Entity\Answer;
use App\Domain\Consultation\Entity\Question;
use App\Domain\Content\Entity\Category;
use App\Domain\Identity\Entity\User;
use PHPUnit\Framework\TestCase;

final class QuestionTest extends TestCase
{
    private function makeQuestion(): array
    {
        $client = new User('client@test.local', 'Клиент', User::ROLE_CLIENT);
        $lawyer = new User('lawyer@test.local', 'Юрист', User::ROLE_LAWYER);
        $category = new Category('Цивільне право', 'civil');
        $question = new Question($client, 'Тестовый вопрос о наследстве', 'Подробное описание ситуации для теста.', $category);
        return [$question, $client, $lawyer];
    }

    public function testAnswerByLawyerRegistersAndChangesStatus(): void
    {
        [$question, , $lawyer] = $this->makeQuestion();
        new Answer($question, $lawyer, 'Развёрнутый ответ юриста.');

        self::assertSame(1, $question->getAnswersCount());
        self::assertSame(Question::STATUS_ANSWERED, $question->getStatus());
    }

    public function testClientCannotAnswer(): void
    {
        [$question, $client] = $this->makeQuestion();
        $this->expectException(\DomainException::class);
        new Answer($question, $client, 'Клиент пытается ответить.');
    }

    public function testOnlyAuthorCanAcceptAnswer(): void
    {
        [$question, , $lawyer] = $this->makeQuestion();
        $answer = new Answer($question, $lawyer, 'Ответ.');
        $stranger = new User('other@test.local', 'Другой', User::ROLE_CLIENT);

        $this->expectException(\DomainException::class);
        $question->acceptAnswer($answer, $stranger);
    }

    public function testAcceptAnswerClosesQuestion(): void
    {
        [$question, $client, $lawyer] = $this->makeQuestion();
        $answer = new Answer($question, $lawyer, 'Ответ.');
        $question->acceptAnswer($answer, $client);

        self::assertTrue($answer->isAccepted());
        self::assertSame(Question::STATUS_CLOSED, $question->getStatus());
        self::assertTrue($question->getAcceptedAnswerId()->equals($answer->getId()));
    }
}
