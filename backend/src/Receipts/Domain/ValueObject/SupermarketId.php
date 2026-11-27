<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

/**
 * Slug of the supermarket a receipt is from (PD-64), such as "mercadona".
 */
final readonly class SupermarketId
{
    private const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidSupermarketId::notASlug($value);
        }

        return new self($value);
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
