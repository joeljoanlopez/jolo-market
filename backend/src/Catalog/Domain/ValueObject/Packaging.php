<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

/**
 * How much product a package holds, normalized to kg, L or units.
 *
 * The quantity is kept as an integer number of thousandths of the unit (500 g is 500 thousandths
 * of a kilogram), so no float is ever involved in a price calculation.
 */
final readonly class Packaging
{
    private const string DECIMAL_PATTERN = '/^(\d+)(?:\.(\d+))?$/D';

    private function __construct(
        private UnitOfMeasure $unit,
        private int $thousandths,
    ) {
        if ($thousandths <= 0) {
            throw InvalidPackaging::notPositive();
        }
    }

    /**
     * Quantities are decimal numbers with a dot as separator, such as "1.5".
     */
    public static function kilograms(string $quantity): self
    {
        return new self(UnitOfMeasure::KILOGRAM, self::toThousandths($quantity, 3));
    }

    public static function grams(string $quantity): self
    {
        return new self(UnitOfMeasure::KILOGRAM, self::toThousandths($quantity, 0));
    }

    public static function litres(string $quantity): self
    {
        return new self(UnitOfMeasure::LITRE, self::toThousandths($quantity, 3));
    }

    public static function centilitres(string $quantity): self
    {
        return new self(UnitOfMeasure::LITRE, self::toThousandths($quantity, 1));
    }

    public static function millilitres(string $quantity): self
    {
        return new self(UnitOfMeasure::LITRE, self::toThousandths($quantity, 0));
    }

    public static function units(int $count): self
    {
        return new self(UnitOfMeasure::UNIT, $count * 1000);
    }

    /**
     * Rebuilds a packaging from its normalized form, for example when it is read from storage.
     */
    public static function fromThousandths(UnitOfMeasure $unit, int $thousandths): self
    {
        return new self($unit, $thousandths);
    }

    /**
     * A multipack: "6 x 33 cl" is 6 items of 33 cl each.
     */
    public function multipliedBy(int $items): self
    {
        return new self($this->unit, $this->thousandths * $items);
    }

    public function unit(): UnitOfMeasure
    {
        return $this->unit;
    }

    public function thousandths(): int
    {
        return $this->thousandths;
    }

    public function equals(self $other): bool
    {
        return $this->unit === $other->unit && $this->thousandths === $other->thousandths;
    }

    /**
     * Shifts the decimal point $exponent places to the right. A result with a fractional
     * part is rejected instead of rounded, because it would be a guessed quantity.
     */
    private static function toThousandths(string $quantity, int $exponent): int
    {
        if (1 !== preg_match(self::DECIMAL_PATTERN, $quantity, $matches)) {
            throw InvalidPackaging::notADecimal($quantity);
        }

        $integerPart = $matches[1];
        $fractionPart = rtrim($matches[2] ?? '', '0');

        if (\strlen($fractionPart) > $exponent) {
            throw InvalidPackaging::tooPrecise($quantity);
        }

        return (int) ($integerPart.str_pad($fractionPart, $exponent, '0'));
    }
}
