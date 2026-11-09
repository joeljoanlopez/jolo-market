<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\UnitOfMeasure;
use App\Catalog\Domain\ValueObject\UnitPrice;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnitPriceTest extends TestCase
{
    #[Test]
    public function it_holds_a_price_per_unit_of_measure(): void
    {
        $unitPrice = UnitPrice::of(Money::eur(265), UnitOfMeasure::LITRE);

        self::assertTrue(Money::eur(265)->equals($unitPrice->price()));
        self::assertSame(UnitOfMeasure::LITRE, $unitPrice->unit());
    }

    #[Test]
    public function it_compares_by_price_and_unit(): void
    {
        $unitPrice = UnitPrice::of(Money::eur(265), UnitOfMeasure::LITRE);

        self::assertTrue($unitPrice->equals(UnitPrice::of(Money::eur(265), UnitOfMeasure::LITRE)));
        self::assertFalse($unitPrice->equals(UnitPrice::of(Money::eur(266), UnitOfMeasure::LITRE)));
        self::assertFalse($unitPrice->equals(UnitPrice::of(Money::eur(265), UnitOfMeasure::KILOGRAM)));
    }
}
