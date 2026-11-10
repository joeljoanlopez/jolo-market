<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

/**
 * Login name of a user (PD-39): 3 to 32 characters of a-z, digits, ".", "_" and "-",
 * starting with a letter or a digit. It is case-insensitive, so it is kept in lowercase.
 */
final readonly class Username
{
    private const string PATTERN = '/^[a-z0-9][a-z0-9._-]{2,31}$/D';

    /**
     * @param non-empty-string $value
     */
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower($value);

        if (1 !== preg_match(self::PATTERN, $normalized)) {
            throw InvalidUsername::notAllowed($value);
        }

        return new self($normalized);
    }

    /**
     * @return non-empty-string
     */
    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
