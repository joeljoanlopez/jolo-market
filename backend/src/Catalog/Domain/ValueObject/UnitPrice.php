<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;

/**
 * Price per kg, per L or per unit: what makes products of different sizes comparable.
 */
final readonly class UnitPrice
{
    private function __construct(
        private Money $price,
        private UnitOfMeasure $unit,
    ) {
    }

    public static function of(Money $price, UnitOfMeasure $unit): self
    {
        return new self($price, $unit);
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function unit(): UnitOfMeasure
    {
        return $this->unit;
    }

    public function equals(self $other): bool
    {
        return $this->unit === $other->unit && $this->price->equals($other->price);
    }
}
