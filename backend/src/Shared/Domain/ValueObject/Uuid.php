<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Identifier in the canonical 8-4-4-4-12 hexadecimal form, stored lowercase.
 * It only validates: generating new ids belongs to an IdGenerator port, so tests stay deterministic.
 */
final readonly class Uuid
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower($value);

        if (1 !== preg_match(self::PATTERN, $normalized)) {
            throw InvalidUuid::malformed($value);
        }

        return new self($normalized);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
