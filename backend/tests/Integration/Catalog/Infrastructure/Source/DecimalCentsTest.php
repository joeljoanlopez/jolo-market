<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Source;

use App\Catalog\Infrastructure\Source\DecimalCents;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DecimalCentsTest extends TestCase
{
    #[Test]
    #[DataProvider('amounts')]
    public function it_converts_a_decimal_euro_amount_to_cents_rounding_half_up(string $amount, ?int $expected): void
    {
        self::assertSame($expected, DecimalCents::fromString($amount));
    }

    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function amounts(): iterable
    {
        yield 'two decimals' => ['16.75', 1675];
        yield 'one decimal' => ['3.5', 350];
        yield 'no decimals' => ['3', 300];
        yield 'third decimal below half' => ['2.354', 235];
        yield 'third decimal at half' => ['2.355', 236];
        yield 'surrounding spaces' => ['       17.25', 1725];
        yield 'not a number' => ['n/a', null];
        yield 'negative' => ['-1.00', null];
        yield 'empty' => ['', null];
    }
}
