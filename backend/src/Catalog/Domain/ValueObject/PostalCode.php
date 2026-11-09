<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * Spanish postal code: five digits whose first two are a province number (01-52).
 */
final readonly class PostalCode
{
    private const string PATTERN = '/^(0[1-9]|[1-4]\d|5[0-2])\d{3}$/D';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if (1 !== preg_match(self::PATTERN, $trimmed)) {
            throw InvalidPostalCode::malformed($value);
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
