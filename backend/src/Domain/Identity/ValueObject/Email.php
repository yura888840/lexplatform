<?php
declare(strict_types=1);

namespace App\Domain\Identity\ValueObject;

use App\Domain\Identity\Exception\InvalidEmailException;

final readonly class Email
{
    public string $value;

    public function __construct(string $value)
    {
        $value = mb_strtolower(trim($value));
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException(sprintf('"%s" не является корректным email.', $value));
        }
        $this->value = $value;
    }

    public function __toString(): string { return $this->value; }
}
