<?php
declare(strict_types=1);

namespace App\Domain\Identity\Exception;

final class UserAlreadyExistsException extends \DomainException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('Пользователь с email "%s" уже зарегистрирован.', $email));
    }
}
