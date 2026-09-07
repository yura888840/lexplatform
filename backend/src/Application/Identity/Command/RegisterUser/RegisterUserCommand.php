<?php
declare(strict_types=1);

namespace App\Application\Identity\Command\RegisterUser;

final readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $fullName,
        public string $role = 'client', // client | lawyer
        public bool $acceptsPersonalDataProcessing = false,
        public ?string $ip = null,
    ) {}
}
