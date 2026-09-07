<?php
declare(strict_types=1);

namespace App\Application\Consultation\Event;

use App\Application\Shared\AsyncMessageInterface;
use Symfony\Component\Uid\Uuid;

final readonly class QuestionPosted implements AsyncMessageInterface
{
    public function __construct(public Uuid $questionId) {}
}
