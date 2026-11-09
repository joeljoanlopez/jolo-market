<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

/**
 * A password hash produced by the PasswordHasher port. The domain never hashes or reads it.
 */
final readonly class PasswordHash
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(#[\SensitiveParameter] string $value): self
    {
        if ('' === $value) {
            throw InvalidStoredSecret::empty('password hash');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
