<?php
declare(strict_types=1);

namespace App\Application\Content\Command\SubmitLawyerArticle;

use Symfony\Component\Uid\Uuid;

final readonly class SubmitLawyerArticleCommand
{
    public function __construct(
        public Uuid $authorId,
        public string $title,
        public string $body,
        public Uuid $specializationId,
        public bool $acceptsCopyrightTransfer,
        public ?string $ip = null,
    ) {}
}
