<?php
declare(strict_types=1);

namespace App\Application\Consultation\Command\CreateQuestion;

use Symfony\Component\Uid\Uuid;

final readonly class CreateQuestionCommand
{
    public function __construct(
        public Uuid $authorId,
        public string $title,
        public string $body,
        public Uuid $categoryId,
        public string $type = 'public',
        public bool $isAnonymous = false,
    ) {}
}
