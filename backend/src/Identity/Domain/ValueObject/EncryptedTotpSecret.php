<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

/**
 * A TOTP secret encrypted by the SecretEncrypter port, so it is never stored in clear.
 */
final readonly class EncryptedTotpSecret
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(#[\SensitiveParameter] string $value): self
    {
        if ('' === $value) {
            throw InvalidStoredSecret::empty('encrypted TOTP secret');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
