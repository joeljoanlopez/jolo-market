<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\Packaging;
use App\Catalog\Domain\ValueObject\UnitOfMeasure;
use App\Shared\Domain\Exception\InvalidArgument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PackagingTest extends TestCase
{
    #[Test]
    public function it_keeps_kilograms_as_kilograms(): void
    {
        $packaging = Packaging::kilograms('1.5');

        self::assertSame(UnitOfMeasure::KILOGRAM, $packaging->unit());
        self::assertSame(1500, $packaging->thousandths());
    }

    #[Test]
    public function it_converts_grams_to_kilograms(): void
    {
        $packaging = Packaging::grams('500');

        self::assertSame(UnitOfMeasure::KILOGRAM, $packaging->unit());
        self::assertSame(500, $packaging->thousandths());
    }

    #[Test]
    public function it_keeps_litres_as_litres(): void
    {
        $packaging = Packaging::litres('0.75');

        self::assertSame(UnitOfMeasure::LITRE, $packaging->unit());
        self::assertSame(750, $packaging->thousandths());
    }

    #[Test]
    public function it_converts_centilitres_to_litres(): void
    {
        $packaging = Packaging::centilitres('33');

        self::assertSame(UnitOfMeasure::LITRE, $packaging->unit());
        self::assertSame(330, $packaging->thousandths());
    }

    #[Test]
    public function it_converts_millilitres_to_litres(): void
    {
        $packaging = Packaging::millilitres('250');

        self::assertSame(UnitOfMeasure::LITRE, $packaging->unit());
        self::assertSame(250, $packaging->thousandths());
    }

    #[Test]
    public function it_converts_a_pack_to_units(): void
    {
        $packaging = Packaging::units(12);

        self::assertSame(UnitOfMeasure::UNIT, $packaging->unit());
        self::assertSame(12000, $packaging->thousandths());
    }

    #[Test]
    public function it_multiplies_a_packaging_by_a_number_of_items(): void
    {
        $packaging = Packaging::centilitres('33')->multipliedBy(6);

        self::assertSame(UnitOfMeasure::LITRE, $packaging->unit());
        self::assertSame(1980, $packaging->thousandths());
    }

    #[Test]
    #[DataProvider('invalidQuantities')]
    public function it_rejects_an_invalid_quantity(callable $create): void
    {
        $this->expectException(InvalidArgument::class);

        $create();
    }

    /**
     * @return iterable<string, array{callable(): Packaging}>
     */
    public static function invalidQuantities(): iterable
    {
        yield 'zero' => [static fn (): Packaging => Packaging::grams('0')];
        yield 'not a number' => [static fn (): Packaging => Packaging::litres('abc')];
        yield 'negative' => [static fn (): Packaging => Packaging::kilograms('-1')];
        yield 'comma as decimal separator' => [static fn (): Packaging => Packaging::litres('1,5')];
        yield 'finer than a gram' => [static fn (): Packaging => Packaging::grams('0.5')];
        yield 'zero units' => [static fn (): Packaging => Packaging::units(0)];
        yield 'zero items' => [static fn (): Packaging => Packaging::grams('100')->multipliedBy(0)];
    }

    #[Test]
    public function it_compares_by_unit_and_quantity(): void
    {
        self::assertTrue(Packaging::grams('1000')->equals(Packaging::kilograms('1')));
        self::assertFalse(Packaging::grams('1000')->equals(Packaging::litres('1')));
        self::assertFalse(Packaging::grams('1000')->equals(Packaging::grams('999')));
    }
}
