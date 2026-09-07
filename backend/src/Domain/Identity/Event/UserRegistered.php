<?php
declare(strict_types=1);

namespace App\Domain\Identity\Event;

use App\Application\Shared\AsyncMessageInterface;
use Symfony\Component\Uid\Uuid;

/** Domain Event (ТЗ §9.2): welcome email + нотификация. Обрабатывается асинхронно. */
final readonly class UserRegistered implements AsyncMessageInterface
{
    public function __construct(
        public Uuid $userId,
        public string $email,
        public string $fullName,
    ) {}
}
