<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

final readonly class ProductName
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ('' === $normalized) {
            throw InvalidProductData::blankName();
        }

        return new self($normalized);
    }

    public function value(): string
    {
        return $this->value;
    }
}
