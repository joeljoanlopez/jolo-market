<?php

declare(strict_types=1);

namespace App\Tests\Unit\Receipts\Domain\ValueObject;

use App\Receipts\Domain\ValueObject\InvalidReceiptDate;
use App\Receipts\Domain\ValueObject\ReceiptDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReceiptDateTest extends TestCase
{
    #[Test]
    public function it_is_a_day_in_iso_format(): void
    {
        self::assertSame('2026-11-14', ReceiptDate::fromString('2026-11-14')->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDays(): iterable
    {
        yield 'empty' => [''];
        yield 'not a day' => ['14/11/2026'];
        yield 'with a time' => ['2026-11-14 10:00'];
        yield 'impossible day' => ['2026-02-30'];
    }

    #[Test]
    #[DataProvider('invalidDays')]
    public function it_rejects_anything_but_a_real_day(string $value): void
    {
        $this->expectException(InvalidReceiptDate::class);

        ReceiptDate::fromString($value);
    }

    #[Test]
    public function today_is_the_day_in_madrid(): void
    {
        // 23:30 UTC on 14 November is already 15 November in Madrid.
        $now = new \DateTimeImmutable('2026-11-14 23:30:00', new \DateTimeZone('UTC'));

        self::assertSame('2026-11-15', ReceiptDate::today($now)->value());
    }

    #[Test]
    public function it_knows_whether_it_is_after_another_day(): void
    {
        self::assertTrue(ReceiptDate::fromString('2026-11-15')->isAfter(ReceiptDate::fromString('2026-11-14')));
        self::assertFalse(ReceiptDate::fromString('2026-11-14')->isAfter(ReceiptDate::fromString('2026-11-14')));
    }

    #[Test]
    public function its_month_is_year_and_month(): void
    {
        self::assertSame('2026-11', ReceiptDate::fromString('2026-11-14')->month());
    }
}
