<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Service;

use App\Catalog\Domain\Service\PackagingParser;
use App\Catalog\Domain\ValueObject\Packaging;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PackagingParserTest extends TestCase
{
    #[Test]
    #[DataProvider('parseableSamples')]
    public function it_parses_a_source_packaging_string(string $source, Packaging $expected): void
    {
        $parsed = new PackagingParser()->parse($source);

        self::assertNotNull($parsed);
        self::assertTrue($expected->equals($parsed), \sprintf('"%s" was not parsed as expected.', $source));
    }

    /**
     * Samples are added here as each supermarket spike finds new formats.
     *
     * @return iterable<string, array{string, Packaging}>
     */
    public static function parseableSamples(): iterable
    {
        yield 'litres' => ['1 L', Packaging::litres('1')];
        yield 'litres lowercase without space' => ['1l', Packaging::litres('1')];
        yield 'litres with decimal comma' => ['1,5 L', Packaging::litres('1.5')];
        yield 'litres with decimal dot' => ['0.75 l', Packaging::litres('0.75')];
        yield 'litres spelled out' => ['2 litros', Packaging::litres('2')];
        yield 'litre abbreviated lt' => ['1 lt', Packaging::litres('1')];
        yield 'centilitres' => ['33 cl', Packaging::centilitres('33')];
        yield 'millilitres' => ['500 ml', Packaging::millilitres('500')];
        yield 'grams' => ['500 g', Packaging::grams('500')];
        yield 'grams abbreviated gr' => ['250 gr', Packaging::grams('250')];
        yield 'kilograms' => ['1 kg', Packaging::kilograms('1')];
        yield 'kilograms with decimal comma' => ['1,2 kg', Packaging::kilograms('1.2')];
        yield 'multipack' => ['6 x 1 L', Packaging::litres('1')->multipliedBy(6)];
        yield 'multipack without spaces' => ['6x33cl', Packaging::centilitres('33')->multipliedBy(6)];
        yield 'multipack with times sign' => ['4 × 125 g', Packaging::grams('125')->multipliedBy(4)];
        yield 'pack prefix on multipack' => ['pack 6 x 1,5 L', Packaging::litres('1.5')->multipliedBy(6)];
        yield 'pack of units' => ['pack 12 ud', Packaging::units(12)];
        yield 'units abbreviated uds' => ['6 uds', Packaging::units(6)];
        yield 'units abbreviated u' => ['10 u', Packaging::units(10)];
        yield 'units spelled out' => ['1 unidad', Packaging::units(1)];
        yield 'units spelled out plural' => ['12 unidades', Packaging::units(12)];
        yield 'surrounding and repeated whitespace' => ['  6  x  1   L ', Packaging::litres('1')->multipliedBy(6)];
        yield 'upper case' => ['PACK 12 UD', Packaging::units(12)];
    }

    #[Test]
    #[DataProvider('unknownSamples')]
    public function it_returns_unknown_for_a_string_it_cannot_parse_with_certainty(string $source): void
    {
        self::assertNull(new PackagingParser()->parse($source));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownSamples(): iterable
    {
        yield 'empty' => [''];
        yield 'no quantity' => ['botella'];
        yield 'no unit' => ['500'];
        yield 'unknown unit' => ['3 docenas'];
        yield 'approximate weight' => ['1,2 kg aprox.'];
        yield 'zero quantity' => ['0 g'];
        yield 'zero items' => ['0 x 1 L'];
        yield 'finer than a gram' => ['0,5 g'];
        yield 'fractional units' => ['1,5 ud'];
        yield 'range' => ['400-500 g'];
    }
}
