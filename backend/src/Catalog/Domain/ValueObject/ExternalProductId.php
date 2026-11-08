<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Id of a product in its supermarket's own store. Unique only within that supermarket.
 */
final readonly class ExternalProductId
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw InvalidProductData::blankExternalId();
        }

        return new self($trimmed);
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
