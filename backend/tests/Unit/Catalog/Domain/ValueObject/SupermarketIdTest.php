<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\SupermarketId;
use App\Shared\Domain\Exception\InvalidArgument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SupermarketIdTest extends TestCase
{
    #[Test]
    public function it_holds_a_slug(): void
    {
        self::assertSame('mercadona', SupermarketId::fromString('mercadona')->value());
    }

    #[Test]
    public function it_accepts_digits_and_inner_hyphens(): void
    {
        self::assertSame('bonpreu-esclat2', SupermarketId::fromString('bonpreu-esclat2')->value());
    }

    #[Test]
    #[DataProvider('invalidSlugs')]
    public function it_rejects_a_value_that_is_not_a_slug(string $invalid): void
    {
        $this->expectException(InvalidArgument::class);

        SupermarketId::fromString($invalid);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSlugs(): iterable
    {
        yield 'empty' => [''];
        yield 'upper case' => ['Mercadona'];
        yield 'space' => ['bon preu'];
        yield 'leading hyphen' => ['-aldi'];
        yield 'trailing hyphen' => ['aldi-'];
        yield 'accent' => ['caprabò'];
    }

    #[Test]
    public function it_compares_by_value(): void
    {
        self::assertTrue(SupermarketId::fromString('aldi')->equals(SupermarketId::fromString('aldi')));
        self::assertFalse(SupermarketId::fromString('aldi')->equals(SupermarketId::fromString('bonpreu')));
    }
}
