<?php
declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Domain\Identity\Event\UserRegistered;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

/** Реакция на UserRegistered: welcome email (ТЗ §9.2, NOTIF-01). */
#[AsMessageHandler]
final readonly class UserRegisteredHandler
{
    public function __construct(private MailerInterface $mailer) {}

    public function __invoke(UserRegistered $event): void
    {
        $this->mailer->send(
            (new Email())
                ->to($event->email)
                ->subject('Добро пожаловать на LexPlatform')
                ->text(sprintf(
                    "Здравствуйте, %s!\n\nВаш аккаунт создан. Подтвердите email по ссылке из письма верификации.\n\n— Команда LexPlatform",
                    $event->fullName
                ))
        );
    }
}
