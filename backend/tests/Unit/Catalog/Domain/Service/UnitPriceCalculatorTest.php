<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Service;

use App\Catalog\Domain\Service\UnitPriceCalculator;
use App\Catalog\Domain\ValueObject\Packaging;
use App\Catalog\Domain\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Rounding rule (PD-21): the exact price per unit is rounded to whole cents half-up,
 * so x.5 cents goes up. Only integers are used.
 */
final class UnitPriceCalculatorTest extends TestCase
{
    #[Test]
    #[DataProvider('prices')]
    public function it_divides_the_price_by_the_normalized_quantity(
        int $priceCents,
        Packaging $packaging,
        int $expectedCents,
        UnitOfMeasure $expectedUnit,
    ): void {
        $unitPrice = new UnitPriceCalculator()->calculate(Money::eur($priceCents), $packaging);

        self::assertSame($expectedCents, $unitPrice->price()->cents());
        self::assertSame($expectedUnit, $unitPrice->unit());
    }

    /**
     * @return iterable<string, array{int, Packaging, int, UnitOfMeasure}>
     */
    public static function prices(): iterable
    {
        yield 'exact per litre' => [99, Packaging::litres('1'), 99, UnitOfMeasure::LITRE];
        yield 'grams to price per kg' => [125, Packaging::grams('500'), 250, UnitOfMeasure::KILOGRAM];
        yield 'multipack per litre' => [594, Packaging::litres('1')->multipliedBy(6), 99, UnitOfMeasure::LITRE];
        yield 'pack per unit' => [300, Packaging::units(12), 25, UnitOfMeasure::UNIT];
        yield 'rounds down below half a cent: 2.6533 -> 2.65' => [199, Packaging::litres('0.75'), 265, UnitOfMeasure::LITRE];
        yield 'rounds up above half a cent: 3.3333... per unit -> 0.33' => [100, Packaging::units(3), 33, UnitOfMeasure::UNIT];
        yield 'rounds up above half a cent: 0.6666... per unit -> 0.67' => [200, Packaging::units(3), 67, UnitOfMeasure::UNIT];
        yield 'rounds half a cent up: 2.655 -> 2.66' => [531, Packaging::kilograms('2'), 266, UnitOfMeasure::KILOGRAM];
        yield 'rounds half a cent up: 0.125 -> 0.13' => [1, Packaging::grams('80'), 13, UnitOfMeasure::KILOGRAM];
        yield 'free product' => [0, Packaging::litres('1'), 0, UnitOfMeasure::LITRE];
    }
}
