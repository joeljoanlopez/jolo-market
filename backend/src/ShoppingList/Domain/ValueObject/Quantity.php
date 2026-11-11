<?php

declare(strict_types=1);

namespace App\ShoppingList\Domain\ValueObject;

/**
 * How many units of an item to buy (PD-45): a whole number from 1 to 99.
 */
final readonly class Quantity
{
    public const int MIN = 1;
    public const int MAX = 99;

    private function __construct(private int $value)
    {
    }

    public static function of(int $value): self
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw InvalidQuantity::outOfRange(self::MIN, self::MAX);
        }

        return new self($value);
    }

    public static function default(): self
    {
        return new self(self::MIN);
    }

    public function add(self $other): self
    {
        return self::of($this->value + $other->value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
