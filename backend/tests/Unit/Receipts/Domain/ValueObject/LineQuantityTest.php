<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Domain\ValueObject;

use App\Receipts\Domain\ValueObject\InvalidLineQuantity;
use App\Receipts\Domain\ValueObject\LineQuantity;
use App\Receipts\Domain\ValueObject\QuantityUnit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LineQuantityTest extends TestCase
{
    #[Test]
    public function it_holds_whole_units_from_1_to_999(): void
    {
        $one = LineQuantity::units(1);
        $max = LineQuantity::units(999);

        self::assertSame(QuantityUnit::UNITS, $one->unit());
        self::assertSame(1, $one->value());
        self::assertSame(999, $max->value());
    }

    #[Test]
    public function it_holds_a_weight_in_thousandths_of_a_kg_from_1_to_99999(): void
    {
        $weight = LineQuantity::kilograms(532);

        self::assertSame(QuantityUnit::KG, $weight->unit());
        self::assertSame(532, $weight->value());
        self::assertSame(99_999, LineQuantity::kilograms(99_999)->value());
        self::assertSame(1, LineQuantity::kilograms(1)->value());
    }

    /**
     * @return iterable<string, array{QuantityUnit, int}>
     */
    public static function outOfRange(): iterable
    {
        yield 'no units' => [QuantityUnit::UNITS, 0];
        yield '1000 units' => [QuantityUnit::UNITS, 1000];
        yield 'no weight' => [QuantityUnit::KG, 0];
        yield '100 kg' => [QuantityUnit::KG, 100_000];
        yield 'negative weight' => [QuantityUnit::KG, -1];
    }

    #[Test]
    #[DataProvider('outOfRange')]
    public function it_rejects_a_quantity_out_of_range(QuantityUnit $unit, int $value): void
    {
        $this->expectException(InvalidLineQuantity::class);

        LineQuantity::of($unit, $value);
    }

    #[Test]
    public function it_compares_by_unit_and_value(): void
    {
        self::assertTrue(LineQuantity::units(2)->equals(LineQuantity::of(QuantityUnit::UNITS, 2)));
        self::assertFalse(LineQuantity::units(2)->equals(LineQuantity::kilograms(2)));
    }
}
