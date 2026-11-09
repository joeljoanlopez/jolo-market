<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Absolute http(s) URL, such as a product image.
 */
final readonly class Url
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $scheme = parse_url($value, \PHP_URL_SCHEME);

        if (false === filter_var($value, \FILTER_VALIDATE_URL) || !\in_array($scheme, ['http', 'https'], true)) {
            throw InvalidProductData::invalidUrl($value);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
