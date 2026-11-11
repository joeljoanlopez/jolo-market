<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\ValueObject;

/**
 * PD-45: 1 to 80 characters after trimming.
 */
final readonly class ListName
{
    public const int MAX_LENGTH = 80;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if ('' === $trimmed || mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidListName::length(self::MAX_LENGTH);
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
