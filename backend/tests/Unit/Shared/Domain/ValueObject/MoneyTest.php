<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidArgument;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[Test]
    public function it_holds_an_amount_in_cents(): void
    {
        self::assertSame(129, Money::eur(129)->cents());
    }

    #[Test]
    public function it_allows_zero(): void
    {
        self::assertSame(0, Money::eur(0)->cents());
    }

    #[Test]
    public function it_rejects_a_negative_amount(): void
    {
        $this->expectException(InvalidArgument::class);

        Money::eur(-1);
    }

    #[Test]
    public function it_adds_two_amounts(): void
    {
        self::assertTrue(Money::eur(250)->equals(Money::eur(100)->add(Money::eur(150))));
    }

    #[Test]
    public function it_multiplies_by_a_quantity(): void
    {
        self::assertTrue(Money::eur(387)->equals(Money::eur(129)->multiply(3)));
    }

    #[Test]
    public function it_rejects_a_negative_multiplier(): void
    {
        $this->expectException(InvalidArgument::class);

        Money::eur(129)->multiply(-2);
    }

    #[Test]
    public function it_is_immutable(): void
    {
        $original = Money::eur(100);

        $original->add(Money::eur(50));
        $original->multiply(2);

        self::assertSame(100, $original->cents());
    }

    #[Test]
    public function it_compares_by_value(): void
    {
        self::assertTrue(Money::eur(100)->equals(Money::eur(100)));
        self::assertFalse(Money::eur(100)->equals(Money::eur(101)));
    }

    #[Test]
    public function it_orders_amounts(): void
    {
        self::assertSame(-1, Money::eur(99)->compareTo(Money::eur(100)));
        self::assertSame(0, Money::eur(100)->compareTo(Money::eur(100)));
        self::assertSame(1, Money::eur(101)->compareTo(Money::eur(100)));
        self::assertTrue(Money::eur(101)->isGreaterThan(Money::eur(100)));
        self::assertFalse(Money::eur(100)->isGreaterThan(Money::eur(100)));
        self::assertTrue(Money::eur(99)->isLessThan(Money::eur(100)));
        self::assertFalse(Money::eur(100)->isLessThan(Money::eur(100)));
    }
}
