<?php

declare(strict_types=1);

namespace App\Receipts\Domain\ValueObject;

/**
 * How much a product line bought (PD-66): whole units from 1 to 999, or a weight from 0.001 to
 * 99.999 kg stored as thousandths of a kg, so no float is ever involved.
 */
final readonly class LineQuantity
{
    public const int MAX_UNITS = 999;
    public const int MAX_THOUSANDTHS = 99_999;

    private function __construct(
        private QuantityUnit $unit,
        private int $value,
    ) {
    }

    public static function of(QuantityUnit $unit, int $value): self
    {
        $max = QuantityUnit::UNITS === $unit ? self::MAX_UNITS : self::MAX_THOUSANDTHS;
        if ($value < 1 || $value > $max) {
            throw InvalidLineQuantity::outOfRange($unit, 1, $max);
        }

        return new self($unit, $value);
    }

    public static function units(int $units): self
    {
        return self::of(QuantityUnit::UNITS, $units);
    }

    public static function kilograms(int $thousandths): self
    {
        return self::of(QuantityUnit::KG, $thousandths);
    }

    public function unit(): QuantityUnit
    {
        return $this->unit;
    }

    /**
     * Units, or thousandths of a kg.
     */
    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->unit === $other->unit && $this->value === $other->value;
    }
}
