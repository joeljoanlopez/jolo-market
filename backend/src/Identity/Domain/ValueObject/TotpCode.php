<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

/**
 * A six-digit code from an authenticator app. Spaces are dropped because apps show it as "123 456".
 */
final readonly class TotpCode
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(#[\SensitiveParameter] string $value): self
    {
        $digits = str_replace(' ', '', $value);

        if (1 !== preg_match('/^\d{6}$/D', $digits)) {
            throw InvalidTotpCode::notSixDigits();
        }

        return new self($digits);
    }

    public function value(): string
    {
        return $this->value;
    }
}
